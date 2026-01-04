<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class CleanTestInvoices extends Command
{
    protected $signature = 'test:clean-invoices';
    protected $description = 'Elimina facturas de prueba';

    public function handle(): int
    {
        $deleted = Invoice::where('number', 'LIKE', 'TEST-%')->delete();
        
        $this->info("🗑️  Eliminadas {$deleted} facturas de prueba.");

        return 0;
    }
}

