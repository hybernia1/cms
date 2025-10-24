<?php
declare(strict_types=1);

namespace Core\Curl;

use JsonException;
use RuntimeException;

/**
 * Jednoduchý HTTP klient postavený nad cURL.
 */
final class Client
{
    private string $baseUri;

    /** @var array<string,string> */
    private array $defaultHeaders;

    /** @var array<string,scalar|list<scalar>> */
    private array $defaultQuery;

    /** @var array<int,mixed> */
    private array $defaultOptions;

    private ?float $timeout;

    /** @var list<callable(Request):Request> */
    private array $requestMiddleware = [];

    /** @var list<callable(Response):Response> */
    private array $responseMiddleware = [];

    private bool $sslVerify;

    private ?string $sslCert;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->baseUri         = rtrim((string)($config['base_uri'] ?? ''), '/');
        $this->defaultHeaders  = $config['headers'] ?? [];
        $this->defaultQuery    = $config['query'] ?? [];
        $this->defaultOptions  = $config['options'] ?? [];
        $this->timeout         = isset($config['timeout']) ? (float)$config['timeout'] : null;
        $this->sslVerify       = $config['ssl_verify'] ?? true;
        $this->sslCert         = is_string($config['ssl_verify']) ? $config['ssl_verify'] : null;

        if (isset($config['middleware']['request'])) {
            /** @var list<callable(Request):Request> $requestMiddlewares */
            $requestMiddlewares = $config['middleware']['request'];
            $this->requestMiddleware = $requestMiddlewares;
        }

        if (isset($config['middleware']['response'])) {
            /** @var list<callable(Response):Response> $responseMiddlewares */
            $responseMiddlewares = $config['middleware']['response'];
            $this->responseMiddleware = $responseMiddlewares;
        }
    }

    public function addRequestMiddleware(callable $middleware): void
    {
        $this->requestMiddleware[] = $middleware;
    }

    public function addResponseMiddleware(callable $middleware): void
    {
        $this->responseMiddleware[] = $middleware;
    }

    public function get(string $url, array $options = []): Response
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): Response
    {
        return $this->request('POST', $url, $options);
    }

    public function put(string $url, array $options = []): Response
    {
        return $this->request('PUT', $url, $options);
    }

    public function delete(string $url, array $options = []): Response
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param array{query?:array<string,scalar|list<scalar>>,headers?:array<string,string>,body?:string|array<mixed>,json?:mixed,timeout?:float|int,options?:array<int,mixed>} $options
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $request = new Request($method, $this->resolveUrl($url));

        $query = array_merge($this->defaultQuery, $options['query'] ?? []);
        $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);

        $body = null;
        if (array_key_exists('json', $options)) {
            try {
                $body = json_encode($options['json'], JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Unable to encode JSON payload: ' . $exception->getMessage(), (int)$exception->getCode(), $exception);
            }
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        } elseif (is_array($options['body'] ?? null)) {
            $body = http_build_query($options['body']);
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded';
        } elseif (array_key_exists('body', $options)) {
            $body = (string)$options['body'];
        }

        if ($body !== null && !isset($headers['Content-Length'])) {
            $headers['Content-Length'] = (string)strlen($body);
        }

        $request = $request
            ->withQuery($query)
            ->withHeaders($headers)
            ->withBody($body);

        foreach ($this->requestMiddleware as $middleware) {
            $result = $middleware($request);
            if ($result instanceof Request) {
                $request = $result;
            }
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL resource.');
        }

        $curlOptions = $this->prepareCurlOptions($request, $options);

        if (!curl_setopt_array($curl, $curlOptions)) {
            throw new RuntimeException('Failed to set cURL options.');
        }

        $rawResponse = curl_exec($curl);
        if ($rawResponse === false) {
            $message = curl_error($curl);
            $code    = curl_errno($curl);
            curl_close($curl);

            throw new RuntimeException(sprintf('cURL error (%d): %s', $code, $message ?: 'Unknown error'), $code);
        }

        $info       = curl_getinfo($curl);
        $statusCode = (int)($info['http_code'] ?? 0);
        $headerSize = (int)($info['header_size'] ?? 0);
        curl_close($curl);

        $headerString = substr($rawResponse, 0, $headerSize) ?: '';
        $bodyString   = substr($rawResponse, $headerSize) ?: '';

        $response = new Response(
            $statusCode,
            $this->parseHeaders($headerString),
            $bodyString,
            is_array($info) ? $info : []
        );

        foreach ($this->responseMiddleware as $middleware) {
            $result = $middleware($response);
            if ($result instanceof Response) {
                $response = $result;
            }
        }

        return $response;
    }

    private function resolveUrl(string $url): string
    {
        if ($this->baseUri === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $this->baseUri . $url;
    }

    /**
     * @param array{timeout?:float|int,options?:array<int,mixed>} $options
     * @return array<int,mixed>
     */
    private function prepareCurlOptions(Request $request, array $options): array
    {
        $curlOptions = [
            CURLOPT_URL            => $request->urlWithQuery(),
            CURLOPT_CUSTOMREQUEST  => $request->method(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($request->body() !== null && $request->method() !== 'GET') {
            $curlOptions[CURLOPT_POSTFIELDS] = $request->body();
        }

        if ($request->headers() !== []) {
            $curlOptions[CURLOPT_HTTPHEADER] = $this->formatHeaders($request->headers());
        }

        $timeout = $options['timeout'] ?? $this->timeout;
        if ($timeout !== null) {
            $curlOptions[CURLOPT_TIMEOUT] = (int)ceil((float)$timeout);
        }

        if ($this->sslCert !== null) {
            $curlOptions[CURLOPT_CAINFO] = $this->sslCert;
        }

        if ($this->sslVerify === false) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if (isset($options['options'])) {
            $curlOptions = $options['options'] + $curlOptions;
        }

        if ($this->defaultOptions !== []) {
            $curlOptions = $this->defaultOptions + $curlOptions;
        }

        return $curlOptions;
    }

    /**
     * @param array<string,string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }

    /**
     * @return array<string,list<string>>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $blocks  = preg_split("/(?:\r?\n){2}/", trim($rawHeaders)) ?: [];
        $last    = end($blocks);
        if ($last === false) {
            return $headers;
        }

        $lines = preg_split("/\r?\n/", trim($last)) ?: [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name  = trim($name);
                $value = trim($value);
                $headers[$name] ??= [];
                $headers[$name][] = $value;
            }
        }

        return $headers;
    }
}
