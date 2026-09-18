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
