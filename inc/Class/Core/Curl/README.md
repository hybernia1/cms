# Core\\Curl

Balíček `Core\\Curl` poskytuje jednoduchý HTTP klient postavený na cURL pro použití v celém CMS.

## Konfigurace

V souboru `config.php` přidej sekci `curl`:

```php
return [
    // ...
    'curl' => [
        'base_uri'   => 'https://api.example.com',
        'headers'    => [
            'User-Agent' => 'MyCms/1.0',
        ],
        'query'      => [
            'lang' => 'cs',
        ],
        'timeout'    => 10,
        'ssl_verify' => true, // nebo cesta k vlastnímu certifikátu
        'options'    => [
            CURLOPT_FOLLOWLOCATION => true,
        ],
        'middleware' => [
            'request'  => [fn(\Core\Curl\Request $request) => $request],
            'response' => [fn(\Core\Curl\Response $response) => $response],
        ],
    ],
];
```

Middleware jsou volitelné a slouží k úpravě požadavků a odpovědí. Pokud vrátí novou instanci `Request` nebo `Response`, klient s ní dále pracuje.

## Bootstrap

V bootstrapu aplikace zavolej:

```php
$config = cms_bootstrap_config_or_redirect();
\Core\Curl\Init::boot($config);
```

Odsud můžeš kdykoliv získat připraveného klienta přes `\Core\Curl\Init::client()`.

## Základní použití

```php
use Core\Curl\Init;

$client = Init::client();
$response = $client->get('/posts', [
    'query' => ['page' => 1],
]);

if ($response->statusCode() === 200) {
    $data = $response->json();
}
```

Další metody (`post`, `put`, `delete`) fungují obdobně. Každý request/response objekt můžeš dále upravit pomocí middleware.
