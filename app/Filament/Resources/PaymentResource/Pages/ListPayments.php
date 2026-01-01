<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Sincronizar')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->dispatch('sync-mercadopago-payments');
                })
                ->requiresConfirmation()
                ->modalHeading('Sincronizar pagos de MercadoPago')
                ->modalDescription('¿Deseas sincronizar los pagos de los últimos 30 días desde MercadoPago?')
                ->modalSubmitActionLabel('Sincronizar'),
        ];
    }
}

