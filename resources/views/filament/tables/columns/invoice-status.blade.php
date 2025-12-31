@php
    $statusLabel = $record->status_label;
    $statusColor = $record->status_color;

    // Información básica desde los campos del modelo
    $customerName = $record->customer_name ?? 'cliente';
    $companyName = $record->customer_description ?? $record->customer_name ?? 'tu empresa';
    $customerEmail = $record->customer_email;
    $invoiceUrl = $record->hosted_invoice_url ?? $record->invoice_pdf;

    // Teléfono: solo desde raw_payload si existe
    $phone = null;
    if (!empty($record->raw_payload)) {
        $payload = $record->raw_payload;
        $phoneRaw = data_get($payload, 'customer.phone')
            ?? data_get($payload, 'customer_details.phone')
            ?? data_get($payload, 'customer.metadata.phone')
            ?? data_get($payload, 'customer.metadata.whatsapp')
            ?? null;

        $phone = $phoneRaw ? preg_replace('/\D+/', '', $phoneRaw) : null;
    }

    // Mensajes
    $baseMessage = "Hola {$customerName}, te contactamos para recordarte que tienes una factura pendiente de {$companyName}.";
    $message = $invoiceUrl
        ? "{$baseMessage}\n\nPuedes revisarla y gestionarla en el siguiente enlace:\n{$invoiceUrl}\n\nCualquier duda, escríbenos por aquí."
        : "{$baseMessage}\n\nCualquier duda, escríbenos por aquí.";

    // Enlaces
    $whatsappLink = $phone ? 'https://wa.me/' . $phone . '?text=' . urlencode($message) : null;

    $emailSubject = "Factura pendiente - {$companyName}";
    $emailLink = (filled($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL))
        ? 'mailto:' . urlencode($customerEmail) . '?subject=' . urlencode($emailSubject) . '&body=' . urlencode($message)
        : null;
@endphp

{{-- DEBUG: Email={{ $customerEmail ?? 'NULL' }} | Link={{ $emailLink ? 'YES' : 'NO' }} --}}

<div class="flex flex-col items-center gap-2 text-center">
    <div class="inline-flex items-center gap-2">
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

                @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
            </a>
        @else
            <x-filament::badge color="{{ $statusColor }}">
                {{ $statusLabel }}
            </x-filament::badge>
        @endif

        @if ($emailLink)
            <a
                href="{{ $emailLink }}"
                class="inline-flex items-center text-primary-600 hover:text-primary-700 fi-link"
                aria-label="Enviar recordatorio por Email"
            >
                @svg('heroicon-o-envelope', 'w-4 h-4')
            </a>
        @endif
    </div>
</div>
