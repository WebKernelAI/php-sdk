<?php

namespace WebKernelAI\SDK\Http;

class Response
{
    private int $statusCode;
    private array $data;
    private string $rawBody;

    public function __construct(int $statusCode, array $data, string $rawBody = '')
    {
        $this->statusCode = $statusCode;
        $this->data       = $data;
        $this->rawBody    = $rawBody;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
