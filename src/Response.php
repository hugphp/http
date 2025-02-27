<?php

namespace HugPHP\Http;

class Response
{
    /**
     * @param  int  $status  HTTP status code (e.g., 200, 404)
     * @param  string  $body  Raw response body
     */
    public function __construct(
        private readonly int $status,
        private readonly string $body
    ) {}

    /**
     * Gets the HTTP status code.
     *
     * @return int The status code (e.g., 200 for OK, 404 for Not Found)
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Gets the raw response body.
     *
     * @return string The response body as a string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decodes the response body as JSON.
     *
     * @return array<string, mixed> Associative array of JSON data, or empty array if invalid
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
