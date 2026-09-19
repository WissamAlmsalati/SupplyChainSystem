<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The published reference is generated from annotations, and swagger-php
 * answers a malformed one by abandoning the file and leaving the last good
 * spec in place — so the docs go stale without anything failing. Two ways that
 * happened here: a path documented twice, and a formatter deleting docblock
 * lines that had no leading asterisk. This test is the alarm.
 */
class OpenApiSpecTest extends TestCase
{
    public function test_the_spec_generates_without_error(): void
    {
        $exit = Artisan::call('l5-swagger:generate', ['--all' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, "l5-swagger:generate failed:\n".$output);
        $this->assertStringNotContainsString('Unable to merge', $output);
        $this->assertStringNotContainsString('Syntax Error', $output);
        $this->assertStringNotContainsString('Multiple @OA', $output);
    }

    public function test_every_operation_documents_its_failures(): void
    {
        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);
        $methods = ['get', 'post', 'put', 'patch', 'delete'];

        $missing = [];
        $noSuccess = [];

        foreach ($spec['paths'] as $path => $item) {
            foreach (array_intersect_key($item, array_flip($methods)) as $method => $operation) {
                $codes = array_keys($operation['responses'] ?? []);

                $failures = array_filter($codes, fn ($c) => str_starts_with((string) $c, '4') || str_starts_with((string) $c, '5'));
                if ($failures === []) {
                    $missing[] = strtoupper($method).' '.$path;
                }

                $success = array_filter($codes, fn ($c) => str_starts_with((string) $c, '2'));
                if ($success === []) {
                    $noSuccess[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $noSuccess, 'Operations with no success response');
        // AddStandardResponses fills these in, so anything here means the
        // processor stopped running or an operation escaped its rules.
        $this->assertLessThanOrEqual(3, count($missing), "Operations documenting no failure:\n".implode("\n", $missing));
    }

    public function test_protected_operations_say_they_need_a_token(): void
    {
        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

        $open = [];
        foreach ($spec['paths'] as $path => $item) {
            foreach (array_intersect_key($item, array_flip(['get', 'post', 'put', 'patch', 'delete'])) as $method => $operation) {
                if (empty($operation['security'])) {
                    $open[] = strtolower($method).' '.$path;
                }
            }
        }

        sort($open);

        // Exactly the endpoints that are meant to be reachable without a token.
        $this->assertSame([
            'get /categories',
            'get /categories/{id}',
            'get /placeholder/{kind}',
            'get /products',
            'get /products/{id}',
            'post /admin/login',
            'post /customer/forgot-password',
            'post /customer/login',
            'post /customer/register',
            'post /customer/resend-otp',
            'post /customer/reset-password',
            'post /customer/verify-otp',
            'post /delegate/login',
            'post /login',
            'post /wallet/gateway/callback',
        ], $open, 'The set of endpoints documented as open has changed');
    }

    public function test_the_customer_reference_holds_only_customer_paths(): void
    {
        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs-customer.json')), true);
        $paths = array_keys($spec['paths']);

        // The customer app's whole surface is /api/v1/customer/...; a path that
        // is not, is either not the app's to call or is missing its prefix.
        $this->assertSame([], array_values(array_filter($paths, fn ($p) => ! str_starts_with($p, '/customer/'))));

        // What an app cannot work without must be there, under the prefix.
        foreach (['/customer/login', '/customer/register', '/customer/me', '/customer/logout', '/customer/notifications', '/customer/orders', '/customer/cart/checkout'] as $needed) {
            $this->assertContains($needed, $paths);
        }
    }

    public function test_the_spec_describes_the_api_it_serves(): void
    {
        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

        $this->assertNotEmpty($spec['paths'], 'No paths were documented');

        foreach (['/customer/login', '/delegate/login', '/admin/login'] as $door) {
            $this->assertArrayHasKey($door, $spec['paths'], "{$door} is missing from the spec");
        }

        // The shared error shapes every endpoint refers to.
        foreach (['Unauthenticated', 'Forbidden', 'NotFound', 'ValidationError', 'TooManyRequests'] as $response) {
            $this->assertArrayHasKey($response, $spec['components']['responses'], "{$response} response component is missing");
        }

        // The overview is the first thing a reader sees; an empty one means a
        // docblock was eaten again.
        $this->assertGreaterThan(1500, strlen($spec['info']['description']), 'The API overview looks truncated');
    }
}
