<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Repositories\NewsletterSendsRepository;
use Cms\Admin\Domain\Services\NewsletterService;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Throwable;

final class NewsletterController extends BaseAdminController
{
    private const PER_PAGE = 20;

    /** @var array<string,array{label:string,badge:string}> */
    private const STATUS_META = [
        NewsletterService::STATUS_PENDING => [
            'label' => 'Čeká na potvrzení',
            'badge' => 'warning',
        ],
        NewsletterService::STATUS_CONFIRMED => [
            'label' => 'Potvrzeno',
            'badge' => 'success',
        ],
        NewsletterService::STATUS_UNSUBSCRIBED => [
            'label' => 'Odhlášen',
            'badge' => 'secondary',
        ],
    ];

    private NewsletterService $service;
    private CmsSettings $settings;
    private MailService $mailService;
    private NewsletterSendsRepository $sendsRepository;

    public function __construct(string $baseViewsPath)
    {
        parent::__construct($baseViewsPath);
        $this->service = new NewsletterService();
        $this->settings = new CmsSettings();
        $this->mailService = new MailService($this->settings);
        $this->sendsRepository = new NewsletterSendsRepository();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'detail':
                $this->detail();
                return;
            case 'confirm':
                $this->confirm();
                return;
            case 'unsubscribe':
                $this->unsubscribe();
                return;
            case 'delete':
                $this->delete();
                return;
            case 'export':
                $this->export();
                return;
            case 'export-confirmed':
                $this->exportConfirmed();
                return;
            case 'send-campaign':
                $this->sendCampaign();
                return;
            case 'index':
            default:
                $this->index();
                return;
        }
    }

    private function index(): void
    {
        $filters = $this->filtersFromRequest();
        $page = max(1, (int)($_GET['page'] ?? 1));

        $paginated = $this->service->paginate($filters, $page, self::PER_PAGE);
        $pagination = $this->paginationData($paginated, $page, self::PER_PAGE);
        $currentPage = (int)($paginated['page'] ?? $pagination['page']);

        $currentUrl = $this->listUrl($filters, $currentPage);
        $items = [];
        foreach ($paginated['items'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->transformSubscriber($row, $currentUrl);
        }

        $baseQuery = [
            'r'      => 'newsletter',
            'status' => $filters['status'] !== '' ? $filters['status'] : null,
            'q'      => $filters['q'] !== '' ? $filters['q'] : null,
            'page'   => $currentPage,
        ];

        $confirmedCount = $this->service->confirmedCount();
        $lastSendRow = $this->sendsRepository->latest();
        $lastSend = null;

        if (is_array($lastSendRow)) {
            $lastSend = [
                'subject'    => (string)($lastSendRow['subject'] ?? ''),
                'created_at' => $this->formatDateTime($lastSendRow['created_at'] ?? null)
                    ?? (string)($lastSendRow['created_at'] ?? ''),
                'sent'       => (int)($lastSendRow['sent_count'] ?? 0),
                'failed'     => (int)($lastSendRow['failed_count'] ?? 0),
                'recipients' => (int)($lastSendRow['recipients_count'] ?? 0),
            ];
        }

        $this->renderAdmin('newsletter/index', [
            'pageTitle'   => 'Newsletter',
            'nav'         => AdminNavigation::build('newsletter'),
            'items'       => $items,
            'filters'     => $filters,
            'statusMeta'  => self::STATUS_META,
            'pagination'  => $pagination,
            'buildUrl'    => $this->listingUrlBuilder($baseQuery),
            'currentUrl'  => $currentUrl,
            'confirmedCount' => $confirmedCount,
            'newsletterSendLimit' => $this->settings->newsletterCampaignLimit(),
            'lastSend'    => $lastSend,
        ]);
    }

    private function sendCampaign(): void
    {
        $this->assertCsrf();

        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $redirect = 'admin.php?r=newsletter';

        if ($subject === '' || $body === '') {
            $this->redirect($redirect, 'danger', 'Vyplňte prosím předmět i obsah kampaně.');
        }

        $limit = $this->settings->newsletterCampaignLimit();
        $totalConfirmed = $this->service->confirmedCount();

        if ($totalConfirmed === 0) {
            $this->redirect($redirect, 'warning', 'Nemáte žádné potvrzené odběratele.');
        }

        if ($totalConfirmed > $limit) {
            $this->redirect(
                $redirect,
                'danger',
                sprintf('Odeslání zastaveno: počet potvrzených odběratelů (%d) překračuje limit %d.', $totalConfirmed, $limit)
            );
        }

        $recipients = $this->service->confirmedEmails($limit);
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $email = (string)($recipient['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $ok = $this->mailService->send($email, $subject, $body, null, strip_tags($body));
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $user = $this->auth->user();

        $this->sendsRepository->create([
            'subject'          => $subject,
            'body'             => $body,
            'recipients_count' => $totalConfirmed,
            'sent_count'       => $sent,
            'failed_count'     => $failed,
            'created_by'       => $user ? (int)($user['id'] ?? 0) : null,
            'created_at'       => DateTimeFactory::nowString(),
        ]);

        if ($failed > 0) {
            $this->redirect(
                $redirect,
                'warning',
                sprintf('Kampaň odeslána, ale %d adres se nepodařilo doručit.', $failed)
            );
        }

        $this->redirect($redirect, 'success', sprintf('Kampaň byla odeslána %d odběratelům.', $sent));
    }

    private function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $backParam = isset($_GET['back']) ? (string)$_GET['back'] : '';
        $back = $backParam !== '' ? rawurldecode($backParam) : '';
        $redirect = $this->normalizeRedirect($back);

        if ($id <= 0) {
            $this->redirect($redirect, 'danger', 'Chybí ID odběratele.');
        }

        $subscriber = $this->service->find($id);
        if (!$subscriber) {
            $this->redirect($redirect, 'danger', 'Odběratel nenalezen.');
        }

        $detailBack = $back !== '' ? $redirect : $this->listUrl(['status' => '', 'q' => ''], 1);
        $detailUrl = $this->detailUrl($id, $detailBack);

        $this->renderAdmin('newsletter/detail', [
            'pageTitle'  => 'Odběratel newsletteru',
            'nav'        => AdminNavigation::build('newsletter'),
            'subscriber' => $this->transformSubscriber($subscriber),
            'statusMeta' => self::STATUS_META,
            'backUrl'    => $redirect,
            'detailUrl'  => $detailUrl,
        ]);
    }

    private function confirm(): void
    {
        $this->assertCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $redirect = $this->normalizeRedirect((string)($_POST['redirect'] ?? ''));

        if ($id <= 0) {
            $this->redirect($redirect, 'danger', 'Chybí ID odběratele.');
        }

        $subscriber = $this->service->find($id);
        if (!$subscriber) {
            $this->redirect($redirect, 'danger', 'Odběratel nenalezen.');
        }

        if ((string)($subscriber['status'] ?? '') === NewsletterService::STATUS_CONFIRMED) {
            $this->redirect($redirect, 'info', 'Tato adresa už byla potvrzena.');
        }

        try {
            $this->service->updateSubscriber(
                $id,
                (string)($subscriber['email'] ?? ''),
                NewsletterService::STATUS_CONFIRMED,
                $this->sourceUrlFromRow($subscriber)
            );
        } catch (Throwable $e) {
            $this->respondFailure('Odběratele se nepodařilo potvrdit.', $redirect, $e);
        }

        $this->redirect($redirect, 'success', 'Odběr byl potvrzen.');
    }

    private function unsubscribe(): void
    {
        $this->assertCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $redirect = $this->normalizeRedirect((string)($_POST['redirect'] ?? ''));

        if ($id <= 0) {
            $this->redirect($redirect, 'danger', 'Chybí ID odběratele.');
        }

        $subscriber = $this->service->find($id);
        if (!$subscriber) {
            $this->redirect($redirect, 'danger', 'Odběratel nenalezen.');
        }

        if ((string)($subscriber['status'] ?? '') === NewsletterService::STATUS_UNSUBSCRIBED) {
            $this->redirect($redirect, 'info', 'Tato adresa už je odhlášena.');
        }

        try {
            $this->service->updateSubscriber(
                $id,
                (string)($subscriber['email'] ?? ''),
                NewsletterService::STATUS_UNSUBSCRIBED,
                $this->sourceUrlFromRow($subscriber)
            );
        } catch (Throwable $e) {
            $this->respondFailure('Odběratele se nepodařilo odhlásit.', $redirect, $e);
        }

        $this->redirect($redirect, 'success', 'Odběr byl odhlášen.');
    }

    private function delete(): void
    {
        $this->assertCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $redirect = $this->normalizeRedirect((string)($_POST['redirect'] ?? ''));

        if ($id <= 0) {
            $this->redirect($redirect, 'danger', 'Chybí ID odběratele.');
        }

        try {
            $this->service->deleteSubscriber($id);
        } catch (Throwable $e) {
            $this->respondFailure('Odběratele se nepodařilo smazat.', $redirect, $e);
        }

        $this->redirect($redirect, 'success', 'Odběratel byl smazán.');
    }

    private function export(): void
    {
        $confirmedStats = $this->service->paginate([
            'status' => NewsletterService::STATUS_CONFIRMED,
        ], 1, 1);

        $confirmedCount = (int)($confirmedStats['total'] ?? 0);

        $this->renderAdmin('newsletter/export', [
            'pageTitle'      => 'Export newsletteru',
            'nav'            => AdminNavigation::build('newsletter'),
            'confirmedCount' => $confirmedCount,
        ]);
    }

    private function exportConfirmed(): void
    {
        $this->assertCsrf();

        $rows = $this->service->confirmedForExport();
        $filename = 'newsletter-confirmed-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            $this->redirect('admin.php?r=newsletter', 'danger', 'Export se nezdařil.');
        }

        fputcsv($out, ['email', 'confirmed_at', 'created_at', 'source_url'], ';');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $confirmed = $this->formatForCsv($row['confirmed_at'] ?? null);
            $created = $this->formatForCsv($row['created_at'] ?? null);
            $source = isset($row['source_url']) ? (string)$row['source_url'] : '';

            fputcsv($out, [
                (string)($row['email'] ?? ''),
                $confirmed,
                $created,
                $source,
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * @return array{status:string,q:string}
     */
    private function filtersFromRequest(): array
    {
        $status = isset($_GET['status']) ? (string)$_GET['status'] : '';
        $allowed = $this->service->statuses();
        if ($status !== '' && !in_array($status, $allowed, true)) {
            $status = '';
        }

        $q = trim((string)($_GET['q'] ?? ''));

        return [
            'status' => $status,
            'q'      => $q,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function transformSubscriber(array $row, ?string $backUrl = null): array
    {
        $status = (string)($row['status'] ?? '');
        $meta = self::STATUS_META[$status] ?? [
            'label' => ucfirst($status),
            'badge' => 'secondary',
        ];

        $transformed = $row;
        $transformed['status_label'] = $meta['label'];
        $transformed['status_badge'] = $meta['badge'];
        $transformed['created_at_display'] = $this->formatDateTime($row['created_at'] ?? null);
        $transformed['confirmed_at_display'] = $this->formatDateTime($row['confirmed_at'] ?? null);
        $transformed['unsubscribed_at_display'] = $this->formatDateTime($row['unsubscribed_at'] ?? null);
        $transformed['confirm_expires_at_display'] = $this->formatDateTime($row['confirm_expires_at'] ?? null);

        if ($backUrl !== null) {
            $transformed['detail_url'] = $this->detailUrl((int)($row['id'] ?? 0), $backUrl);
        }

        return $transformed;
    }

    private function detailUrl(int $id, string $backUrl): string
    {
        $url = 'admin.php?r=newsletter&a=detail&id=' . $id;
        if ($backUrl !== '') {
            $url .= '&back=' . rawurlencode($backUrl);
        }

        return $url;
    }

    /**
     * @param array{status:string,q:string} $filters
     */
    private function listUrl(array $filters, int $page): string
    {
        $query = ['r' => 'newsletter'];
        if ($filters['status'] !== '') {
            $query['status'] = $filters['status'];
        }
        if ($filters['q'] !== '') {
            $query['q'] = $filters['q'];
        }
        if ($page > 1) {
            $query['page'] = $page;
        }

        return 'admin.php?' . http_build_query($query);
    }

    private function normalizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '') {
            return 'admin.php?r=newsletter';
        }

        if (!preg_match('~^admin\.php~', $redirect)) {
            return 'admin.php?r=newsletter';
        }

        return $redirect;
    }

    private function formatDateTime(mixed $value): ?string
    {
        $value = $value !== null ? (string)$value : '';
        if ($value === '') {
            return null;
        }

        $dateTime = DateTimeFactory::fromStorage($value);
        if ($dateTime === null) {
            return $value;
        }

        return $this->settings->formatDateTime($dateTime);
    }

    private function formatForCsv(mixed $value): string
    {
        $value = $value !== null ? (string)$value : '';
        if ($value === '') {
            return '';
        }

        $dateTime = DateTimeFactory::fromStorage($value);
        if ($dateTime === null) {
            return $value;
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string,mixed> $row
     */
    private function sourceUrlFromRow(array $row): ?string
    {
        $source = $row['source_url'] ?? null;
        if ($source === null) {
            return null;
        }

        $source = trim((string)$source);

        return $source === '' ? null : $source;
    }

    private function respondFailure(string $message, string $redirect, ?Throwable $exception = null): never
    {
        if ($this->isAjax()) {
            $payload = [
                'success' => false,
                'message' => $message,
            ];

            if ($exception instanceof Throwable) {
                $payload['error'] = $exception->getMessage();
            }

            $this->jsonResponse($payload, 500);
        }

        $this->redirect($redirect, 'danger', $message);
    }
}
