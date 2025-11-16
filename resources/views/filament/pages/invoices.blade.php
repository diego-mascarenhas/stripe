<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm ring-1 ring-black/5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Historial de facturas</h2>
                    <p class="text-sm text-gray-500">Últimas 100 facturas registradas en Stripe</p>
                </div>
            </div>

            @if(empty($this->invoices))
                <p class="text-sm text-gray-500">No se encontraron facturas recientes.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-3 text-left">id</th>
                                <th class="px-3 py-3 text-left">Amount Due</th>
                                <th class="px-3 py-3 text-left">Billing</th>
                                <th class="px-3 py-3 text-left">Closed</th>
                                <th class="px-3 py-3 text-left">Currency</th>
                                <th class="px-3 py-3 text-left">Customer</th>
                                <th class="px-3 py-3 text-left">Date (UTC)</th>
                                <th class="px-3 py-3 text-left">Due Date (UTC)</th>
                                <th class="px-3 py-3 text-left">Number</th>
                                <th class="px-3 py-3 text-left">Paid</th>
                                <th class="px-3 py-3 text-left">Subscription</th>
                                <th class="px-3 py-3 text-left">Subtotal</th>
                                <th class="px-3 py-3 text-left">Total Discount Amount</th>
                                <th class="px-3 py-3 text-left">Applied Coupons</th>
                                <th class="px-3 py-3 text-left">Tax</th>
                                <th class="px-3 py-3 text-left">Tax Percent</th>
                                <th class="px-3 py-3 text-left">Total</th>
                                <th class="px-3 py-3 text-left">Amount Paid</th>
                                <th class="px-3 py-3 text-left">Status</th>
                                <th class="px-3 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->invoices as $invoice)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $invoice['id'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['amount_due'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['billing'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['closed'] }}</td>
                                    <td class="px-3 py-3 uppercase">{{ $invoice['currency'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['customer'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['date'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['due_date'] ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ $invoice['number'] ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $invoice['paid'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['subscription'] ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $invoice['subtotal'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['total_discount'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['coupons'] ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ $invoice['tax'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['tax_percent'] ?? '—' }}</td>
                                    <td class="px-3 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $invoice['total'] }}</td>
                                    <td class="px-3 py-3">{{ $invoice['amount_paid'] }}</td>
                                    <td class="px-3 py-3 capitalize">{{ $invoice['status'] }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <div class="inline-flex items-center gap-3 text-primary-600">
                                            @if($invoice['invoice_pdf'])
                                                <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" class="hover:underline">PDF</a>
                                            @endif
                                            @if($invoice['hosted_invoice_url'])
                                                <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" class="hover:underline">Ver</a>
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

