<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Entities\NewsletterCampaignSchedule;
use Cms\Admin\Domain\Services\NewsletterCampaignsService;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;

final class NewsletterCampaignController extends BaseAdminController
{
    private const PER_PAGE = 20;

    private const CAMPAIGN_STATUS_LABELS = [
        'draft'     => 'Návrh',
        'scheduled' => 'Naplánováno',
        'sending'   => 'Probíhá rozesílka',
        'completed' => 'Dokončeno',
        'failed'    => 'Chyba',
    ];

    private const SCHEDULE_STATUS_LABELS = [
        NewsletterCampaignSchedule::STATUS_DRAFT     => 'Návrh',
        NewsletterCampaignSchedule::STATUS_SCHEDULED => 'Naplánováno',
        NewsletterCampaignSchedule::STATUS_RUNNING   => 'Probíhá',
        NewsletterCampaignSchedule::STATUS_PAUSED    => 'Pozastaveno',
        NewsletterCampaignSchedule::STATUS_COMPLETED => 'Dokončeno',
    ];

    private NewsletterCampaignsService $service;

    public function __construct(string $baseViewsPath)
    {
        parent::__construct($baseViewsPath);
        $this->service = new NewsletterCampaignsService();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'detail':
                $this->detail();
                return;

            case 'edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->update();
                    return;
                }
                $this->index();
                return;

