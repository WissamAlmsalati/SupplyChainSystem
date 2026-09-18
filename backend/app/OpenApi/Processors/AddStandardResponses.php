<?php

namespace App\OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

/**
 * Gives every operation the failures it can actually produce.
 *
 * Writing these by hand on 199 operations would mean 199 chances to forget
 * one, and a reader who is told only about the happy path has to discover the
 * rest in production. The rules are the same ones the application follows, so
 * they are applied here once rather than repeated in every docblock:
 *
 * - needs a token        → 401, and 403 because permissions are checked per route
 * - has an id in the path → 404
 * - takes a request body → 422
 * - signs someone in     → 429, those routes are rate limited
 *
 * An operation that already documents a code keeps its own wording: a specific
 * failure ("delivered cannot go back to pending") always beats the generic one.
 */
class AddStandardResponses
{
    /**
     * What is open, by method — the storefront is readable by anyone, but
     * writing to it is not, so this cannot be a list of paths alone.
     */
    private const PUBLIC_OPERATIONS = [
        'post /login', 'post /customer/login', 'post /delegate/login', 'post /admin/login',
        'post /customer/register', 'post /customer/verify-otp', 'post /customer/resend-otp',
        'post /customer/forgot-password', 'post /customer/reset-password',
        'get /products', 'get /products/{id}', 'get /categories', 'get /categories/{id}',
        'get /placeholder/{kind}', 'post /wallet/gateway/callback', 'get /wallet/gateway/sandbox/{token}',
    ];

    /** Rate limited hard, because they are how an attacker would guess. */
    private const THROTTLED = [
        '/login', '/customer/login', '/delegate/login', '/admin/login',
        '/customer/register', '/customer/verify-otp', '/customer/resend-otp',
        '/customer/forgot-password', '/customer/reset-password',
    ];

    /** Responses captured from the running API, keyed by "method path". */
    private ?array $examples = null;

    public function __invoke(Analysis $analysis): void
    {
        /** @var OA\Operation[] $operations */
        $operations = $analysis->getAnnotationsOfType(OA\Operation::class);

        foreach ($operations as $operation) {
            $path = $operation->path;
            if ($path === Generator::UNDEFINED) {
                continue;
            }

            $method = strtolower($operation->method ?? '');
            $isPublic = in_array($method.' '.$path, self::PUBLIC_OPERATIONS, true);
            $wanted = [];

            if (! $isPublic) {
                $wanted['401'] = 'Unauthenticated';
                $wanted['403'] = 'Forbidden';

                // Most operations never declared this, so the reference showed
                // them as open and its "try it" button sent no token.
                if ($operation->security === Generator::UNDEFINED) {
                    $operation->security = [['bearerAuth' => []]];
                }
            }

            if (str_contains($path, '{')) {
                $wanted['404'] = 'NotFound';
            }

            if ($operation->requestBody !== Generator::UNDEFINED) {
                $wanted['422'] = 'ValidationError';
            }

            if (in_array($path, self::THROTTLED, true)) {
                $wanted['429'] = 'TooManyRequests';
            }

            foreach ($wanted as $code => $component) {
                $this->addIfMissing($analysis, $operation, (string) $code, $component);
            }

            $this->describeSuccess($analysis, $operation, $method, $path);
        }
    }

    /**
     * A 2xx that says only "OK" tells a reader nothing. Where an operation has
     * not described its own body, give it the shape it really answers with —
     * and, for the endpoints we have captured, the answer itself.
     */
    private function describeSuccess(Analysis $analysis, OA\Operation $operation, string $method, string $path): void
    {
        $responses = $operation->responses === Generator::UNDEFINED ? [] : $operation->responses;

        foreach ($responses as $response) {
            $code = (string) $response->response;
            if (! str_starts_with($code, '2')) {
                continue;
            }
            if ($response->content !== Generator::UNDEFINED || $response->ref !== Generator::UNDEFINED) {
                continue; // it already documents itself
            }
            if ($code === '204') {
                continue; // nothing to describe
            }

            // An update answers with the record it just changed, which is the
            // same shape the reader already saw on the GET.
            $example = $this->examples()[$method.' '.$path]
                ?? (in_array($method, ['put', 'patch'], true) ? ($this->examples()['get '.$path] ?? null) : null);
            $isCollection = $method === 'get' && ! str_ends_with($path, '}');

            if ($example === null && $method === 'delete') {
                $response->description = $response->description === Generator::UNDEFINED
                    ? 'Deleted. No body.'
                    : $response->description;

                continue;
            }

            $schema = new OA\Schema([
                'type' => 'object',
                '_context' => $response->_context,
            ]);

            if ($example !== null) {
                $schema->example = $example;
            } elseif ($isCollection) {
                $schema->properties = [
                    new OA\Property(['property' => 'data', 'type' => 'array', 'items' => new OA\Items(['type' => 'object', '_context' => $response->_context]), '_context' => $response->_context]),
                    new OA\Property(['property' => 'meta', 'ref' => '#/components/schemas/PaginationMeta', '_context' => $response->_context]),
                ];
            } elseif ($code === '201') {
                $schema->ref = '#/components/schemas/Created';
            } else {
                continue; // a single record: its own annotation knows best
            }

            $content = new OA\MediaType([
                'mediaType' => 'application/json',
                'schema' => $schema,
                '_context' => $response->_context,
            ]);
            // Attached to the response, not registered as standalone
            // annotations: swagger-php validates a loose @OA\Schema as
            // misplaced, while one reached through a MediaType is fine.
            $response->content = ['application/json' => $content];
        }
    }

    private function examples(): array
    {
        if ($this->examples === null) {
            $file = __DIR__.'/../examples.json';
            $this->examples = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        }

        return $this->examples;
    }

    private function addIfMissing(Analysis $analysis, OA\Operation $operation, string $code, string $component): void
    {
        $responses = $operation->responses === Generator::UNDEFINED ? [] : $operation->responses;

        foreach ($responses as $existing) {
            if ((string) $existing->response === $code) {
                return;
            }
        }

        $response = new OA\Response([
            'response' => $code,
            'ref' => '#/components/responses/'.$component,
            '_context' => $operation->_context,
        ]);

        $responses[] = $response;
        $operation->responses = $responses;
        $analysis->addAnnotation($response, $operation->_context);
    }
}
