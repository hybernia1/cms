<?php
declare(strict_types=1);

namespace Core\Curl;

/**
 * Objekt představující HTTP požadavek.
 */
final class Request
{
    private string $method;
    private string $url;

    /** @var array<string,scalar|list<scalar>> */
    private array $query;

    /** @var array<string,string> */
    private array $headers;

    private ?string $body;

    public function __construct(string $method, string $url)
    {
        $this->method  = strtoupper($method);
        $this->url     = $url;
        $this->query   = [];
        $this->headers = [];
        $this->body    = null;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * @return array<string,scalar|list<scalar>>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * @param array<string,scalar|list<scalar>> $query
     */
    public function withQuery(array $query): self
    {
        $clone        = clone $this;
        $clone->query = $query;

        return $clone;
    }

    /**
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone          = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    public function withBody(?string $body): self
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function withUrl(string $url): self
    {
        $clone      = clone $this;
        $clone->url = $url;

        return $clone;
    }

    public function withMethod(string $method): self
    {
        $clone         = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function urlWithQuery(): string
    {
        if ($this->query === []) {
            return $this->url;
        }

        $queryString = http_build_query($this->query);
        $separator   = str_contains($this->url, '?') ? '&' : '?';

        return $this->url . $separator . $queryString;
    }
}
