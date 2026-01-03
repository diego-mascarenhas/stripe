<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'mail:test {email=test@example.com}';
    protected $description = 'Send a test email to verify mail configuration';

    public function handle(): int
    {
        $to = $this->argument('email');
        
        $this->info("Sending test email to {$to}...");
        
        try {
            Mail::raw('This is a test email from Laravel Stripe', function ($message) use ($to) {
                $message->to($to)
                        ->subject('Test Email from Stripe');
            });
            
            $this->info('✅ Email sent successfully!');
            $this->line('Check MailPit at http://localhost:8025');
            
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Failed to send email: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
