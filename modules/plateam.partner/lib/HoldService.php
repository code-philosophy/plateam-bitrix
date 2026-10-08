<?php

namespace Plateam\Partner;

class HoldService
{
    private ApiClient $client;

    public function __construct(?ApiClient $client = null)
    {
        $this->client = $client ?? new ApiClient();
    }

    public function createHold(array $params): array
    {
        $orderId = (string) ($params['orderId'] ?? '');
        $checkoutToken = trim((string) ($params['checkoutToken'] ?? ''));
        if ($checkoutToken === '') {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'missing_checkout_token',
                'body' => null,
                'raw' => null,
            ];
        }
        $body = [
            'visitorId' => $params['visitorId'] ?? null,
            'userId' => $params['userId'] ?? null,
            'orderId' => $orderId,
            'sesKop' => (int) ($params['sesKop'] ?? 0),
            'uesKop' => (int) ($params['uesKop'] ?? 0),
            'checkoutToken' => $checkoutToken,
            'operationId' => $params['operationId'] ?? ('hold-' . $orderId),
        ];
        $idempotency = $params['idempotencyKey'] ?? $body['operationId'];
        return $this->client->post('holds', $body, $idempotency);
    }

    public function releaseHold(string $holdId): array
    {
        return $this->client->post('holds/' . rawurlencode($holdId) . '/release', [], 'release-' . $holdId);
    }
}
