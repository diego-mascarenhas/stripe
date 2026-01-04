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
}

