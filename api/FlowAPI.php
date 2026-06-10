<?php
/**
 * SURTEADOS — Flow.cl API client
 * Docs: https://www.flow.cl/docs/api.html
 */
class FlowAPI {
    private string $apiKey;
    private string $secretKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $secretKey, string $env = 'sandbox') {
        $this->apiKey    = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl   = $env === 'production'
            ? 'https://www.flow.cl/api'
            : 'https://sandbox.flow.cl/api';
    }

    /**
     * Build HMAC-SHA256 signature.
     * Algorithm: sort params by key → concat key+value pairs → sign.
     */
    private function sign(array $params): string {
        ksort($params);
        $chain = '';
        foreach ($params as $k => $v) {
            $chain .= $k . $v;
        }
        return hash_hmac('sha256', $chain, $this->secretKey);
    }

    private function request(string $method, string $endpoint, array $params): array {
        $params['apiKey'] = $this->apiKey;
        $params['s']      = $this->sign($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (strtoupper($method) === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint . '?' . http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $resp  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Flow cURL error: ' . $error);
        }

        $data = json_decode($resp, true);
        if ($data === null) {
            throw new RuntimeException('Flow respuesta inválida: ' . $resp);
        }

        // Flow returns error codes as 'code' (integer != 200 = error)
        if (isset($data['code']) && $data['code'] !== 200 && isset($data['message'])) {
            throw new RuntimeException('Flow error ' . $data['code'] . ': ' . $data['message']);
        }

        return $data;
    }

    /**
     * Create payment order.
     * @param array $params Must include: commerceOrder, subject, currency, amount, email,
     *                      urlConfirmation, urlReturn
     * @return array { token, url, flowOrder }
     */
    public function createPayment(array $params): array {
        return $this->request('POST', '/payment/create', $params);
    }

    /**
     * Get payment status by token.
     * Status codes: 1=pending, 2=paid, 3=rejected, 4=cancelled
     */
    public function getStatus(string $token): array {
        return $this->request('GET', '/payment/getStatus', ['token' => $token]);
    }

    public function getPaymentStatus(string $token): array {
        return $this->getStatus($token);
    }

    /**
     * Get payment status by commerceOrder.
     */
    public function getStatusByCommerceId(string $commerceId): array {
        return $this->request('GET', '/payment/getStatusByCommerceId', ['commerceId' => $commerceId]);
    }
}
