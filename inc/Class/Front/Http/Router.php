<?php
declare(strict_types=1);

namespace Cms\Front\Http;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Auth\AuthService;
use Cms\Admin\Auth\Passwords;
use Cms\Admin\Domain\Repositories\UsersRepository;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\UploadPathFactory;
use Core\Validation\Validator;
use Cms\Front\Data\CommentProvider;
use Cms\Front\Data\MenuProvider;
use Cms\Front\Data\PostProvider;
use Cms\Front\Data\ProductCatalog;
use Cms\Front\Data\TermProvider;
use Cms\Front\Support\SeoMeta;
use Cms\Front\View\ThemeViewEngine;
use Core\Files\PathResolver;
use Core\Files\Uploader;
use Core\Database\Init as DB;
use Cms\Services\OrderService;
use Cms\Admin\Domain\Services\UserSlugService;
use Throwable;

final class Router
{
    private const SHARED_NOTIFICATION_KEY = '_front_notification';

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
    private ProductCatalog $catalog;
    private OrderService $orders;
    private ?PathResolver $uploadPaths = null;

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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->view = $view;
        $this->posts = $posts;
        $this->terms = $terms;
        $this->menus = $menus;
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator(null, $this->settings);
        $this->comments = $comments ?? new CommentProvider($this->settings, $this->links);
        $this->users = $users ?? new UsersRepository();
        $this->mail = $mail ?? new MailService($this->settings);
        $this->templates = $templates ?? new TemplateManager();
        $this->auth = $auth ?? new AuthService();
        $this->catalog = new ProductCatalog(links: $this->links);
        $this->orders = new OrderService();

