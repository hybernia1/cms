<?php
declare(strict_types=1);

namespace Core\Curl;

use JsonException;

/**
 * Objekt reprezentující HTTP odpověď.
 */
final class Response
{
    private int $statusCode;

    /** @var array<string,list<string>> */
    private array $headers;

    private string $body;

    /** @var array<string,mixed> */
    private array $info;

    /**
     * @param array<string,list<string>> $headers
     * @param array<string,mixed>        $info
     */
    public function __construct(int $statusCode, array $headers, string $body, array $info = [])
    {
        $this->statusCode = $statusCode;
        $this->headers    = $headers;
        $this->body       = $body;
        $this->info       = $info;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $normalized) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string,mixed>
     */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * Zkus dekódovat JSON tělo.
     *
     * @return mixed
     */
    public function json(bool $assoc = true): mixed
    {
        try {
            return json_decode($this->body, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Unable to decode JSON response: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function withBody(string $body): self
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * @param array<string,list<string>> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone         = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone             = clone $this;
        $clone->statusCode = $status;

        return $clone;
    }
}
