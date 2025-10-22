<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\NewsletterSubscribersRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Validation\Validator;

final class NewsletterService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    private const DEFAULT_CONFIRM_TTL_HOURS = 48;
    private const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_UNSUBSCRIBED,
    ];

    public function __construct(private readonly NewsletterSubscribersRepository $repo = new NewsletterSubscribersRepository())
    {
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->repo->findByEmail($email);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->repo->paginate($filters, $page, $perPage);
    }

    public function createSubscriber(string $email, ?string $sourceUrl = null, int $confirmTtlHours = self::DEFAULT_CONFIRM_TTL_HOURS): int
    {
        $email = trim($email);
        $sourceUrl = $this->normaliseSourceUrl($sourceUrl);
        $data = ['email' => $email];

        $v = (new Validator())
            ->require($data, 'email')
            ->email($data, 'email');

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        $existing = $this->repo->findByEmail($email);
        $confirmToken = $this->generateToken();
        $confirmExpiresAt = $this->computeExpiry($confirmTtlHours);

        if ($existing) {
            $update = [
                'status'            => self::STATUS_PENDING,
                'source_url'        => $sourceUrl,
                'confirm_token'     => $confirmToken,
                'confirm_expires_at'=> $confirmExpiresAt,
                'confirmed_at'      => null,
                'unsubscribed_at'   => null,
            ];

            if (empty($existing['unsubscribe_token'])) {
                $update['unsubscribe_token'] = $this->generateToken();
            }

            $this->repo->update((int) $existing['id'], $update);
            return (int) $existing['id'];
        }

        $now = DateTimeFactory::nowString();

        return $this->repo->create([
            'email'              => $email,
            'status'             => self::STATUS_PENDING,
            'source_url'         => $sourceUrl,
            'confirm_token'      => $confirmToken,
            'confirm_expires_at' => $confirmExpiresAt,
            'unsubscribe_token'  => $this->generateToken(),
            'created_at'         => $now,
            'confirmed_at'       => null,
            'unsubscribed_at'    => null,
        ]);
    }

    public function updateSubscriber(int $id, string $email, string $status, ?string $sourceUrl = null): int
    {
        $subscriber = $this->repo->find($id);
        if (!$subscriber) {
            throw new \RuntimeException('Subscriber not found.');
        }

        $email = trim($email);
        $status = trim($status);
        $sourceUrl = $this->normaliseSourceUrl($sourceUrl);

        $payload = ['email' => $email, 'status' => $status];
        $v = (new Validator())
            ->require($payload, 'email')
            ->email($payload, 'email')
            ->enum($payload, 'status', self::STATUSES);

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        $duplicate = $this->repo->findByEmail($email);
        if ($duplicate && (int) $duplicate['id'] !== $id) {
            throw new \RuntimeException('Subscriber with this e-mail already exists.');
        }

        $update = [
            'email'      => $email,
            'status'     => $status,
            'source_url' => $sourceUrl,
        ];

        $now = DateTimeFactory::nowString();

        switch ($status) {
            case self::STATUS_CONFIRMED:
                $update['confirmed_at'] = $subscriber['confirmed_at'] ?: $now;
                $update['confirm_token'] = null;
                $update['confirm_expires_at'] = null;
                if (empty($subscriber['unsubscribe_token'])) {
                    $update['unsubscribe_token'] = $this->generateToken();
                }
                break;

            case self::STATUS_PENDING:
                $update['confirmed_at'] = null;
                $update['unsubscribed_at'] = null;
                if (empty($subscriber['confirm_token'])) {
                    $update['confirm_token'] = $this->generateToken();
                }
                if (empty($subscriber['confirm_expires_at'])) {
                    $update['confirm_expires_at'] = $this->computeExpiry(self::DEFAULT_CONFIRM_TTL_HOURS);
                }
                if (empty($subscriber['unsubscribe_token'])) {
                    $update['unsubscribe_token'] = $this->generateToken();
                }
                break;

            case self::STATUS_UNSUBSCRIBED:
                $update['unsubscribed_at'] = $subscriber['unsubscribed_at'] ?: $now;
                $update['confirm_token'] = null;
                $update['confirm_expires_at'] = null;
                break;
        }

        return $this->repo->update($id, $update);
    }

    public function deleteSubscriber(int $id): int
    {
        return $this->repo->delete($id);
    }

    public function confirmByToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $subscriber = $this->repo->findByConfirmToken($token);
        if (!$subscriber) {
            return false;
        }

        if ((string) ($subscriber['status'] ?? '') === self::STATUS_UNSUBSCRIBED) {
            return false;
        }

        if ((string) ($subscriber['status'] ?? '') === self::STATUS_CONFIRMED) {
            return true;
        }

        $expiresAt = $subscriber['confirm_expires_at'] ?? null;
        if ($expiresAt !== null) {
            $expires = DateTimeFactory::fromStorage((string) $expiresAt);
            if ($expires !== null && $expires < DateTimeFactory::now()) {
                return false;
            }
        }

        $this->repo->update((int) $subscriber['id'], [
            'status'             => self::STATUS_CONFIRMED,
            'confirm_token'      => null,
            'confirm_expires_at' => null,
            'confirmed_at'       => DateTimeFactory::nowString(),
        ]);

        return true;
    }

    public function unsubscribeByToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $subscriber = $this->repo->findByUnsubscribeToken($token);
        if (!$subscriber) {
            return false;
        }

        if ((string) ($subscriber['status'] ?? '') === self::STATUS_UNSUBSCRIBED) {
            return true;
        }

        $this->repo->update((int) $subscriber['id'], [
            'status'             => self::STATUS_UNSUBSCRIBED,
            'confirm_token'      => null,
            'confirm_expires_at' => null,
            'unsubscribed_at'    => DateTimeFactory::nowString(),
        ]);

        return true;
    }

    public function regenerateConfirmToken(int $id, int $confirmTtlHours = self::DEFAULT_CONFIRM_TTL_HOURS): ?array
    {
        $subscriber = $this->repo->find($id);
        if (!$subscriber) {
            return null;
        }

        $token = $this->generateToken();
        $expires = $this->computeExpiry($confirmTtlHours);

        $this->repo->update($id, [
            'confirm_token'      => $token,
            'confirm_expires_at' => $expires,
        ]);

        return $this->repo->find($id);
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    private function computeExpiry(int $ttlHours): string
    {
        $ttlHours = max(1, $ttlHours);
        $expires = DateTimeFactory::now()->modify(sprintf('+%d hours', $ttlHours));
        return DateTimeFactory::formatForStorage($expires);
    }

    private function generateToken(int $bytes = 20): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function normaliseSourceUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null) {
            return null;
        }

        $sourceUrl = trim($sourceUrl);

        return $sourceUrl === '' ? null : $sourceUrl;
    }
}
