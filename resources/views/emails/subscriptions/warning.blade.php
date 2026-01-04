@extends('emails.subscriptions.layout')

@section('content')
<tr>
	<td>
		<!-- Greeting -->
		<div style="margin-bottom: 25px">
			<p style="font-size: 16px; color: #333; margin: 0"><strong>Hola {{ $subscription->customer_name }}</strong> 👋</p>
			<p style="font-size: 14px; color: #666; margin: 5px 0 0 0">Tu servicio está próximo a vencer</p>
		</div>

		<!-- Warning Card -->
		<div
			style="
				background: #fff3cd;
				border-radius: 16px;
				padding: 25px;
				margin: 25px 0;
				border-left: 4px solid #ffc107;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
			"
		>
			<div style="text-align: center; margin-bottom: 20px">
				<span style="font-size: 48px">⚠️</span>
				<h3 style="color: #856404; margin: 10px 0 5px 0; font-size: 24px">
					@if($daysRemaining == 5)
					¡Quedan 5 días!
					@else
					¡Solo quedan 2 días!
					@endif
				</h3>
				<p style="color: #856404; font-size: 16px; margin: 0">Tu suscripción vence pronto</p>
			</div>

			<!-- Service Details -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px">
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						📦 Servicio
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333">
						{{ $serviceName }}
					</p>
				</div>
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						💰 Monto
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #ff1a1d">
						{{ $amount }}
					</p>
				</div>
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						⏰ Fecha de vencimiento
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333">
						{{ $dueDate }}
					</p>
				</div>
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						⏳ Tiempo restante
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #856404">
						{{ $daysRemaining }} {{ $daysRemaining == 1 ? 'día' : 'días' }}
					</p>
				</div>
			</div>

			<!-- Important Note -->
			<div style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #ff1a1d">
				<p style="margin: 0; font-size: 14px; color: #333; line-height: 1.6">
					<strong>¿Qué significa esto?</strong><br>
					Si tu suscripción no se renueva antes de la fecha indicada, tu servicio será suspendido automáticamente.
					Por favor, asegurate de que tu método de pago esté actualizado.
				</p>
			</div>
		</div>

		<!-- CTA Section -->
		<div style="text-align: center; margin: 35px 0">
			<p style="font-size: 16px; color: #333; margin-bottom: 20px; text-align: center;">Pagá tu factura ahora</p>
			<a
				href="{{ $paymentUrl }}"
				style="
					background: linear-gradient(135deg, #ff1a1d 0%, #e6171a 100%);
					color: white;
					padding: 16px 32px;
					text-decoration: none;
					border-radius: 50px;
					font-weight: 700;
					font-size: 16px;
					display: inline-block;
					box-shadow: 0 4px 15px rgba(255, 26, 29, 0.3);
					text-transform: uppercase;
					letter-spacing: 0.5px;
				"
			>
				💳 Pagar Factura
			</a>
			<p style="font-size: 12px; color: #888; margin-top: 10px; text-align: center;">
				Pago seguro procesado por Stripe
			</p>
		</div>

		<!-- Support Card -->
		<div
			style="
				background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
				border: 2px solid #36f1cd;
				border-radius: 12px;
				padding: 28px;
				margin: 18px 0;
				text-align: center;
				box-shadow: 0 6px 14px rgba(0,0,0,0.06);
			"
		>
			<h4 style="color: #2a333d; margin: 0 0 15px 0; font-size: 18px; font-weight: 600">💬 ¿Necesitás ayuda?</h4>
			<p style="color:#2a333d; font-size:16px; line-height:1.7; margin:0 0 18px;">
				Nuestro equipo está disponible <strong>24x7</strong> para ayudarte con cualquier consulta.
			</p>
			<div style="margin-top: 10px;">
				<a
					href="https://revisionalpha.com/login?redirect=/cms/tickets/create"
					style="
						display:inline-block; margin: 0 6px 10px;
						padding: 12px 22px; border-radius: 47px; font-weight: 700; font-size: 14px;
						background: #ff1a1d; color: #ffffff; text-decoration: none; letter-spacing: .2px;
					"
				>
					📩 Crear ticket
				</a>
			</div>
		</div>
	</td>
</tr>
@endsection
