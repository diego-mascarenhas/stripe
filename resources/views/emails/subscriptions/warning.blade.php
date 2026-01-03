@component('mail::message')
# {{ $daysRemaining === 5 ? '⚠️ Aviso Importante' : '🚨 Última Oportunidad' }}

Hola **{{ $subscription->customer_name }}**,

Te escribimos para informarte que tu suscripción al servicio **{{ $serviceName }}** está próxima a vencer.

@component('mail::panel')
**Días restantes:** {{ $daysRemaining }} días  
**Fecha de vencimiento:** {{ $dueDate }}  
**Monto:** {{ $amount }}
@endcomponent

@if($daysRemaining === 5)
Si no realizas el pago antes de la fecha indicada, tu servicio será **suspendido automáticamente**.
@else
**Esta es tu última oportunidad.** Si no pagas en las próximas 48 horas, procederemos a **suspender tu servicio**.
@endif

@component('mail::button', ['url' => config('app.url')])
Realizar Pago Ahora
@endcomponent

Si ya realizaste el pago, por favor ignora este mensaje.

Saludos,<br>
{{ config('app.name') }}

---
<small>Este es un correo automático. Si necesitas ayuda, contáctanos.</small>
@endcomponent
