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
use Illuminate\Support\Facades\Log;
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

    /** @var array<string, mixed>|null */
    public ?array $whmDNS = null;

    /** @var string|null */
    public ?string $whmStatus = null;

    /** @var array<string, mixed>|null */
    public ?array $dnsValidation = null;

    public function mount($record): void
    {
        parent::mount($record);

        $this->loadStripeData();
        $this->loadWHMData();
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

            // Try to load invoices from Stripe API as fallback
            try {
                $stripeInvoicesList = $stripe->invoices->all([
                    'subscription' => $this->record->stripe_id,
                    'limit' => 50,
                ]);

                $this->stripeInvoices = collect($stripeInvoicesList->data)
                ->map(function ($invoice) {
                    return [
                            'id' => $invoice->id,
                            'number' => $invoice->number ?? $invoice->id,
                            'amount' => ($invoice->total ?? 0) / 100,
                        'currency' => strtoupper($invoice->currency ?? 'USD'),
                        'status' => $invoice->status,
                            'created_at' => $invoice->created ? \Carbon\Carbon::createFromTimestamp($invoice->created) : null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'invoice_pdf' => $invoice->invoice_pdf,
                    ];
                })
                ->all();
            } catch (\Throwable $invoiceException) {
                Log::warning('Could not load invoices from Stripe API', [
                    'subscription_stripe_id' => $this->record->stripe_id,
                    'error' => $invoiceException->getMessage(),
                ]);
                $this->stripeInvoices = [];
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->stripeInvoices = [];
        }
    }

    protected function loadWHMData(): void
    {
        if (! $this->record instanceof Subscription) {
            return;
        }

        $server = data_get($this->record->data, 'server');
        $user = data_get($this->record->data, 'user');
        $domain = data_get($this->record->data, 'domain');

        if (empty($server) || empty($user)) {
            Log::info('WHM data not loaded: missing server or user', [
                'subscription_id' => $this->record->id,
                'has_server' => !empty($server),
                'has_user' => !empty($user),
            ]);
            return;
        }

        try {
            Log::info('Loading WHM data', [
                'subscription_id' => $this->record->id,
                'server' => $server,
                'user' => $user,
                'domain' => $domain,
            ]);

            $whm = app(\App\Services\WHM\WHMServerManager::class);

            // Load account status
            $this->whmStatus = $whm->getAccountStatus($server, $user);
            Log::info('WHM status loaded', ['status' => $this->whmStatus]);

            // Load account package/plan
            $whmPlan = $whm->getAccountPackage($server, $user);
            if ($whmPlan && $whmPlan !== data_get($this->record->data, 'plan')) {
                Log::info('WHM plan differs from stored plan', [
                    'whm_plan' => $whmPlan,
                    'stored_plan' => data_get($this->record->data, 'plan'),
                ]);
                // Update plan in data if different
                $data = $this->record->data ?? [];
                $data['plan'] = $whmPlan;
                $this->record->update(['data' => $data]);
            }

            // Load DNS validation if domain exists
            if ($domain) {
                $dnsService = app(\App\Services\DNS\DNSLookupService::class);
                $this->dnsValidation = $dnsService->validateRevisionAlphaConfiguration($domain);
                Log::info('DNS validation completed', [
                    'domain' => $domain,
                    'has_own_ns' => $this->dnsValidation['has_own_ns'],
                    'domain_points_to_service' => $this->dnsValidation['domain_points_to_service'],
                    'mail_points_to_service' => $this->dnsValidation['mail_points_to_service'],
                    'has_spf_include' => $this->dnsValidation['has_spf_include'],
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to load WHM data', [
                'subscription_id' => $this->record->id,
                'server' => $server,
                'user' => $user,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->whmStatus = null;
            $this->whmDNS = null;
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
                    try {
                        // 1. Sync with Stripe API (fetch updated data)
                        $stripe = app(\Stripe\StripeClient::class);
                        $stripeSubscription = $stripe->subscriptions->retrieve($this->record->stripe_id, [
                            'expand' => ['customer', 'latest_invoice', 'default_payment_method'],
                        ]);

                        $payload = $stripeSubscription->toArray();
                        $item = \Illuminate\Support\Arr::get($payload, 'items.data.0', []);
                        $price = \Illuminate\Support\Arr::get($item, 'price', []);
                        $metadata = \Illuminate\Support\Arr::get($payload, 'metadata', []);

                        // 2. Update key fields from Stripe
                        $updates = [
                            'status' => \Illuminate\Support\Arr::get($payload, 'status'),
                            'customer_email' => \Illuminate\Support\Arr::get($payload, 'customer.email'),
                            'customer_name' => \Illuminate\Support\Arr::get($payload, 'customer.name'),
                            'plan_name' => \Illuminate\Support\Arr::get($price, 'nickname')
                                ?? \Illuminate\Support\Arr::get($price, 'product.name'),
                            'current_period_start' => \Illuminate\Support\Arr::get($payload, 'current_period_start')
                                ? \Carbon\Carbon::createFromTimestamp(\Illuminate\Support\Arr::get($payload, 'current_period_start'))
                                : null,
                            'current_period_end' => \Illuminate\Support\Arr::get($payload, 'current_period_end')
                                ? \Carbon\Carbon::createFromTimestamp(\Illuminate\Support\Arr::get($payload, 'current_period_end'))
                                : null,
                            'cancel_at_period_end' => (bool) \Illuminate\Support\Arr::get($payload, 'cancel_at_period_end', false),
                            'raw_payload' => $payload,
                            'last_synced_at' => now(),
                        ];

                        // Update metadata preserving local data
                        if (!empty($metadata)) {
                            $category = \Illuminate\Support\Arr::get($metadata, 'category')
                                ?? \Illuminate\Support\Arr::get($metadata, 'type');

                            $stripeMetadata = array_filter([
                                'category' => $category,
                                'server' => \Illuminate\Support\Arr::get($metadata, 'server'),
                                'domain' => \Illuminate\Support\Arr::get($metadata, 'domain'),
                                'user' => \Illuminate\Support\Arr::get($metadata, 'user'),
                                'email' => \Illuminate\Support\Arr::get($metadata, 'email'),
                                'auto_suspend' => \Illuminate\Support\Arr::get($metadata, 'auto_suspend'),
                            ], fn ($value) => $value !== null && $value !== '');

                            // Merge with existing data
                            $updates['data'] = array_merge($this->record->data ?? [], $stripeMetadata);
                        }

                        $this->record->update(array_filter($updates, fn ($value) => $value !== null));

                        // 3. Reload view data
                        $this->record->refresh();
                        $this->loadStripeData();
                        $this->loadWHMData();

                        Notification::make()
                            ->title('Suscripción sincronizada')
                            ->body('Los datos se actualizaron correctamente desde Stripe.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Log::error('Error al actualizar desde Stripe', [
                            'subscription_id' => $this->record->id,
                            'stripe_id' => $this->record->stripe_id,
                            'error' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Error al sincronizar')
                            ->body('No se pudo actualizar desde Stripe: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
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
                        'status' => $record->status ?? 'active',
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
                // Main service and category
                Section::make($this->record->plan_name ?? 'Suscripción')
                    ->columnSpan('full')
                    ->schema([
                        TextEntry::make('data.category')
                            ->label('')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'hosting' => 'Hosting',
                                'web_cloud' => 'Web Cloud',
                                'vps' => 'VPS',
                                'domain' => 'Dominio',
                                'backups' => 'Backups',
                                'mailer' => 'Mailer',
                                'whatsapp' => 'WhatsApp',
                                null => '—',
                                default => ucfirst(str_replace('_', ' ', $state)),
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'hosting' => 'success',
                                'web_cloud' => 'info',
                                'vps' => 'warning',
                                'domain' => 'primary',
                                'backups' => 'gray',
                                'mailer' => 'danger',
                                'whatsapp' => 'success',
                                default => 'gray',
                            }),
                    ]),

                Grid::make(2)
                    ->columnSpan('full')
                    ->schema([
                        Section::make($this->record->customer_name ?? 'Cliente')
                            ->description('Creado ' . ($this->record->created_at?->translatedFormat('d M Y') ?? '—'))
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->columnSpan(1)
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
                                    ->label('Email')
                                    ->columnSpan(1),
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
                            ])
                            ->columns(2),

                        Section::make('Importe')
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
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('plan_interval')
                                            ->label('Frecuencia')
                                            ->formatStateUsing(function () {
                                                $interval = $this->record->plan_interval;
                                                $count = $this->record->plan_interval_count ?? 1;

                                                if (!$interval) {
                                                    return '—';
                                                }

                                                return match($interval) {
                                                    'month' => $count > 1 ? "Cada $count meses" : 'Mensual',
                                                    'year' => $count > 1 ? "Cada $count años" : 'Anual',
                                                    'week' => $count > 1 ? "Cada $count semanas" : 'Semanal',
                                                    'day' => $count > 1 ? "Cada $count días" : 'Diario',
                                                    default => ucfirst($interval),
                                                };
                                            })
                                            ->badge()
                                            ->color('primary'),
                                        TextEntry::make('current_period_end')
                                            ->label('Próxima renovación')
                                            ->date('d/m/Y'),
                                    ]),
                            ])
                            ->columns(1),
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
                    ]),
                Section::make('Detalles del Servicio')
                    ->columnSpan('full')
                    ->schema([
                        TextEntry::make('plan')
                            ->label('Plan')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '—')
                            ->state(fn () => data_get($this->record->data, 'plan')),
                        TextEntry::make('server')
                            ->label('Servidor')
                            ->state(fn () => data_get($this->record->data, 'server') ?? '—'),
                        TextEntry::make('domain')
                            ->label('Dominio')
                            ->state(fn () => data_get($this->record->data, 'domain') ?? '—'),
                        TextEntry::make('user')
                            ->label('Usuario')
                            ->state(fn () => data_get($this->record->data, 'user') ?? '—'),
                        TextEntry::make('service_email')
                            ->label('Email del servicio')
                            ->state(fn () => data_get($this->record->data, 'email') ?? '—'),
                        TextEntry::make('auto_suspend')
                            ->label('Auto-suspensión')
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'warning' : 'success')
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Activada' : 'Desactivada')
                            ->state(fn () => data_get($this->record->data, 'auto_suspend', false)),
                    ])
                    ->columns(3)
                    ->visible(fn () => $this->record->type === 'buy' && !empty(array_filter([
                        data_get($this->record->data, 'plan'),
                        data_get($this->record->data, 'server'),
                        data_get($this->record->data, 'domain'),
                        data_get($this->record->data, 'user'),
                        data_get($this->record->data, 'email'),
                    ]))),
                Section::make('Validación de Configuración DNS')
                    ->columnSpan('full')
                    ->schema([
                        // Nameservers
                        TextEntry::make('dns_nameservers')
                            ->label('Nameservers')
                            ->badge()
                            ->state(fn () => $this->dnsValidation['has_own_ns'] ?? false ? 'Configurado correctamente' : 'No usa nameservers configurados')
                            ->color(fn () => $this->dnsValidation['has_own_ns'] ?? false ? 'success' : 'warning')
                            ->icon(fn () => $this->dnsValidation['has_own_ns'] ?? false ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                            ->helperText(fn () =>
                                'Actual: ' . implode(', ', $this->dnsValidation['current_nameservers'] ?? []) .
                                ' | Esperado: ' . implode(', ', $this->dnsValidation['expected_nameservers'] ?? [])
                            ),

                        // Domain IP
                        TextEntry::make('dns_domain_ip')
                            ->label('IP del Dominio')
                            ->badge()
                            ->state(fn () => $this->dnsValidation['domain_points_to_service'] ?? false
                                ? 'Apunta al servicio (' . ($this->dnsValidation['matching_domain_ip'] ?? '') . ')'
                                : 'No apunta al servicio'
                            )
                            ->color(fn () => $this->dnsValidation['domain_points_to_service'] ?? false ? 'success' : 'danger')
                            ->icon(fn () => $this->dnsValidation['domain_points_to_service'] ?? false ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->helperText(fn () =>
                                'IPs actuales: ' . implode(', ', $this->dnsValidation['domain_ips'] ?? ['No encontradas']) .
                                ' | IPs válidas: ' . implode(', ', $this->dnsValidation['expected_ips'] ?? [])
                            ),

                        // Mail Server IP
                        TextEntry::make('dns_mail_ip')
                            ->label('IP del Mail Server')
                            ->badge()
                            ->state(fn () => $this->dnsValidation['mail_points_to_service'] ?? false
                                ? 'Apunta al servicio (' . ($this->dnsValidation['matching_mail_ip'] ?? '') . ')'
                                : 'No apunta al servicio'
                            )
                            ->color(fn () => $this->dnsValidation['mail_points_to_service'] ?? false ? 'success' : 'danger')
                            ->icon(fn () => $this->dnsValidation['mail_points_to_service'] ?? false ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->helperText(function () {
                                $mxRecords = $this->dnsValidation['mx_records'] ?? [];
                                if (empty($mxRecords)) {
                                    return 'No hay registros MX configurados';
                                }
                                return 'Registros MX: ' . implode(', ', array_column($mxRecords, 'exchange'));
                            }),

                        // SPF Record
                        TextEntry::make('dns_spf')
                            ->label('Registro SPF')
                            ->badge()
                            ->state(fn () => $this->dnsValidation['has_spf_include'] ?? false
                                ? 'Incluye spf.revisionalpha.com'
                                : 'No incluye spf.revisionalpha.com'
                            )
                            ->color(fn () => $this->dnsValidation['has_spf_include'] ?? false ? 'success' : 'warning')
                            ->icon(fn () => $this->dnsValidation['has_spf_include'] ?? false ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                            ->helperText(fn () =>
                                'SPF actual: ' . ($this->dnsValidation['spf_record'] ?? 'No configurado')
                            ),
                    ])
                    ->columns(1)
                    ->visible(fn () =>
                        $this->record->type === 'sell' &&
                        !empty(data_get($this->record->data, 'domain'))
                    ),
                Section::make('Metadatos')
                    ->columnSpan('full')
                    ->headerActions([
                        Action::make('edit_metadata')
                            ->label('Editar Metadata')
                            ->icon('heroicon-o-pencil-square')
                            ->color('gray')
                            ->visible(fn () => $this->record->type === 'sell')
                            ->form(fn () => \App\Support\Subscriptions\SubscriptionMetadataManager::schema())
                            ->fillForm(function (): array {
                                $data = \App\Support\Subscriptions\SubscriptionMetadataManager::fillForm($this->record);

                                // Load plan from WHM if server and user exist
                                if (!empty($data['server']) && !empty($data['user'])) {
                                    try {
                                        $whmPlan = app(\App\Services\WHM\WHMServerManager::class)
                                            ->getAccountPackage($data['server'], $data['user']);

                                        if ($whmPlan) {
                                            $data['plan'] = $whmPlan;
                                        }
                                    } catch (\Throwable $e) {
                                        Log::warning('Could not load WHM plan', [
                                            'server' => $data['server'],
                                            'user' => $data['user'],
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }

                                return $data;
                            })
                            ->action(function (array $data): void {
                                try {
                                    // 1. Update basic metadata in Stripe
                                    app(\App\Actions\Subscriptions\UpdateStripeSubscriptionMetadata::class)
                                        ->handle($this->record, $data);

                                    // 2. Sync email with WHM if provided
                                    if (!empty($data['server']) && !empty($data['user']) && !empty($data['email'])) {
                                        try {
                                            app(\App\Services\WHM\WHMServerManager::class)
                                                ->updateContactEmail($data['server'], $data['user'], $data['email']);

                                            Log::info('Contact email synced with WHM', [
                                                'server' => $data['server'],
                                                'user' => $data['user'],
                                                'email' => $data['email'],
                                            ]);
                                        } catch (\Throwable $whmException) {
                                            Log::warning('Failed to sync email with WHM', [
                                                'server' => $data['server'],
                                                'user' => $data['user'],
                                                'email' => $data['email'],
                                                'error' => $whmException->getMessage(),
                                            ]);
                                        }
                                    }

                                    // 3. Sync all server data with Stripe (WHM status, DNS, etc.)
                                    $this->record->refresh();
                                    $syncResult = app(\App\Actions\Subscriptions\SyncSubscriptionWithStripe::class)
                                        ->handle($this->record);

                                    $messages = ['Metadata actualizada correctamente en Stripe'];
                                    if (!empty($syncResult['synced'])) {
                                        $messages[] = 'Sincronizado: ' . implode(', ', $syncResult['synced']);
                                    }
                                    if (!empty($syncResult['errors'])) {
                                        $messages[] = 'Advertencias: ' . implode(', ', $syncResult['errors']);
                                    }

                                    Notification::make()
                                        ->title('Actualización completa')
                                        ->body(implode("\n", $messages))
                                        ->success()
                                        ->send();

                                    // Refresh page to show new data
                                    redirect()->to(SubscriptionResource::getUrl('view', ['record' => $this->record]));
                                } catch (\Throwable $exception) {
                                    Notification::make()
                                        ->title('Error al actualizar')
                                        ->body('No se pudo actualizar la metadata: '.$exception->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                        Action::make('refresh_whm')
                            ->label('Sincronizar')
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->visible(fn () =>
                                !empty(data_get($this->record->data, 'server')) &&
                                !empty(data_get($this->record->data, 'user'))
                            )
                            ->action(function () {
                                // Sync with Stripe
                                $syncResult = app(\App\Actions\Subscriptions\SyncSubscriptionWithStripe::class)
                                    ->handle($this->record);

                                // Reload local data
                                $this->record->refresh();
                                $this->loadWHMData();

                                if ($syncResult['success']) {
                                    $syncedFields = implode(', ', $syncResult['synced']);
                                    Notification::make()
                                        ->title('Datos sincronizados con Stripe')
                                        ->body("Campos actualizados: {$syncedFields}")
                                        ->success()
                                        ->send();
                                } else {
                                    $errors = implode(', ', $syncResult['errors']);
                                    Notification::make()
                                        ->title('Sincronización parcial')
                                        ->body("Errores: {$errors}")
                                        ->warning()
                                        ->send();
                                }
                            }),
                        Action::make('suspend')
                            ->label('Suspender cuenta WHM')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->visible(fn () =>
                                ! empty(data_get($this->record->data, 'server')) &&
                                ! empty(data_get($this->record->data, 'user')) &&
                                $this->whmStatus === 'active'
                            )
                            ->requiresConfirmation()
                            ->modalHeading('Suspender cuenta en WHM')
                            ->modalDescription(fn () => new HtmlString(
                                "¿Estás seguro de suspender la cuenta <strong>" .
                                data_get($this->record->data, 'user') .
                                "</strong> en el servidor <strong>" .
                                data_get($this->record->data, 'server') .
                                "</strong>?"
                            ))
                            ->action(function () {
                                try {
                                    $server = data_get($this->record->data, 'server');
                                    $user = data_get($this->record->data, 'user');

                                    app(\App\Services\WHM\WHMServerManager::class)
                                        ->suspendAccount($server, $user, 'Suspendido manualmente desde el panel');

                                    // Reload WHM data
                                    $this->loadWHMData();

                                    Notification::make()
                                        ->title('Cuenta suspendida')
                                        ->body("La cuenta {$user} fue suspendida exitosamente en WHM.")
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Error al suspender')
                                        ->body("No se pudo suspender la cuenta: {$e->getMessage()}")
                                        ->danger()
                                        ->send();
                                }
                            }),
                        Action::make('unsuspend')
                            ->label('Reactivar cuenta WHM')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn () =>
                                ! empty(data_get($this->record->data, 'server')) &&
                                ! empty(data_get($this->record->data, 'user')) &&
                                $this->whmStatus === 'suspended'
                            )
                            ->requiresConfirmation()
                            ->modalHeading('Reactivar cuenta en WHM')
                            ->modalDescription(fn () => new HtmlString(
                                "¿Estás seguro de reactivar la cuenta <strong>" .
                                data_get($this->record->data, 'user') .
                                "</strong> en el servidor <strong>" .
                                data_get($this->record->data, 'server') .
                                "</strong>?"
                            ))
                            ->action(function () {
                                try {
                                    $server = data_get($this->record->data, 'server');
                                    $user = data_get($this->record->data, 'user');

                                    app(\App\Services\WHM\WHMServerManager::class)
                                        ->unsuspendAccount($server, $user);

                                    // Reload WHM data
                                    $this->loadWHMData();

                                    Notification::make()
                                        ->title('Cuenta reactivada')
                                        ->body("La cuenta {$user} fue reactivada exitosamente en WHM.")
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Error al reactivar')
                                        ->body("No se pudo reactivar la cuenta: {$e->getMessage()}")
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('data.category')
                                    ->label('Categoría de servicio')
                                    ->badge()
                                    ->state(fn () => data_get($this->record->data, 'category') ?? data_get($this->record->data, 'type'))
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'hosting' => 'Hosting',
                                        'web_cloud' => 'Web Cloud',
                                        'vps' => 'VPS',
                                        'domain' => 'Domain',
                                        'backups' => 'Backups',
                                        'mailer' => 'Mailer',
                                        'whatsapp' => 'WhatsApp',
                                        default => ucfirst($state ?? '—'),
                                    })
                                    ->color(fn (?string $state): string => match ($state) {
                                        'hosting' => 'info',
                                        'web_cloud' => 'success',
                                        'vps' => 'warning',
                                        'domain' => 'primary',
                                        'backups' => 'gray',
                                        'mailer' => 'danger',
                                        'whatsapp' => 'success',
                                        default => 'gray',
                                    })
                                    ->visible(fn () => filled(data_get($this->record->data, 'category')) || filled(data_get($this->record->data, 'type'))),
                                TextEntry::make('whm_plan')
                                    ->label('Plan')
                                    ->badge()
                                    ->state(fn () => data_get($this->record->data, 'plan') ?? '—')
                                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? '—'))
                                    ->color('primary')
                                    ->icon('heroicon-o-circle-stack')
                                    ->visible(fn () => filled(data_get($this->record->data, 'plan'))),
                                TextEntry::make('whm_status')
                                    ->label('Estado')
                                    ->badge()
                                    ->state(fn () => $this->whmStatus)
                                    ->formatStateUsing(fn (?string $state): string => match($state) {
                                        'active' => 'Activo',
                                        'suspended' => 'Suspendido',
                                        default => 'Sin datos'
                                    })
                                    ->color(fn (?string $state): string => match($state) {
                                        'active' => 'success',
                                        'suspended' => 'danger',
                                        default => 'gray'
                                    })
                                    ->icon(fn (?string $state): string => match($state) {
                                        'active' => 'heroicon-o-check-circle',
                                        'suspended' => 'heroicon-o-x-circle',
                                        default => 'heroicon-o-question-mark-circle'
                                    })
                                    ->visible(fn () =>
                                        !empty(data_get($this->record->data, 'server')) &&
                                        !empty(data_get($this->record->data, 'user'))
                                    ),
                                TextEntry::make('data.server')
                                    ->label('Servidor')
                                    ->icon('heroicon-o-server')
                                    ->visible(fn () => filled(data_get($this->record->data, 'server'))),
                                TextEntry::make('data.domain')
                                    ->label('Dominio')
                                    ->icon('heroicon-o-globe-alt')
                                    ->visible(fn () => filled(data_get($this->record->data, 'domain'))),
                                TextEntry::make('data.user')
                                    ->label('Usuario')
                                    ->icon('heroicon-o-user')
                                    ->copyable()
                                    ->visible(fn () => filled(data_get($this->record->data, 'user'))),
                                TextEntry::make('data.email')
                                    ->label('Email del servicio')
                                    ->icon('heroicon-o-envelope')
                                    ->visible(fn () => filled(data_get($this->record->data, 'email'))),
                                TextEntry::make('data.auto_suspend')
                                    ->label('Auto-suspensión')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state ? 'Activada' : 'Desactivada')
                                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                                    ->visible(fn () => isset($this->record->data['auto_suspend'])),
                            ])
                            ->visible(fn () => ! empty($this->record->data)),
                        Group::make([
                            TextEntry::make('no_metadata')
                                ->label('')
                                ->state('No hay metadatos registrados.')
                                ->visible(fn () => empty($this->record->data)),
                        ]),
                    ]),
                Section::make('Facturas')
                    ->description(fn () => count($this->stripeInvoices) > 0
                        ? 'Últimas ' . count($this->stripeInvoices) . ' facturas emitidas'
                        : 'No hay facturas registradas')
                    ->columnSpan('full')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->label('')
                            ->state(fn () => $this->stripeInvoices)
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Número')
                                    ->default(fn ($record) => $record['id'] ?? '—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->date('d/m/Y'),
                                TextEntry::make('amount')
                                    ->label('Monto')
                                    ->formatStateUsing(fn ($state, $record): string =>
                                        number_format($state, 2, ',', '.') . ' ' . ($record['currency'] ?? 'ARS')
                                    ),
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

        // If this is a manual purchase (type 'buy'), get type from data field
        $serviceType = null;
        if ($this->record?->type === 'buy') {
            $serviceType = data_get($this->record->data, 'category') ?? data_get($this->record->data, 'type');
        } else {
            // For Stripe subscriptions, try to get from customer metadata
            // Try 'category' first, fallback to 'type' for backwards compatibility
            $serviceType = data_get($this->stripeCustomer, 'metadata.category')
                ?? data_get($this->stripeCustomer, 'metadata.type')
                ?? data_get($this->record->data, 'category')
                ?? data_get($this->record->data, 'type');
        }

        return collect($items)
            ->map(function ($item) use ($serviceType) {
                $unitAmount = Arr::get($item, 'price.unit_amount');

                return [
                    'name' => Arr::get($item, 'price.nickname')
                        ?? Arr::get($item, 'price.product.name')
                        ?? Arr::get($item, 'plan.nickname')
                        ?? $this->record->plan_name,
                    'type' => $serviceType,
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

