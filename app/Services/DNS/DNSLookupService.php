<?php

namespace App\Services\DNS;

use Illuminate\Support\Facades\Log;

class DNSLookupService
{
    /**
     * Get all DNS records for a domain
     */
    public function getAllRecords(string $domain): array
    {
        try {
            Log::info('Looking up DNS records', ['domain' => $domain]);

            $domain = $this->normalizeDomain($domain);

            return [
                'A' => $this->getARecords($domain),
                'MX' => $this->getMXRecords($domain),
                'NS' => $this->getNSRecords($domain),
                'TXT' => $this->getTXTRecords($domain),
                'CNAME' => $this->getCNAMERecords($domain),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to lookup DNS records', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'A' => [],
                'MX' => [],
                'NS' => [],
                'TXT' => [],
                'CNAME' => [],
            ];
        }
    }

    /**
     * Get A records (IPv4 addresses)
     */
    public function getARecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_A);

        if ($records === false) {
            Log::warning('Failed to get A records', ['domain' => $domain]);
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'address' => $record['ip'] ?? '',
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'A',
        ])->all();
    }

    /**
     * Get MX records (Mail Exchange)
     */
    public function getMXRecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_MX);

        if ($records === false) {
            Log::warning('Failed to get MX records', ['domain' => $domain]);
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'exchange' => $record['target'] ?? '',
            'priority' => $record['pri'] ?? 0,
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'MX',
        ])->sortBy('priority')->values()->all();
    }

    /**
     * Get NS records (Name Servers)
     */
    public function getNSRecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_NS);

        if ($records === false) {
            Log::warning('Failed to get NS records', ['domain' => $domain]);
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'nameserver' => $record['target'] ?? '',
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'NS',
        ])->all();
    }

    /**
     * Get authoritative nameservers from WHOIS (most reliable during propagation)
     */
    public function getAuthoritativeNameserversViaWhois(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        
        // Try using whois command
        $output = [];
        $returnCode = 0;
        @exec("whois {$domain} 2>&1 | grep -i '^nserver' | awk '{print $2}'", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $nameservers = collect($output)
                ->map(fn($ns) => rtrim(strtolower(trim($ns)), '.'))
                ->filter(fn($ns) => !empty($ns) && strpos($ns, '.') !== false) // Filter out IPs and empty values
                ->unique()
                ->values()
                ->all();
            
            if (!empty($nameservers)) {
                Log::info('Nameservers retrieved from WHOIS', [
                    'domain' => $domain,
                    'nameservers' => $nameservers,
                ]);
                return $nameservers;
            }
        }
        
        Log::warning('whois command not available or no nameservers found', [
            'domain' => $domain,
            'return_code' => $returnCode,
        ]);
        
        return [];
    }

    /**
     * Get authoritative nameservers for domain using dig command
     */
    public function getAuthoritativeNameserversViaDig(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        
        // Try using dig command if available
        $output = [];
        $returnCode = 0;
        @exec("dig +short NS {$domain} 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            return collect($output)
                ->map(fn($ns) => rtrim(strtolower(trim($ns)), '.'))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        
        Log::warning('dig command not available or failed', [
            'domain' => $domain,
            'return_code' => $returnCode,
        ]);
        
        return [];
    }

    /**
     * Get authoritative nameservers for domain (checks delegation)
     * Priority: WHOIS > dig > dns_get_record
     */
    public function getAuthoritativeNameservers(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        
        // Try WHOIS first (most reliable, shows configured delegation)
        $whoisResults = $this->getAuthoritativeNameserversViaWhois($domain);
        if (!empty($whoisResults)) {
            return $whoisResults;
        }
        
        // Try dig (reliable for propagated DNS)
        $digResults = $this->getAuthoritativeNameserversViaDig($domain);
        if (!empty($digResults)) {
            return $digResults;
        }
        
        // Fallback to dns_get_record
        $records = @dns_get_record($domain, DNS_NS);

        if ($records === false) {
            return [];
        }

        return collect($records)
            ->pluck('target')
            ->map(fn($ns) => rtrim(strtolower($ns), '.'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get TXT records
     */
    public function getTXTRecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_TXT);

        if ($records === false) {
            Log::warning('Failed to get TXT records', ['domain' => $domain]);
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'text' => $record['txt'] ?? '',
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'TXT',
        ])->all();
    }

    /**
     * Get CNAME records
     */
    public function getCNAMERecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_CNAME);

        if ($records === false) {
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'target' => $record['target'] ?? '',
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'CNAME',
        ])->all();
    }

    /**
     * Get AAAA records (IPv6 addresses)
     */
    public function getAAAARecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return collect($records)->map(fn ($record) => [
            'name' => $record['host'] ?? $domain,
            'address' => $record['ipv6'] ?? '',
            'ttl' => $record['ttl'] ?? 0,
            'type' => 'AAAA',
        ])->all();
    }

    /**
     * Normalize domain (remove protocol, www, trailing slashes, etc.)
     */
    private function normalizeDomain(string $domain): string
    {
        // Remove protocol
        $domain = preg_replace('#^https?://#i', '', $domain);
        
        // Remove port
        $domain = preg_replace('#:\d+$#', '', $domain);
        
        // Remove path
        $domain = explode('/', $domain)[0];
        
        // Remove trailing dot
        $domain = rtrim($domain, '.');

        return strtolower(trim($domain));
    }

    /**
     * Check if domain exists and has DNS records
     */
    public function domainExists(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        
        // Try to get any DNS record type
        $records = @dns_get_record($domain, DNS_ANY);
        
        return $records !== false && count($records) > 0;
    }

    /**
     * Get domain's IP address (first A record)
     */
    public function getIPAddress(string $domain): ?string
    {
        $aRecords = $this->getARecords($domain);
        
        return $aRecords[0]['address'] ?? null;
    }

    /**
     * Validate domain configuration for a service
     */
    public function validateServiceConfiguration(string $domain, string $service = null): array
    {
        $domain = $this->normalizeDomain($domain);
        
        // Use default service if not specified
        $service = $service ?? config('dns.default_service', 'whm');
        
        // Get service configuration
        $serviceConfig = config("dns.services.{$service}");
        
        if (!$serviceConfig) {
            Log::warning('DNS service configuration not found', ['service' => $service]);
            return $this->getEmptyValidationResult();
        }

        $validNameservers = $serviceConfig['nameservers'] ?? [];
        $validIPs = $serviceConfig['valid_ips'] ?? [];
        $spfInclude = $serviceConfig['spf_include'] ?? null;

        // 1. Check if using service's nameservers (use authoritative query from WHOIS)
        $currentNS = $this->getAuthoritativeNameservers($domain);
        
        Log::info('NS Records retrieved', [
            'domain' => $domain,
            'current_ns' => $currentNS,
            'valid_ns' => $validNameservers,
        ]);
        
        // Check if ANY of the valid nameservers is present
        $hasOwnNS = !empty(array_intersect($currentNS, $validNameservers));

        // 2. Check if domain A record points to service's IPs
        $aRecords = $this->getARecords($domain);
        $domainIPs = collect($aRecords)->pluck('address')->all();
        $domainPointsToService = !empty(array_intersect($domainIPs, $validIPs));
        $matchingDomainIP = collect($domainIPs)->first(fn($ip) => in_array($ip, $validIPs));

        // 3. Check if MX records point to service's IPs
        $mxRecords = $this->getMXRecords($domain);
        $mailPointsToService = false;
        $matchingMailIP = null;

        foreach ($mxRecords as $mx) {
            $mailHost = rtrim($mx['exchange'], '.');
            $mailIPs = @dns_get_record($mailHost, DNS_A);
            
            if ($mailIPs !== false) {
                foreach ($mailIPs as $mailIP) {
                    if (in_array($mailIP['ip'] ?? '', $validIPs)) {
                        $mailPointsToService = true;
                        $matchingMailIP = $mailIP['ip'];
                        break 2;
                    }
                }
            }
        }

        // 4. Check if SPF record includes the service's SPF domain
        $txtRecords = $this->getTXTRecords($domain);
        $spfRecord = null;
        $hasSPFInclude = false;

        foreach ($txtRecords as $txt) {
            $text = $txt['text'] ?? '';
            if (str_starts_with($text, 'v=spf1')) {
                $spfRecord = $text;
                if ($spfInclude && str_contains($text, $spfInclude)) {
                    $hasSPFInclude = true;
                }
                break;
            }
        }

        return [
            'has_own_ns' => $hasOwnNS,
            'current_nameservers' => $currentNS,
            'expected_nameservers' => $validNameservers,
            
            'domain_points_to_service' => $domainPointsToService,
            'domain_ips' => $domainIPs,
            'matching_domain_ip' => $matchingDomainIP,
            'expected_ips' => $validIPs,
            
            'mail_points_to_service' => $mailPointsToService,
            'matching_mail_ip' => $matchingMailIP,
            'mx_records' => $mxRecords,
            
            'has_spf_include' => $hasSPFInclude,
            'spf_record' => $spfRecord,
            'expected_spf_include' => $spfInclude ? "include:{$spfInclude}" : null,
        ];
    }

    /**
     * Get empty validation result
     */
    private function getEmptyValidationResult(): array
    {
        return [
            'has_own_ns' => false,
            'current_nameservers' => [],
            'expected_nameservers' => [],
            
            'domain_points_to_service' => false,
            'domain_ips' => [],
            'matching_domain_ip' => null,
            'expected_ips' => [],
            
            'mail_points_to_service' => false,
            'matching_mail_ip' => null,
            'mx_records' => [],
            
            'has_spf_include' => false,
            'spf_record' => null,
            'expected_spf_include' => null,
        ];
    }

    /**
     * Validate domain configuration for Revision Alpha (backward compatibility)
     */
    public function validateRevisionAlphaConfiguration(string $domain): array
    {
        return $this->validateServiceConfiguration($domain, 'default');
    }
}

