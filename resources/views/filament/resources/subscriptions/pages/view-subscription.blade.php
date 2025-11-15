@php
    use Illuminate\Support\Str;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <x-filament::section>
                    <x-slot name="heading">
                        Contacto / {{ $record->customer_name ?? 'Cliente' }}
                    </x-slot>

                    <div class="space-y-3 text-sm">
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">Estado</span>
                            <span class="text-base font-semibold">
                                @php
                                    $statusLabels = [
                                        'active' => '🟢 Activa',
                                        'past_due' => '🟡 Vencida',
                                        'canceled' => '🔴 Cancelada',
                                        'trialing' => '🔵 En prueba',
                                        'incomplete' => '🟠 Incompleta',
                                        'unpaid' => '🔴 Impaga',
                                        'incomplete_expired' => '⚫ Expirada',
                                    ];
                                @endphp
                                {{ $statusLabels[$record->status] ?? Str::ucfirst($record->status ?? '—') }}
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span>{{ $record->customer_email ?? '—' }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">Teléfono</span>
                            <span>{{ data_get($stripeCustomer, 'phone', '—') }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">País</span>
                            <span>{{ strtoupper($record->customer_country ?? '—') }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">ID Fiscal</span>
                            <span>
                                @if($record->customer_tax_id)
                                    {{ $record->customer_tax_id }}
                                    @if($record->customer_tax_id_type)
                                        ({{ strtoupper($record->customer_tax_id_type) }})
                                    @endif
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400">Cuenta Stripe</span>
                            <span>
                                @if($record->customer_id)
                                    <a href="https://dashboard.stripe.com/customers/{{ $record->customer_id }}" target="_blank" class="text-primary-600 hover:underline">
                                        {{ $record->customer_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <x-filament::section>
                    <x-slot name="heading">
                        Servicios
                    </x-slot>

                    @if(empty($subscriptionItems))
                        <div class="text-sm text-gray-500">No hay servicios registrados.</div>
                    @else
                        <div class="space-y-4">
                            @foreach($subscriptionItems as $item)
                                <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p class="text-base font-semibold">{{ $item['name'] ?? 'Servicio' }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $item['quantity'] ?? 1 }} ×
                                                {{ $item['unit_amount'] ? number_format($item['unit_amount'], 2, ',', '.') : '—' }}
                                                {{ $item['currency'] }}
                                            </p>
                                        </div>
                                        <div class="text-right text-sm text-gray-600">
                                            @if($item['interval'])
                                                Cada {{ $item['interval_count'] ?? 1 }} {{ $item['interval'] }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        Métdos de Pago
                    </x-slot>

                    @if($stripePaymentMethod)
                        <div class="flex flex-col gap-2 rounded-xl border border-gray-100 p-4 dark:border-gray-700">
                            <div class="text-lg font-semibold">
                                {{ strtoupper(data_get($stripePaymentMethod, 'card.brand', 'Método')) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                **** **** **** {{ data_get($stripePaymentMethod, 'card.last4') }}
                                · Expira {{ data_get($stripePaymentMethod, 'card.exp_month') }}/{{ data_get($stripePaymentMethod, 'card.exp_year') }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Titular: {{ data_get($stripePaymentMethod, 'billing_details.name', $record->customer_name ?? '—') }}
                            </div>
                        </div>
                    @else
                        <div class="text-sm text-gray-500">
                            No hay métodos de pago registrados.
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        Facturación
                    </x-slot>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase text-gray-500">Importe</p>
                            <p class="text-lg font-semibold">
                                @php
                                    $amount = $record->amount_total
                                        ?? $record->amount_subtotal
                                        ?? ($record->unit_amount ? $record->unit_amount * ($record->quantity ?? 1) : null);
                                @endphp
                                @if($amount)
                                    {{ number_format($amount, 2, ',', '.') }} {{ strtoupper($record->price_currency ?? 'USD') }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">Equivalente en EUR</p>
                            <p class="text-lg font-semibold">
                                {{ $record->amount_eur ? number_format($record->amount_eur, 2, ',', '.') . ' EUR' : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">Próxima renovación</p>
                            <p class="text-lg font-semibold">
                                @if($record->current_period_end)
                                    {{ $record->current_period_end->format('d/m/Y') }}
                                    <span class="text-sm text-gray-500">({{ $record->current_period_end->diffForHumans() }})</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                </x-filament::section>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">
                Facturas (últimas 10)
            </x-slot>

            @if(empty($stripeInvoices))
                <div class="text-sm text-gray-500">
                    No hay facturas registradas para este cliente.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-2">Número</th>
                                <th class="px-4 py-2">Fecha</th>
                                <th class="px-4 py-2">Monto</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($stripeInvoices as $invoice)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $invoice['number'] ?? $invoice['id'] }}</td>
                                    <td class="px-4 py-3">{{ $invoice['created_at']->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">{{ number_format($invoice['amount'], 2, ',', '.') }} {{ $invoice['currency'] }}</td>
                                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $invoice['status']) }}</td>
                                    <td class="px-4 py-3 flex items-center gap-3 text-primary-600">
                                        @if($invoice['invoice_pdf'])
                                            <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" class="hover:underline">PDF</a>
                                        @endif
                                        @if($invoice['hosted_invoice_url'])
                                            <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" class="hover:underline">Ver</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

