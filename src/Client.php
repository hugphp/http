<?php

namespace HugPHP\Http;

use RuntimeException;

class Client
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $body = [];

    private string $url = '';

    private string $method = 'GET';

    private bool $verifySsl = true;

    private ?int $rateLimitRequests = null;

    private ?string $rateLimitPeriod = null;

    private bool $debug = false;

    /** @var array<string, array{status: int, body: string}> */
    private array $mocks = [];

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
     * @param  array<string, mixed>  $data  Associative array to encode as JSON
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
        $this->verifySsl = ! $disable;

        return $this;
    }

    /**
     * Sets rate limiting with a number of requests per time period.
     *
     * @param  int  $requests  Number of allowed requests
     * @param  string  $period  Time period ('second', 'minute', 'hour')
     * @return self Fluent interface for method chaining
     */
    public function withRateLimit(int $requests, string $period): self
    {
        $this->rateLimitRequests = $requests;
        $this->rateLimitPeriod = $period;

        return $this;
    }

    /**
     * Enables debug mode for detailed request/response logging.
     *
     * @return self Fluent interface for method chaining
     */
    public function debug(): self
    {
        $this->debug = true;

        return $this;
    }

    /**
     * Mocks a response for a given URL.
     *
     * @param  string  $url  URL to mock
     * @param  array{status: int, body: string}  $response  Mocked response data
     * @return self Fluent interface for method chaining
     */
    public function mock(string $url, array $response): self
    {
        $this->mocks[$url] = $response;

        return $this;
    }

    /**
     * Executes a GET request.
     *
     * @return Response The HTTP response
     *
     * @throws RuntimeException If the request fails
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
     * @throws RuntimeException If the request fails
     */
    public function post(): Response
    {
        $this->method = 'POST';

        return $this->send();
    }

    /**
     * Validates the response against a JSON schema and transforms it into an object.
     *
     * @template T
     *
     * @param  string  $schemaPath  Path to JSON schema file
     * @param  class-string<T>  $class  Class to transform response into
     * @return object|null Transformed object or null if invalid
     */
    public function validateSchema(string $schemaPath, string $class): ?object
    {
        $response = $this->send();
        $data = $response->json(); // Returns array<string, mixed>

        if (! file_exists($schemaPath)) {
            throw new RuntimeException("Schema file not found: $schemaPath");
        }

        $schema = json_decode((string) file_get_contents($schemaPath));
        $validator = new \JsonSchema\Validator;
        $validator->validate($data, $schema);

        if (! $validator->isValid()) {
            $errors = array_map(fn ($error) => $error['message'], $validator->getErrors());
            throw new RuntimeException('Response validation failed: '.implode(', ', $errors));
        }

        return $this->transformToObject($data, $class);
    }

    /**
     * Sends the HTTP request using cURL with rate limiting and debugging.
     *
     * @return Response The HTTP response
     *
     * @throws RuntimeException If cURL execution fails
     */
    private function send(): Response
    {
        if (isset($this->mocks[$this->url])) {
            $mock = $this->mocks[$this->url];

            return new Response($mock['status'], $mock['body']);
        }

        if ($this->rateLimitRequests && $this->rateLimitPeriod) {
            $this->handleRateLimit();
        }

        $startTime = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
            throw new RuntimeException("cURL request failed: $error");
        }

        if ($this->debug) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            echo "[$this->method] $this->url ($status, {$duration}ms)\n";
        }

        return new Response($status, (string) $response);
    }

    /**
     * Handles rate limiting with exponential backoff.
     */
    private function handleRateLimit(): void
    {
        static $requests = [];
        $now = time();
        $periodSeconds = match ($this->rateLimitPeriod) {
            'second' => 1,
            'minute' => 60,
            'hour' => 3600,
            default => throw new RuntimeException("Invalid rate limit period: $this->rateLimitPeriod"),
        };

        $requests = array_filter($requests, fn ($time): bool => $time > $now - $periodSeconds);
        $requests[] = $now;

        if (count($requests) > $this->rateLimitRequests) {
            $wait = (int) min(2 ** (count($requests) - $this->rateLimitRequests), 10); // Max 10s
            if ($this->debug) {
                echo "Rate limit hit, waiting {$wait}s\n";
            }
            sleep($wait);
        }
    }

    /**
     * Transforms JSON data into a PHP object.
     *
     * @param  array<string, mixed>  $data  JSON data
     * @param  class-string  $class  Class to transform into
     * @return object Transformed object
     */
    private function transformToObject(array $data, string $class): object
    {
        if (! class_exists($class)) {
            throw new RuntimeException("Class not found: $class");
        }

        $object = new $class;
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }

        return $object;
    }
}
