@php
    $statusLabel = $record->status_label;
    $statusColor = $record->status_color;

    $payload = $record->raw_payload ?? [];
    $customer = is_array(data_get($payload, 'customer')) ? data_get($payload, 'customer') : [];
    $customerDetails = is_array(data_get($payload, 'customer_details')) ? data_get($payload, 'customer_details') : [];

    $personalName = data_get($customer, 'individual_name')
        ?? data_get($customerDetails, 'name')
        ?? data_get($customer, 'name')
        ?? $record->customer_name
        ?? 'cliente';

    $companyName = data_get($customer, 'metadata.company')
        ?? $record->customer_description
        ?? $record->customer_name
        ?? 'tu empresa';

    $invoiceUrl = $record->hosted_invoice_url ?? $record->invoice_pdf;

    $paymentMethod = data_get($payload, 'default_payment_method');

    $phoneRaw = collect([
        data_get($customer, 'phone'),
        data_get($customer, 'phone_number'),
        data_get($customer, 'address.phone'),
        data_get($customer, 'shipping.phone'),
        data_get($customerDetails, 'phone'),
        data_get($customerDetails, 'phone_number'),
        data_get($customerDetails, 'address.phone'),
        data_get($payload, 'customer_phone'),
        data_get($payload, 'customer_details.phone'),
        data_get($payload, 'customer_details.phone_number'),
        data_get($payload, 'customer_details.address.phone'),
        data_get($paymentMethod, 'billing_details.phone'),
        data_get($paymentMethod, 'card.phone'),
        data_get($customer, 'metadata.phone'),
        data_get($customer, 'metadata.whatsapp'),
        data_get($customer, 'metadata.whatsapp_phone'),
        data_get($customer, 'metadata.telefono'),
    ])->first(fn ($value) => ! blank($value));

    $phone = $phoneRaw ? preg_replace('/\D+/', '', $phoneRaw) : null;

    $baseMessage = "Hola {$personalName}, te contactamos para recordarte que tienes una factura pendiente de {$companyName}.";

    $message = $invoiceUrl
        ? "{$baseMessage}\n\nPuedes revisarla y gestionarla en el siguiente enlace:\n{$invoiceUrl}\n\nCualquier duda, escríbenos por aquí."
        : "{$baseMessage}\n\nCualquier duda, escríbenos por aquí.";

    $whatsappLink = $phone
        ? 'https://wa.me/' . $phone . '?text=' . urlencode($message)
        : null;
@endphp

<div class="flex flex-col items-center gap-2 text-center">
    @if ($whatsappLink)
        <a
            href="{{ $whatsappLink }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 fi-link"
            aria-label="Enviar recordatorio por WhatsApp"
        >
            <x-filament::badge color="{{ $statusColor }}">
                {{ $statusLabel }}
            </x-filament::badge>

            <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
        </a>
    @else
        <x-filament::badge color="{{ $statusColor }}">
            {{ $statusLabel }}
        </x-filament::badge>
        <span class="text-xs text-gray-500">Sin enlace</span>
    @endif
</div>

