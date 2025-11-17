<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Actions\Subscriptions\RefreshSubscriptionNotes;
use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Support\Subscriptions\ManualPurchaseManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action as TableAction;

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
            Action::make('add-buy-subscription')
                ->label('Registrar compra')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->form(ManualPurchaseManager::schema())
                ->action(function (array $data): void {
                    ManualPurchaseManager::save($data);

                    Notification::make()
                        ->title('Compra registrada')
                        ->body('La suscripción de compra se agregó correctamente.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

