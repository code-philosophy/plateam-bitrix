<?php

namespace Plateam\Partner;

class ApiClient
{
    private string $apiBase;
    private string $apiKey;

    public function __construct(?string $apiBase = null, ?string $apiKey = null)
    {
        $this->apiBase = $apiBase ?? Config::apiBase();
        $this->apiKey = $apiKey ?? Config::apiKey();
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, $body, $idempotencyKey);
    }

    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'missing_api_key', 'body' => null];
        }

        $url = $this->apiBase . '/' . ltrim($path, '/');
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $siteOrigin = Config::siteOrigin();
        if ($siteOrigin !== '') {
            $headers[] = 'Origin: ' . $siteOrigin;
        }
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;
        $response = $this->transport($method, $url, $headers, $payload);

        $decoded = null;
        if ($response['raw'] !== null && $response['raw'] !== '') {
            $decoded = json_decode($response['raw'], true);
        }

        return [
            'ok' => $response['status'] >= 200 && $response['status'] < 300,
            'status' => $response['status'],
            'body' => $decoded,
            'raw' => $response['raw'],
            'error' => $response['error'],
        ];
    }

    /** @return array{status:int, raw:?string, error:?string} */
    private function transport(string $method, string $url, array $headers, ?string $body): array
    {
        if (function_exists('curl_init')) {
            return $this->transportCurl($method, $url, $headers, $body);
        }
        return $this->transportStream($method, $url, $headers, $body);
    }

    /** @return array{status:int, raw:?string, error:?string} */
    private function transportCurl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'raw' => null, 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status' => 0, 'raw' => null, 'error' => $err !== '' ? $err : 'curl_exec failed'];
        }

        return ['status' => $status, 'raw' => $raw, 'error' => null];
    }

    /** @return array{status:int, raw:?string, error:?string} */
    private function transportStream(string $method, string $url, array $headers, ?string $body): array
    {
        if (!ini_get('allow_url_fopen')) {
            return ['status' => 0, 'raw' => null, 'error' => 'allow_url_fopen disabled and curl missing'];
        }

        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) {
            $opts['http']['content'] = $body;
        }

        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            $last = error_get_last();
            return [
                'status' => 0,
                'raw' => null,
                'error' => is_array($last) ? (string) ($last['message'] ?? 'stream failed') : 'stream failed',
            ];
        }

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return ['status' => $status, 'raw' => $raw, 'error' => null];
    }

    public function partnersMe(): array
    {
        return $this->get('partners/me');
    }
}
