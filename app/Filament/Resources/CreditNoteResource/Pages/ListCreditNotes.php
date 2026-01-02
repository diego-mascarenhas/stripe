<?php

namespace App\Filament\Resources\CreditNoteResource\Pages;

use App\Actions\CreditNotes\SyncStripeCreditNotes;
use App\Filament\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\ExchangeRate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCreditNotes extends ListRecords
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sincronizar con Stripe')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $count = app(SyncStripeCreditNotes::class)->handle();

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body("{$count} notas de crédito sincronizadas desde Stripe.")
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Error al sincronizar')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('download-csv')
                ->label('Descargar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    protected function downloadCsv(): StreamedResponse
    {
        $fileName = 'notas-credito-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // CSV Headers (alineadas con facturas)
            fputcsv($handle, [
                'Comprobante',
                'Fecha',
                'Razón Social',
                'ID Fiscal',
                'Importe',
                'Moneda',
                'Cambio',
                'Importe (EUR)',
                'Tax (EUR)',
                'Total (EUR)',
                'País',
                'Estado',
                'Tipo',
                'Razón',
                'Memo',
                'Link',
            ]);

            CreditNote::where('voided', false)
                ->orderByDesc('credit_note_created_at')
                ->orderByRaw("CAST(REPLACE(number, '-', '') AS UNSIGNED) DESC")
                ->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $creditNote) {
                        $currency = strtoupper($creditNote->currency ?? 'EUR');
                        $date = $creditNote->credit_note_created_at;

                        // Raw payload en centavos
                        $payload = $creditNote->raw_payload ?? [];
                        $rawSubtotal = data_get($payload, 'subtotal');
                        $rawTotal = data_get($payload, 'total');
                        $rawTax = data_get($payload, 'tax');
                        $rawDiscountAmounts = data_get($payload, 'total_discount_amounts', []);
                        $rawDiscount = is_array($rawDiscountAmounts)
                            ? collect($rawDiscountAmounts)->sum(fn ($d) => data_get($d, 'amount', 0))
                            : 0;

                        // Convertir a unidades
                        $payloadSubtotal = $rawSubtotal !== null ? $rawSubtotal / 100 : null;
                        $payloadTotal = $rawTotal !== null ? $rawTotal / 100 : null;
                        $payloadTax = $rawTax !== null ? $rawTax / 100 : null;
                        $payloadDiscount = $rawDiscount / 100;

                        // Valores modelo
                        $modelSubtotal = $creditNote->subtotal ?? 0;
                        $modelTax = $creditNote->tax ?? 0;
                        $modelTotal = $creditNote->total ?? 0;

                        // Total realmente acreditado (con descuento)
                        $amount = $creditNote->amount ?? $modelTotal ?? $payloadTotal ?? 0;

                        // Base subtotal/total
                        $baseSubtotal = $modelSubtotal ?: ($payloadSubtotal ?? 0);
                        $baseTotal = $modelTotal ?: ($payloadTotal ?? 0);

                        // Ratio de descuento
                        if ($payloadSubtotal && $payloadTotal) {
                            $discountRatio = $payloadSubtotal > 0 ? ($payloadTotal / $payloadSubtotal) : 1;
                        } else {
                            $totalOriginal = $baseTotal + ($creditNote->discount_amount ?? $payloadDiscount ?? 0);
                            $discountRatio = $totalOriginal > 0 ? ($amount / $totalOriginal) : 1;
                        }

                        // Si es cero, forzar cero
                        if (($amount ?? 0) == 0) {
                            $subtotal = 0;
                            $tax = 0;
                            $total = 0;
                            $discountRatio = 0;
                        } else {
                            $subtotal = $baseSubtotal * $discountRatio;
                            $tax = ($modelTax ?: ($payloadTax ?? 0)) * $discountRatio;
                            $total = $amount;
                        }

                        // Tipo de cambio (similar a facturas)
                        $rateValue = null;
                        $exchangeDisplay = '';

                        if ($currency === 'USD' && $date) {
                            $usdToEur = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', 'EUR')
                                ->where('fetched_at', '<=', $date)
                                ->orderByDesc('fetched_at')
                                ->first();

                            if (! $usdToEur) {
                                $usdToEur = ExchangeRate::where('base_currency', 'USD')
                                    ->where('target_currency', 'EUR')
                                    ->where('fetched_at', '>=', $date)
                                    ->orderBy('fetched_at')
                                    ->first();
                            }

                            if ($usdToEur) {
                                $rateValue = (float) $usdToEur->rate;
                                $exchangeDisplay = number_format(1 / $rateValue, 4, ',', '.'); // EUR→USD
                            } else {
                                $exchangeDisplay = 'N/A';
                            }
                        } elseif ($currency !== 'EUR' && $date) {
                            $usdToTarget = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', $currency)
                                ->where('fetched_at', '<=', $date)
                                ->orderByDesc('fetched_at')
                                ->first();

                            if (! $usdToTarget) {
                                $usdToTarget = ExchangeRate::where('base_currency', 'USD')
                                    ->where('target_currency', $currency)
                                    ->where('fetched_at', '>=', $date)
                                    ->orderBy('fetched_at')
                                    ->first();
                            }

                            $usdToEur = ExchangeRate::where('base_currency', 'USD')
                                ->where('target_currency', 'EUR')
                                ->where('fetched_at', '<=', $date)
                                ->orderByDesc('fetched_at')
                                ->first();

                            if (! $usdToEur) {
                                $usdToEur = ExchangeRate::where('base_currency', 'USD')
                                    ->where('target_currency', 'EUR')
                                    ->where('fetched_at', '>=', $date)
                                    ->orderBy('fetched_at')
                                    ->first();
                            }

                            if ($usdToTarget && $usdToEur) {
                                $rateValue = (float) $usdToEur->rate / (float) $usdToTarget->rate; // MONEDA→EUR
                                $exchangeDisplay = number_format(1 / $rateValue, 4, ',', '.'); // EUR→MONEDA
                            } else {
                                $exchangeDisplay = 'N/A';
                            }
                        }

                        // ID Fiscal y País (fallback al payload)
                        // ID Fiscal (misma lógica que facturas)
                        $taxId = $creditNote->customer_tax_id;
                        if (! $taxId) {
                            // credit_note.customer_tax_ids
                            $taxIds = data_get($payload, 'customer_tax_ids', []);
                            if (is_array($taxIds) && ! empty($taxIds)) {
                                $first = collect($taxIds)->first(fn ($item) => data_get($item, 'value'));
                                $value = data_get($first, 'value');
                                $type = data_get($first, 'type');
                                if ($value) {
                                    $taxId = $type ? "{$value} ({$type})" : $value;
                                }
                            }
                        }
                        if (! $taxId) {
                            $taxIds = data_get($payload, 'customer_details.tax_ids', []);
                            if (is_array($taxIds) && ! empty($taxIds)) {
                                $first = collect($taxIds)->first(fn ($item) => data_get($item, 'value'));
                                $value = data_get($first, 'value');
                                $type = data_get($first, 'type');
                                if ($value) {
                                    $taxId = $type ? "{$value} ({$type})" : $value;
                                }
                            }
                        }

                        // País (misma lógica que facturas)
                        $country = $creditNote->customer_address_country;
                        if ($country) {
                            $country = strtoupper($country);
                        } else {
                            $country = strtoupper(
                                data_get($payload, 'customer_details.address.country')
                                ?? data_get($payload, 'customer.address.country')
                                ?? ''
                            );
                        }

                        // Conversiones a EUR (con descuento)
                        $subtotalEur = null;
                        $taxEur = null;
                        $totalEur = null;

                        if ($currency === 'EUR') {
                            $subtotalEur = $subtotal;
                            $taxEur = $tax;
                            $totalEur = $total;
                            $exchangeDisplay = '';
                        } elseif ($rateValue) {
                            $subtotalEur = $subtotal * $rateValue;
                            $taxEur = $tax * $rateValue;
                            $totalEur = $total * $rateValue;
                        }

                        $subtotalEurFmt = $subtotalEur !== null ? number_format($subtotalEur, 2, ',', '.') : '';
                        $taxEurFmt = $taxEur !== null ? number_format($taxEur, 2, ',', '.') : '';
                        $totalEurFmt = $totalEur !== null ? number_format($totalEur, 2, ',', '.') : '';

                        // Status translation
                        $statusLabel = match ($creditNote->status) {
                            'issued' => 'Emitida',
                            'void' => 'Anulada',
                            default => ucfirst($creditNote->status ?? '—'),
                        };

                        // Type translation
                        $typeLabel = match ($creditNote->type) {
                            'pre_payment' => 'Pre-pago',
                            'post_payment' => 'Post-pago',
                            default => ucfirst($creditNote->type ?? '—'),
                        };

                        // Reason translation
                        $reasonLabel = match ($creditNote->reason) {
                            'duplicate' => 'Duplicado',
                            'fraudulent' => 'Fraudulento',
                            'order_change' => 'Cambio de orden',
                            'product_unsatisfactory' => 'Producto insatisfactorio',
                            default => ucfirst($creditNote->reason ?? '—'),
                        };

                        // Link
                        $link = $creditNote->pdf ?? $creditNote->hosted_credit_note_url ?? '';

                        fputcsv($handle, [
                            $creditNote->number ?? $creditNote->stripe_id,
                            $creditNote->credit_note_created_at?->format('d/m/Y') ?? '',
                            $creditNote->customer_name ?? '',
                            $taxId ?? '',
                            number_format($total, 2, ',', '.'), // importe con descuento (total)
                            $currency,
                            $exchangeDisplay,
                            $subtotalEurFmt,
                            $taxEurFmt,
                            $totalEurFmt,
                            $country,
                            $statusLabel,
                            $typeLabel,
                            $reasonLabel,
                            $creditNote->memo ?? '',
                            $link,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}

