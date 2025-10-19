<?php
declare(strict_types=1);

namespace Cms\Front\Http;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Auth\AuthService;
use Cms\Admin\Auth\Passwords;
use Cms\Admin\Domain\Repositories\UsersRepository;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Validation\Validator;
use Cms\Front\Data\CommentProvider;
use Cms\Front\Data\MenuProvider;
use Cms\Front\Data\PostProvider;
use Cms\Front\Data\TermProvider;
use Cms\Front\Support\SeoMeta;
use Cms\Front\View\ThemeViewEngine;
use Core\Database\Init as DB;
use Throwable;

final class Router
{
    private ThemeViewEngine $view;
    private PostProvider $posts;
    private TermProvider $terms;
    private MenuProvider $menus;
    private CommentProvider $comments;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private UsersRepository $users;
    private MailService $mail;
    private TemplateManager $templates;
    private AuthService $auth;

    public function __construct(
        ThemeViewEngine $view,
        PostProvider $posts,
        TermProvider $terms,
        MenuProvider $menus,
        ?CommentProvider $comments = null,
        ?CmsSettings $settings = null,
        ?LinkGenerator $links = null,
        ?UsersRepository $users = null,
        ?MailService $mail = null,
        ?TemplateManager $templates = null,
        ?AuthService $auth = null
    ) {
        $this->view = $view;
        $this->posts = $posts;
        $this->terms = $terms;
        $this->menus = $menus;
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator(null, $this->settings);
        $this->comments = $comments ?? new CommentProvider($this->settings);
        $this->users = $users ?? new UsersRepository();
        $this->mail = $mail ?? new MailService($this->settings);
        $this->templates = $templates ?? new TemplateManager();
        $this->auth = $auth ?? new AuthService();

        $this->view->share([
            'navigation' => $this->menus->menusByLocation(),
        ]);
    }

    public function dispatch(): void
    {
        $result = $this->resolve();
        http_response_code($result->status);
        $this->view->renderWithLayout($result->layout, $result->template, $result->data);
    }

    private function resolve(): RouteResult
    {
        $route = $this->detectRoute();
        $name = $route['name'];
        $params = $route['params'];

        return match ($name) {
            'home' => $this->handleHome(),
            'post' => $this->handlePost((string)($params['slug'] ?? '')),
            'page' => $this->handlePage((string)($params['slug'] ?? '')),
            'type' => $this->handleType((string)($params['type'] ?? 'post')),
            'category' => $this->handleTerm((string)($params['slug'] ?? ''), 'category'),
            'tag' => $this->handleTerm((string)($params['slug'] ?? ''), 'tag'),
            'search' => $this->handleSearch((string)($params['query'] ?? ($params['s'] ?? ''))),
            'register' => $this->handleRegister(),
            'lost' => $this->handleLost(),
            'reset' => $this->handleReset((string)($params['token'] ?? ''), (int)($params['id'] ?? 0)),
            default => $this->notFound(),
        };
    }

    /**
     * @return array{name:string,params:array<string,mixed>}
     */
    private function detectRoute(): array
    {
        $route = isset($_GET['r']) ? (string)$_GET['r'] : null;
        if ($route !== null && $route !== '') {
            $params = $_GET;
            unset($params['r']);
            return ['name' => $route, 'params' => $params];
        }

        if (isset($_GET['s'])) {
            return ['name' => 'search', 'params' => ['query' => (string)$_GET['s']]];
        }

        if (!$this->links->prettyUrlsEnabled()) {
            return ['name' => 'home', 'params' => []];
        }

        return $this->detectPrettyRoute();
    }

