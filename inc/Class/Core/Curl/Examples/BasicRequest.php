<?php
declare(strict_types=1);

use Core\Curl\Init;

require_once __DIR__ . '/../../../../../load.php';

$config = cms_bootstrap_config_or_redirect();
Init::boot($config);

$client = Init::client();

$response = $client->get('https://api.github.com/repos/octocat/Hello-World', [
    'headers' => [
        'User-Agent' => 'Core-Curl-Example',
        'Accept'     => 'application/vnd.github+json',
    ],
]);

echo 'Status: ' . $response->statusCode() . PHP_EOL;
echo 'Rate Limit: ' . ($response->header('x-ratelimit-remaining') ?? 'n/a') . PHP_EOL;

echo PHP_EOL . 'Body:' . PHP_EOL;
print_r($response->json());
