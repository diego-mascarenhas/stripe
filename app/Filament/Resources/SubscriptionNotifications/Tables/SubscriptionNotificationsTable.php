<?php

namespace App\Filament\Resources\SubscriptionNotifications\Tables;

use App\Models\SubscriptionNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subscription.customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->url(fn (SubscriptionNotification $record) =>
                        \App\Filament\Resources\Subscriptions\SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                    )
                    ->color('primary'),
                TextColumn::make('subscription.customer_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('notification_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'warning_5_days' => 'warning',
                        'warning_2_days' => 'danger',
                        'suspended' => 'gray',
                        'reactivated' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'warning_5_days' => 'Aviso 5 días',
                        'warning_2_days' => 'Aviso 2 días',
                        'suspended' => 'Suspendido',
                        'reactivated' => 'Reactivado',
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Enviado',
                        'pending' => 'Pendiente',
                        'failed' => 'Fallido',
                        default => $state,
                    }),
                TextColumn::make('scheduled_at')
                    ->label('Programado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Abierto')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('open_count')
                    ->label('Aperturas')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state === 1 => 'success',
                        $state > 1 => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->tooltip('Ver contenido')
                    ->modalContent(fn (SubscriptionNotification $record) => new \Illuminate\Support\HtmlString(
                        '<div class="prose dark:prose-invert max-w-none">' . $record->body . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
                Action::make('resend')
                    ->label('')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->tooltip('Reenviar')
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar notificación')
                    ->modalDescription('¿Estás seguro de reenviar esta notificación?')
                    ->action(function (SubscriptionNotification $record) {
                        try {
                            $subscription = $record->subscription;

                            // Determinar qué mail enviar según el tipo
                            $mail = match($record->notification_type) {
                                'warning_5_days' => new \App\Mail\SubscriptionWarningMail($subscription, 5),
                                'warning_2_days' => new \App\Mail\SubscriptionWarningMail($subscription, 2),
                                'suspended' => new \App\Mail\SubscriptionSuspendedMail($subscription),
                                'reactivated' => new \App\Mail\SubscriptionReactivatedMail($subscription),
                                default => null,
                            };

                            if ($mail) {
                                \Illuminate\Support\Facades\Mail::to($subscription->customer_email)
                                    ->send($mail);

                                // Actualizar el registro
                                $record->update([
                                    'status' => 'sent',
                                    'sent_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Notificación reenviada')
                                    ->body('La notificación se ha reenviado correctamente.')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al reenviar')
                                ->body('No se pudo reenviar la notificación: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}

