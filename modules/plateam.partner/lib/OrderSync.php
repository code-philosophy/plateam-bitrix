<?php

namespace Plateam\Partner;

class OrderSync
{
    private ApiClient $client;

    public function __construct(?ApiClient $client = null)
    {
        $this->client = $client ?? new ApiClient();
    }

    public function markPaid(array $params): array
    {
        $orderId = (string) ($params['orderId'] ?? '');
        $body = [
            'orderId' => $orderId,
            'visitorId' => $params['visitorId'] ?? null,
            'userId' => $params['userId'] ?? null,
            'cashKop' => (int) ($params['cashKop'] ?? 0),
            'holdId' => $params['holdId'] ?? null,
            'operationId' => $params['operationId'] ?? ('paid-' . $orderId),
        ];
        $idempotency = $params['idempotencyKey'] ?? $body['operationId'];
        return $this->client->post('orders/paid', $body, $idempotency);
    }

    public function markCancelled(array $params): array
    {
        $orderId = (string) ($params['orderId'] ?? '');
        $body = [
            'orderId' => $orderId,
            'operationId' => $params['operationId'] ?? ('cancelled-' . $orderId),
        ];
        $idempotency = $params['idempotencyKey'] ?? $body['operationId'];
        return $this->client->post('orders/cancelled', $body, $idempotency);
    }
}
