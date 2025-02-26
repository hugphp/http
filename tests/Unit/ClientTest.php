<?php

use HugPHP\Http\Client;

it('can make a GET request', function () {
    $client = new Client;
    $response = $client->to('https://jsonplaceholder.typicode.com/todos/1')
        ->withOutSSLCertificate()
        ->get();

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('title');
});

it('can make a POST request with JSON', function () {
    $client = new Client;
    $response = $client->to('https://jsonplaceholder.typicode.com/posts')
        ->sendJson(['title' => 'HugPHP'])
        ->withOutSSLCertificate()
        ->post();

    expect($response->status())->toBe(201);
    expect($response->json())->toHaveKey('id');
});
