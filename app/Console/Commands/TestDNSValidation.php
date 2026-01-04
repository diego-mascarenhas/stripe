<?php

namespace App\Console\Commands;

use App\Services\DNS\DNSLookupService;
use Illuminate\Console\Command;

class TestDNSValidation extends Command
{
    protected $signature = 'dns:test {domain} {--service=whm : Service to validate against}';

    protected $description = 'Test DNS validation for a domain';

    public function handle(): int
    {
        $domain = $this->argument('domain');
        $service = $this->option('service');

        $this->info("Testing DNS validation for: {$domain}");
        $this->info("Service: {$service}");
        $this->newLine();

        $dnsService = app(DNSLookupService::class);
        
        try {
            $validation = $dnsService->validateServiceConfiguration($domain, $service);

            // Display configuration
            $this->displayConfiguration($service);
            $this->newLine();

            // Display results
            $this->displayValidationResults($validation);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function displayConfiguration(string $service): void
    {
        $config = config("dns.services.{$service}");
        
        if (!$config) {
            $this->warn("⚠️  Service '{$service}' not found in configuration");
            return;
        }

        $this->info("📋 Expected Configuration for '{$service}':");
        $this->line("   Nameservers: " . implode(', ', $config['nameservers'] ?? []));
        $this->line("   Valid IPs: " . implode(', ', $config['valid_ips'] ?? []));
        $this->line("   SPF Include: " . ($config['spf_include'] ?? 'N/A'));
    }

    private function displayValidationResults(array $validation): void
    {
        $this->info("🔍 Validation Results:");
        $this->newLine();

        // Nameservers
        $nsStatus = $validation['has_own_ns'] ? '✅' : '❌';
        $this->line("{$nsStatus} Nameservers: " . 
            ($validation['has_own_ns'] ? 'Valid' : 'Invalid'));
        $this->line("   Current: " . implode(', ', $validation['current_nameservers']));
        $this->line("   Expected: " . implode(', ', $validation['expected_nameservers']));
        $this->newLine();

        // Domain IP
        $domainIPStatus = $validation['domain_points_to_service'] ? '✅' : '❌';
        $this->line("{$domainIPStatus} Domain IP: " . 
            ($validation['domain_points_to_service'] ? 'Points to service' : 'Does not point to service'));
        $this->line("   Current: " . implode(', ', $validation['domain_ips']));
        $this->line("   Matching: " . ($validation['matching_domain_ip'] ?? 'None'));
        $this->line("   Expected: " . implode(', ', $validation['expected_ips']));
        $this->newLine();

        // Mail Server IP
        $mailIPStatus = $validation['mail_points_to_service'] ? '✅' : '❌';
        $this->line("{$mailIPStatus} Mail Server IP: " . 
            ($validation['mail_points_to_service'] ? 'Points to service' : 'Does not point to service'));
        
        if (!empty($validation['mx_records'])) {
            $this->line("   MX Records:");
            foreach ($validation['mx_records'] as $mx) {
                $this->line("      - {$mx['exchange']} (priority: {$mx['priority']})");
            }
        }
        
        $this->line("   Matching IP: " . ($validation['matching_mail_ip'] ?? 'None'));
        $this->newLine();

        // SPF
        $spfStatus = $validation['has_spf_include'] ? '✅' : '❌';
        $this->line("{$spfStatus} SPF Record: " . 
            ($validation['has_spf_include'] ? 'Valid include found' : 'Include not found'));
        $this->line("   Current: " . ($validation['spf_record'] ?? 'Not configured'));
        $this->line("   Expected: " . ($validation['expected_spf_include'] ?? 'N/A'));
    }
}

