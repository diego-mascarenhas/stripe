<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Actions\Subscriptions\RefreshSubscriptionNotes;
use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync-stripe')
                ->label('Sincronizar con Stripe')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    $processed = app(SyncStripeSubscriptionsAction::class)->handle();
                    $updatedNotes = app(RefreshSubscriptionNotes::class)->handle();

                    Notification::make()
                        ->title('Sincronización completada')
                        ->body("Suscripciones procesadas: {$processed}. Notas actualizadas: {$updatedNotes}.")
                        ->success()
                        ->send();
                })
                ->color('primary'),
        ];
    }
}