            case 'duplicate':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->duplicate();
                    return;
                }
                $this->index();
                return;

            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->delete();
                    return;
                }
                $this->index();
                return;

            case 'schedule':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->schedule();
                    return;
                }
                $this->index();
                return;

            case 'pause':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->pause();
                    return;
                }
                $this->index();
                return;

            case 'resume':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->resume();
                    return;
                }
                $this->index();
                return;

            case 'trigger':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->trigger();
                    return;
                }
                $this->index();
                return;

            case 'index':
            default:
                $this->index();
                return;
        }
    }

    private function index(): void
    {
        $filters = $this->filtersFromArray($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $paginated = $this->service->paginate($filters, $page, self::PER_PAGE);
        $pagination = $this->paginationData($paginated, $page, self::PER_PAGE);
        $currentPage = (int) ($paginated['page'] ?? $pagination['page']);

        $settings = new CmsSettings();

        $items = $this->normalizeCreatedAt($paginated['items'] ?? [], true);
        $items = array_map(function (array $row) use ($filters, $currentPage, $settings): array {
            $id = (int) ($row['id'] ?? 0);
            $scheduleData = isset($row['schedule']) && is_array($row['schedule']) ? $row['schedule'] : null;
            $schedule = $this->presentSchedule($scheduleData, $settings);
            $status = (string) ($row['status'] ?? 'draft');
            $statusMeta = $this->presentCampaignStatus($status);

            return [
                'id'               => $id,
                'subject'          => (string) ($row['subject'] ?? ''),
                'body'             => (string) ($row['body'] ?? ''),
                'status'           => $status,
                'status_label'     => $statusMeta['label'],
                'status_badge'     => $statusMeta['badge'],
                'recipients_count' => (int) ($row['recipients_count'] ?? 0),
                'sent_count'       => (int) ($row['sent_count'] ?? 0),
                'failed_count'     => (int) ($row['failed_count'] ?? 0),
                'created_at_raw'   => (string) ($row['created_at_raw'] ?? ''),
                'created_at_display' => (string) ($row['created_at_display'] ?? ''),
                'created_at_iso'   => (string) ($row['created_at_iso'] ?? ''),
                'author_name'      => (string) ($row['author_name'] ?? ''),
                'author_email'     => isset($row['author_email']) ? (string) $row['author_email'] : null,
                'detail_url'       => 'admin.php?r=newsletter-campaigns&a=detail&id=' . $id,
                'filters'          => $filters,
                'page'             => $currentPage,
                'schedule'         => $schedule,
            ];
        }, $items);

        $baseQuery = [
            'r'      => 'newsletter-campaigns',
            'q'      => $filters['q'] !== '' ? $filters['q'] : null,
            'author' => $filters['author'] > 0 ? $filters['author'] : null,
            'page'   => $currentPage,
        ];

        $buildUrl = $this->listingUrlBuilder($baseQuery);
        $currentUrl = $this->currentUrl();
        $csrf = $this->token();
        $authors = $this->service->authors();

        if ($this->wantsJsonIndex()) {
            $payload = [
                'success'    => true,
                'filters'    => $filters,
                'pagination' => $pagination,
                'csrf'       => $csrf,
                'partials'   => [
                    'toolbar' => $this->renderPartial('newsletter/campaigns/partials/toolbar', [
                        'filters'  => $filters,
                        'authors'  => $authors,
                        'buildUrl' => $buildUrl,
                        'total'    => (int) ($pagination['total'] ?? 0),
                    ]),
                    'table' => $this->renderPartial('newsletter/campaigns/partials/table', [
                        'items'   => $items,
                        'csrf'    => $csrf,
                        'filters' => $filters,
                        'page'    => $currentPage,
                    ]),
                    'pagination' => $this->renderPartial('newsletter/campaigns/partials/pagination', [
                        'pagination' => $pagination,
                        'buildUrl'   => $buildUrl,
                    ]),
                ],
            ];

            $this->jsonResponse($payload);
        }

        $this->renderAdmin('newsletter/campaigns/index', [
            'pageTitle'  => 'Newsletter kampaně',
            'nav'        => AdminNavigation::build('newsletter:campaigns'),
            'filters'    => $filters,
                'items'      => $items,
                'pagination' => $pagination,
                'authors'    => $authors,
                'buildUrl'   => $buildUrl,
                'csrf'       => $csrf,
            'currentUrl' => $currentUrl,
        ]);
    }

    private function detail(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect($this->listUrl($this->filtersFromArray($_GET)), 'danger', 'Kampaň nebyla nalezena.');
        }

        $filters = $this->filtersFromArray($_GET);
        $page = isset($_GET['page']) ? (int) $_GET['page'] : null;

        $campaign = $this->service->find($id);
        if (!$campaign) {
            $this->redirect($this->listUrl($filters, $page), 'danger', 'Kampaň nebyla nalezena.');
        }

        $presented = $this->normalizeCreatedAt([$campaign], true);
        $campaign = $presented[0];

        $this->renderAdmin('newsletter/campaigns/detail', [
            'pageTitle' => (string) ($campaign['subject'] ?? 'Detail kampaně'),
            'nav'       => AdminNavigation::build('newsletter:campaigns'),
            'campaign'  => $campaign,
            'backUrl'   => $this->listUrl($filters, $page),
        ]);
    }

    private function update(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $subject = (string) ($_POST['subject'] ?? '');
        $body = (string) ($_POST['body'] ?? '');

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->update($id, $subject, $body);
        } catch (\InvalidArgumentException $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Kampaň byla upravena.');
    }

    private function duplicate(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        $user = $this->auth->user();
        $userId = $user ? (int) ($user['id'] ?? 0) : null;

        try {
            $campaign = $this->service->duplicate($id, $userId);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $redirect = 'admin.php?r=newsletter-campaigns&a=detail&id=' . (int) ($campaign['id'] ?? 0);

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success'    => true,
                'flash'      => [
                    'type' => 'success',
                    'msg'  => 'Kampaň byla duplikována.',
                ],
                'refreshUrl' => $this->listUrl($filters, $page),
            ]);
        }

        $this->redirect($redirect, 'success', 'Kampaň byla duplikována.');
    }

    private function delete(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->delete($id);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Kampaň byla odstraněna.');
    }

    private function schedule(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $startAt = isset($_POST['start_at']) ? (string) $_POST['start_at'] : null;
        $endAt = isset($_POST['end_at']) ? (string) $_POST['end_at'] : null;
        $interval = isset($_POST['interval_minutes']) ? (int) $_POST['interval_minutes'] : 0;
        $maxAttempts = isset($_POST['max_attempts']) ? (int) $_POST['max_attempts'] : 1;

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->configureSchedule($id, $startAt, $endAt, $interval, $maxAttempts);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Plán kampaně byl uložen.');
    }

    private function pause(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->pauseSchedule($id);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Plán kampaně byl pozastaven.');
    }

    private function resume(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->resumeSchedule($id);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Plán kampaně byl znovu aktivován.');
    }

    private function trigger(): void
    {
        $this->assertCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $filters = $this->filtersFromArray($_POST);
        $page = max(1, (int) ($_POST['page'] ?? 1));

        if ($id <= 0) {
            $this->respondActionError($filters, $page, 'danger', 'Chybí identifikátor kampaně.', 422);
        }

        try {
            $this->service->triggerNow($id);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            $this->respondActionError($filters, $page, 'danger', $exception->getMessage(), 500);
        }

        $this->respondActionSuccess($filters, $page, 'success', 'Kampaň byla zařazena k okamžitému odeslání.');
    }

    private function filtersFromArray(array $source): array
    {
        $q = isset($source['q']) ? trim((string) $source['q']) : '';
        $author = isset($source['author']) ? (int) $source['author'] : 0;

        return [
            'q'      => $q,
            'author' => $author > 0 ? $author : 0,
        ];
    }

    private function listUrl(array $filters, ?int $page = null): string
    {
        $query = [
            'r'      => 'newsletter-campaigns',
            'q'      => $filters['q'] !== '' ? $filters['q'] : null,
            'author' => $filters['author'] > 0 ? $filters['author'] : null,
        ];

        $builder = $this->listingUrlBuilder($query);

        if ($page !== null) {
            return $builder(['page' => $page]);
        }

        return $builder([]);
    }

    private function presentCampaignStatus(string $status): array
    {
        $normalized = strtolower($status);
        $label = self::CAMPAIGN_STATUS_LABELS[$normalized] ?? ucfirst($normalized);

        $badge = match ($normalized) {
            'completed' => 'success',
            'sending'   => 'primary',
            'scheduled' => 'info',
            'failed'    => 'danger',
            default     => 'secondary',
        };

        return [
            'label' => $label,
            'badge' => $badge,
        ];
    }

    /**
     * @param array<string,mixed>|null $schedule
     */
    private function presentSchedule(?array $schedule, CmsSettings $settings): array
    {
        $status = is_string($schedule['status'] ?? null)
            ? (string) $schedule['status']
            : NewsletterCampaignSchedule::STATUS_DRAFT;

        $format = static function (?string $value) use ($settings): ?string {
            $date = DateTimeFactory::fromStorage($value);
            return $date ? $settings->formatDateTime($date) : null;
        };

        $toLocal = static function (?string $value): ?string {
            $date = DateTimeFactory::fromStorage($value);
            return $date ? $date->format('Y-m-d\TH:i') : null;
        };

        $startRaw = isset($schedule['start_at']) ? (string) $schedule['start_at'] : null;
        $endRaw = isset($schedule['end_at']) ? (string) $schedule['end_at'] : null;
        $nextRaw = isset($schedule['next_run_at']) ? (string) $schedule['next_run_at'] : null;
        $lastRaw = isset($schedule['last_run_at']) ? (string) $schedule['last_run_at'] : null;

        return [
            'status'            => $status,
            'status_label'      => self::SCHEDULE_STATUS_LABELS[$status] ?? ucfirst($status),
            'start_at_raw'      => $startRaw,
            'start_at_display'  => $format($startRaw),
            'start_at_local'    => $toLocal($startRaw),
            'end_at_raw'        => $endRaw,
            'end_at_display'    => $format($endRaw),
            'end_at_local'      => $toLocal($endRaw),
            'next_run_at_raw'   => $nextRaw,
            'next_run_at_display' => $format($nextRaw),
            'last_run_at_raw'   => $lastRaw,
            'last_run_at_display' => $format($lastRaw),
            'interval_minutes'  => isset($schedule['interval_minutes']) ? (int) $schedule['interval_minutes'] : 0,
            'max_attempts'      => isset($schedule['max_attempts']) ? (int) $schedule['max_attempts'] : 1,
            'attempts'          => isset($schedule['attempts']) ? (int) $schedule['attempts'] : 0,
        ];
    }

    private function respondActionSuccess(array $filters, int $page, string $flashType, string $message): void
    {
        $refreshUrl = $this->listUrl($filters, $page);

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success'    => true,
                'flash'      => [
                    'type' => $flashType,
                    'msg'  => $message,
                ],
                'refreshUrl' => $refreshUrl,
            ]);
        }

        $this->redirect($refreshUrl, $flashType, $message);
    }

    private function respondActionError(array $filters, int $page, string $flashType, string $message, int $status): void
    {
        $refreshUrl = $this->listUrl($filters, $page);

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => false,
                'flash'   => [
                    'type' => $flashType,
                    'msg'  => $message,
                ],
                'refreshUrl' => $refreshUrl,
            ], $status);
        }

        $this->redirect($refreshUrl, $flashType, $message);
    }

    private function wantsJsonIndex(): bool
    {
        if ($this->isAjax()) {
            return true;
        }

        $format = isset($_GET['format']) ? strtolower((string) $_GET['format']) : '';
        return $format === 'json';
    }

    private function renderPartial(string $template, array $data): string
    {
        ob_start();
        try {
            $this->view->render($template, $data);
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }

    private function currentUrl(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : 'admin.php?r=newsletter-campaigns';
    }
}

