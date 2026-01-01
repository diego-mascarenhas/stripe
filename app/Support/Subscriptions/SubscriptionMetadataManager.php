<?php

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use Filament\Forms\Components\TextInput;

class SubscriptionMetadataManager
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
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
        ];
    }

    /**
     * Fill form with subscription metadata
     */
    public static function fillForm(Subscription $subscription): array
    {
        $data = $subscription->data ?? [];

        return [
            'server' => $data['server'] ?? null,
            'domain' => $data['domain'] ?? null,
            'user' => $data['user'] ?? null,
            'email' => $data['email'] ?? null,
        ];
    }
}
