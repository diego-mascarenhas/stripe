@php
    $statusLabel = $record->status_label;
    $statusColor = $record->status_color;

    // Información básica desde los campos del modelo
    // Intentar obtener el nombre individual desde raw_payload, sino usar customer_name
    $individualName = null;
    if (!empty($record->raw_payload)) {
        $payload = $record->raw_payload;
        $individualName = data_get($payload, 'customer.individual_name')
            ?? data_get($payload, 'customer_details.name')
            ?? data_get($payload, 'customer.name')
            ?? null;
    }
    $customerName = $individualName ?? $record->customer_name ?? 'cliente';
    $companyName = $record->customer_description ?? $record->customer_name ?? 'tu empresa';
    $customerEmail = $record->customer_email;
    $invoiceUrl = $record->hosted_invoice_url ?? $record->invoice_pdf;

    // Formatear monto de la factura
    $currency = strtoupper($record->currency ?? 'EUR');
    $amount = $record->amount_remaining ?? $record->total ?? 0;
    $amountFormatted = number_format($amount, 2, ',', '.') . ' ' . $currency;

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

    // Mensaje para WhatsApp
    $whatsappMessage = "Buenos dias {$customerName},\n\n";
    $whatsappMessage .= "Te escribimos para recordarte que tienes una factura pendiente de {$companyName} por {$amountFormatted}.\n\n";
    if ($invoiceUrl) {
        $whatsappMessage .= "Puedes revisar y pagar tu factura aqui:\n{$invoiceUrl}\n\n";
    }
    $whatsappMessage .= "Si ya realizaste el pago, ignora este mensaje.\n\n";
    $whatsappMessage .= "Un cordial saludo";

    // Mensaje para Email
    $emailSubject = "Recordatorio de pago - Factura {$record->number}";
    $emailMessage = "Buenos dias {$customerName},%0D%0A%0D%0A";
    $emailMessage .= "Te escribimos para recordarte que tienes una factura pendiente de {$companyName}.%0D%0A%0D%0A";
    $emailMessage .= "Importe: {$amountFormatted}%0D%0A";
    if ($record->number) {
        $emailMessage .= "Factura: {$record->number}%0D%0A";
    }
    if ($record->invoice_due_date) {
        $emailMessage .= "Vencimiento: {$record->invoice_due_date->format('d/m/Y')}%0D%0A";
    }
    $emailMessage .= "%0D%0A";
    if ($invoiceUrl) {
        $emailMessage .= "Puedes revisar y pagar tu factura en:%0D%0A{$invoiceUrl}%0D%0A%0D%0A";
    }
    $emailMessage .= "Si ya realizaste el pago, ignora este mensaje.%0D%0A%0D%0A";
    $emailMessage .= "Un cordial saludo";

    // Enlaces
    $whatsappLink = $phone ? 'https://wa.me/' . $phone . '?text=' . urlencode($whatsappMessage) : null;
    $emailLink = (filled($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL))
        ? 'mailto:' . $customerEmail . '?subject=' . rawurlencode($emailSubject) . '&body=' . $emailMessage
        : null;
@endphp

<div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
    <x-filament::badge color="{{ $statusColor }}">
        {{ $statusLabel }}
    </x-filament::badge>

    @if ($whatsappLink || $emailLink)
        <div style="display: flex; flex-direction: row; align-items: center; gap: 8px;">
            @if ($whatsappLink)
                <a
                    href="{{ $whatsappLink }}"
                    target="_blank"
                    rel="noopener"
                    style="display: inline-flex; color: #16a34a;"
                    title="Enviar recordatorio por WhatsApp"
                >
                    <x-filament::icon
                        icon="heroicon-o-chat-bubble-left-right"
                        class="h-5 w-5"
                    />
                </a>
            @endif

            @if ($emailLink)
                <a
                    href="{{ $emailLink }}"
                    style="display: inline-flex; color: #2563eb;"
                    title="Enviar recordatorio por Email"
                >
                    <x-filament::icon
                        icon="heroicon-o-envelope"
                        class="h-5 w-5"
                    />
                </a>
            @endif
        </div>
    @endif
</div>
