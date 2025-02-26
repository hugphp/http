<?php

namespace HugPHP\Http;

class Client
{
    private string $url = '';

    private array $headers = [];

    private array $body = [];

    private string $method = 'GET';

    private bool $verifySsl = true;

    /**
     * Sets the URL for the HTTP request.
     *
     * @param  string  $url  The target URL
     * @return self Fluent interface for method chaining
     */
    public function to(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Adds a custom header to the request.
     *
     * @param  string  $name  Header name (e.g., "Authorization")
     * @param  string  $value  Header value (e.g., "Bearer token")
     * @return self Fluent interface for method chaining
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Sets the request body as JSON data.
     *
     * @param  array  $data  Associative array to encode as JSON
     * @return self Fluent interface for method chaining
     */
    public function sendJson(array $data): self
    {
        $this->body = $data;
        $this->withHeader('Content-Type', 'application/json');

        return $this;
    }

    /**
     * Configures SSL certificate verification.
     *
     * @param  bool  $disable  If true (default), disables SSL verification; if false, enables it
     * @return self Fluent interface for method chaining
     */
    public function withOutSSLCertificate(bool $disable = true): self
    {
        $this->verifySsl = ! $disable; // True means disable SSL (false verifies)

        return $this;
    }

    /**
     * Executes a GET request.
     *
     * @return Response The HTTP response
     *
     * @throws \RuntimeException If the request fails
     */
    public function get(): Response
    {
        $this->method = 'GET';

        return $this->send();
    }

    /**
     * Executes a POST request.
     *
     * @return Response The HTTP response
     *
     * @throws \RuntimeException If the request fails
     */
    public function post(): Response
    {
        $this->method = 'POST';

        return $this->send();
    }

    /**
     * Sends the HTTP request using cURL.
     *
     * @return Response The HTTP response
     *
     * @throws \RuntimeException If cURL execution fails
     */
    private function send(): Response
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        if ($this->method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        if ($this->headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
                fn ($k, $v): string => "$k: $v",
                array_keys($this->headers),
                $this->headers
            ));
        }

        if ($this->body !== [] && $this->method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status === 0) {
            throw new \RuntimeException("cURL request failed: $error");
        }

        return new Response($status, $response);
    }
}
