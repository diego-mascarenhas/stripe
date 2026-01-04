<?php

namespace App\Filament\Resources\SubscriptionNotifications;

use App\Filament\Resources\SubscriptionNotifications\Pages\ListSubscriptionNotifications;
use App\Filament\Resources\SubscriptionNotifications\Tables\SubscriptionNotificationsTable;
use App\Models\SubscriptionNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SubscriptionNotificationResource extends Resource
{
    protected static ?string $model = SubscriptionNotification::class;

    public static function table(Table $table): Table
    {
        return SubscriptionNotificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionNotifications::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Notificaciones';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Notificación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Notificaciones';
    }
}
