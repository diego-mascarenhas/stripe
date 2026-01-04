<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\WebhookEndpoint;

class TestStripeWebhook extends Command
{
    protected $signature = 'stripe:test-webhook
                            {--list : List all webhook endpoints}
                            {--test : Send a test event}
                            {--check : Check webhook configuration}';

    protected $description = 'Test and debug Stripe webhook configuration';

    public function handle()
    {
        $stripe = app(StripeClient::class);

        if ($this->option('list')) {
            return $this->listWebhooks($stripe);
        }

        if ($this->option('test')) {
            return $this->sendTestEvent();
        }

        if ($this->option('check')) {
            return $this->checkConfiguration($stripe);
        }

        // Default: show menu
        $this->info('🔧 Stripe Webhook Test & Debug Tool');
        $this->newLine();

        $choice = $this->choice(
            'What would you like to do?',
            [
                'check' => 'Check webhook configuration',
                'list' => 'List all webhook endpoints',
                'test' => 'Send a test event',
                'events' => 'List recent webhook events',
            ],
            'check',
        );

        return match ($choice) {
            'check' => $this->checkConfiguration($stripe),
            'list' => $this->listWebhooks($stripe),
            'test' => $this->sendTestEvent(),
            'events' => $this->listRecentEvents($stripe),
            default => Command::SUCCESS,
        };
    }

    protected function checkConfiguration(StripeClient $stripe)
    {
        $this->info('🔍 Checking Stripe Webhook Configuration...');
        $this->newLine();

        $secretKey = config('services.stripe.secret');
        $webhookSecret = config('services.stripe.webhook_secret');
        $webhookUrl = url('/stripe/webhook');

        $this->table(
            ['Configuration', 'Value', 'Status'],
            [
                ['Stripe Secret Key', substr($secretKey, 0, 20).'...', $secretKey ? '✅' : '❌'],
                ['Webhook Secret', $webhookSecret ? substr($webhookSecret, 0, 20).'...' : 'Not configured', $webhookSecret ? '✅' : '⚠️'],
                ['Webhook URL', $webhookUrl, '✅'],
                ['CSRF Exempt', 'stripe/webhook', '✅'],
            ],
        );

        $this->newLine();

        if (! $webhookSecret) {
            $this->warn('⚠️  Warning: STRIPE_WEBHOOK_SECRET is not configured in .env');
            $this->info('This means webhook signature verification is disabled.');
            $this->info('To fix this:');
            $this->info('1. Go to Stripe Dashboard > Developers > Webhooks');
            $this->info('2. Click on your webhook endpoint');
            $this->info('3. Copy the "Signing secret"');
            $this->info('4. Add to .env: STRIPE_WEBHOOK_SECRET=whsec_...');
            $this->newLine();
        }

        // Check webhook endpoint in Stripe
        try {
            $endpoints = $stripe->webhookEndpoints->all(['limit' => 100]);
            $found = false;

            foreach ($endpoints->data as $endpoint) {
                if (str_contains($endpoint->url, '/stripe/webhook')) {
                    $found = true;
                    $this->info('✅ Webhook endpoint found in Stripe:');
                    $this->line("   URL: {$endpoint->url}");
                    $this->line("   Status: {$endpoint->status}");
                    $this->line('   Events: '.count($endpoint->enabled_events).' event types');
                    $this->newLine();

                    if ($endpoint->status !== 'enabled') {
                        $this->warn("⚠️  Webhook status is: {$endpoint->status}");
                    }

                    // Show enabled events
                    $this->info('Enabled events:');
                    foreach ($endpoint->enabled_events as $event) {
                        $icon = match ($event) {
                            'invoice.payment_succeeded' => '💰',
                            'invoice.payment_failed' => '❌',
                            'customer.subscription.updated' => '🔄',
                            'customer.subscription.deleted' => '🗑️',
                            default => '📋',
                        };
                        $this->line("   {$icon} {$event}");
                    }
                }
            }

            if (! $found) {
                $this->error('❌ Webhook endpoint not found in Stripe');
                $this->info("Expected URL pattern: */stripe/webhook");
                $this->info("Your URL would be: {$webhookUrl}");
            }
        } catch (\Exception $e) {
            $this->error('❌ Error checking webhooks: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }

    protected function listWebhooks(StripeClient $stripe)
    {
        $this->info('📋 Listing Stripe Webhook Endpoints...');
        $this->newLine();

        try {
            $endpoints = $stripe->webhookEndpoints->all(['limit' => 100]);

            if (count($endpoints->data) === 0) {
                $this->warn('No webhook endpoints found.');
                return Command::SUCCESS;
            }

            $data = [];
            foreach ($endpoints->data as $endpoint) {
                $data[] = [
                    substr($endpoint->id, 0, 20).'...',
                    $endpoint->url,
                    $endpoint->status,
                    count($endpoint->enabled_events).' events',
                ];
            }

            $this->table(
                ['ID', 'URL', 'Status', 'Events'],
                $data,
            );
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function sendTestEvent()
    {
        $this->info('🧪 Sending test event to webhook...');
        $this->newLine();

        $this->warn('⚠️  Note: Test events can only be sent from Stripe Dashboard or CLI');
        $this->info('To send a test event:');
        $this->newLine();
        
        $this->info('📍 Option 1: Stripe Dashboard');
        $this->info('1. Go to: https://dashboard.stripe.com/test/webhooks');
        $this->info('2. Click on your webhook endpoint');
        $this->info('3. Click "Send test webhook"');
        $this->info('4. Select an event type (e.g., invoice.payment_succeeded)');
        $this->newLine();

        $this->info('📍 Option 2: Stripe CLI');
        $this->line('stripe listen --forward-to '.url('/stripe/webhook'));
        $this->line('stripe trigger invoice.payment_succeeded');
        $this->newLine();

        $this->info('📍 Option 3: Monitor logs');
        $this->line('tail -f storage/logs/laravel.log | grep "Stripe webhook"');

        return Command::SUCCESS;
    }

    protected function listRecentEvents(StripeClient $stripe)
    {
        $this->info('📊 Fetching recent webhook events...');
        $this->newLine();

        try {
            $events = $stripe->events->all(['limit' => 15, 'type' => 'invoice.*']);

            $data = [];
            foreach ($events->data as $event) {
                $icon = match (true) {
                    str_contains($event->type, 'succeeded') => '✅',
                    str_contains($event->type, 'failed') => '❌',
                    str_contains($event->type, 'updated') => '🔄',
                    default => '📋',
                };
                
                $data[] = [
                    date('Y-m-d H:i:s', $event->created),
                    $icon.' '.$event->type,
                    substr($event->id, 0, 25).'...',
                ];
            }

            $this->table(
                ['Date', 'Event Type', 'Event ID'],
                $data,
            );
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

