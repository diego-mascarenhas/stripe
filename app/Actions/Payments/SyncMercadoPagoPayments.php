<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\MercadoPago\MercadoPagoService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncMercadoPagoPayments
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
    ) {
    }

    /**
     * Sync payments from MercadoPago
     */
    public function handle(?string $beginDate = null, ?string $endDate = null): int
    {
        $processed = 0;

        try
        {
            // If no date range provided, sync last 30 days
            if (! $beginDate) {
                $beginDate = now()->subDays(30)->toIso8601String();
            }

            if (! $endDate) {
                $endDate = now()->toIso8601String();
            }

            $limit = 50;
            $offset = 0;
            $hasMore = true;

            while ($hasMore)
            {
                $payments = $this->mercadoPago->getPaymentsByDateRange(
                    $beginDate,
                    $endDate,
                    $limit,
                    $offset
                );

                if (empty($payments)) {
                    $hasMore = false;
                    break;
                }

                foreach ($payments as $paymentData)
                {
                    $this->syncPayment($paymentData);
                    $processed++;
                }

                // Check if there are more results
                $hasMore = count($payments) === $limit;
                $offset += $limit;
            }
        }
        catch (\Throwable $exception)
        {
            Log::error('Error syncing MercadoPago payments', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            report($exception);
            throw $exception;
        }

        return $processed;
    }

    /**
     * Sync a single payment by ID
     */
    public function syncPaymentById(string $paymentId): ?Payment
    {
        try
        {
            $paymentData = $this->mercadoPago->getPayment($paymentId);

            if (! $paymentData) {
                return null;
            }

            return $this->syncPayment($paymentData);
        }
        catch (\Throwable $exception)
        {
            Log::error('Error syncing MercadoPago payment by ID', [
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);

            report($exception);
            throw $exception;
        }
    }

    /**
     * Sync payment data to database
     */
    private function syncPayment(array $paymentData): Payment
    {
        $mapped = $this->mapPayment($paymentData);

        $payment = Payment::firstWhere('mercadopago_id', $mapped['mercadopago_id']);

        if ($payment) {
            $payment->update($mapped + ['last_synced_at' => now()]);
        }
        else
        {
            $payment = Payment::create($mapped + ['last_synced_at' => now()]);
        }

        return $payment;
    }

    /**
     * Map MercadoPago payment data to database structure
     */
    private function mapPayment(array $payload): array
    {
        $payer = Arr::get($payload, 'payer', []);
        $identification = Arr::get($payer, 'identification', []);
        $transactionDetails = Arr::get($payload, 'transaction_details', []);
        $feeDetails = Arr::get($payload, 'fee_details', []);

        // Calculate MercadoPago fee
        $mercadoPagoFee = 0;
        if (is_array($feeDetails)) {
            foreach ($feeDetails as $fee) {
                if (Arr::get($fee, 'type') === 'mercadopago_fee') {
                    $mercadoPagoFee += (float) Arr::get($fee, 'amount', 0);
                }
            }
        }

        return [
            'mercadopago_id' => (string) Arr::get($payload, 'id'),
            'external_reference' => Arr::get($payload, 'external_reference'),
            'payment_type' => Arr::get($payload, 'payment_type_id'),
            'payment_method' => Arr::get($payload, 'payment_method_id'),
            'status' => Arr::get($payload, 'status'),
            'status_detail' => Arr::get($payload, 'status_detail'),
            
            // Payer information
            'payer_id' => (string) Arr::get($payer, 'id'),
            'payer_email' => Arr::get($payer, 'email'),
            'payer_first_name' => Arr::get($payer, 'first_name'),
            'payer_last_name' => Arr::get($payer, 'last_name'),
            'payer_identification_type' => Arr::get($identification, 'type'),
            'payer_identification_number' => Arr::get($identification, 'number'),
            
            // Amount details
            'currency' => strtolower(Arr::get($payload, 'currency_id', 'ars')),
            'transaction_amount' => (float) Arr::get($payload, 'transaction_amount'),
            'net_amount' => (float) Arr::get($transactionDetails, 'net_received_amount'),
            'total_paid_amount' => (float) Arr::get($transactionDetails, 'total_paid_amount'),
            'shipping_cost' => (float) Arr::get($payload, 'shipping_amount', 0),
            'mercadopago_fee' => $mercadoPagoFee,
            
            // Dates
            'payment_created_at' => $this->normalizeDate(Arr::get($payload, 'date_created')),
            'payment_approved_at' => $this->normalizeDate(Arr::get($payload, 'date_approved')),
            'money_release_date' => $this->normalizeDate(Arr::get($payload, 'money_release_date')),
            
            // Additional information
            'description' => Arr::get($payload, 'description'),
            'installments' => (int) Arr::get($payload, 'installments', 1),
            'issuer_id' => (string) Arr::get($payload, 'issuer_id'),
            'operation_type' => Arr::get($payload, 'operation_type'),
            'live_mode' => (bool) Arr::get($payload, 'live_mode', true),
            'captured' => (bool) Arr::get($payload, 'captured', true),
            
            // Store full payload for reference
            'raw_payload' => $payload,
        ];
    }

    /**
     * Normalize ISO 8601 date string to Carbon instance
     */
    private function normalizeDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try
        {
            return Carbon::parse($dateString)->setTimezone(config('app.timezone'));
        }
        catch (\Exception $e)
        {
            Log::warning('Failed to parse date', [
                'date' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