        $this->shareCommonViewData();
    }

    private function shareCommonViewData(): void
    {
        $this->view->share([
            'navigation' => $this->menus->menusByLocation(),
            'notifications' => $this->takeSharedNotifications(),
            'currentUser' => $this->currentUserContext(),
            'cart' => $this->cartState(),
        ]);
    }

    /**
     * @return array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}
     */
    private function cartState(): array
    {
        if (function_exists('cms_cart')) {
            $cart = cms_cart();
            if (is_array($cart)) {
                return $cart;
            }
        }

        return [
            'items' => [],
            'subtotal' => 0.0,
            'total' => 0.0,
            'currency' => 'USD',
            'count' => 0,
            'updated_at' => null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function currentUserContext(): ?array
    {
        $user = $this->auth->user();
        if (!is_array($user)) {
            return null;
        }

        $profile = $this->presentUserProfile($user);

        return [
            'id' => $profile['id'] ?? null,
            'name' => $profile['name'] ?? '',
            'profile_url' => $profile['profile_url'] ?? '',
            'profile_edit_url' => $this->links->account(),
            'avatar_url' => $profile['avatar_url'] ?? '',
            'admin_url' => $this->links->admin(),
            'logout_url' => $this->links->logout(),
        ];
    }

    /**
     * @return array<int,array{type:string,message:string}>
     */
    private function takeSharedNotifications(): array
    {
        if (!isset($_SESSION[self::SHARED_NOTIFICATION_KEY])) {
            return [];
        }

        $raw = $_SESSION[self::SHARED_NOTIFICATION_KEY];
        unset($_SESSION[self::SHARED_NOTIFICATION_KEY]);

        if (!is_array($raw)) {
            return [];
        }

        $notifications = [];

        if ($raw === []) {
            return $notifications;
        }

        $isList = true;
        $expectedKey = 0;
        foreach (array_keys($raw) as $key) {
            if (!is_int($key) || $key !== $expectedKey) {
                $isList = false;
                break;
            }
            $expectedKey++;
        }

        if ($isList) {
            foreach ($raw as $item) {
                $normalized = $this->normalizeNotification($item);
                if ($normalized !== null) {
                    $notifications[] = $normalized;
                }
            }
        } else {
            $normalized = $this->normalizeNotification($raw);
            if ($normalized !== null) {
                $notifications[] = $normalized;
            }
        }

        return $notifications;
    }

    /**
     * @param mixed $candidate
     * @return array{type:string,message:string}|null
     */
    private function normalizeNotification($candidate): ?array
    {
        if (!is_array($candidate)) {
            return null;
        }

        $message = (string)($candidate['message'] ?? ($candidate['msg'] ?? ''));
        $type = strtolower((string)($candidate['type'] ?? ''));

        if ($message === '') {
            return null;
        }

        if ($type === '') {
            $type = 'info';
        }

        if ($type === 'error') {
            $type = 'danger';
        }

        $allowed = ['info', 'success', 'warning', 'danger'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        return [
            'type' => $type,
            'message' => $message,
        ];
    }

    public function dispatch(): void
    {
        $result = $this->resolve();
        http_response_code($result->status);
        $this->view->renderWithLayout($result->layout, $result->template, $result->data);
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_front'])) {
            $_SESSION['csrf_front'] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION['csrf_front'];
    }

    private function verifyCsrf(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_front'] ?? '';
        if ($sessionToken === '') {
            return false;
        }

        return hash_equals((string)$sessionToken, $token);
    }

    private function storeFormState(string $key, array $form, int $status): void
    {
        $state = ['status' => $status];
        foreach (['success', 'message', 'errors', 'old', 'allowForm'] as $field) {
            if (array_key_exists($field, $form)) {
                $state[$field] = $form[$field];
            }
        }

        if (!isset($_SESSION['_front_forms']) || !is_array($_SESSION['_front_forms'])) {
            $_SESSION['_front_forms'] = [];
        }

        $_SESSION['_front_forms'][$key] = $state;
    }

    private function pullFormState(string $key): ?array
    {
        $forms = $_SESSION['_front_forms'] ?? null;
        if (!is_array($forms) || !array_key_exists($key, $forms)) {
            return null;
        }

        $state = $forms[$key];
        unset($_SESSION['_front_forms'][$key]);

        return is_array($state) ? $state : null;
    }

    private function redirect(string $url, int $status = 303): never
    {
        if ($url === '') {
            $url = $this->links->home();
        }

        header('Location: ' . $url, true, $status);
        exit;
    }

    private function commentRedirectUrl(array $post): string
    {
        $permalink = isset($post['permalink']) ? (string)$post['permalink'] : '';
        if ($permalink !== '') {
            return $permalink;
        }

        $slug = isset($post['slug']) ? (string)$post['slug'] : '';
        $type = isset($post['type']) ? (string)$post['type'] : 'post';
        if ($slug !== '') {
            return $type === 'page'
                ? $this->links->page($slug)
                : $this->links->postOfType($type, $slug);
        }

        return $this->links->home();
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function presentUserProfile(array $user): array
    {
        $id = isset($user['id']) ? (int)$user['id'] : 0;
        $name = trim((string)($user['name'] ?? ''));
        $slug = trim((string)($user['slug'] ?? ''));
        $createdAt = isset($user['created_at']) ? (string)$user['created_at'] : '';
        $updatedAt = isset($user['updated_at']) ? (string)$user['updated_at'] : '';
        $websiteRaw = isset($user['website_url']) ? (string)$user['website_url'] : '';
        $websiteNormalized = $this->normalizeWebsiteUrl($websiteRaw);
        if ($websiteNormalized === null) {
            $websiteNormalized = '';
        }
        $websiteLabel = $websiteNormalized !== '' ? $this->websiteLabel($websiteNormalized) : '';
        $avatarPath = isset($user['avatar_path']) ? (string)$user['avatar_path'] : '';
        $avatarUrl = $this->resolveAvatarUrl($avatarPath);
        $avatarInitial = $this->avatarInitial($name);
        $bio = isset($user['bio']) ? (string)$user['bio'] : '';
        $bio = $this->sanitizeBio($bio);

        $profileUrl = $this->links->user($slug !== '' ? $slug : null, $id > 0 ? $id : null);

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'profile_url' => $profileUrl,
            'website_url' => $websiteNormalized,
            'website_label' => $websiteLabel,
            'avatar_path' => $avatarPath,
            'avatar_url' => $avatarUrl,
            'avatar_initial' => $avatarInitial,
            'bio' => $bio,
        ];
    }

    /**
     * @return array{shipping:array<string,array{label:string,amount:float,description?:string}>,payments:array<string,array{label:string,description?:string}>}
     */
    private function checkoutOptions(): array
    {
        return [
            'shipping' => [
                'standard' => [
                    'label' => 'Standardní doručení (3-5 dní)',
                    'amount' => 5.0,
                    'description' => 'Doručení kurýrem v rámci ČR.',
                ],
                'express' => [
                    'label' => 'Expresní doručení (1-2 dny)',
                    'amount' => 15.0,
                    'description' => 'Přednostní doručení do 48 hodin.',
                ],
                'pickup' => [
                    'label' => 'Osobní odběr',
                    'amount' => 0.0,
                    'description' => 'Vyberte si objednávku na naší pobočce.',
                ],
            ],
            'payments' => [
                'card' => [
                    'label' => 'Platba kartou online',
                ],
                'cod' => [
                    'label' => 'Dobírka',
                ],
                'bank' => [
                    'label' => 'Bankovní převod',
                ],
            ],
        ];
    }

    /**
     * @param array{shipping:array<string,array{label:string,amount:float}>,payments:array<string,array{label:string}>} $options
     * @return array<string,mixed>
     */
    private function defaultCheckoutForm(array $options): array
    {
        $shippingDefault = array_key_first($options['shipping']) ?? 'standard';
        $paymentDefault = array_key_first($options['payments']) ?? 'card';

        return [
            'customer' => [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
            ],
            'billing' => [
                'first_name' => '',
                'last_name' => '',
                'company' => '',
                'line1' => '',
                'line2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'CZ',
            ],
            'shipping' => [
                'first_name' => '',
                'last_name' => '',
                'company' => '',
                'line1' => '',
                'line2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'CZ',
            ],
            'shipping_same' => true,
            'payment_method' => $paymentDefault,
            'shipping_method' => $shippingDefault,
            'marketing_opt_in' => false,
            'create_account' => false,
            'notes' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function prefillCheckoutForm(array $form): array
    {
        $user = $this->auth->user();
        if (!is_array($user)) {
            return $form;
        }

        $email = isset($user['email']) ? trim((string)$user['email']) : '';
        if ($email !== '') {
            $form['customer']['email'] = $email;
        }

        $name = isset($user['name']) ? trim((string)$user['name']) : '';
        if ($name !== '') {
            $parts = preg_split('~\s+~u', $name, 2) ?: [];
            if ($form['customer']['first_name'] === '' && isset($parts[0])) {
                $form['customer']['first_name'] = $parts[0];
            }
            if ($form['customer']['last_name'] === '' && isset($parts[1])) {
                $form['customer']['last_name'] = $parts[1];
            }
        }

        return $form;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function captureCheckoutInput(array $form): array
    {
        $form['customer']['first_name'] = $this->sanitizeCheckoutString($_POST['customer_first_name'] ?? $form['customer']['first_name']);
        $form['customer']['last_name'] = $this->sanitizeCheckoutString($_POST['customer_last_name'] ?? $form['customer']['last_name']);
        $form['customer']['email'] = strtolower($this->sanitizeCheckoutString($_POST['customer_email'] ?? $form['customer']['email']));
        $form['customer']['phone'] = $this->sanitizeCheckoutString($_POST['customer_phone'] ?? $form['customer']['phone'], 50);

        $form['billing']['first_name'] = $this->sanitizeCheckoutString($_POST['billing_first_name'] ?? $form['billing']['first_name']);
        $form['billing']['last_name'] = $this->sanitizeCheckoutString($_POST['billing_last_name'] ?? $form['billing']['last_name']);
        $form['billing']['company'] = $this->sanitizeCheckoutString($_POST['billing_company'] ?? $form['billing']['company']);
        $form['billing']['line1'] = $this->sanitizeCheckoutString($_POST['billing_line1'] ?? $form['billing']['line1']);
        $form['billing']['line2'] = $this->sanitizeCheckoutString($_POST['billing_line2'] ?? $form['billing']['line2']);
        $form['billing']['city'] = $this->sanitizeCheckoutString($_POST['billing_city'] ?? $form['billing']['city']);
        $form['billing']['state'] = $this->sanitizeCheckoutString($_POST['billing_state'] ?? $form['billing']['state']);
        $form['billing']['postal_code'] = $this->sanitizeCheckoutString($_POST['billing_postal_code'] ?? $form['billing']['postal_code']);
        $form['billing']['country'] = strtoupper($this->sanitizeCheckoutString($_POST['billing_country'] ?? $form['billing']['country'], 2));

        $form['shipping']['first_name'] = $this->sanitizeCheckoutString($_POST['shipping_first_name'] ?? $form['shipping']['first_name']);
        $form['shipping']['last_name'] = $this->sanitizeCheckoutString($_POST['shipping_last_name'] ?? $form['shipping']['last_name']);
        $form['shipping']['company'] = $this->sanitizeCheckoutString($_POST['shipping_company'] ?? $form['shipping']['company']);
        $form['shipping']['line1'] = $this->sanitizeCheckoutString($_POST['shipping_line1'] ?? $form['shipping']['line1']);
        $form['shipping']['line2'] = $this->sanitizeCheckoutString($_POST['shipping_line2'] ?? $form['shipping']['line2']);
        $form['shipping']['city'] = $this->sanitizeCheckoutString($_POST['shipping_city'] ?? $form['shipping']['city']);
        $form['shipping']['state'] = $this->sanitizeCheckoutString($_POST['shipping_state'] ?? $form['shipping']['state']);
        $form['shipping']['postal_code'] = $this->sanitizeCheckoutString($_POST['shipping_postal_code'] ?? $form['shipping']['postal_code']);
        $form['shipping']['country'] = strtoupper($this->sanitizeCheckoutString($_POST['shipping_country'] ?? $form['shipping']['country'], 2));

        $form['shipping_same'] = !empty($_POST['shipping_same']);
        $form['create_account'] = !empty($_POST['create_account']);
        $form['marketing_opt_in'] = !empty($_POST['marketing_opt_in']);
        $form['payment_method'] = $this->sanitizeCheckoutString($_POST['payment_method'] ?? $form['payment_method'], 50);
        $form['shipping_method'] = $this->sanitizeCheckoutString($_POST['shipping_method'] ?? $form['shipping_method'], 50);
        $form['notes'] = $this->sanitizeCheckoutText($_POST['order_notes'] ?? ($form['notes'] ?? ''), 1000);
        $form['password'] = (string)($_POST['password'] ?? '');
        $form['password_confirmation'] = (string)($_POST['password_confirmation'] ?? '');

        return $form;
    }

    private function sanitizeCheckoutString($value, int $length = 190): string
    {
        $string = trim((string)$value);
        $string = preg_replace('~[\x00-\x1F]+~u', '', $string) ?? $string;
        if ($length > 0) {
            if (function_exists('mb_substr')) {
                $string = mb_substr($string, 0, $length, 'UTF-8');
            } else {
                $string = substr($string, 0, $length) ?: '';
            }
        }

        return $string;
    }

    private function sanitizeCheckoutText($value, int $length = 1000): string
    {
        $string = trim((string)$value);
        $string = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]+~u', '', $string) ?? $string;
        if ($length > 0) {
            if (function_exists('mb_substr')) {
                $string = mb_substr($string, 0, $length, 'UTF-8');
            } else {
                $string = substr($string, 0, $length) ?: '';
            }
        }

        return $string;
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $options
     * @return array{0:array<string,list<string>>,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function validateCheckoutForm(array $form, array $options): array
    {
        $errors = [];

        if ($form['customer']['first_name'] === '') {
            $errors['customer_first_name'][] = 'Zadejte křestní jméno.';
        }
        if ($form['customer']['last_name'] === '') {
            $errors['customer_last_name'][] = 'Zadejte příjmení.';
        }
        if ($form['customer']['email'] === '' || !filter_var($form['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'][] = 'Zadejte platnou e-mailovou adresu.';
        }

        foreach (['first_name', 'last_name', 'line1', 'city', 'postal_code'] as $field) {
            if ($form['billing'][$field] === '') {
                $errors['billing_' . $field][] = 'Vyplňte prosím toto pole.';
            }
        }
        if ($form['billing']['country'] === '') {
            $errors['billing_country'][] = 'Vyberte zemi.';
        }

        $shipping = $form['shipping'];
        if ($form['shipping_same']) {
            $shipping = $form['billing'];
        } else {
            foreach (['first_name', 'last_name', 'line1', 'city', 'postal_code', 'country'] as $field) {
                if ($shipping[$field] === '') {
                    $errors['shipping_' . $field][] = 'Vyplňte prosím toto pole.';
                }
            }
        }

        $shippingCode = $form['shipping_method'];
        if (!isset($options['shipping'][$shippingCode])) {
            $shippingCode = array_key_first($options['shipping']) ?? $shippingCode;
        }

        $paymentCode = $form['payment_method'];
        if (!isset($options['payments'][$paymentCode])) {
            $paymentCode = array_key_first($options['payments']) ?? $paymentCode;
        }

        if ($form['create_account']) {
            if ($form['password'] === '' || strlen($form['password']) < 8) {
                $errors['password'][] = 'Heslo musí mít alespoň 8 znaků.';
            }
            if ($form['password'] !== $form['password_confirmation']) {
                $errors['password_confirmation'][] = 'Hesla se neshodují.';
            }
        }

        $prepared = [
            'customer' => [
                'first_name' => $form['customer']['first_name'],
                'last_name' => $form['customer']['last_name'],
                'email' => strtolower($form['customer']['email']),
                'phone' => $form['customer']['phone'],
                'marketing_opt_in' => (bool)$form['marketing_opt_in'],
            ],
            'billing' => [
                'first_name' => $form['billing']['first_name'],
                'last_name' => $form['billing']['last_name'],
                'company' => $form['billing']['company'],
                'line1' => $form['billing']['line1'],
                'line2' => $form['billing']['line2'],
                'city' => $form['billing']['city'],
                'state' => $form['billing']['state'],
                'postal_code' => $form['billing']['postal_code'],
                'country' => strtoupper($form['billing']['country']),
                'phone' => $form['customer']['phone'],
                'email' => strtolower($form['customer']['email']),
            ],
            'shipping' => [
                'first_name' => $shipping['first_name'],
                'last_name' => $shipping['last_name'],
                'company' => $shipping['company'],
                'line1' => $shipping['line1'],
                'line2' => $shipping['line2'],
                'city' => $shipping['city'],
                'state' => $shipping['state'],
                'postal_code' => $shipping['postal_code'],
                'country' => strtoupper($shipping['country']),
                'phone' => $form['customer']['phone'],
                'email' => strtolower($form['customer']['email']),
            ],
            'shipping_method' => $shippingCode,
            'shipping_method_label' => (string)($options['shipping'][$shippingCode]['label'] ?? $shippingCode),
            'shipping_total' => (float)($options['shipping'][$shippingCode]['amount'] ?? 0.0),
            'payment_method' => $paymentCode,
            'payment_method_label' => (string)($options['payments'][$paymentCode]['label'] ?? $paymentCode),
            'notes' => $form['notes'],
            'create_account' => (bool)$form['create_account'],
            'password' => (string)($form['password'] ?? ''),
            'password_confirmation' => (string)($form['password_confirmation'] ?? ''),
        ];

        $form['shipping'] = $shipping;
        $form['shipping_method'] = $shippingCode;
        $form['payment_method'] = $paymentCode;
        $form['password'] = '';
        $form['password_confirmation'] = '';

        return [$errors, $prepared, $form];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     */
    private function resolveCheckoutUserId(array $payload, array &$errors): ?int
    {
        $current = $this->auth->user();
        if (is_array($current)) {
            $userId = isset($current['id']) ? (int)$current['id'] : 0;
            return $userId > 0 ? $userId : null;
        }

        if (empty($payload['create_account'])) {
            return null;
        }

        $existing = $this->users->findByEmail($payload['customer']['email']);
        if ($existing) {
            $errors['customer_email'][] = 'Na tento e-mail již existuje účet. Přihlaste se, prosím.';
            return null;
        }

        $password = (string)($payload['password'] ?? '');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            $errors['password'][] = 'Nepodařilo se vytvořit účet. Zkuste jiné heslo.';
            return null;
        }

        $name = trim($payload['customer']['first_name'] . ' ' . $payload['customer']['last_name']);
        if ($name === '') {
            $name = $payload['customer']['email'];
        }

        $slugService = new UserSlugService($this->users);
        $slug = $slugService->generate($name);
        $userId = $this->users->create([
            'name' => $name,
            'email' => $payload['customer']['email'],
            'slug' => $slug,
            'password_hash' => $hash,
            'role' => 'user',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId > 0 ? $userId : null;
    }

    /**
     * @param array<string,mixed> $product
     * @return list<array<string,mixed>>
     */
    private function buildProductStructuredData(array $product, string $canonical): array
    {
        $price = isset($product['price']) ? (float)$product['price'] : 0.0;
        $currency = isset($product['currency']) ? (string)$product['currency'] : 'USD';
        $description = strip_tags((string)($product['short_description'] ?? ($product['description'] ?? '')));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string)($product['name'] ?? ''),
            'description' => $description,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => number_format($price, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'url' => $canonical,
            ],
        ];

        $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
        if ($variants !== []) {
            $firstVariant = $variants[0];
            if (isset($firstVariant['sku']) && $firstVariant['sku'] !== '') {
                $data['sku'] = (string)$firstVariant['sku'];
            }
            if (isset($firstVariant['price'])) {
                $variantPrice = (float)$firstVariant['price'];
                $data['offers']['price'] = number_format($variantPrice, 2, '.', '');
                if (!empty($firstVariant['currency'])) {
                    $data['offers']['priceCurrency'] = (string)$firstVariant['currency'];
                }
            }
        }

        return [$data];
    }

    private function normalizeWebsiteUrl(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $trimmed)) {
            $trimmed = 'https://' . $trimmed;
        }

        $normalized = filter_var($trimmed, FILTER_VALIDATE_URL);
        if ($normalized === false) {
            return null;
        }

        return $normalized;
    }

    private function sanitizeBio(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $cleaned = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]+~u', '', $trimmed);
        if (!is_string($cleaned)) {
            $cleaned = $trimmed;
        }

        return str_replace(["\r\n", "\r"], "\n", $cleaned);
    }

    private function websiteLabel(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $stripped = preg_replace('~^https?://~i', '', $url) ?? $url;
        return rtrim($stripped, '/');
    }

    private function avatarInitial(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '?';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $initial = mb_substr($trimmed, 0, 1, 'UTF-8');
            return mb_strtoupper($initial, 'UTF-8');
        }

        $initial = substr($trimmed, 0, 1);
        return $initial !== false ? strtoupper($initial) : '?';
    }

    private function resolveAvatarUrl(?string $relative): string
    {
        $path = is_string($relative) ? trim($relative) : '';
        if ($path === '') {
            return '';
        }

        $resolver = $this->uploads();
        if ($resolver !== null) {
            try {
                return $resolver->publicUrl($path);
            } catch (Throwable $exception) {
                error_log('Failed to resolve avatar URL: ' . $exception->getMessage());
            }
        }

        $normalized = ltrim($path, '/');
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return '/' . $normalized;
        }

        return '/uploads/' . $normalized;
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{relative:string,mime:string}
     */
    private function processAvatarUpload(array $file): array
    {
        $paths = $this->uploads();
        if ($paths === null) {
            throw new \RuntimeException('Nelze zapisovat do adresáře s uploady.');
        }

        $allowed = ['image/jpeg', 'image/png'];

        $payload = [
            'name' => (string)($file['name'] ?? ''),
            'type' => (string)($file['type'] ?? ''),
            'tmp_name' => (string)($file['tmp_name'] ?? ''),
            'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($file['size'] ?? 0),
        ];

        $uploader = new Uploader($paths, $allowed, 5_000_000);
        $result = $uploader->handle($payload, 'avatars', false);

        $relative = (string)($result['relative'] ?? '');
        $mime = strtolower((string)($result['mime'] ?? ''));

        if ($relative === '') {
            throw new \RuntimeException('Nepodařilo se uložit soubor.');
        }

        if (!in_array($mime, $allowed, true)) {
            $this->deleteAvatar($relative);
            throw new \RuntimeException('Nepodporovaný formát souboru.');
        }

        $this->resizeAvatar($relative, $mime);

        return ['relative' => $relative, 'mime' => $mime];
    }

    private function resizeAvatar(string $relative, string $mime): void
    {
        $relative = trim($relative);
        if ($relative === '') {
            return;
        }

        $paths = $this->uploads();
        if ($paths === null) {
            return;
        }

        try {
            $abs = $paths->absoluteFromRelative($relative);
        } catch (Throwable $exception) {
            error_log('Failed to access avatar for resize: ' . $exception->getMessage());
            return;
        }

        if (!is_file($abs)) {
            return;
        }

        $resource = $this->createAvatarImageResource($abs, $mime);
        if (!$resource) {
            return;
        }

        $width = imagesx($resource);
        $height = imagesy($resource);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($resource);
            return;
        }

        $targetSize = 64;
        $crop = min($width, $height);
        $srcX = (int)max(0, floor(($width - $crop) / 2));
        $srcY = (int)max(0, floor(($height - $crop) / 2));

        $target = imagecreatetruecolor($targetSize, $targetSize);
        if ($target === false) {
            imagedestroy($resource);
            return;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);

        imagecopyresampled($target, $resource, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $crop, $crop);

        $normalizedMime = strtolower($mime);
        if ($normalizedMime === 'image/png') {
            imagepng($target, $abs);
        } elseif (in_array($normalizedMime, ['image/jpeg', 'image/pjpeg', 'image/jpg'], true)) {
            imagejpeg($target, $abs, 85);
        } else {
            imagejpeg($target, $abs, 85);
        }

        @chmod($abs, 0644);

        imagedestroy($target);
        imagedestroy($resource);
    }

    private function createAvatarImageResource(string $absPath, string $mime)
    {
        $normalized = strtolower($mime);

        return match ($normalized) {
            'image/png' => (function () use ($absPath) {
                $img = @imagecreatefrompng($absPath);
                if ($img) {
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                }
                return $img;
            })(),
            'image/jpeg', 'image/pjpeg', 'image/jpg' => @imagecreatefromjpeg($absPath),
            default => null,
        };
    }

    private function deleteAvatar(?string $relative, ?string $current = null): void
    {
        $path = is_string($relative) ? trim($relative) : '';
        $keep = is_string($current) ? trim($current) : '';
        if ($path === '' || $path === $keep) {
            return;
        }

        $paths = $this->uploads();
        if ($paths === null) {
            return;
        }

        try {
            $abs = $paths->absoluteFromRelative($path);
        } catch (Throwable) {
            return;
        }

        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function uploads(): ?PathResolver
    {
        if ($this->uploadPaths !== null) {
            return $this->uploadPaths;
        }

        try {
            $this->uploadPaths = UploadPathFactory::forUploads();
        } catch (Throwable $exception) {
            error_log('Uploads path unavailable: ' . $exception->getMessage());
            $this->uploadPaths = null;
        }

        return $this->uploadPaths;
    }

    private function resolve(): RouteResult
    {
        $route = $this->detectRoute();
        $name = $route['name'];
        $params = $route['params'];

        return match ($name) {
            'home' => $this->handleHome(),
            'post' => $this->handlePost(
                (string)($params['slug'] ?? ''),
                (string)($params['type'] ?? 'post')
            ),
            'page' => $this->handlePage((string)($params['slug'] ?? '')),
            'type' => $this->handleType((string)($params['type'] ?? 'post')),
            'category' => $this->handleTerm((string)($params['slug'] ?? ''), 'category'),
            'tag' => $this->handleTerm((string)($params['slug'] ?? ''), 'tag'),
            'search' => $this->handleSearch((string)($params['query'] ?? ($params['s'] ?? ''))),
            'account' => $this->handleAccount(),
            'register' => $this->handleRegister(),
            'lost' => $this->handleLost(),
            'reset' => $this->handleReset((string)($params['token'] ?? ''), (int)($params['id'] ?? 0)),
            'user' => $this->handleUser((string)($params['slug'] ?? ''), (int)($params['id'] ?? 0)),
            'catalog' => $this->handleCatalog(),
            'catalog-product' => $this->handleCatalogProduct((string)($params['slug'] ?? '')),
            'checkout' => $this->handleCheckout(),
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
            if ($route === 'catalog' || $route === 'catalog-product' || $route === 'checkout') {
                return ['name' => $route, 'params' => $params];
            }
            $mapped = $this->mapPostRouteFromSlug($route, $params);
            if ($mapped !== null) {
                return $mapped;
            }

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

            if ($first === 'products') {
                if ($second === '') {
                    return ['name' => 'catalog', 'params' => []];
                }

                return ['name' => 'catalog-product', 'params' => ['slug' => $second]];
            }
            if ($first === 'checkout') {
                return ['name' => 'checkout', 'params' => []];
            }
            if ($bases['post_base'] !== '' && $first === trim($bases['post_base'], '/')) {
                return ['name' => 'post', 'params' => ['slug' => $second, 'type' => 'post']];
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
            if ($first === 'account') {
                return ['name' => 'account', 'params' => []];
            }
            if ($bases['author_base'] !== '' && $first === trim($bases['author_base'], '/')) {
                return ['name' => 'user', 'params' => ['slug' => $second]];
            }
            $customPost = $this->mapPrettyPostRoute($first, $second);
            if ($customPost !== null) {
                return $customPost;
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
                return ['name' => 'post', 'params' => ['slug' => $slug, 'type' => 'post']];
            }
        }

        return ['name' => 'page', 'params' => ['slug' => $segments[0] ?? '']];
    }

    /**
     * @param array<string,mixed> $params
     * @return array{name:string,params:array<string,mixed>}|null
     */
    private function mapPostRouteFromSlug(string $slug, array $params): ?array
    {
        $type = PostTypeRegistry::typeForSlug($slug);
        if ($type === null) {
            return null;
        }

        if ($type === 'page') {
            unset($params['type']);

            return ['name' => 'page', 'params' => $params];
        }

        $params['type'] = $type;

        return ['name' => 'post', 'params' => $params];
    }

    /**
     * @return array{name:string,params:array<string,mixed>}|null
     */
    private function mapPrettyPostRoute(string $base, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        $type = PostTypeRegistry::typeForSlug($base);
        if ($type === null) {
            return null;
        }

        if ($type === 'page') {
            return ['name' => 'page', 'params' => ['slug' => $slug]];
        }

        $baseInfo = $this->links->postTypeBase($type);
        $expected = trim($baseInfo['pretty'], '/');
        $normalizedExpected = strtolower($expected);
        $normalizedBase = strtolower(trim($base, '/'));
        if ($normalizedExpected === '' || $normalizedExpected !== $normalizedBase) {
            return null;
        }

        return ['name' => 'post', 'params' => ['slug' => $slug, 'type' => $type]];
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
        $siteTitle = $this->settings->siteTitle();
        $tagline = $this->limitString($this->settings->siteTagline());
        $canonical = $this->absoluteUrl($this->links->home());

        $meta = new SeoMeta(
            $siteTitle,
            $tagline !== '' ? $tagline : null,
            $canonical,
            structuredData: $this->buildHomeStructuredData($canonical)
        );

        return new RouteResult('home', [
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handlePost(string $slug, string $type = 'post'): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $normalizedType = trim($type) !== '' ? $type : 'post';
        $post = $this->posts->findPublished($slug, $normalizedType);
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
        $sessionForm = $this->pullFormState('comment:' . (int)($post['id'] ?? 0));
        if (is_array($sessionForm)) {
            if (isset($sessionForm['success'])) {
                $commentForm['success'] = !empty($sessionForm['success']);
            }
            if (isset($sessionForm['message'])) {
                $commentForm['message'] = (string)$sessionForm['message'];
            }
            if (isset($sessionForm['errors']) && is_array($sessionForm['errors'])) {
                $commentForm['errors'] = $sessionForm['errors'];
            }
            if (isset($sessionForm['old']) && is_array($sessionForm['old'])) {
                $commentForm['old'] = array_replace($commentForm['old'], $sessionForm['old']);
            }
            if (isset($sessionForm['status'])) {
                $status = (int)$sessionForm['status'];
            }
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['comment_form'])) {
            if (!$commentsAllowed) {
                $commentForm['message'] = 'Komentáře jsou u tohoto článku uzavřeny.';
                $status = 403;
            } else {
                $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
                if (!$this->verifyCsrf($token)) {
                    $commentForm['message'] = 'Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.';
                    $commentForm['errors']['general'][] = 'Ověření formuláře selhalo.';
                    $status = 419;
                    $this->storeFormState('comment:' . (int)($post['id'] ?? 0), $commentForm, $status);
                    $this->redirect($this->commentRedirectUrl($post));
                }

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

            $this->storeFormState('comment:' . (int)($post['id'] ?? 0), $commentForm, $status);
            $this->redirect($this->commentRedirectUrl($post));
        }

        $commentData = $this->comments->publishedForPost((int)($post['id'] ?? 0));

        $commentForm['csrf'] = $this->csrfToken();

        $canonical = $this->absoluteUrl((string)($post['permalink'] ?? ''));
        $excerpt = isset($post['excerpt']) ? (string)$post['excerpt'] : '';

        $meta = new SeoMeta(
            $post['title'] . ' | ' . $this->settings->siteTitle(),
            $excerpt !== '' ? $excerpt : null,
            $canonical,
            extra: $this->buildContentMetaExtra($post, true),
            structuredData: $this->buildPostStructuredData($post, $canonical, 'BlogPosting')
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

        $canonical = $this->absoluteUrl((string)($page['permalink'] ?? ''));
        $excerpt = isset($page['excerpt']) ? (string)$page['excerpt'] : '';

        $meta = new SeoMeta(
            $page['title'] . ' | ' . $this->settings->siteTitle(),
            $excerpt !== '' ? $excerpt : null,
            $canonical,
            extra: $this->buildContentMetaExtra($page, false),
            structuredData: $this->buildPostStructuredData($page, $canonical, 'WebPage')
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
        $typeTitle = ucfirst($type);
        $canonical = $this->absoluteUrl($this->links->type($type));
        $description = $this->limitString(sprintf('Archiv příspěvků typu %s na %s.', $typeTitle, $this->settings->siteTitle()));

        $meta = new SeoMeta(
            $typeTitle . ' | ' . $this->settings->siteTitle(),
            $description !== '' ? $description : null,
            $canonical,
            structuredData: $this->buildCollectionStructuredData($typeTitle, $canonical, $description)
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
        $canonicalUrl = $this->absoluteUrl($canonical);

        $termName = (string)$term['name'];
        $rawDescription = isset($term['description']) ? (string)$term['description'] : '';
        $descriptionSource = $rawDescription !== ''
            ? $rawDescription
            : sprintf('Obsah označený jako %s.', $termName);
        $description = $this->limitString($descriptionSource);

        $meta = new SeoMeta(
            $termName . ' | ' . $this->settings->siteTitle(),
            $description !== '' ? $description : null,
            $canonicalUrl,
            structuredData: $this->buildCollectionStructuredData($termName, $canonicalUrl, $description)
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

        $canonicalUrl = $this->absoluteUrl($this->links->search($query !== '' ? $query : null));
        $siteTitle = $this->settings->siteTitle();
        $titleBase = $query === '' ? 'Vyhledávání' : 'Hledám "' . $query . '"';
        $descriptionSource = $query === ''
            ? sprintf('Vyhledávání napříč webem %s.', $siteTitle)
            : sprintf('Výsledky hledání pro "%s" na %s.', $query, $siteTitle);
        $description = $this->limitString($descriptionSource);

        $meta = new SeoMeta(
            $titleBase . ' | ' . $siteTitle,
            $description !== '' ? $description : null,
            $canonicalUrl,
            structuredData: $this->buildSearchStructuredData($canonicalUrl, $query)
        );

        return new RouteResult('search', [
            'query' => $query,
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleCatalog(): RouteResult
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $listing = $this->catalog->paginate($page, 12);
        $siteTitle = $this->settings->siteTitle();
        $tagline = $this->settings->siteTagline();

        $meta = new SeoMeta(
            'Produkty | ' . $siteTitle,
            $tagline !== '' ? $this->limitString($tagline) : null,
            $this->absoluteUrl($this->links->products())
        );

        return new RouteResult('product-list', [
            'products' => $listing['items'],
            'pagination' => $listing['pagination'],
            'cart' => $this->cartState(),
            'csrf' => $this->csrfToken(),
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleCatalogProduct(string $slug): RouteResult
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $this->notFound();
        }

        $product = $this->catalog->findBySlug($slug);
        if ($product === null) {
            return $this->notFound();
        }

        $canonical = $this->absoluteUrl($this->links->productDetail($slug));
        $rawDescription = (string)($product['short_description'] ?? '');
        if ($rawDescription === '') {
            $rawDescription = strip_tags((string)($product['description'] ?? ''));
        }
        $description = $this->limitString($rawDescription);

        $meta = new SeoMeta(
            (string)$product['name'] . ' | ' . $this->settings->siteTitle(),
            $description !== '' ? $description : null,
            $canonical,
            structuredData: $this->buildProductStructuredData($product, $canonical)
        );

        return new RouteResult('product-detail', [
            'product' => $product,
            'variants' => $product['variants'] ?? [],
            'cart' => $this->cartState(),
            'csrf' => $this->csrfToken(),
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleCheckout(): RouteResult
    {
        $cart = $this->cartState();
        $options = $this->checkoutOptions();
        $form = $this->defaultCheckoutForm($options);
        $form = $this->prefillCheckoutForm($form);
        $errors = [];
        $status = 200;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $orderResult = null;

        if (($cart['count'] ?? 0) <= 0) {
            $meta = new SeoMeta(
                'Pokladna | ' . $this->settings->siteTitle(),
                'Váš košík je prázdný.',
                $this->absoluteUrl($this->links->checkout())
            );

            return new RouteResult('checkout/empty', [
                'cart' => $cart,
                'meta' => $meta->toArray(),
            ]);
        }

        if ($method === 'POST' && isset($_POST['checkout_form'])) {
            $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
            if (!$this->verifyCsrf($token)) {
                $errors['general'][] = 'Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.';
                $status = 419;
            } else {
                $form = $this->captureCheckoutInput($form);
                [$errors, $payload, $form] = $this->validateCheckoutForm($form, $options);

                if ($errors === []) {
                    $userId = $this->resolveCheckoutUserId($payload, $errors);
                    if ($errors === []) {
                        try {
                            $orderResult = $this->orders->create($cart, [
                                'customer' => [
                                    'email' => $payload['customer']['email'],
                                    'first_name' => $payload['customer']['first_name'],
                                    'last_name' => $payload['customer']['last_name'],
                                    'phone' => $payload['customer']['phone'],
                                    'marketing_opt_in' => $payload['customer']['marketing_opt_in'],
                                ],
                                'billing' => $payload['billing'],
                                'shipping' => $payload['shipping'],
                                'payment_method' => $payload['payment_method_label'],
                                'shipping_method' => $payload['shipping_method_label'],
                                'shipping_total' => $payload['shipping_total'],
                                'notes' => $payload['notes'],
                                'user_id' => $userId,
                            ]);
                            $completedCart = $cart;
                            if (function_exists('cms_cart_clear')) {
                                cms_cart_clear();
                                $cart = $this->cartState();
                                $this->view->share(['cart' => $cart]);
                            }

                            $order = $orderResult['order'];
                            $customer = $orderResult['customer'];
                            $meta = new SeoMeta(
                                'Objednávka dokončena | ' . $this->settings->siteTitle(),
                                'Děkujeme za váš nákup.',
                                $this->absoluteUrl($this->links->checkout())
                            );

                            return new RouteResult('checkout/complete', [
                                'order' => $order->toArray(),
                                'customer' => $customer->toArray(),
                                'cart' => $completedCart,
                                'shipping' => [
                                    'code' => $payload['shipping_method'],
                                    'label' => $payload['shipping_method_label'],
                                    'amount' => $payload['shipping_total'],
                                ],
                                'payment' => [
                                    'code' => $payload['payment_method'],
                                    'label' => $payload['payment_method_label'],
                                ],
                                'meta' => $meta->toArray(),
                            ]);
                        } catch (Throwable $exception) {
                            error_log('Failed to create order: ' . $exception->getMessage());
                            $errors['general'][] = 'Objednávku se nepodařilo dokončit. Zkuste to prosím znovu.';
                            $status = 500;
                        }
                    } else {
                        $status = 422;
                    }
                } else {
                    $status = 422;
                }
            }
        }

        if (!isset($form['password'])) {
            $form['password'] = '';
        }
        if (!isset($form['password_confirmation'])) {
            $form['password_confirmation'] = '';
        }

        $selectedShipping = $form['shipping_method'];
        if (!isset($options['shipping'][$selectedShipping])) {
            $selectedShipping = array_key_first($options['shipping']) ?? $selectedShipping;
        }
        $shippingInfo = $options['shipping'][$selectedShipping] ?? ['amount' => 0.0, 'label' => ''];

        $meta = new SeoMeta(
            'Pokladna | ' . $this->settings->siteTitle(),
            'Dokončete svou objednávku.',
            $this->absoluteUrl($this->links->checkout())
        );

        return new RouteResult('checkout/index', [
            'cart' => $cart,
            'form' => $form,
            'errors' => $errors,
            'options' => $options,
            'selected_shipping' => $selectedShipping,
            'shipping_total' => (float)($shippingInfo['amount'] ?? 0.0),
            'meta' => $meta->toArray(),
            'csrf' => $this->csrfToken(),
        ], $status);
    }

    private function handleUser(string $slug, int $id): RouteResult
    {
        $slug = trim($slug);
        $id = $id > 0 ? $id : 0;

        $user = null;
        if ($slug !== '') {
            $user = $this->users->findBySlug($slug);
        }
        if (!$user && $id > 0) {
            $user = $this->users->find($id);
        }

        if (!$user) {
            return $this->notFound();
        }

        $profile = $this->presentUserProfile($user);
        $canonicalPath = isset($profile['profile_url']) ? (string)$profile['profile_url'] : '';
        $canonical = $this->absoluteUrl($canonicalPath);
        $profileSlug = isset($profile['slug']) ? (string)$profile['slug'] : '';

        if ($this->links->prettyUrlsEnabled()
            && $slug !== ''
            && $profileSlug !== ''
            && $slug !== $profileSlug
            && $canonicalPath !== ''
        ) {
            $this->redirect($canonicalPath, 301);
        }

        $authorId = isset($profile['id']) ? (int)$profile['id'] : 0;
        $posts = $authorId > 0 ? $this->posts->byAuthor($authorId, 20) : [];
        $profile['post_count'] = count($posts);
        $commentsData = $authorId > 0 ? $this->comments->publishedByUser($authorId, 20) : ['items' => [], 'total' => 0];
        $comments = is_array($commentsData['items'] ?? null) ? $commentsData['items'] : [];
        $profile['comment_count'] = isset($commentsData['total']) ? (int)$commentsData['total'] : count($comments);

        $siteTitle = $this->settings->siteTitle();
        $displayName = isset($profile['name']) ? trim((string)$profile['name']) : '';
        $title = ($displayName !== '' ? $displayName : 'Autor') . ' | ' . $siteTitle;
        $bio = isset($profile['bio']) ? trim((string)$profile['bio']) : '';
        $descriptionSource = $bio !== ''
            ? $bio
            : ($displayName !== ''
                ? sprintf('Profil autora %s na %s.', $displayName, $siteTitle)
                : sprintf('Profil autora na %s.', $siteTitle));
        $description = $this->limitString($descriptionSource);

        if ($canonical !== '') {
            $profile['canonical'] = $canonical;
        }

        $metaExtra = [
            'og:type' => 'profile',
            'twitter:card' => 'summary',
        ];

        if ($canonical !== '') {
            $metaExtra['og:url'] = $canonical;
        }

        if ($displayName !== '') {
            $metaExtra['og:title'] = $title;
            $metaExtra['twitter:title'] = $title;
        }

        if ($description !== '') {
            $metaExtra['og:description'] = $description;
            $metaExtra['twitter:description'] = $description;
        }

        if ($profileSlug !== '') {
            $metaExtra['profile:username'] = $profileSlug;
        }

        $avatarUrl = isset($profile['avatar_url']) ? (string)$profile['avatar_url'] : '';
        if ($avatarUrl !== '') {
            $metaExtra['og:image'] = $avatarUrl;
            $metaExtra['twitter:image'] = $avatarUrl;
        }

        $websiteUrl = isset($profile['website_url']) ? (string)$profile['website_url'] : '';
        if ($websiteUrl !== '') {
            $metaExtra['og:see_also'] = $websiteUrl;
        }

        $meta = new SeoMeta(
            $title,
            $description !== '' ? $description : null,
            $canonical,
            $metaExtra,
            structuredData: $this->buildAuthorStructuredData($profile, $canonical)
        );

        return new RouteResult('user', [
            'user' => $profile,
            'posts' => $posts,
            'comments' => $comments,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleAccount(): RouteResult
    {
        $currentUser = $this->auth->user();
        $siteTitle = $this->settings->siteTitle();
        $meta = new SeoMeta('Můj profil | ' . $siteTitle, canonical: $this->links->account());

        $profile = $currentUser ? $this->presentUserProfile($currentUser) : null;
        $defaultOld = [
            'name' => $profile['name'] ?? '',
            'website' => $profile['website_url'] ?? '',
            'bio' => $profile['bio'] ?? '',
        ];

        $data = [
            'meta' => $meta->toArray(),
            'user' => $profile,
            'old' => $defaultOld,
            'errors' => [],
            'message' => null,
            'success' => false,
            'allowForm' => $currentUser !== null,
            'loginUrl' => $this->links->login(),
            'profileUrl' => $profile['profile_url'] ?? null,
        ];
        $status = $currentUser ? 200 : 401;

        $sessionForm = $this->pullFormState('account');
        if (is_array($sessionForm)) {
            if (isset($sessionForm['success'])) {
                $data['success'] = !empty($sessionForm['success']);
            }
            if (isset($sessionForm['message'])) {
                $data['message'] = $sessionForm['message'];
            }
            if (isset($sessionForm['errors']) && is_array($sessionForm['errors'])) {
                $data['errors'] = $sessionForm['errors'];
            }
            if (isset($sessionForm['old']) && is_array($sessionForm['old'])) {
                $data['old'] = array_replace($data['old'], $sessionForm['old']);
            }
            if (isset($sessionForm['allowForm'])) {
                $data['allowForm'] = !empty($sessionForm['allowForm']);
            }
            if (isset($sessionForm['status'])) {
                $status = (int)$sessionForm['status'];
            }
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            if (!$currentUser) {
                $data['message'] = 'Pro úpravu profilu se přihlaste.';
            }
            $data['csrf'] = $this->csrfToken();
            return new RouteResult('account', $data, $status);
        }

        if (!$currentUser) {
            $data['message'] = 'Pro úpravu profilu se přihlaste.';
            $data['allowForm'] = false;
            $status = 401;
            $this->storeFormState('account', $data, $status);
            $this->redirect($this->links->account());
        }

        $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        if (!$this->verifyCsrf($token)) {
            $data['message'] = 'Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.';
            $data['errors']['general'][] = 'Ověření formuláře selhalo.';
            $status = 419;
            $this->storeFormState('account', $data, $status);
            $this->redirect($this->links->account());
        }

        $input = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'website' => trim((string)($_POST['website'] ?? '')),
            'bio' => trim((string)($_POST['bio'] ?? '')),
        ];

        $data['old']['name'] = $input['name'];
        $data['old']['website'] = $input['website'];
        $data['old']['bio'] = $input['bio'];

        $errors = [];
        if ($input['name'] === '') {
            $errors['name'][] = 'Zadejte jméno.';
        }

        $websiteNormalized = '';
        if ($input['website'] !== '') {
            $normalized = $this->normalizeWebsiteUrl($input['website']);
            if ($normalized === null) {
                $errors['website'][] = 'Zadejte platnou URL adresu.';
            } else {
                if (strlen($normalized) > 255) {
                    $errors['website'][] = 'URL je příliš dlouhá.';
                }
                $websiteNormalized = $normalized;
                $data['old']['website'] = $websiteNormalized;
            }
        }

        $bioNormalized = '';
        if ($input['bio'] !== '') {
            $bioNormalized = $this->sanitizeBio($input['bio']);
            if ($bioNormalized !== '') {
                $length = function_exists('mb_strlen')
                    ? mb_strlen($bioNormalized, 'UTF-8')
                    : strlen($bioNormalized);
                if ($length > 600) {
                    $errors['bio'][] = 'Krátké představení může mít maximálně 600 znaků.';
                }
                $data['old']['bio'] = $bioNormalized;
            } else {
                $data['old']['bio'] = '';
            }
        }

        $avatarUpload = null;
        if (isset($_FILES['avatar']) && is_array($_FILES['avatar'])) {
            $fileError = (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                try {
                    $avatarUpload = $this->processAvatarUpload($_FILES['avatar']);
                } catch (Throwable $exception) {
                    error_log('Avatar upload failed: ' . $exception->getMessage());
                    $errors['avatar'][] = 'Nahrání avataru se nezdařilo. Nahrajte prosím soubor JPEG nebo PNG.';
                }
            }
        }

        if ($errors !== []) {
            if ($avatarUpload !== null) {
                $this->deleteAvatar($avatarUpload['relative']);
            }
            $data['errors'] = $errors;
            $data['message'] = 'Zkontrolujte zvýrazněná pole.';
            $status = 422;
            $this->storeFormState('account', $data, $status);
            $this->redirect($this->links->account());
        }

        $update = [
            'name' => $input['name'],
            'updated_at' => DateTimeFactory::nowString(),
            'website_url' => $websiteNormalized !== '' ? $websiteNormalized : null,
        ];
        if ($bioNormalized !== '') {
            $update['bio'] = $bioNormalized;
        } else {
            $update['bio'] = null;
        }
        if ($avatarUpload !== null) {
            $update['avatar_path'] = $avatarUpload['relative'];
        }

        try {
            $this->users->update((int)$currentUser['id'], $update);
        } catch (Throwable $exception) {
            error_log('Profile update failed: ' . $exception->getMessage());
            if ($avatarUpload !== null) {
                $this->deleteAvatar($avatarUpload['relative']);
            }
            $data['message'] = 'Profil se nepodařilo uložit. Zkuste to prosím znovu.';
            $data['errors'] = ['form' => ['Profil se nepodařilo uložit.']];
            $status = 500;
            $this->storeFormState('account', $data, $status);
            $this->redirect($this->links->account());
        }

        if ($avatarUpload !== null) {
            $this->deleteAvatar((string)($currentUser['avatar_path'] ?? ''), $avatarUpload['relative']);
        }

        $data['success'] = true;
        $data['message'] = 'Profil byl úspěšně aktualizován.';
        $data['errors'] = [];
        $data['old'] = [
            'name' => $update['name'],
            'website' => $websiteNormalized !== '' ? $websiteNormalized : '',
            'bio' => $bioNormalized !== '' ? $bioNormalized : '',
        ];
        $data['allowForm'] = true;
        $status = 200;

        $this->storeFormState('account', $data, $status);
        $this->redirect($this->links->account());
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
        $status = 200;
        $sessionForm = $this->pullFormState('register');
        if (is_array($sessionForm)) {
            if (isset($sessionForm['success'])) {
                $data['success'] = !empty($sessionForm['success']);
            }
            if (isset($sessionForm['message'])) {
                $data['message'] = $sessionForm['message'];
            }
            if (isset($sessionForm['errors']) && is_array($sessionForm['errors'])) {
                $data['errors'] = $sessionForm['errors'];
            }
            if (isset($sessionForm['old']) && is_array($sessionForm['old'])) {
                $data['old'] = array_replace($data['old'], $sessionForm['old']);
            }
            if (isset($sessionForm['status'])) {
                $status = (int)$sessionForm['status'];
            }
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            if (!$allowed) {
                $data['message'] = 'Registrace je aktuálně vypnutá.';
                $status = 403;
            }

            $data['csrf'] = $this->csrfToken();
            return new RouteResult('register', $data, $status);
        }

        if (!$allowed) {
            $data['message'] = 'Registrace je aktuálně vypnutá.';
            $status = 403;
            $this->storeFormState('register', $data, $status);
            $this->redirect($this->links->register());
        }

        $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        if (!$this->verifyCsrf($token)) {
            $data['message'] = 'Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.';
            $data['errors']['general'][] = 'Ověření formuláře selhalo.';
            $status = 419;
            $this->storeFormState('register', $data, $status);
            $this->redirect($this->links->register());
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
            $status = 422;
            $this->storeFormState('register', $data, $status);
            $this->redirect($this->links->register());
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
            $status = 500;
            $this->storeFormState('register', $data, $status);
            $this->redirect($this->links->register());
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

        if ($autoApprove) {
            try {
                $this->auth->attempt($input['email'], $input['password']);
            } catch (Throwable $e) {
                error_log('Registrace: automatické přihlášení selhalo: ' . $e->getMessage());
            }
        }

        $data['success'] = true;
        $data['errors'] = [];
        $data['old'] = ['name' => '', 'email' => ''];
        $data['message'] = $autoApprove
            ? 'Registrace proběhla úspěšně. Byli jste automaticky přihlášeni.'
            : 'Registrace byla přijata. Vyčkejte prosím na schválení administrátorem.';
        $status = 200;

        $this->storeFormState('register', $data, $status);
        $this->redirect($this->links->register());
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
        $status = 200;
        $sessionForm = $this->pullFormState('lost');
        if (is_array($sessionForm)) {
            if (isset($sessionForm['success'])) {
                $data['success'] = !empty($sessionForm['success']);
            }
            if (isset($sessionForm['message'])) {
                $data['message'] = $sessionForm['message'];
            }
            if (isset($sessionForm['errors']) && is_array($sessionForm['errors'])) {
                $data['errors'] = $sessionForm['errors'];
            }
            if (isset($sessionForm['old']) && is_array($sessionForm['old'])) {
                $data['old'] = array_replace($data['old'], $sessionForm['old']);
            }
            if (isset($sessionForm['status'])) {
                $status = (int)$sessionForm['status'];
            }
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            $data['csrf'] = $this->csrfToken();
            return new RouteResult('lost', $data, $status);
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $data['old']['email'] = $email;

        $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        if (!$this->verifyCsrf($token)) {
            $data['message'] = 'Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.';
            $data['errors']['general'][] = 'Ověření formuláře selhalo.';
            $status = 419;
            $this->storeFormState('lost', $data, $status);
            $this->redirect($this->links->lost());
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $data['errors']['email'][] = 'Zadejte platnou e-mailovou adresu.';
            $data['message'] = 'Zkontrolujte zvýrazněné pole.';
            $status = 422;
            $this->storeFormState('lost', $data, $status);
            $this->redirect($this->links->lost());
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
        $status = 200;

        $this->storeFormState('lost', $data, $status);
        $this->redirect($this->links->lost());
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
            $data['csrf'] = $this->csrfToken();
            return new RouteResult('reset', $data);
        }

        $csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        if (!$this->verifyCsrf($csrf)) {
            $data['allowForm'] = false;
            $data['message'] = 'Formulář vypršel. Požádejte prosím o nový odkaz.';
            return new RouteResult('reset', $data, 419);
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
            $data['csrf'] = $this->csrfToken();
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

    /**
     * @param array<string,mixed> $content
     * @return array<string,string>
     */
    private function buildContentMetaExtra(array $content, bool $asArticle): array
    {
        $extra = [];

        if ($asArticle) {
            $extra['og:type'] = 'article';
        }

        $image = $this->resolvePrimaryImage($content);
        if ($image !== null) {
            $extra['og:image'] = $image['url'];
            $extra['twitter:image'] = $image['url'];
            if ($image['mime'] !== null && $image['mime'] !== '') {
                $extra['og:image:type'] = $image['mime'];
            }
            if ($image['width'] !== null && $image['width'] > 0) {
                $extra['og:image:width'] = (string)$image['width'];
            }
            if ($image['height'] !== null && $image['height'] > 0) {
                $extra['og:image:height'] = (string)$image['height'];
            }
        }

        if ($asArticle) {
            $published = isset($content['published_at_iso']) ? (string)$content['published_at_iso'] : '';
            if ($published !== '') {
                $extra['article:published_time'] = $published;
            }
            $updated = isset($content['updated_at_iso']) ? (string)$content['updated_at_iso'] : '';
            if ($updated !== '') {
                $extra['article:modified_time'] = $updated;
            }
            $author = trim((string)($content['author'] ?? ''));
            if ($author !== '') {
                $extra['article:author'] = $author;
            }
        }

        return $extra;
    }

    /**
     * @param array<string,mixed> $content
     * @return list<array<string,mixed>>
     */
    private function buildAuthorStructuredData(array $user, string $canonical): array
    {
        $siteName = $this->settings->siteTitle();
        $siteUrl = $this->absoluteUrl($this->settings->siteUrl());

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string)($user['name'] ?? ''),
            'url' => $canonical,
        ];

        $memberSince = isset($user['created_at']) ? (string)$user['created_at'] : '';
        if ($memberSince !== '') {
            $data['memberSince'] = $memberSince;
        }

        if ($siteName !== '') {
            $data['worksFor'] = [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => $siteUrl !== '' ? $siteUrl : $canonical,
            ];
        }

        $avatarUrl = isset($user['avatar_url']) ? (string)$user['avatar_url'] : '';
        if ($avatarUrl !== '') {
            $data['image'] = $avatarUrl;
        }

        $websiteUrl = isset($user['website_url']) ? (string)$user['website_url'] : '';
        if ($websiteUrl !== '') {
            $data['sameAs'] = [$websiteUrl];
        }

        $bio = isset($user['bio']) ? trim((string)$user['bio']) : '';
        if ($bio !== '') {
            $data['description'] = $this->limitString($bio);
        }

        return $this->cleanStructuredDataList([$data]);
    }

    private function buildPostStructuredData(array $content, string $canonical, string $schemaType): array
    {
        $siteName = $this->settings->siteTitle();
        $siteLocale = $this->settings->siteLocale();
        $siteUrl = $this->absoluteUrl($this->settings->siteUrl());
        $siteLogo = $this->absoluteUrl($this->settings->siteLogo());

        $image = $this->resolvePrimaryImage($content);
        $imageObject = $image !== null
            ? $this->cleanStructuredData([
                '@type' => 'ImageObject',
                'url' => $image['url'],
                'width' => $image['width'],
                'height' => $image['height'],
            ])
            : [];
        $imageObject = $imageObject !== [] ? $imageObject : null;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'name' => (string)($content['title'] ?? ''),
            'description' => $this->limitString((string)($content['excerpt'] ?? '')),
            'url' => $canonical,
            'datePublished' => $content['published_at_iso'] ?? null,
            'dateModified' => $content['updated_at_iso'] ?? ($content['published_at_iso'] ?? null),
            'inLanguage' => $siteLocale,
        ];

        if ($schemaType === 'BlogPosting') {
            $data['headline'] = (string)($content['title'] ?? '');
            if ($imageObject !== null) {
                $data['image'] = $imageObject;
            }
            $data['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ];

            $authorName = trim((string)($content['author'] ?? ''));
            $authorUrl = isset($content['author_url']) ? $this->absoluteUrl((string)$content['author_url']) : '';
            if ($authorName !== '') {
                $authorData = ['@type' => 'Person', 'name' => $authorName];
                if ($authorUrl !== '') {
                    $authorData['url'] = $authorUrl;
                }
                $data['author'] = $authorData;
            } else {
                $organization = ['@type' => 'Organization', 'name' => $siteName];
                if ($authorUrl !== '') {
                    $organization['url'] = $authorUrl;
                }
                $data['author'] = $organization;
            }

            $publisher = [
                '@type' => 'Organization',
                'name' => $siteName,
            ];
            if ($siteLogo !== '') {
                $publisher['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => $siteLogo,
                ];
            }
            $data['publisher'] = $publisher;

            $data['isPartOf'] = [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl !== '' ? $siteUrl : $canonical,
            ];

            $wordCount = $this->estimateWordCount((string)($content['content'] ?? ''));
            if ($wordCount > 0) {
                $data['wordCount'] = $wordCount;
            }

            $categories = [];
            $keywords = [];
            if (isset($content['terms']) && is_array($content['terms'])) {
                foreach ($content['terms'] as $term) {
                    if (!is_array($term)) {
                        continue;
                    }
                    $name = trim((string)($term['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $termType = (string)($term['type'] ?? '');
                    if ($termType === 'category') {
                        $categories[] = $name;
                    } elseif ($termType === 'tag') {
                        $keywords[] = $name;
                    }
                }
            }

            if ($categories !== []) {
                $data['articleSection'] = count($categories) === 1 ? $categories[0] : $categories;
            }
            if ($keywords !== []) {
                $data['keywords'] = implode(', ', $keywords);
            }
        } else {
            $data['headline'] = (string)($content['title'] ?? '');
            $data['isPartOf'] = [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl !== '' ? $siteUrl : $canonical,
            ];
            if ($imageObject !== null) {
                $data['primaryImageOfPage'] = $imageObject;
            }
        }

        return $this->cleanStructuredDataList([$data]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildHomeStructuredData(string $canonical): array
    {
        $siteName = $this->settings->siteTitle();
        $siteLocale = $this->settings->siteLocale();
        $siteUrl = $this->absoluteUrl($this->settings->siteUrl());
        $siteDescription = $this->limitString($this->settings->siteTagline());
        $siteLogo = $this->absoluteUrl($this->settings->siteLogo());
        $siteEmail = $this->settings->siteEmail();
        $searchTarget = $this->buildSearchTarget();

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $canonical !== '' ? $canonical : $siteUrl,
            'description' => $siteDescription,
            'inLanguage' => $siteLocale,
        ];
        if ($searchTarget !== '') {
            $website['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => $searchTarget,
                'query-input' => 'required name=search_term_string',
            ];
        }

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $siteUrl !== '' ? $siteUrl : $canonical,
        ];
        if ($siteLogo !== '') {
            $organization['logo'] = [
                '@type' => 'ImageObject',
                'url' => $siteLogo,
            ];
        }
        if ($siteEmail !== '') {
            $organization['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $siteEmail,
            ]];
        }

        return $this->cleanStructuredDataList([$website, $organization]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildCollectionStructuredData(string $title, string $canonical, string $description): array
    {
        $siteName = $this->settings->siteTitle();
        $siteUrl = $this->absoluteUrl($this->settings->siteUrl());

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $this->limitString($description),
            'url' => $canonical,
            'inLanguage' => $this->settings->siteLocale(),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl !== '' ? $siteUrl : $canonical,
            ],
        ];

        return $this->cleanStructuredDataList([$data]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildSearchStructuredData(string $canonical, string $query): array
    {
        $siteName = $this->settings->siteTitle();
        $siteLocale = $this->settings->siteLocale();
        $searchTarget = $this->buildSearchTarget();

        $descriptionText = $query === ''
            ? sprintf('Vyhledávání napříč webem %s.', $siteName)
            : sprintf('Výsledky hledání pro "%s" na %s.', $query, $siteName);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'name' => $query === '' ? 'Vyhledávání' : sprintf('Výsledky hledání pro "%s"', $query),
            'description' => $this->limitString($descriptionText),
            'url' => $canonical,
            'inLanguage' => $siteLocale,
        ];
        if ($query !== '') {
            $data['query'] = $query;
        }
        if ($searchTarget !== '') {
            $data['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => $searchTarget,
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $this->cleanStructuredDataList([$data]);
    }

    /**
     * @param array<string,mixed> $content
     * @return array{url:string,width:?int,height:?int,mime:?string}|null
     */
    private function resolvePrimaryImage(array $content): ?array
    {
        $candidates = [];
        if (isset($content['thumbnail_url'])) {
            $candidates[] = (string)$content['thumbnail_url'];
        }
        if (is_array($content['thumbnail'] ?? null) && isset($content['thumbnail']['url'])) {
            $candidates[] = (string)$content['thumbnail']['url'];
        }
        if (isset($content['thumbnail_webp_url'])) {
            $candidates[] = (string)$content['thumbnail_webp_url'];
        }

        $url = '';
        foreach ($candidates as $candidate) {
            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                $url = $trimmed;
                break;
            }
        }

        if ($url === '') {
            $fallback = $this->settings->siteSocialImage();
            if ($fallback === '') {
                $fallback = $this->settings->siteLogo();
            }
            $url = $fallback;
        }

        $absolute = $this->absoluteUrl($url);
        if ($absolute === '') {
            return null;
        }

        $meta = is_array($content['thumbnail_meta'] ?? null) ? $content['thumbnail_meta'] : [];
        $width = null;
        foreach (['w', 'width'] as $widthKey) {
            if (isset($meta[$widthKey]) && (int)$meta[$widthKey] > 0) {
                $width = (int)$meta[$widthKey];
                break;
            }
        }
        $height = null;
        foreach (['h', 'height'] as $heightKey) {
            if (isset($meta[$heightKey]) && (int)$meta[$heightKey] > 0) {
                $height = (int)$meta[$heightKey];
                break;
            }
        }

        $mime = null;
        if (is_array($content['thumbnail'] ?? null) && isset($content['thumbnail']['mime'])) {
            $mimeCandidate = trim((string)$content['thumbnail']['mime']);
            if ($mimeCandidate !== '') {
                $mime = $mimeCandidate;
            }
        }

        return [
            'url' => $absolute,
            'width' => $width,
            'height' => $height,
            'mime' => $mime,
        ];
    }

    private function estimateWordCount(string $content): int
    {
        $text = trim(strip_tags($content));
        if ($text === '') {
            return 0;
        }

        $words = preg_split('~\s+~u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words)) {
            return 0;
        }

        return count($words);
    }

    private function limitString(string $value, int $limit = 160): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($trimmed) <= $limit) {
                return $trimmed;
            }
            return rtrim(mb_substr($trimmed, 0, $limit - 1)) . '…';
        }

        if (strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return rtrim(substr($trimmed, 0, $limit - 1)) . '…';
    }

    private function buildSearchTarget(): string
    {
        $searchBase = $this->absoluteUrl($this->links->search());
        if ($searchBase === '') {
            return '';
        }

        $separator = str_contains($searchBase, '?') ? '&' : '?';
        return $searchBase . $separator . 's={search_term_string}';
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function cleanStructuredDataList(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $clean = $this->cleanStructuredData($item);
            if ($clean === []) {
                continue;
            }
            $result[] = $clean;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function cleanStructuredData(array $data): array
    {
        $clean = $this->cleanStructuredDataValue($data);
        return is_array($clean) ? $clean : [];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function cleanStructuredDataValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                $result = [];
                foreach ($value as $key => $child) {
                    $cleaned = $this->cleanStructuredDataValue($child);
                    if ($cleaned === null) {
                        continue;
                    }
                    $result[$key] = $cleaned;
                }
                return $result === [] ? null : $result;
            }

            $result = [];
            foreach ($value as $child) {
                $cleaned = $this->cleanStructuredDataValue($child);
                if ($cleaned === null) {
                    continue;
                }
                $result[] = $cleaned;
            }

            return $result === [] ? null : $result;
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return null;
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function absoluteUrl(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, './')) {
            $trimmed = substr($trimmed, 2) ?: '';
        }

        $base = $this->settings->siteUrl();
        if ($base === '') {
            return $trimmed;
        }

        return rtrim($base, '/') . '/' . ltrim($trimmed, '/');
    }

    private function notFound(): RouteResult
    {
        $meta = new SeoMeta('Nenalezeno | ' . $this->settings->siteTitle(), canonical: null);

        return new RouteResult('404', [
            'meta' => $meta->toArray(),
        ], 404);
    }
}
