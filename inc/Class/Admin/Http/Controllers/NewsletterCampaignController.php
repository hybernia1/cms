<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Services\NewsletterCampaignsService;
use Cms\Admin\Utils\AdminNavigation;

final class NewsletterCampaignController extends BaseAdminController
{
    private const PER_PAGE = 20;

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

        $items = $this->normalizeCreatedAt($paginated['items'] ?? [], true);
        $items = array_map(function (array $row) use ($filters, $currentPage): array {
            $id = (int) ($row['id'] ?? 0);
            return [
                'id'               => $id,
                'subject'          => (string) ($row['subject'] ?? ''),
                'body'             => (string) ($row['body'] ?? ''),
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

