<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Support\Subscriptions\ManualPurchaseManager;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

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

            $this->stripeInvoices = $this->record
                ->invoices()
                ->limit(50)
                ->get()
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->stripe_id,
                        'number' => $invoice->number ?? $invoice->stripe_id,
                        'amount' => $invoice->total ?? 0,
                        'currency' => strtoupper($invoice->currency ?? 'USD'),
                        'status' => $invoice->status,
                        'created_at' => $invoice->invoice_created_at ?? $invoice->created_at,
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
                ->action(function () {
                    $this->loadStripeData();

                    Notification::make()
                        ->title('Datos actualizados')
                        ->success()
                        ->send();
                }),
            Action::make('edit-buy')
                ->label('Editar compra')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (): bool => $this->record instanceof Subscription && $this->record->type === 'buy')
                ->form(fn () => ManualPurchaseManager::schema())
                ->fillForm(function (): array {
                    /** @var Subscription $record */
                    $record = $this->record;

                    return [
                        'vendor_name' => $record->customer_name,
                        'vendor_email' => $record->customer_email,
                        'plan_name' => $record->plan_name,
                        'plan_interval' => $record->plan_interval ?? 'month',
                        'plan_interval_count' => $record->plan_interval === 'indefinite'
                            ? null
                            : ($record->plan_interval_count ?? 1),
                        'price_currency' => strtolower($record->price_currency ?? 'eur'),
                        'amount_total' => $record->amount_total ?? 0,
                        'current_period_end' => $record->current_period_end ?? now()->addMonth(),
                        'notes' => $record->invoice_note,
                    ];
                })
                ->action(function (array $data): void {
                    ManualPurchaseManager::save($data, $this->record);

                    Notification::make()
                        ->title('Compra actualizada')
                        ->body('La suscripción de compra se actualizó correctamente.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),
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

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make($this->record->customer_name ?? 'Cliente')
                    ->description('Creado ' . ($this->record->created_at?->translatedFormat('d M Y') ?? '—'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'past_due' => 'warning',
                                        'canceled' => 'danger',
                                        'trialing' => 'info',
                                        'incomplete', 'unpaid' => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'active' => 'Activa',
                                        'past_due' => 'Vencida',
                                        'canceled' => 'Cancelada',
                                        'trialing' => 'En prueba',
                                        'incomplete' => 'Incompleta',
                                        'unpaid' => 'Impaga',
                                        'incomplete_expired' => 'Expirada',
                                        default => Str::ucfirst($state),
                                    }),
                                TextEntry::make('customer_email')
                                    ->label('Email'),
                                TextEntry::make('customer_phone')
                                    ->label('Teléfono')
                                    ->state(fn () => data_get($this->stripeCustomer, 'phone') ?? '—'),
                                TextEntry::make('customer_country')
                                    ->label('País')
                                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? '—')),
                                TextEntry::make('customer_tax_id')
                                    ->label('ID Fiscal')
                                    ->state(function () {
                                        $taxIdValue = $this->record->customer_tax_id ?? data_get($this->stripeTaxIds, '0.value');
                                        $taxIdType = $this->record->customer_tax_id_type ?? Str::upper((string) data_get($this->stripeTaxIds, '0.type'));
                                        return $taxIdValue ? trim($taxIdValue . ' ' . ($taxIdType ? "($taxIdType)" : '')) : '—';
                                    }),
                                TextEntry::make('customer_id')
                                    ->label('ID Stripe cus_')
                                    ->fontFamily('mono'),
                            ]),
                    ]),
                Section::make('Servicios')
                    ->description(fn () => count($this->getSubscriptionItems()) . ' activos')
                    ->schema([
                        RepeatableEntry::make('subscription_items')
                            ->label('')
                            ->state(fn () => $this->getSubscriptionItems())
                            ->schema([
                                Group::make([
                                    TextEntry::make('name')
                                        ->label('')
                                        ->weight('medium')
                                        ->formatStateUsing(fn ($state, $record): HtmlString => new HtmlString(
                                            '<div class="space-y-1">' .
                                            '<div class="font-medium">' . e($state ?? 'Servicio') . '</div>' .
                                            '<div class="text-sm text-gray-500 dark:text-gray-400">' .
                                            e($record['quantity'] ?? 1) . ' × ' .
                                            e($record['unit_amount'] ? number_format($record['unit_amount'], 2, ',', '.') : '—') . ' ' .
                                            e($record['currency']) .
                                            '</div>' .
                                            '</div>'
                                        )),
                                ])
                                    ->columnSpan(2),
                                TextEntry::make('interval')
                                    ->label('')
                                    ->formatStateUsing(function ($state, $record): string {
                                        if ($state) {
                                            return 'Cada ' . ($record['interval_count'] ?? 1) . ' ' . $state;
                                        }
                                        return '—';
                                    }),
                            ])
                            ->columns(3)
                            ->contained(false),
                    ])
                    ->visible(fn () => ! empty($this->getSubscriptionItems())),
                Section::make('Importe')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount_total')
                                    ->label('Importe')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(function () {
                                        $amount = $this->record->amount_total ?? $this->record->amount_subtotal ?? ($this->record->unit_amount ? $this->record->unit_amount * ($this->record->quantity ?? 1) : null);
                                        if ($amount) {
                                            return number_format($amount, 2, ',', '.') . ' ' . strtoupper($this->record->price_currency ?? 'USD');
                                        }
                                        return '—';
                                    }),
                                TextEntry::make('amount_eur')
                                    ->label('Equivalente en EUR')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->formatStateUsing(fn (?float $state): string => $state ? number_format($state, 2, ',', '.') . ' EUR' : '—'),
                                TextEntry::make('current_period_end')
                                    ->label('Próxima renovación')
                                    ->date('d/m/Y'),
                            ]),
                    ]),
                Section::make('Métodos de Pago')
                    ->schema([
                        Group::make([
                            TextEntry::make('payment_method')
                                ->label('')
                                ->state(function () {
                                    if (! $this->stripePaymentMethod) {
                                        return 'No hay métodos de pago registrados.';
                                    }

                                    $brand = strtoupper(data_get($this->stripePaymentMethod, 'card.brand', 'Método'));
                                    $last4 = data_get($this->stripePaymentMethod, 'card.last4');
                                    $expMonth = data_get($this->stripePaymentMethod, 'card.exp_month');
                                    $expYear = data_get($this->stripePaymentMethod, 'card.exp_year');

                                    return "$brand **** **** **** $last4\nExpira $expMonth/$expYear";
                                }),
                        ]),
                    ]),
                Section::make('Facturas')
                    ->description(fn () => count($this->stripeInvoices) > 0 
                        ? 'Últimas ' . count($this->stripeInvoices) . ' facturas emitidas'
                        : 'No hay facturas registradas')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->label('')
                            ->state(fn () => $this->stripeInvoices)
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Número')
                                    ->state(fn ($record) => $record['number'] ?? $record['id']),
                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->date('d/m/Y'),
                                TextEntry::make('amount')
                                    ->label('Monto')
                                    ->formatStateUsing(fn ($state, $record): string => number_format($state, 2, ',', '.') . ' ' . $record['currency']),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'open' => 'warning',
                                        'void', 'uncollectible' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'paid' => 'Pagada',
                                        'open' => 'Abierta',
                                        'void' => 'Anulada',
                                        'uncollectible' => 'Incobrable',
                                        'draft' => 'Borrador',
                                        default => Str::ucfirst(str_replace('_', ' ', $state)),
                                    }),
                                TextEntry::make('actions')
                                    ->label('Acciones')
                                    ->state(function ($record): HtmlString {
                                        $links = [];
                                        if ($record['invoice_pdf']) {
                                            $links[] = '<a href="' . e($record['invoice_pdf']) . '" target="_blank" class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline">Descargar</a>';
                                        }
                                        if ($record['hosted_invoice_url']) {
                                            $links[] = '<a href="' . e($record['hosted_invoice_url']) . '" target="_blank" class="text-sm font-semibold text-gray-700 dark:text-gray-200 hover:underline">Ver</a>';
                                        }
                                        return new HtmlString(implode(' · ', $links));
                                    }),
                            ])
                            ->columns(5)
                            ->contained(false)
                            ->visible(fn () => ! empty($this->stripeInvoices)),
                    ]),
            ]);
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

