@php
    use Illuminate\Support\Str;

    $statusLabels = [
        'active' => ['label' => 'Activa', 'color' => 'text-green-600', 'dot' => 'bg-green-500'],
        'past_due' => ['label' => 'Vencida', 'color' => 'text-yellow-600', 'dot' => 'bg-yellow-500'],
        'canceled' => ['label' => 'Cancelada', 'color' => 'text-red-600', 'dot' => 'bg-red-500'],
        'trialing' => ['label' => 'En prueba', 'color' => 'text-blue-600', 'dot' => 'bg-blue-500'],
        'incomplete' => ['label' => 'Incompleta', 'color' => 'text-orange-600', 'dot' => 'bg-orange-500'],
        'unpaid' => ['label' => 'Impaga', 'color' => 'text-red-600', 'dot' => 'bg-red-500'],
        'incomplete_expired' => ['label' => 'Expirada', 'color' => 'text-gray-600', 'dot' => 'bg-gray-500'],
    ];

    $statusInfo = $statusLabels[$record->status] ?? ['label' => Str::ucfirst($record->status ?? '—'), 'color' => 'text-gray-600', 'dot' => 'bg-gray-400'];

    $taxIdValue = $record->customer_tax_id
        ?? data_get($stripeTaxIds, '0.value');
    $taxIdType = $record->customer_tax_id_type
        ?? Str::upper((string) data_get($stripeTaxIds, '0.type'));
    $taxIdDisplay = $taxIdValue ? trim($taxIdValue.' '.($taxIdType ? "($taxIdType)" : '')) : '—';
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-orange-500 to-red-500 text-2xl font-semibold text-white grid place-items-center">
                        {{ Str::of($record->customer_name)->substr(0, 2)->upper() ?? 'CL' }}
                    </div>
                    <div class="flex-1">
                        <p class="text-xl font-semibold text-gray-900 dark:text-gray-50">{{ $record->customer_name ?? 'Cliente' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Creado {{ $record->created_at?->translatedFormat('d M Y') ?? '—' }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-5 text-sm">
                    <div class="flex items-center gap-2 rounded-2xl bg-gray-50 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <span class="h-2 w-2 rounded-full {{ $statusInfo['dot'] }}"></span>
                        <span class="font-medium {{ $statusInfo['color'] }}">{{ $statusInfo['label'] }}</span>
                    </div>
                    <div class="grid gap-4 text-gray-600 dark:text-gray-300">
                        <div class="grid gap-1">
                            <label class="text-xs font-semibold uppercase text-gray-500">Email</label>
                            <input type="text" class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" value="{{ $record->customer_email ?? '—' }}" disabled>
                        </div>
                        <div class="grid gap-1">
                            <label class="text-xs font-semibold uppercase text-gray-500">Teléfono</label>
                            <input type="text" class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" value="{{ data_get($stripeCustomer, 'phone') ?? '—' }}" disabled>
                        </div>
                        <div class="grid gap-1">
                            <label class="text-xs font-semibold uppercase text-gray-500">País</label>
                            <input type="text" class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" value="{{ strtoupper($record->customer_country ?? '—') }}" disabled>
                        </div>
                        <div class="grid gap-1">
                            <label class="text-xs font-semibold uppercase text-gray-500">ID Fiscal</label>
                            <input type="text" class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" value="{{ $taxIdDisplay }}" disabled>
                        </div>
                        <div class="grid gap-1">
                            <label class="text-xs font-semibold uppercase text-gray-500">Cuenta Stripe</label>
                            <input type="text" class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" value="{{ $record->customer_id ?? '—' }}" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Servicios</h3>
                        <span class="text-sm text-gray-500">{{ count($subscriptionItems) }} activos</span>
                    </div>

                    @if(empty($subscriptionItems))
                        <p class="text-sm text-gray-500">No hay servicios registrados.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($subscriptionItems as $item)
                                <div class="rounded-2xl border border-gray-100 px-4 py-3 text-sm shadow-sm dark:border-gray-800">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $item['name'] ?? 'Servicio' }}
                                            </p>
                                            <p class="text-gray-500">
                                                {{ $item['quantity'] ?? 1 }} ×
                                                {{ $item['unit_amount'] ? number_format($item['unit_amount'], 2, ',', '.') : '—' }}
                                                {{ $item['currency'] }}
                                            </p>
                                        </div>
                                        <div class="text-sm text-gray-500">
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

                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase text-gray-500">Importe</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
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
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ $record->amount_eur ? number_format($record->amount_eur, 2, ',', '.') . ' EUR' : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-gray-500">Próxima renovación</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                @if($record->current_period_end)
                                    {{ $record->current_period_end->format('d/m/Y') }}
                                    <span class="ml-1 text-sm text-gray-500">({{ $record->current_period_end->diffForHumans() }})</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Métodos de Pago</h3>
                    </div>
                    @if($stripePaymentMethod)
                        <div class="flex flex-col gap-2 rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                            <div class="text-xl font-semibold">
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
                        <p class="text-sm text-gray-500">
                            No hay métodos de pago registrados.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Facturas</h3>
                    <p class="text-sm text-gray-500">Últimas 10 facturas emitidas</p>
                </div>
            </div>

            @if(empty($stripeInvoices))
                <p class="text-sm text-gray-500"> No hay facturas registradas.</p>
            @else
                <div class="overflow-hidden rounded-2xl border border-gray-100 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Número</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Monto</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($stripeInvoices as $invoice)
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $invoice['number'] ?? $invoice['id'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $invoice['created_at']->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ number_format($invoice['amount'], 2, ',', '.') }} {{ $invoice['currency'] }}</td>
                                    <td class="px-4 py-3 capitalize text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $invoice['status']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-3 text-primary-600">
                                            @if($invoice['invoice_pdf'])
                                                <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" class="inline-flex items-center gap-1 rounded-full border border-primary-100 px-3 py-1 text-xs font-semibold hover:bg-primary-50 dark:border-primary-500/40">
                                                    Descargar
                                                </a>
                                            @endif
                                            @if($invoice['hosted_invoice_url'])
                                                <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                                                    Ver
                                                </a>
                                            @endif
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
</x-filament-panels::page>

