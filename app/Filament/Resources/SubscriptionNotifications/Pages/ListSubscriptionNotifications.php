<?php

namespace App\Filament\Resources\SubscriptionNotifications\Pages;

use App\Filament\Resources\SubscriptionNotifications\SubscriptionNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionNotifications extends ListRecords
{
    protected static string $resource = SubscriptionNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
