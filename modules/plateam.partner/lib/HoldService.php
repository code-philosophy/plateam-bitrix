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
        $body = [
            'visitorId' => $params['visitorId'] ?? null,
            'userId' => $params['userId'] ?? null,
            'orderId' => $orderId,
            'sesKop' => (int) ($params['sesKop'] ?? 0),
            'uesKop' => (int) ($params['uesKop'] ?? 0),
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
