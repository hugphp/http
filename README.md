<p align="center">
    <img src="https://raw.githubusercontent.com/hugphp/http/main/docs/logo.png" height="300" alt="hugphp/http">
    <p align="center">
        <a href="https://github.com/hugphp/http/actions"><img alt="GitHub Workflow Status (main)" src="https://github.com/hugphp/http/actions/workflows/tests.yml/badge.svg"></a>
        <a href="https://packagist.org/packages/hugphp/http"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/hugphp/http"></a>
        <a href="https://packagist.org/packages/hugphp/http"><img alt="Latest Version" src="https://img.shields.io/packagist/v/hugphp/http"></a>
        <a href="https://packagist.org/packages/hugphp/http"><img alt="License" src="https://img.shields.io/packagist/l/hugphp/http"></a>
    </p>
</p>

------
# HugPHP HTTP Client

A **delightful HTTP client** for PHP with a human-readable API, designed to make HTTP requests simple, lovable, and powerful.

> **Requires [PHP 8.3+](https://php.net/releases/)** and `ext-curl`

## Installation

Add HugPHP HTTP to your project using [Composer](https://getcomposer.org):

```bash
composer require hugphp/http
```

## Features

- Fluent, chainable API for intuitive request building.
- Built-in JSON support for easy data sending and parsing.
- Optional SSL verification toggle for flexibility.
- Lightweight and dependency-free (uses native cURL).

## Usage

```php
use HugPHP\Http\Client;

$client = new Client();

// Simple GET request (SSL verification enabled by default)
$response = $client->to('https://api.example.com/data')->get();
echo $response->body();

// POST with JSON, disabling SSL verification
$response = $client->to('https://api.example.com/post')
                   ->withHeader('Authorization', 'Bearer token')
                   ->sendJson(['name' => 'HugPHP'])
                   ->withOutSSLCertificate() // Disables SSL verification
                   ->post();
print_r($response->json());

// Fetch JSON directly
$data = $client->to('https://api.example.com/users/1')
               ->withOutSSLCertificate()
               ->get()
               ->json();
echo $data['name'];
```

## Development

🧹 Keep a modern codebase with **Pint**:
```bash
composer lint
```

✅ Run refactors using **Rector**
```bash
composer refacto
```

⚗️ Run static analysis using **PHPStan**:
```bash
composer test:types
```

✅ Run unit tests using **PEST**
```bash
composer test:unit
```

🚀 Run the entire test suite:
```bash
composer test
```

**Skeleton PHP** was created by **[Nuno Maduro](https://twitter.com/enunomaduro)** under the **[MIT license](https://opensource.org/licenses/MIT)**.
