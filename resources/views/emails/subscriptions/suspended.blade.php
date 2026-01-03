@extends('emails.subscriptions.layout')

@section('content')
<tr>
	<td>
		<!-- Greeting -->
		<div style="margin-bottom: 25px">
			<p style="font-size: 16px; color: #333; margin: 0"><strong>Hola {{ $subscription->customer_name }}</strong></p>
			<p style="font-size: 14px; color: #666; margin: 5px 0 0 0">Información importante sobre tu servicio</p>
		</div>

		<!-- Suspended Card -->
		<div
			style="
				background: #f8d7da;
				border-radius: 16px;
				padding: 25px;
				margin: 25px 0;
				border-left: 4px solid #dc3545;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
			"
		>
			<div style="text-align: center; margin-bottom: 20px">
				<span style="font-size: 48px">❌</span>
				<h3 style="color: #721c24; margin: 10px 0 5px 0; font-size: 24px">
					Servicio Suspendido
				</h3>
				<p style="color: #721c24; font-size: 16px; margin: 0">Tu suscripción ha sido suspendida temporalmente</p>
			</div>

			<!-- Service Details -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px">
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						📦 Servicio
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333">
						{{ $subscription->plan_name }}
					</p>
				</div>
				<div style="background: white; padding: 15px; border-radius: 8px">
					<p style="margin: 0; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px">
						💰 Monto pendiente
					</p>
					<p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #dc3545">
						{{ number_format($subscription->amount_total, 2) }} {{ strtoupper($subscription->price_currency) }}
					</p>
				</div>
			</div>

			<!-- Reason -->
			<div style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #ff1a1d; margin-bottom: 15px">
				<p style="margin: 0; font-size: 14px; color: #333; line-height: 1.6">
					<strong>¿Por qué fue suspendido?</strong><br>
					Tu servicio fue suspendido debido a un pago pendiente. Para reactivarlo, por favor actualizá tu método de pago 
					y regularizá tu situación.
				</p>
			</div>

			<!-- What happens now -->
			<div style="background: white; padding: 15px; border-radius: 8px; border-left: 3px solid #ffc107">
				<p style="margin: 0; font-size: 14px; color: #333; line-height: 1.6">
					<strong>¿Qué significa esto?</strong><br>
					• Tu sitio web o servicio no estará accesible<br>
					• Tus datos están seguros y se mantendrán por tiempo limitado<br>
					• Podés reactivar el servicio en cualquier momento
				</p>
			</div>
		</div>

		<!-- CTA Section -->
		<div style="text-align: center; margin: 35px 0">
			<p style="font-size: 16px; color: #333; margin-bottom: 20px">Reactivá tu servicio ahora</p>
			<a
				href="https://revisionalpha.com/login"
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
				🔐 Actualizar método de pago
			</a>
			<p style="font-size: 12px; color: #888; margin-top: 10px">
				Acceso seguro a tu área de clientes
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
				Si tenés alguna duda o necesitás asistencia para reactivar tu servicio, 
				nuestro equipo está disponible <strong>24x7</strong>.
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
					📩 Contactar soporte
				</a>
			</div>
		</div>
	</td>
</tr>
@endsection
