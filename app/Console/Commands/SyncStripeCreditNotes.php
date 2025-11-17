<?php

namespace App\Console\Commands;

use App\Actions\CreditNotes\SyncStripeCreditNotes as SyncStripeCreditNotesAction;
use Illuminate\Console\Command;

class SyncStripeCreditNotes extends Command
{
    protected $signature = 'creditnotes:sync';

    protected $description = 'Sincroniza las notas de crédito de Stripe a la base de datos local.';

    public function handle(SyncStripeCreditNotesAction $syncStripeCreditNotes): int
    {
        $this->info('Sincronizando notas de crédito de Stripe...');
        $count = $syncStripeCreditNotes->handle();
        $this->info("Sincronización completada: {$count} notas de crédito procesadas.");

        return self::SUCCESS;
    }
}

