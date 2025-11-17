<?php

namespace App\Console\Commands;

use App\Actions\Invoices\SyncStripeInvoices as SyncStripeInvoicesAction;
use Illuminate\Console\Command;

class SyncStripeInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las facturas de Stripe con la base de datos local.';

    /**
     * Execute the console command.
     */
    public function handle(SyncStripeInvoicesAction $sync): int
    {
        $this->info('Iniciando sincronización de facturas desde Stripe...');

        try {
            $count = $sync->handle();

            $this->info("✓ Sincronización completada: {$count} facturas procesadas.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('✗ Error durante la sincronización: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

