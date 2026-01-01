<?php

namespace App\Console\Commands;

use App\Actions\Payments\SyncMercadoPagoPayments as SyncMercadoPagoPaymentsAction;
use Illuminate\Console\Command;

class SyncMercadoPagoPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:sync-mercadopago 
                            {--begin-date= : Fecha inicial en formato ISO 8601}
                            {--end-date= : Fecha final en formato ISO 8601}
                            {--days=30 : Número de días hacia atrás para sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los pagos de MercadoPago con la base de datos local.';

    /**
     * Execute the console command.
     */
    public function handle(SyncMercadoPagoPaymentsAction $sync): int
    {
        $this->info('Iniciando sincronización de pagos desde MercadoPago...');

        try
        {
            $beginDate = $this->option('begin-date');
            $endDate = $this->option('end-date');
            $days = (int) $this->option('days');

            // If no begin date provided, calculate from days option
            if (! $beginDate && $days > 0) {
                $beginDate = now()->subDays($days)->toIso8601String();
            }

            // If no end date provided, use current time
            if (! $endDate) {
                $endDate = now()->toIso8601String();
            }

            $this->comment("Rango de fechas: {$beginDate} a {$endDate}");

            $count = $sync->handle($beginDate, $endDate);

            $this->info("✓ Sincronización completada: {$count} pagos procesados.");

            return self::SUCCESS;
        }
        catch (\Throwable $exception)
        {
            $this->error('✗ Error durante la sincronización: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

