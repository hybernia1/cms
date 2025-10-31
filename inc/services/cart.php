<?php
declare(strict_types=1);

use Cms\Models\Repositories\ProductRepository;
use Cms\Models\Repositories\ProductVariantRepository;
use Cms\Models\Product;
use Cms\Models\ProductVariant;

const CMS_CART_SESSION_KEY = '_cms_cart';

function cms_cart_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION[CMS_CART_SESSION_KEY]) || !is_array($_SESSION[CMS_CART_SESSION_KEY])) {
        $_SESSION[CMS_CART_SESSION_KEY] = cms_cart_default_state();
    } else {
        $cart = $_SESSION[CMS_CART_SESSION_KEY];
        if (!isset($cart['items']) || !is_array($cart['items'])) {
            $cart['items'] = [];
        }
        $_SESSION[CMS_CART_SESSION_KEY] = $cart;
    }
}

/**
 * @return array{items:array<string,array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}
 */
function cms_cart_default_state(): array
{
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
 * @return array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}
 */
function cms_cart(): array
{
    cms_cart_boot();

    /** @var array<string,mixed> $cart */
    $cart = $_SESSION[CMS_CART_SESSION_KEY] ?? cms_cart_default_state();

    return cms_cart_present($cart);
}

/**
 * @param array<string,mixed> $cart
 * @return array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}
 */
function cms_cart_present(array $cart): array
{
    $items = [];
    $rawItems = isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : [];
    foreach ($rawItems as $key => $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!isset($item['id'])) {
            $item['id'] = (string)$key;
        }
        $item['quantity'] = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $item['price'] = isset($item['price']) ? (float)$item['price'] : 0.0;
        $item['subtotal'] = isset($item['subtotal']) ? (float)$item['subtotal'] : (float)($item['price'] * $item['quantity']);
        $items[] = $item;
    }

    $subtotal = isset($cart['subtotal']) ? (float)$cart['subtotal'] : 0.0;
    $total = isset($cart['total']) ? (float)$cart['total'] : $subtotal;
    $currency = isset($cart['currency']) && is_string($cart['currency']) ? $cart['currency'] : 'USD';
    $count = isset($cart['count']) ? (int)$cart['count'] : 0;
    $updatedAt = isset($cart['updated_at']) && is_string($cart['updated_at']) ? $cart['updated_at'] : null;

    return [
        'items' => $items,
        'subtotal' => round($subtotal, 2),
        'total' => round($total, 2),
        'currency' => $currency,
        'count' => $count,
        'updated_at' => $updatedAt,
    ];
}

/**
 * @param array<string,mixed> $cart
 */
function cms_cart_recalculate(array &$cart): void
{
    $items = isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : [];
    $subtotal = 0.0;
    $count = 0;
    $currency = isset($cart['currency']) && is_string($cart['currency']) ? $cart['currency'] : 'USD';

    foreach ($items as $key => &$item) {
        if (!is_array($item)) {
            unset($items[$key]);
            continue;
        }

        $quantity = max(0, (int)($item['quantity'] ?? 0));
        $price = (float)($item['price'] ?? 0.0);

        if ($quantity <= 0) {
            unset($items[$key]);
            continue;
        }

        $item['id'] = (string)$key;
        $item['quantity'] = $quantity;
        $item['price'] = $price;
        $item['subtotal'] = round($price * $quantity, 2);
        $itemCurrency = isset($item['currency']) && is_string($item['currency']) ? $item['currency'] : '';
        if ($itemCurrency !== '') {
            $currency = $itemCurrency;
        }

        $subtotal += $item['subtotal'];
        $count += $quantity;
    }

    unset($item);

    $cart['items'] = $items;
    $cart['subtotal'] = round($subtotal, 2);
    $cart['total'] = round($subtotal, 2);
    $cart['count'] = $count;
    $cart['currency'] = $currency !== '' ? $currency : 'USD';
    $cart['updated_at'] = gmdate('Y-m-d H:i:s');
}

/**
 * @return array{success:bool,message:string,status:int,cart:array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string},item?:array<string,mixed>}
 */
