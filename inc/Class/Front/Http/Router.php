<?php
declare(strict_types=1);

namespace Cms\Front\Http;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Repositories\UsersRepository;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\Utils\UploadPathFactory;
use Cms\Front\Data\MenuProvider;
use Cms\Front\Data\ProductCatalog;
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
    private MenuProvider $menus;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private UsersRepository $users;
    private AuthService $auth;
    private ProductCatalog $catalog;
    private OrderService $orders;
    private ?PathResolver $uploadPaths = null;

    public function __construct(
        ThemeViewEngine $view,
        MenuProvider $menus,
        ?CmsSettings $settings = null,
        ?LinkGenerator $links = null,
        ?UsersRepository $users = null,
        ?AuthService $auth = null
    ) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->view = $view;
        $this->menus = $menus;
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator(null, $this->settings);
        $this->users = $users ?? new UsersRepository();
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

        $legacy = $this->resolveLegacyRoute($name);
        if ($legacy !== null) {
            return $legacy;
        }

        return match ($name) {
            'home' => $this->handleHome(),
            'catalog' => $this->handleCatalog(),
            'catalog-product' => $this->handleCatalogProduct((string)($params['slug'] ?? '')),
            'checkout' => $this->handleCheckout(),
            default => $this->notFound(),
        };
    }

    private function resolveLegacyRoute(string $name): ?RouteResult
    {
        if ($name === 'login') {
            $this->redirect($this->links->login(), 302);
        }

        if ($name === 'logout') {
            $this->redirect($this->links->logout(), 302);
        }

        $redirectToHome = ['account', 'register', 'lost', 'reset'];
        if (in_array($name, $redirectToHome, true)) {
            $this->redirect($this->links->home(), 302);
        }

        $notFoundRoutes = ['post', 'page', 'type', 'category', 'tag', 'search', 'user'];
        if (in_array($name, $notFoundRoutes, true)) {
            return $this->notFound();
        }

        return null;
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
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $listing = $this->catalog->paginate($page, 12);
        $siteTitle = $this->settings->siteTitle();
        $tagline = $this->limitString($this->settings->siteTagline());
        $canonical = $this->absoluteUrl($this->links->home());

        $meta = new SeoMeta(
            $siteTitle,
            $tagline !== '' ? $tagline : null,
            $canonical,
            structuredData: $this->buildHomeStructuredData($canonical)
        );

        return new RouteResult('product-list', [
            'products' => $listing['items'],
            'pagination' => $listing['pagination'],
            'cart' => $this->cartState(),
            'csrf' => $this->csrfToken(),
            'meta' => $meta->toArray(),
        ]);
    }

    private function handlePost(string $slug, string $type = 'post'): RouteResult
    {
        return $this->notFound();
    }



    private function handlePage(string $slug): RouteResult
    {
        return $this->notFound();
    }

    private function handleType(string $type = 'post'): RouteResult
    {
        return $this->notFound();
    }

    private function handleTerm(string $slug, string $type): RouteResult
    {
        return $this->notFound();
    }

    private function handleSearch(string $query): RouteResult
    {
        return $this->notFound();
    }

    private function handleCatalog(): RouteResult
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $listing = $this->catalog->paginate($page, 12);
        $siteTitle = $this->settings->siteTitle();
        $tagline = $this->settings->siteTagline();

        $canonical = $this->absoluteUrl($this->links->products());
        $meta = new SeoMeta(
            'Produkty | ' . $siteTitle,
            $tagline !== '' ? $this->limitString($tagline) : null,
            $canonical,
            structuredData: $this->buildCollectionStructuredData(
                'Produkty',
                $canonical,
                $tagline !== '' ? $tagline : 'Katalog dostupného zboží.'
            )
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
        return $this->notFound();
    }

    private function handleAccount(): RouteResult
    {
        return $this->notFound();
    }

    private function handleRegister(): RouteResult
    {
        return $this->notFound();
    }

    private function handleLost(): RouteResult
    {
        return $this->notFound();
    }

    private function handleReset(string $tokenParam, int $userIdParam): RouteResult
    {
        return $this->notFound();
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
        return '';
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
