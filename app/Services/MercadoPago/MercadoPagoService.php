<?php

namespace App\Services\MercadoPago;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    protected string $baseUrl = 'https://api.mercadopago.com';
    protected string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
    }

    /**
     * Get payments from Mercado Pago API
     */
    public function getPayments(array $filters = []): array
    {
        try
        {
            $params = array_merge([
                'sort' => 'date_created',
                'criteria' => 'desc',
                'range' => 'date_created',
            ], $filters);

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/v1/payments/search", $params);

            if ($response->failed()) {
                Log::error('MercadoPago API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return $response->json('results', []);
        }
        catch (\Exception $e)
        {
            Log::error('Error fetching payments from MercadoPago', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a single payment by ID
     */
    public function getPayment(string $paymentId): ?array
    {
        try
        {
            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

            if ($response->failed()) {
                Log::error('MercadoPago API error fetching payment', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        }
        catch (\Exception $e)
        {
            Log::error('Error fetching payment from MercadoPago', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get payments created in a date range
     */
    public function getPaymentsByDateRange(string $beginDate, string $endDate, int $limit = 50, int $offset = 0): array
    {
        return $this->getPayments([
            'begin_date' => $beginDate,
            'end_date' => $endDate,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Get payments by external reference
     */
    public function getPaymentsByExternalReference(string $externalReference): array
    {
        return $this->getPayments([
            'external_reference' => $externalReference,
        ]);
    }

    /**
     * Get payments by payer email
     */
    public function getPaymentsByPayerEmail(string $email): array
    {
        return $this->getPayments([
            'payer.email' => $email,
        ]);
    }

    /**
     * Get approved payments
     */
    public function getApprovedPayments(array $filters = []): array
    {
        return $this->getPayments(array_merge($filters, [
            'status' => 'approved',
        ]));
    }

    /**
     * Get pending payments
     */
    public function getPendingPayments(array $filters = []): array
    {
        return $this->getPayments(array_merge($filters, [
            'status' => 'pending',
        ]));
    }
}

