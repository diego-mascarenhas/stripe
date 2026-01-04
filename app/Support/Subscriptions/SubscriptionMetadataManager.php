<?php

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class SubscriptionMetadataManager
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Select::make('type')
                ->label('Tipo de servicio')
                ->options([
                    'hosting' => 'Hosting',
                    'web_cloud' => 'Web Cloud',
                    'vps' => 'VPS',
                    'domain' => 'Domain',
                    'backups' => 'Backups',
                    'mailer' => 'Mailer',
                    'whatsapp' => 'WhatsApp',
                ])
                ->required(),
            TextInput::make('plan')
                ->label('Plan')
                ->disabled()
                ->dehydrated()
                ->helperText('Este plan se obtiene automáticamente')
                ->placeholder('Se cargará automáticamente'),
            TextInput::make('server')
                ->label('Servidor')
                ->placeholder('server.example.com')
                ->maxLength(255),
            TextInput::make('domain')
                ->label('Dominio')
                ->placeholder('example.com')
                ->maxLength(255),
            TextInput::make('user')
                ->label('Usuario')
                ->placeholder('username')
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->placeholder('user@example.com')
                ->maxLength(255),
            Checkbox::make('auto_suspend')
                ->label('Auto-suspensión si el cliente no paga')
                ->default(false)
                ->helperText('El servicio se suspenderá automáticamente si no se recibe el pago'),
        ];
    }

    /**
     * Fill form with subscription metadata
     */
    public static function fillForm(Subscription $subscription): array
    {
        $data = $subscription->data ?? [];

        return [
            'type' => $data['type'] ?? null,
            'plan' => $data['plan'] ?? null,
            'server' => $data['server'] ?? null,
            'domain' => $data['domain'] ?? null,
            'user' => $data['user'] ?? null,
            'email' => $data['email'] ?? null,
            'auto_suspend' => $data['auto_suspend'] ?? false,
        ];
    }
}
