<?php

namespace Plateam\Partner;

class ReturnNotify
{
    private ApiClient $client;

    public function __construct(?ApiClient $client = null)
    {
        $this->client = $client ?? new ApiClient();
    }

    public function notify(array $params): array
    {
        $orderId = (string) ($params['orderId'] ?? '');
        $body = [
            'orderId' => $orderId,
            'operationId' => $params['operationId'] ?? ('return-' . $orderId),
            'amountKop' => isset($params['amountKop']) ? (int) $params['amountKop'] : null,
            'reason' => $params['reason'] ?? null,
        ];
        $idempotency = $params['idempotencyKey'] ?? $body['operationId'];
        return $this->client->post('returns/notify', $body, $idempotency);
    }
}