    /**
     * @return array{name:string,params:array<string,mixed>}
     */
    private function detectPrettyRoute(): array
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $path = $this->trimBase($path);
        if ($path === '' || $path === 'index.php') {
            return ['name' => 'home', 'params' => []];
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($part) => $part !== ''));
        if ($segments !== []) {
            $segments = array_map(
                static fn ($part) => rawurldecode((string)$part),
                $segments
            );
        }
        $bases = $this->settings->permalinkBases();

        if ($segments !== []) {
            $first = $segments[0];
            $second = $segments[1] ?? '';

            if ($bases['post_base'] !== '' && $first === trim($bases['post_base'], '/')) {
                return ['name' => 'post', 'params' => ['slug' => $second]];
            }
            if ($bases['page_base'] !== '' && $first === trim($bases['page_base'], '/')) {
                return ['name' => 'page', 'params' => ['slug' => $second]];
            }
            if ($bases['category_base'] !== '' && $first === trim($bases['category_base'], '/')) {
                return ['name' => 'category', 'params' => ['slug' => $second]];
            }
            if ($bases['tag_base'] !== '' && $first === trim($bases['tag_base'], '/')) {
                return ['name' => 'tag', 'params' => ['slug' => $second]];
            }
            if (in_array($first, ['type', 'archive', 'archiv'], true)) {
                return ['name' => 'type', 'params' => ['type' => $second]];
            }
            if ($first === 'search') {
                $query = $segments[1] ?? '';
                if ($query === '') {
                    $query = $_GET['s'] ?? ($_GET['q'] ?? '');
                }
                return ['name' => 'search', 'params' => ['query' => (string)rawurldecode((string)$query)]];
            }
            if ($first === 'register') {
                return ['name' => 'register', 'params' => []];
            }
            if ($first === 'lost') {
                return ['name' => 'lost', 'params' => []];
            }
            if ($first === 'reset') {
                $token = $segments[1] ?? '';
                $user = $segments[2] ?? '';
                $params = [];
                if ($token !== '') {
                    $params['token'] = $token;
                }
                if ($user !== '') {
                    $params['id'] = $user;
                }
                if (isset($_GET['token'])) {
                    $params['token'] = (string)$_GET['token'];
                }
                if (isset($_GET['id'])) {
                    $params['id'] = (string)$_GET['id'];
                }
                return ['name' => 'reset', 'params' => $params];
            }
        }

        // Pokud je page base prázdný, považuj první segment za slug stránky.
        if ($bases['page_base'] === '' && $segments !== []) {
            return ['name' => 'page', 'params' => ['slug' => $segments[0]]];
        }

        // Pokud je post base prázdný, zkus nejdříve post a případně fallback na stránku.
        if ($bases['post_base'] === '' && $segments !== []) {
            $slug = $segments[0];
            $post = $this->posts->findPublished($slug, 'post');
            if ($post) {
                return ['name' => 'post', 'params' => ['slug' => $slug]];
            }
        }

        return ['name' => 'page', 'params' => ['slug' => $segments[0] ?? '']];
    }

    private function trimBase(string $path): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', (string)dirname($script)), '/');
        if ($base !== '' && $base !== '.') {
            $prefix = '/' . ltrim($base, '/');
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }
        return trim($path, '/');
    }

    private function handleHome(): RouteResult
    {
        $posts = $this->posts->latest('post', 10);
        $meta = new SeoMeta($this->settings->siteTitle(), canonical: $this->links->home());

        return new RouteResult('home', [
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handlePost(string $slug): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $post = $this->posts->findPublished($slug, 'post');
        if (!$post) {
            return $this->notFound();
        }

        $commentsAllowed = isset($post['comments_allowed']) ? (bool)$post['comments_allowed'] : false;
        $commentForm = [
            'success' => false,
            'message' => null,
            'errors' => [],
            'old' => [
                'name' => '',
                'email' => '',
                'content' => '',
                'parent_id' => null,
            ],
            'user' => null,
        ];
        $authUser = $this->auth->user();
        if (is_array($authUser)) {
            $commentUser = [
                'id' => isset($authUser['id']) ? (int)$authUser['id'] : 0,
                'name' => trim((string)($authUser['name'] ?? '')),
                'email' => trim((string)($authUser['email'] ?? '')),
            ];
            $commentForm['user'] = $commentUser;
            if ($commentUser['name'] !== '') {
                $commentForm['old']['name'] = $commentUser['name'];
            }
            if ($commentUser['email'] !== '') {
                $commentForm['old']['email'] = $commentUser['email'];
            }
        } else {
            $commentUser = null;
        }
        $status = 200;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['comment_form'])) {
            if (!$commentsAllowed) {
                $commentForm['message'] = 'Komentáře jsou u tohoto článku uzavřeny.';
                $status = 403;
            } else {
                $input = [
                    'name' => trim((string)($_POST['comment_name'] ?? '')),
                    'email' => trim((string)($_POST['comment_email'] ?? '')),
                    'content' => trim((string)($_POST['comment_content'] ?? '')),
                    'parent_id' => (int)($_POST['comment_parent'] ?? 0),
                    'post_id' => (int)($_POST['comment_post'] ?? 0),
                ];

                if ($commentUser !== null) {
                    $input['name'] = $commentUser['name'] !== '' ? $commentUser['name'] : 'Anonym';
                    $input['email'] = $commentUser['email'];
                }

                $commentForm['old']['name'] = $input['name'];
                $commentForm['old']['email'] = $input['email'];
                $commentForm['old']['content'] = $input['content'];
                $commentForm['old']['parent_id'] = $input['parent_id'] > 0 ? $input['parent_id'] : null;

                $validator = (new Validator())
                    ->require($input, 'name', 'Zadejte své jméno.')
                    ->require($input, 'content', 'Napište komentář.')
                    ->email($input, 'email', 'Zadejte platný e-mail.');

                $errors = $validator->errors();

                if ($input['post_id'] !== (int)($post['id'] ?? 0)) {
                    $errors['general'][] = 'Komentář se nepodařilo ověřit. Obnovte stránku a zkuste to prosím znovu.';
                }

                $requestedParentId = $input['parent_id'] > 0 ? $input['parent_id'] : null;
                $parentId = $requestedParentId;
                if ($requestedParentId !== null) {
                    try {
                        $parent = DB::query()
                            ->table('comments')
                            ->select(['id','post_id','parent_id'])
                            ->where('id','=', $requestedParentId)
                            ->first();
                    } catch (Throwable $e) {
                        error_log('Failed to validate comment parent: ' . $e->getMessage());
                        $parent = null;
                    }

                    if (!$parent || (int)($parent['post_id'] ?? 0) !== (int)($post['id'] ?? 0)) {
                        $errors['parent'][] = 'Na komentář nelze odpovědět.';
                        $parentId = null;
                        $commentForm['old']['parent_id'] = null;
                    } else {
                        $parentRootId = (int)($parent['parent_id'] ?? 0) > 0
                            ? $this->resolveCommentThreadRoot((int)$parent['id'])
                            : (int)$parent['id'];

                        if ($parentRootId <= 0) {
                            $errors['parent'][] = 'Na komentář nelze odpovědět.';
                            $parentId = null;
                            $commentForm['old']['parent_id'] = null;
                        } else {
                            $parentId = $parentRootId;
                            $commentForm['old']['parent_id'] = $parentId;
                        }
                    }
                }

                if ($errors !== []) {
                    $commentForm['errors'] = $errors;
                    $commentForm['message'] = 'Zkontrolujte zvýrazněná pole.';
                    $status = 422;
                } else {
                    $limit = static function (string $value, int $max): string {
                        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                            if (mb_strlen($value) <= $max) {
                                return $value;
                            }

                            return mb_substr($value, 0, $max);
                        }

                        if (strlen($value) <= $max) {
                            return $value;
                        }

                        return substr($value, 0, $max);
                    };

                    $name = $limit($input['name'], 150);
                    $email = $limit($input['email'], 190);
                    $ipRaw = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                    $uaRaw = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
                    $ip = $limit($ipRaw, 45);
                    $ua = $limit($uaRaw, 255);
                    $ip = $ip !== '' ? $ip : null;
                    $ua = $ua !== '' ? $ua : null;

                    $timestamp = DateTimeFactory::nowString();
                    $userId = $commentUser !== null && $commentUser['id'] > 0 ? $commentUser['id'] : null;
                    $statusValue = $commentUser !== null ? 'published' : 'draft';

                    try {
                        DB::query()
                            ->table('comments')
                            ->insert([
                                'post_id' => (int)($post['id'] ?? 0),
                                'user_id' => $userId,
                                'parent_id' => $parentId,
                                'author_name' => $name,
                                'author_email' => $email !== '' ? $email : null,
                                'content' => $input['content'],
                                'status' => $statusValue,
                                'ip' => $ip,
                                'ua' => $ua,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ])
                            ->insertGetId();

                        $commentForm['success'] = true;
                        $commentForm['message'] = $statusValue === 'published'
                            ? 'Komentář byl zveřejněn.'
                            : 'Komentář byl odeslán ke schválení.';
                        $commentForm['old'] = [
                            'name' => $commentUser['name'] ?? '',
                            'email' => $commentUser['email'] ?? '',
                            'content' => '',
                            'parent_id' => null,
                        ];
                    } catch (Throwable $e) {
                        error_log('Failed to store comment: ' . $e->getMessage());
                        $commentForm['message'] = 'Komentář se nepodařilo uložit. Zkuste to prosím znovu.';
                        $status = 500;
                    }
                }
            }
        }

        $commentData = $this->comments->publishedForPost((int)($post['id'] ?? 0));

        $meta = new SeoMeta(
            $post['title'] . ' | ' . $this->settings->siteTitle(),
            $post['excerpt'],
            $post['permalink']
        );

        return new RouteResult('single', [
            'post' => $post,
            'comments' => $commentData['items'],
            'commentCount' => $commentData['total'],
            'commentsAllowed' => $commentsAllowed,
            'commentForm' => $commentForm,
            'meta' => $meta->toArray(),
        ], $status);
    }

    private function resolveCommentThreadRoot(int $commentId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;

        while ($currentId > 0 && $guard < 20) {
            try {
                $row = DB::query()
                    ->table('comments')
                    ->select(['id','parent_id'])
                    ->where('id','=', $currentId)
                    ->first();
            } catch (Throwable $e) {
                error_log('Failed to resolve comment thread root: ' . $e->getMessage());
                return 0;
            }

            if (!$row) {
                return 0;
            }

            $parentId = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)($row['id'] ?? 0);
            }

            $currentId = $parentId;
            $guard++;
        }

        return $guard >= 20 ? 0 : $currentId;
    }

    private function handlePage(string $slug): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $page = $this->posts->findPublished($slug, 'page');
        if (!$page) {
            return $this->notFound();
        }

        $meta = new SeoMeta(
            $page['title'] . ' | ' . $this->settings->siteTitle(),
            $page['excerpt'],
            $page['permalink']
        );

        return new RouteResult('page', [
            'page' => $page,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleType(string $type): RouteResult
    {
        $type = $type !== '' ? $type : 'post';
        $posts = $this->posts->latest($type, 20);
        $meta = new SeoMeta(
            ucfirst($type) . ' | ' . $this->settings->siteTitle(),
            canonical: $this->links->type($type)
        );

        return new RouteResult('archive', [
            'posts' => $posts,
            'type' => $type,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleTerm(string $slug, string $type): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $term = $this->terms->findBySlug($slug, $type);
        if (!$term) {
            return $this->notFound();
        }

        $posts = $this->posts->forTerm($slug, $type, 20);

        $canonical = $type === 'category'
            ? $this->links->category($slug)
            : $this->links->tag($slug);

        $meta = new SeoMeta(
            (string)$term['name'] . ' | ' . $this->settings->siteTitle(),
            canonical: $canonical
        );

        $template = $type === 'category' ? 'category' : 'tag';

        return new RouteResult($template, [
            'term' => $term,
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleSearch(string $query): RouteResult
    {
        $query = trim($query);
        $posts = $query === '' ? [] : $this->posts->search($query, 20);

        $canonical = $this->links->search($query !== '' ? $query : null);
        $meta = new SeoMeta(
            ($query === '' ? 'Vyhledávání' : 'Hledám "' . $query . '"') . ' | ' . $this->settings->siteTitle(),
            canonical: $canonical
        );

        return new RouteResult('search', [
            'query' => $query,
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleRegister(): RouteResult
    {
        $allowed = $this->settings->registrationAllowed();
        $autoApprove = $this->settings->registrationAutoApprove();
        $meta = new SeoMeta('Registrace | ' . $this->settings->siteTitle(), canonical: $this->links->register());

        $data = [
            'meta' => $meta->toArray(),
            'errors' => [],
            'old' => ['name' => '', 'email' => ''],
            'success' => false,
            'message' => null,
            'allowed' => $allowed,
            'autoApprove' => $autoApprove,
            'loginUrl' => $this->links->login(),
        ];

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            if (!$allowed) {
                $data['message'] = 'Registrace je aktuálně vypnutá.';
                return new RouteResult('register', $data, 403);
            }

            return new RouteResult('register', $data);
        }

        if (!$allowed) {
            $data['message'] = 'Registrace je aktuálně vypnutá.';
            return new RouteResult('register', $data, 403);
        }

        $input = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'password' => (string)($_POST['password'] ?? ''),
            'password_confirm' => (string)($_POST['password_confirm'] ?? ''),
        ];

        $data['old']['name'] = $input['name'];
        $data['old']['email'] = $input['email'];

        $validator = (new Validator())
            ->require($input, 'name', 'Zadejte jméno.')
            ->require($input, 'email', 'Zadejte e-mail.')
            ->email($input, 'email', 'Zadejte platný e-mail.')
            ->require($input, 'password', 'Zadejte heslo.')
            ->minLen($input, 'password', 8, 'Heslo musí mít alespoň 8 znaků.');

        $errors = $validator->errors();

        if (trim($input['password_confirm']) === '') {
            $errors['password_confirm'][] = 'Potvrďte heslo.';
        } elseif ($input['password'] !== $input['password_confirm']) {
            $errors['password_confirm'][] = 'Zadaná hesla se neshodují.';
        }

        if ($input['email'] !== '') {
            try {
                $existing = $this->users->findByEmail($input['email']);
            } catch (Throwable $e) {
                error_log('Registrace: nepodařilo se ověřit e-mail: ' . $e->getMessage());
                $existing = null;
            }
            if ($existing) {
                $errors['email'][] = 'Účet s tímto e-mailem již existuje.';
            }
        }

        if ($errors !== []) {
            $data['errors'] = $errors;
            $data['message'] = 'Zkontrolujte zvýrazněná pole.';
            return new RouteResult('register', $data, 422);
        }

        $now = DateTimeFactory::nowString();
        $insert = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => Passwords::hash($input['password']),
            'active' => $autoApprove ? 1 : 0,
            'role' => 'user',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $this->users->create($insert);
        } catch (Throwable $e) {
            error_log('Registrace: nepodařilo se vytvořit uživatele: ' . $e->getMessage());
            $data['message'] = 'Registraci se nepodařilo dokončit. Zkuste to prosím znovu.';
            return new RouteResult('register', $data, 500);
        }

        $templateKey = $autoApprove ? 'registration_welcome' : 'registration_pending';
        $mailData = [
            'siteTitle' => $this->settings->siteTitle(),
            'userName' => $input['name'],
            'userEmail' => $input['email'],
        ];
        if ($autoApprove) {
            $mailData['loginUrl'] = $this->links->login();
        }

        try {
            $template = $this->templates->render($templateKey, $mailData);
            $this->mail->sendTemplate($input['email'], $template, $input['name'] ?: null);
        } catch (Throwable $e) {
            error_log('Registrace: e-mail se nepodařilo odeslat: ' . $e->getMessage());
        }

        $data['success'] = true;
        $data['errors'] = [];
        $data['old'] = ['name' => '', 'email' => ''];
        $data['message'] = $autoApprove
            ? 'Registrace proběhla úspěšně. Nyní se můžete přihlásit.'
            : 'Registrace byla přijata. Vyčkejte prosím na schválení administrátorem.';

        return new RouteResult('register', $data);
    }

    private function handleLost(): RouteResult
    {
        $meta = new SeoMeta('Obnova hesla | ' . $this->settings->siteTitle(), canonical: $this->links->lost());

        $data = [
            'meta' => $meta->toArray(),
            'errors' => [],
            'old' => ['email' => ''],
            'success' => false,
            'message' => null,
            'loginUrl' => $this->links->login(),
        ];

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            return new RouteResult('lost', $data);
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $data['old']['email'] = $email;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $data['errors']['email'][] = 'Zadejte platnou e-mailovou adresu.';
            $data['message'] = 'Zkontrolujte zvýrazněné pole.';
            return new RouteResult('lost', $data, 422);
        }

        $user = null;
        try {
            $user = $this->users->findByEmail($email);
        } catch (Throwable $e) {
            error_log('Lost password lookup failed: ' . $e->getMessage());
        }

        if (is_array($user)) {
            try {
                $reset = $this->auth->beginPasswordReset($email);
            } catch (Throwable $e) {
                error_log('Lost password token generation failed: ' . $e->getMessage());
                $reset = null;
            }

            if (is_array($reset) && isset($reset['token'], $reset['user_id'])) {
                $resetUrl = $this->links->absolute(
                    $this->links->reset((string)$reset['token'], (int)$reset['user_id'])
                );
                $mailData = [
                    'siteTitle' => $this->settings->siteTitle(),
                    'userName' => (string)($user['name'] ?? ''),
                    'resetUrl' => $resetUrl,
                ];

                try {
                    $template = $this->templates->render('lost_password', $mailData);
                    $this->mail->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);
                } catch (Throwable $e) {
                    error_log('Lost password email dispatch failed: ' . $e->getMessage());
                }
            }
        }

        $data['success'] = true;
        $data['message'] = 'Pokud e-mail existuje v naší databázi, poslali jsme na něj pokyny k obnovení hesla.';
        $data['old']['email'] = '';

        return new RouteResult('lost', $data);
    }

    private function handleReset(string $tokenParam, int $userIdParam): RouteResult
    {
        $token = preg_replace('~[^a-f0-9]~i', '', trim($tokenParam)) ?? '';
        $userId = $userIdParam > 0 ? $userIdParam : 0;

        if (isset($_GET['token'])) {
            $token = preg_replace('~[^a-f0-9]~i', '', (string)$_GET['token']) ?? $token;
        }
        if (isset($_GET['id'])) {
            $idFromQuery = (int)$_GET['id'];
            if ($idFromQuery > 0) {
                $userId = $idFromQuery;
            }
        }
        if (isset($_POST['token'])) {
            $token = preg_replace('~[^a-f0-9]~i', '', (string)$_POST['token']) ?? $token;
        }
        if (isset($_POST['user_id'])) {
            $idFromPost = (int)$_POST['user_id'];
            if ($idFromPost > 0) {
                $userId = $idFromPost;
            }
        } elseif (isset($_POST['id'])) {
            $idFromPost = (int)$_POST['id'];
            if ($idFromPost > 0) {
                $userId = $idFromPost;
            }
        }

        $canonical = $token !== '' && $userId > 0
            ? $this->links->reset($token, $userId)
            : $this->links->reset();

        $meta = new SeoMeta('Reset hesla | ' . $this->settings->siteTitle(), canonical: $canonical);

        $data = [
            'meta' => $meta->toArray(),
            'errors' => [],
            'message' => null,
            'success' => false,
            'allowForm' => true,
            'token' => $token,
            'userId' => $userId,
            'loginUrl' => $this->links->login(),
            'lostUrl' => $this->links->lost(),
        ];

        if ($token === '' || $userId <= 0) {
            $data['allowForm'] = false;
            $data['message'] = 'Resetovací odkaz je neplatný.';
            return new RouteResult('reset', $data, 400);
        }

        $user = null;
        try {
            $user = $this->users->findByResetToken($userId, $token);
        } catch (Throwable $e) {
            error_log('Reset password lookup failed: ' . $e->getMessage());
        }

        $expiresAt = '';
        if (is_array($user)) {
            $expiresAt = (string)($user['token_expire'] ?? '');
        }

        $tokenValid = is_array($user) && $expiresAt !== '' && strtotime($expiresAt) >= time();

        if (!$tokenValid) {
            $data['allowForm'] = false;
            $data['message'] = 'Resetovací odkaz je neplatný nebo vypršel.';
            $status = $expiresAt !== '' ? 410 : 400;
            return new RouteResult('reset', $data, $status);
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            return new RouteResult('reset', $data);
        }

        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if (trim($password) === '') {
            $data['errors']['password'][] = 'Zadejte nové heslo.';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
        if ($length < 8) {
            $data['errors']['password'][] = 'Heslo musí mít alespoň 8 znaků.';
        }

        if (trim($confirm) === '') {
            $data['errors']['password_confirm'][] = 'Potvrďte heslo.';
        } elseif ($password !== $confirm) {
            $data['errors']['password_confirm'][] = 'Zadaná hesla se neshodují.';
        }

        if ($data['errors'] !== []) {
            $data['message'] = 'Zkontrolujte zvýrazněná pole.';
            return new RouteResult('reset', $data, 422);
        }

        try {
            $ok = $this->auth->completePasswordReset($userId, $token, $password);
        } catch (Throwable $e) {
            error_log('Reset password completion failed: ' . $e->getMessage());
            $ok = false;
        }

        if (!$ok) {
            $data['message'] = 'Nepodařilo se dokončit reset hesla. Požádejte prosím o nový odkaz.';
            $data['allowForm'] = false;
            return new RouteResult('reset', $data, 400);
        }

        $data['success'] = true;
        $data['allowForm'] = false;
        $data['message'] = 'Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.';

        return new RouteResult('reset', $data);
    }

    private function notFound(): RouteResult
    {
        $meta = new SeoMeta('Nenalezeno | ' . $this->settings->siteTitle(), canonical: null);

        return new RouteResult('404', [
            'meta' => $meta->toArray(),
        ], 404);
    }
}
