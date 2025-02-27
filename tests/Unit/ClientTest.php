<?php

use HugPHP\Http\Client;

it('can make a GET request', function (): void {
    $client = new Client;
    $response = $client->to('https://jsonplaceholder.typicode.com/todos/1')
        ->withOutSSLCertificate()
        ->get();

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('title');
});

it('can make a POST request with JSON', function (): void {
    $client = new Client;
    $response = $client->to('https://jsonplaceholder.typicode.com/posts')
        ->withOutSSLCertificate()
        ->sendJson(['title' => 'HugPHP'])
        ->post();

    expect($response->status())->toBe(201);
    expect($response->json())->toHaveKey('id');
});

it('handles rate limiting', function (): void {
    $client = new Client;
    $client->to('https://jsonplaceholder.typicode.com/todos/1')
        ->withOutSSLCertificate()
        ->withRateLimit(2, 'second');

    // Run multiple requests in a loop to trigger rate limiting
    $start = microtime(true);
    for ($i = 0; $i < 5; $i++) { // 5 requests exceed the 2-per-second limit
        $client->get();
    }
    $duration = microtime(true) - $start;

    expect($duration)->toBeGreaterThan(1); // Should wait due to backoff
});

it('supports debugging', function (): void {
    $client = new Client;
    $output = captureOutput(function () use ($client): void {
        $client->to('https://jsonplaceholder.typicode.com/todos/1')
            ->withOutSSLCertificate()
            ->debug()
            ->get();
    });

    expect($output)->toContain('[GET] https://jsonplaceholder.typicode.com/todos/1 (200');
});

it('can mock responses', function (): void {
    $client = new Client;
    $client->mock('https://example.com', ['status' => 200, 'body' => '{"mocked": true}']);
    $response = $client->to('https://example.com')->get();

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('mocked');
});

it('validates and transforms responses', function (): void {
    $client = new Client;
    $client->mock('https://example.com', ['status' => 200, 'body' => '{"id": 1, "name": "Test"}']);
    file_put_contents('schema.json', '{"type": "array", "properties": {"id": {"type": "integer"}, "name": {"type": "string"}}, "required": ["id", "name"]}');

    $user = $client->to('https://example.com')
        ->validateSchema('schema.json', User::class);

    expect($user->name)->toBe('Test');
    unlink('schema.json'); // Clean up
});

function captureOutput(callable $callback): string
{
    ob_start();
    $callback();

    return ob_get_clean();
}

class User
{
    public int $id;

    public string $name;
}
