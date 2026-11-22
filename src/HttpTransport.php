<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Default HTTP layer: cURL when the extension is loaded, PHP streams otherwise.
 * Redirects are not followed.
 */
final class HttpTransport
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public static function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        if (extension_loaded('curl')) {
            return self::sendCurl($method, $url, $headers, $body, $timeout);
        }
        return self::sendStream($method, $url, $headers, $body, $timeout);
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private static function sendCurl(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $lines = ['Expect:'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $responseHeaders = [];
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Could not initialise the HTTP request.');
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_FOLLOWLOCATION => false,
            // Sub-second timeouts would otherwise use SIGALRM, which is unsafe in threaded SAPIs.
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TIMEOUT_MS => max(1, (int) round($timeout * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round(min($timeout, 10.0) * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, 'HTTP/')) {
                    $responseHeaders = [];
                } elseif (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        $result = curl_exec($handle);
        if ($result === false) {
            $message = curl_error($handle);
            throw new TransportException('Shortn request failed before receiving a response: ' . $message);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $result];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private static function sendStream(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $lines = ['Connection: close'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $lines),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
                'timeout' => $timeout,
            ],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            $error = error_get_last();
            throw new TransportException('Shortn request failed before receiving a response' . ($error === null ? '.' : ': ' . $error['message']));
        }
        $result = stream_get_contents($stream);
        $meta = stream_get_meta_data($stream);
        fclose($stream);
        if ($result === false || !empty($meta['timed_out'])) {
            throw new TransportException('Shortn request timed out while reading the response.');
        }

        $status = 0;
        $responseHeaders = [];
        foreach ((array) ($meta['wrapper_data'] ?? []) as $line) {
            $line = (string) $line;
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
                $responseHeaders = [];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }
        if ($status === 0) {
            throw new TransportException('Shortn request returned no HTTP status.');
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $result];
    }
}
