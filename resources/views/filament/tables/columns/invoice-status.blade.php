@php
    $statusLabel = $record->status_label;
    $statusColor = $record->status_color;

    // Información básica desde los campos del modelo
    $customerName = $record->customer_name ?? 'cliente';
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

    // Mensaje para WhatsApp (más informal)
    $whatsappMessage = "Hola {$customerName} 👋\n\n";
    $whatsappMessage .= "Te escribimos de {$companyName} para recordarte que tienes una factura pendiente de pago por *{$amountFormatted}*.\n\n";
    if ($invoiceUrl) {
        $whatsappMessage .= "Puedes revisar y pagar tu factura aquí:\n{$invoiceUrl}\n\n";
    }
    $whatsappMessage .= "Si ya realizaste el pago, por favor ignora este mensaje.\n\n";
    $whatsappMessage .= "Cualquier consulta, estamos a tu disposición. ¡Gracias!";

    // Mensaje para Email (más formal)
    $emailSubject = "Recordatorio de pago - Factura pendiente";
    $emailMessage = "Estimado/a {$customerName},\n\n";
    $emailMessage .= "Le escribimos de {$companyName} para recordarle que tiene una factura pendiente de pago.\n\n";
    $emailMessage .= "Detalles de la factura:\n";
    $emailMessage .= "- Importe: {$amountFormatted}\n";
    if ($record->number) {
        $emailMessage .= "- Número: {$record->number}\n";
    }
    if ($record->invoice_due_date) {
        $emailMessage .= "- Vencimiento: {$record->invoice_due_date->format('d/m/Y')}\n";
    }
    $emailMessage .= "\n";
    if ($invoiceUrl) {
        $emailMessage .= "Puede revisar y gestionar el pago de su factura en el siguiente enlace:\n{$invoiceUrl}\n\n";
    }
    $emailMessage .= "Si ya ha realizado el pago, por favor ignore este mensaje.\n\n";
    $emailMessage .= "Quedamos a su disposición para cualquier consulta.\n\n";
    $emailMessage .= "Saludos cordiales,\n";
    $emailMessage .= "Equipo de {$companyName}";

    // Enlaces
    $whatsappLink = $phone ? 'https://wa.me/' . $phone . '?text=' . urlencode($whatsappMessage) : null;
    $emailLink = (filled($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL))
        ? 'mailto:' . urlencode($customerEmail) . '?subject=' . urlencode($emailSubject) . '&body=' . urlencode($emailMessage)
        : null;
@endphp

<div class="flex items-center justify-center gap-2">
    <x-filament::badge color="{{ $statusColor }}">
        {{ $statusLabel }}
    </x-filament::badge>

    <div class="flex items-center gap-1.5">
        @if ($whatsappLink)
            <a
                href="{{ $whatsappLink }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center justify-center text-success-600 hover:text-success-700 transition"
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
                class="inline-flex items-center justify-center text-primary-600 hover:text-primary-700 transition"
                title="Enviar recordatorio por Email"
            >
                <x-filament::icon
                    icon="heroicon-o-envelope"
                    class="h-5 w-5"
                />
            </a>
        @endif
    </div>
</div>
