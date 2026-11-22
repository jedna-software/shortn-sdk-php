<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use Shortn\Sdk\Client;
use Shortn\Sdk\RetryOptions;
use Throwable;

final class AssertionFailed extends \RuntimeException
{
}

/**
 * A tiny test runner: register tests with `Harness::test()`, run them with `Harness::run()`.
 */
final class Harness
{
    /** @var array<string,callable():void> */
    private static array $tests = [];
    public static int $assertions = 0;

    public static function test(string $name, callable $fn): void
    {
        if (isset(self::$tests[$name])) {
            throw new \LogicException('Duplicate test name: ' . $name);
        }
        self::$tests[$name] = $fn;
    }

    public static function run(?string $filter = null): int
    {
        $passed = 0;
        $failed = [];
        foreach (self::$tests as $name => $fn) {
            if ($filter !== null && !str_contains($name, $filter)) {
                continue;
            }
            try {
                $fn();
                $passed++;
                echo "  ok    {$name}\n";
            } catch (Throwable $error) {
                $failed[$name] = $error;
                echo "  FAIL  {$name}\n";
            }
        }
        foreach ($failed as $name => $error) {
            echo "\n--- {$name}\n" . get_class($error) . ': ' . $error->getMessage() . "\n"
                . $error->getFile() . ':' . $error->getLine() . "\n";
        }
        printf("\n%d passed, %d failed, %d assertions\n", $passed, count($failed), self::$assertions);
        return $failed === [] ? 0 : 1;
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    Harness::$assertions++;
    if ($expected !== $actual) {
        throw new AssertionFailed(($message === '' ? 'Values differ' : $message) . "\nexpected: "
            . var_export($expected, true) . "\nactual:   " . var_export($actual, true));
    }
}

function assert_true(bool $condition, string $message = 'Expected true'): void
{
    Harness::$assertions++;
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

/**
 * @template T of Throwable
 * @param class-string<T> $class
 * @return T
 */
function expect_throws(string $class, callable $fn): Throwable
{
    Harness::$assertions++;
    try {
        $fn();
    } catch (Throwable $error) {
        if ($error instanceof $class) {
            return $error;
        }
        throw new AssertionFailed('Expected ' . $class . ', got ' . get_class($error) . ': ' . $error->getMessage());
    }
    throw new AssertionFailed('Expected ' . $class . ' to be thrown');
}

/**
 * Records requests and answers from a queue of canned responses. A queued
 * Throwable is thrown instead of answering.
 */
final class MockTransport
{
    /** @var list<array{method:string,url:string,path:string,query:array<string,string>,rawQuery:string,headers:array<string,string>,body:?string,json:mixed,timeout:float}> */
    public array $requests = [];
    /** @var list<float> */
    public array $sleeps = [];

    /** @param list<array<string,mixed>|Throwable> $responses */
    public function __construct(private array $responses = [])
    {
    }

    /** @param array<string,mixed>|Throwable $response */
    public function push(array|Throwable $response): void
    {
        $this->responses[] = $response;
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,mixed>
     */
    public function __invoke(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $parts = parse_url($url);
        $rawQuery = $parts['query'] ?? '';
        $query = [];
        if ($rawQuery !== '') {
            foreach (explode('&', $rawQuery) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $query[rawurldecode($key)] = rawurldecode($value);
            }
        }
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'path' => $parts['path'] ?? '',
            'query' => $query,
            'rawQuery' => $rawQuery,
            'headers' => $headers,
            'body' => $body,
            'json' => $body === null ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            'timeout' => $timeout,
        ];
        $response = array_shift($this->responses) ?? ['status' => 200, 'body' => '{}'];
        if ($response instanceof Throwable) {
            throw $response;
        }
        return $response;
    }

    public function last(): array
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new AssertionFailed('No request was sent');
        }
        return $last;
    }

    public function sleeper(): callable
    {
        return function (float $seconds): void {
            $this->sleeps[] = $seconds;
        };
    }
}

/**
 * @param list<array<string,mixed>|Throwable> $responses
 * @return array{0:Client,1:MockTransport}
 */
function mock_client(array $responses = [], ?RetryOptions $retry = null, ?string $apiKey = 'sk_test_123', string $baseUrl = 'https://api.example.test'): array
{
    $transport = new MockTransport($responses);
    $client = new Client(
        apiKey: $apiKey,
        baseUrl: $baseUrl,
        timeout: 12.5,
        retry: $retry ?? new RetryOptions(initialDelay: 0.25, jitter: false),
        transport: $transport,
        sleep: $transport->sleeper(),
    );
    return [$client, $transport];
}

/** @param array<string,mixed>|list<mixed> $data */
function json_response(array $data, int $status = 200, array $headers = []): array
{
    return ['status' => $status, 'headers' => $headers + ['Content-Type' => 'application/json'], 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
}
