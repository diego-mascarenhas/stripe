@php
    use Illuminate\Support\Str;

    $statusLabels = [
        'active' => ['label' => 'Activa', 'color' => 'success'],
        'past_due' => ['label' => 'Vencida', 'color' => 'warning'],
        'canceled' => ['label' => 'Cancelada', 'color' => 'danger'],
        'trialing' => ['label' => 'En prueba', 'color' => 'info'],
        'incomplete' => ['label' => 'Incompleta', 'color' => 'warning'],
        'unpaid' => ['label' => 'Impaga', 'color' => 'danger'],
        'incomplete_expired' => ['label' => 'Expirada', 'color' => 'gray'],
    ];

    $statusInfo = $statusLabels[$record->status] ?? ['label' => Str::ucfirst($record->status ?? '—'), 'color' => 'gray'];
    $taxIdValue = $record->customer_tax_id ?? data_get($stripeTaxIds, '0.value');
    $taxIdType = $record->customer_tax_id_type ?? Str::upper((string) data_get($stripeTaxIds, '0.type'));
    $taxIdDisplay = $taxIdValue ? trim($taxIdValue.' '.($taxIdType ? "($taxIdType)" : '')) : '—';
    $amount = $record->amount_total ?? $record->amount_subtotal ?? ($record->unit_amount ? $record->unit_amount * ($record->quantity ?? 1) : null);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Información del Cliente --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        {{ $record->customer_name ?? 'Cliente' }}
                    </h3>
                    <p class="fi-section-description text-sm text-gray-500 dark:text-gray-400">
                        Creado {{ $record->created_at?->translatedFormat('d M Y') ?? '—' }}
                    </p>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                            <dd class="mt-1">
                                <x-filament::badge :color="$statusInfo['color']">
                                    {{ $statusInfo['label'] }}
                                </x-filament::badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->customer_email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Teléfono</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ data_get($stripeCustomer, 'phone') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">País</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ strtoupper($record->customer_country ?? '—') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID Fiscal</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $taxIdDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID Stripe cus_</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ $record->customer_id ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Servicios --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Servicios
                    </h3>
                    <p class="fi-section-description text-sm text-gray-500 dark:text-gray-400">
                        {{ count($subscriptionItems) }} activos
                    </p>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    @if(empty($subscriptionItems))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay servicios registrados.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($subscriptionItems as $item)
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ $item['name'] ?? 'Servicio' }}
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $item['quantity'] ?? 1 }} × {{ $item['unit_amount'] ? number_format($item['unit_amount'], 2, ',', '.') : '—' }} {{ $item['currency'] }}
                                            </p>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
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
                </div>
            </div>
        </div>

        {{-- Importe --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content-ctn">
                <div class="fi-section-content p-6">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Importe</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                @if($amount)
                                    {{ number_format($amount, 2, ',', '.') }} {{ strtoupper($record->price_currency ?? 'USD') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Equivalente en EUR</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $record->amount_eur ? number_format($record->amount_eur, 2, ',', '.') . ' EUR' : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Próxima renovación</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                @if($record->current_period_end)
                                    {{ $record->current_period_end->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Métodos de Pago --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Métodos de Pago
                    </h3>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content p-6">
                    @if($stripePaymentMethod)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ strtoupper(data_get($stripePaymentMethod, 'card.brand', 'Método')) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                **** **** **** {{ data_get($stripePaymentMethod, 'card.last4') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Expira {{ data_get($stripePaymentMethod, 'card.exp_month') }}/{{ data_get($stripePaymentMethod, 'card.exp_year') }}
                            </p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No hay métodos de pago registrados.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Facturas --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Facturas
                    </h3>
                    <p class="fi-section-description text-sm text-gray-500 dark:text-gray-400">
                        Últimas {{ count($stripeInvoices) }} facturas emitidas
                    </p>
                </div>
            </div>
            <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
                <div class="fi-section-content">
                    @if(empty($stripeInvoices))
                        <div class="p-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay facturas registradas.</p>
                        </div>
                    @else
                        <div class="fi-ta overflow-hidden">
                            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                                    <tr class="bg-gray-50 dark:bg-white/5">
                                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Número</span>
                                        </th>
                                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Fecha</span>
                                        </th>
                                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Monto</span>
                                        </th>
                                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Estado</span>
                                        </th>
                                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                                    @foreach($stripeInvoices as $invoice)
                                        @php
                                            $statusBadgeColor = match($invoice['status']) {
                                                'paid' => 'success',
                                                'open' => 'warning',
                                                'void' => 'danger',
                                                'uncollectible' => 'danger',
                                                'draft' => 'gray',
                                                default => 'gray',
                                            };
                                            $statusLabel = match($invoice['status']) {
                                                'paid' => 'Pagada',
                                                'open' => 'Abierta',
                                                'void' => 'Anulada',
                                                'uncollectible' => 'Incobrable',
                                                'draft' => 'Borrador',
                                                default => ucfirst(str_replace('_', ' ', $invoice['status'])),
                                            };
                                        @endphp
                                        <tr>
                                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                                <div class="fi-ta-col-wrp px-3 py-4">
                                                    <span class="text-sm text-gray-950 dark:text-white">{{ $invoice['number'] ?? $invoice['id'] }}</span>
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                                <div class="fi-ta-col-wrp px-3 py-4">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice['created_at']->format('d/m/Y') }}</span>
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                                <div class="fi-ta-col-wrp px-3 py-4">
                                                    <span class="text-sm text-gray-950 dark:text-white">{{ number_format($invoice['amount'], 2, ',', '.') }} {{ $invoice['currency'] }}</span>
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                                <div class="fi-ta-col-wrp px-3 py-4">
                                                    <x-filament::badge :color="$statusBadgeColor">
                                                        {{ $statusLabel }}
                                                    </x-filament::badge>
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                                <div class="fi-ta-col-wrp px-3 py-4">
                                                    <div class="flex items-center gap-3">
                                                        @if($invoice['invoice_pdf'])
                                                            <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" class="fi-link group/link relative inline-flex items-center justify-center outline-none">
                                                                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                                                    Descargar
                                                                </span>
                                                            </a>
                                                        @endif
                                                        @if($invoice['hosted_invoice_url'])
                                                            <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" class="fi-link group/link relative inline-flex items-center justify-center outline-none">
                                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                                    Ver
                                                                </span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