function cms_cart_add_item(int $productId, ?int $variantId, int $quantity): array
{
    cms_cart_boot();

    $quantity = max(1, $quantity);

    $products = new ProductRepository();
    $product = $products->find($productId);
    if (!$product instanceof Product || (string)$product->status !== 'active') {
        return cms_cart_error('Produkt nebyl nalezen nebo je nedostupný.', 404);
    }

    $variant = null;
    if ($variantId !== null) {
        $variants = new ProductVariantRepository();
        $variantModel = $variants->find($variantId);
        if (!$variantModel instanceof ProductVariant || (int)$variantModel->product_id !== $productId) {
            return cms_cart_error('Vybraná varianta produktu není dostupná.', 422);
        }
        $variant = $variantModel;
    }

    $key = $productId . ':' . ($variant ? (int)$variant->id : 'base');

    /** @var array<string,mixed> $cart */
    $cart = $_SESSION[CMS_CART_SESSION_KEY] ?? cms_cart_default_state();
    if (!isset($cart['items']) || !is_array($cart['items'])) {
        $cart['items'] = [];
    }

    $existing = isset($cart['items'][$key]) && is_array($cart['items'][$key])
        ? $cart['items'][$key]
        : null;

    $name = $variant !== null && isset($variant->name) && (string)$variant->name !== ''
        ? (string)$variant->name
        : (string)$product->name;

    $currency = $variant !== null && isset($variant->currency) && (string)$variant->currency !== ''
        ? (string)$variant->currency
        : (string)$product->currency;

    $price = $variant !== null && isset($variant->price)
        ? (float)$variant->price
        : (float)$product->price;

    $quantityTotal = $quantity;
    if ($existing !== null) {
        $quantityTotal += max(0, (int)($existing['quantity'] ?? 0));
    }

    $cart['items'][$key] = [
        'id' => (string)$key,
        'product_id' => (int)$product->id,
        'variant_id' => $variant ? (int)$variant->id : null,
        'sku' => $variant ? (string)$variant->sku : null,
        'slug' => (string)$product->slug,
        'name' => $name,
        'price' => $price,
        'currency' => $currency,
        'quantity' => $quantityTotal,
        'subtotal' => round($price * $quantityTotal, 2),
    ];

    cms_cart_recalculate($cart);

    $_SESSION[CMS_CART_SESSION_KEY] = $cart;

    return [
        'success' => true,
        'message' => 'Produkt byl přidán do košíku.',
        'status' => 200,
        'cart' => cms_cart_present($cart),
        'item' => $cart['items'][$key],
    ];
}

/**
 * @return array{success:bool,message:string,status:int,cart:array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}}
 */
function cms_cart_update_item(string $itemId, int $quantity): array
{
    cms_cart_boot();

    $quantity = max(0, $quantity);

    /** @var array<string,mixed> $cart */
    $cart = $_SESSION[CMS_CART_SESSION_KEY] ?? cms_cart_default_state();
    if (!isset($cart['items']) || !is_array($cart['items'])) {
        $cart['items'] = [];
    }

    if (!isset($cart['items'][$itemId])) {
        return cms_cart_error('Položka v košíku nebyla nalezena.', 404);
    }

    if ($quantity <= 0) {
        unset($cart['items'][$itemId]);
    } else {
        $cart['items'][$itemId]['quantity'] = $quantity;
    }

    cms_cart_recalculate($cart);
    $_SESSION[CMS_CART_SESSION_KEY] = $cart;

    return [
        'success' => true,
        'message' => 'Košík byl aktualizován.',
        'status' => 200,
        'cart' => cms_cart_present($cart),
    ];
}

/**
 * @return array{success:bool,message:string,status:int,cart:array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}}
 */
function cms_cart_remove_item(string $itemId): array
{
    return cms_cart_update_item($itemId, 0);
}

function cms_cart_clear(): void
{
    cms_cart_boot();
    $_SESSION[CMS_CART_SESSION_KEY] = cms_cart_default_state();
}

function cms_cart_verify_csrf(string $token): bool
{
    $sessionToken = $_SESSION['csrf_front'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function cms_cart_wants_json(): bool
{
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
    $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? (string)$_SERVER['HTTP_X_REQUESTED_WITH'] : '';

    if (stripos($requestedWith, 'xmlhttprequest') !== false) {
        return true;
    }

    return stripos($accept, 'application/json') !== false;
}

/**
 * @return array{success:bool,message:string,status:int,cart:array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}}
 */
function cms_cart_error(string $message, int $status = 400): array
{
    return [
        'success' => false,
        'message' => $message,
        'status' => $status,
        'cart' => cms_cart(),
    ];
}
