<?php
/**
 * Flow.cl API helper — Spa Infinity
 * https://www.flow.cl/docs/api.html
 *
 * Maneja firma HMAC-SHA256 + requests REST a Flow.
 */

class FlowClient
{
    private $apiKey;
    private $secretKey;
    private $baseUrl;

    public function __construct(array $config)
    {
        $this->apiKey    = $config['apiKey'];
        $this->secretKey = $config['secretKey'];
        $this->baseUrl   = ($config['mode'] === 'production')
            ? 'https://www.flow.cl/api'
            : 'https://sandbox.flow.cl/api';
    }

    /** Genera firma HMAC-SHA256 ordenando params alfabéticamente */
    private function sign(array $params): string
    {
        ksort($params);
        $toSign = '';
        foreach ($params as $k => $v) {
            $toSign .= $k . $v;
        }
        return hash_hmac('sha256', $toSign, $this->secretKey);
    }

    /** POST a Flow API */
    public function post(string $endpoint, array $params): array
    {
        $params['apiKey'] = $this->apiKey;
        $params['s']      = $this->sign($params);

        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Flow API connection error: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['message'] ?? ('HTTP ' . $httpCode);
            throw new Exception('Flow error: ' . $msg);
        }
        if (!is_array($data)) {
            throw new Exception('Flow returned invalid response');
        }
        return $data;
    }

    /** GET a Flow API */
    public function get(string $endpoint, array $params): array
    {
        $params['apiKey'] = $this->apiKey;
        $params['s']      = $this->sign($params);

        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new Exception('Flow error: ' . ($data['message'] ?? "HTTP $httpCode"));
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Crea una orden de pago en Flow.
     * Retorna ['url' => ..., 'token' => ..., 'flowOrder' => ...]
     */
    public function createPayment(array $order): array
    {
        $params = [
            'commerceOrder'    => $order['commerceOrder'],
            'subject'          => $order['subject'],
            'currency'         => $order['currency'] ?? 'CLP',
            'amount'           => $order['amount'],
            'email'            => $order['email'],
            'urlConfirmation'  => $order['urlConfirmation'],
            'urlReturn'        => $order['urlReturn'],
        ];
        if (!empty($order['paymentMethod'])) {
            $params['paymentMethod'] = (int)$order['paymentMethod'];
        }
        if (!empty($order['optional'])) {
            $params['optional'] = json_encode($order['optional']);
        }
        return $this->post('/payment/create', $params);
    }

    /** Obtiene el status de un pago por token */
    public function getPaymentStatus(string $token): array
    {
        return $this->get('/payment/getStatus', ['token' => $token]);
    }

    /** URL completa a la que redirigir al cliente (url + token) */
    public function paymentRedirectUrl(array $createResponse): string
    {
        return $createResponse['url'] . '?token=' . $createResponse['token'];
    }
}
