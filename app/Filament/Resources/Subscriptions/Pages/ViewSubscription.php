<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected string $view = 'filament.resources.subscriptions.pages.view-subscription';

    /** @var array<int, array<string, mixed>> */
    public array $stripeInvoices = [];

    /** @var array<string, mixed>|null */
    public ?array $stripePaymentMethod = null;

    /** @var array<string, mixed>|null */
    public ?array $stripeCustomer = null;

    /** @var array<int, array<string, mixed>> */
    public array $stripeTaxIds = [];

    public function mount($record): void
    {
        parent::mount($record);

        $this->loadStripeData();
    }

    protected function loadStripeData(): void
    {
        if (! $this->record instanceof Subscription || blank($this->record->customer_id)) {
            return;
        }

        try {
            /** @var StripeClient $stripe */
            $stripe = app(StripeClient::class);

            $customer = $stripe->customers->retrieve($this->record->customer_id, [
                'expand' => [
                    'invoice_settings.default_payment_method',
                ],
            ]);

            $this->stripeCustomer = $customer->toArray();
            $this->stripePaymentMethod = Arr::get($this->stripeCustomer, 'invoice_settings.default_payment_method');

            $taxIds = $stripe->customers->allTaxIds($this->record->customer_id, [
                'limit' => 5,
            ]);

            $this->stripeTaxIds = collect($taxIds->data)
                ->map(fn ($taxId) => $taxId->toArray())
                ->all();

            $invoices = $stripe->invoices->all([
                'customer' => $this->record->customer_id,
                'limit' => 10,
            ]);

            $this->stripeInvoices = collect($invoices->data)
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'amount' => ($invoice->amount_due ?? 0) / 100,
                        'currency' => strtoupper($invoice->currency ?? 'usd'),
                        'status' => $invoice->status,
                        'created_at' => Carbon::createFromTimestamp($invoice->created ?? now()->timestamp),
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'invoice_pdf' => $invoice->invoice_pdf,
                    ];
                })
                ->all();
        } catch (\Throwable $exception) {
            report($exception);
            $this->stripeInvoices = [];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Actualizar desde Stripe')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadStripeData()),
            Action::make('open-stripe')
                ->label('Ver en Stripe')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => $this->record?->customer_id
                    ? "https://dashboard.stripe.com/customers/{$this->record->customer_id}"
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record?->customer_id)),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
            'stripeInvoices' => $this->stripeInvoices,
            'stripePaymentMethod' => $this->stripePaymentMethod,
            'stripeCustomer' => $this->stripeCustomer,
            'stripeTaxIds' => $this->stripeTaxIds,
            'subscriptionItems' => $this->getSubscriptionItems(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getSubscriptionItems(): array
    {
        $items = Arr::get($this->record?->raw_payload ?? [], 'items.data', []);

        return collect($items)
            ->map(function ($item) {
                $unitAmount = Arr::get($item, 'price.unit_amount');

                return [
                    'name' => Arr::get($item, 'price.nickname')
                        ?? Arr::get($item, 'price.product.name')
                        ?? Arr::get($item, 'plan.nickname')
                        ?? $this->record->plan_name,
                    'status' => $this->record->status,
                    'unit_amount' => $unitAmount !== null ? $unitAmount / 100 : null,
                    'currency' => strtoupper(Arr::get($item, 'price.currency', $this->record->price_currency ?? 'usd')),
                    'interval' => Arr::get($item, 'price.recurring.interval'),
                    'interval_count' => Arr::get($item, 'price.recurring.interval_count', 1),
                    'quantity' => Arr::get($item, 'quantity', 1),
                ];
            })
            ->all();
    }
}

