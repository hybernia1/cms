<?php
declare(strict_types=1);

namespace Core\Curl;

use RuntimeException;

/**
 * Bootstrap pro cURL klienta v rámci aplikace.
 *
 * @psalm-type CurlConfig=array{
 *     base_uri?:string,
 *     headers?:array<string,string>,
 *     query?:array<string,scalar>,
 *     timeout?:float|int,
 *     ssl_verify?:bool|string,
 *     options?:array<int,mixed>,
 *     middleware?:array{request?:list<callable(Request):Request>,response?:list<callable(Response):Response>}
 * }
 */
final class Init
{
    private static ?Client $client = null;

    /** @var array<string,mixed> */
    private static array $config = [];
    private static bool $booted = false;

    /**
     * Předej kompletní konfiguraci z config.php.
     * Očekává klíč `curl`, který obsahuje nastavení klienta.
     *
     * @param array<string,mixed> $config
     */
    public static function boot(array $config): void
    {
        if (self::$booted) {
            return;
        }

        /** @var array<string,mixed> $curlConfig */
        $curlConfig     = $config['curl'] ?? [];
        self::$config   = $curlConfig;
        self::$client   = new Client($curlConfig);
        self::$booted   = true;
    }

    public static function client(): Client
    {
        self::ensureBooted();
        \assert(self::$client instanceof Client);

        return self::$client;
    }

    /**
     * Získej původní konfiguraci předanou z config.php.
     *
     * @return array<string,mixed>
     */
    public static function config(): array
    {
        self::ensureBooted();

        return self::$config;
    }

    private static function ensureBooted(): void
    {
        if (!self::$booted || !self::$client) {
            throw new RuntimeException('Curl client not booted. Call Init::boot($config) first.');
        }
    }
}
