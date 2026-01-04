<?php

namespace App\Services\WHM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WHMServerManager
{
    private ?string $username;
    private ?string $password;
    private int $port;
    private bool $verifySSL;
    private int $timeout;

    public function __construct()
    {
        $this->username = config('whm.username');
        $this->password = config('whm.password');
        $this->port = config('whm.api_port', 2087);
        $this->verifySSL = config('whm.verify_ssl', true);
        $this->timeout = config('whm.timeout', 30);
    }

    /**
     * Build the API URL for a specific server
     */
    private function buildUrl(string $server, string $function): string
    {
        return sprintf(
            'https://%s:%d/json-api/%s',
            $server,
            $this->port,
            $function
        );
    }

    /**
     * Make an API call to WHM
     */
    private function makeRequest(string $server, string $function, array $params = []): array
    {
        $url = $this->buildUrl($server, $function);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout($this->timeout)
            ->withOptions([
                'verify' => $this->verifySSL,
            ])
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "WHM API request failed: {$response->status()} - {$response->body()}"
            );
        }

        $data = $response->json();

        if (isset($data['metadata']['result']) && $data['metadata']['result'] !== 1) {
            throw new \RuntimeException(
                "WHM API returned error: " . ($data['metadata']['reason'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Suspend a cPanel account
     */
    public function suspendAccount(string $server, string $username, string $reason = 'Payment overdue'): bool
    {
        try {
            Log::info('Suspending WHM account', [
                'server' => $server,
                'user' => $username,
                'reason' => $reason,
            ]);

            $response = $this->makeRequest($server, 'suspendacct', [
                'user' => $username,
                'reason' => $reason,
            ]);

            Log::info('WHM account suspended successfully', [
                'server' => $server,
                'user' => $username,
                'response' => $response,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to suspend WHM account', [
                'server' => $server,
                'user' => $username,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Unsuspend a cPanel account
     */
    public function unsuspendAccount(string $server, string $username): bool
    {
        try {
            Log::info('Unsuspending WHM account', [
                'server' => $server,
                'user' => $username,
            ]);

            $response = $this->makeRequest($server, 'unsuspendacct', [
                'user' => $username,
            ]);

            Log::info('WHM account unsuspended successfully', [
                'server' => $server,
                'user' => $username,
                'response' => $response,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to unsuspend WHM account', [
                'server' => $server,
                'user' => $username,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get account information
     */
    public function getAccountInfo(string $server, string $username): ?array
    {
        try {
            Log::info('Requesting WHM account info', compact('server', 'username'));

            $response = $this->makeRequest($server, 'accountsummary', [
                'user' => $username,
            ]);

            Log::info('WHM account info response', [
                'server' => $server,
                'user' => $username,
                'response' => $response,
            ]);

            // WHM returns data directly in 'acct', not wrapped in 'data'
            $accountData = $response['acct'][0] ?? null;

            if (!$accountData) {
                Log::warning('No account data found in WHM response', [
                    'server' => $server,
                    'user' => $username,
                    'response_structure' => array_keys($response),
                ]);
            } else {
                Log::info('Account data extracted successfully', [
                    'server' => $server,
                    'user' => $username,
                    'plan' => $accountData['plan'] ?? null,
                    'suspended' => $accountData['suspended'] ?? null,
                ]);
            }

            return $accountData;
        } catch (\Throwable $e) {
            Log::error('Failed to get WHM account info', [
                'server' => $server,
                'user' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get account package/plan
     */
    public function getAccountPackage(string $server, string $username): ?string
    {
        $accountInfo = $this->getAccountInfo($server, $username);

        if (!$accountInfo) {
            return null;
        }

        return $accountInfo['plan'] ?? null;
    }

    /**
     * Get account status (active/suspended)
     */
    public function getAccountStatus(string $server, string $username): ?string
    {
        $accountInfo = $this->getAccountInfo($server, $username);

        if (!$accountInfo) {
            return null;
        }

        // WHM returns 1 for suspended, 0 for active
        $suspended = $accountInfo['suspended'] ?? 0;
        return $suspended == 1 ? 'suspended' : 'active';
    }

    /**
     * Get DNS zones for a domain
     */
    public function getDNSZones(string $server, string $domain): ?array
    {
        try {
            Log::info('Getting DNS zones', compact('server', 'domain'));

            $response = $this->makeRequest($server, 'dumpzone', [
                'domain' => $domain,
            ]);

            Log::info('DNS zones response', [
                'server' => $server,
                'domain' => $domain,
                'response_keys' => array_keys($response),
                'response' => $response,
            ]);

            // WHM dumpzone returns zones in 'zone' key, not 'data'
            $zones = $response['zone'][0]['record'] ?? $response['data'] ?? [];

            if (empty($zones)) {
                Log::warning('No DNS zones found', [
                    'server' => $server,
                    'domain' => $domain,
                ]);
                return null;
            }

            Log::info('DNS zones extracted', [
                'server' => $server,
                'domain' => $domain,
                'zone_count' => count($zones),
            ]);

            return $this->parseDNSZones($zones);
        } catch (\Throwable $e) {
            Log::error('Failed to get DNS zones', [
                'server' => $server,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse DNS zones from WHM response
     */
    private function parseDNSZones(array $zones): array
    {
        $parsed = [
            'A' => [],
            'MX' => [],
            'NS' => [],
        ];

        foreach ($zones as $zone) {
            $type = $zone['type'] ?? null;

            if ($type === 'A') {
                $parsed['A'][] = [
                    'name' => $zone['name'] ?? '',
                    'address' => $zone['address'] ?? '',
                    'ttl' => $zone['ttl'] ?? '',
                ];
            } elseif ($type === 'MX') {
                $parsed['MX'][] = [
                    'name' => $zone['name'] ?? '',
                    'exchange' => $zone['exchange'] ?? '',
                    'priority' => $zone['preference'] ?? '',
                    'ttl' => $zone['ttl'] ?? '',
                ];
            } elseif ($type === 'NS') {
                $parsed['NS'][] = [
                    'name' => $zone['name'] ?? '',
                    'nsdname' => $zone['nsdname'] ?? '',
                    'ttl' => $zone['ttl'] ?? '',
                ];
            }
        }

        return $parsed;
    }

    /**
     * Create a new cPanel account
     */
    public function createAccount(array $data): bool
    {
        $server = $data['server'] ?? config('whm.default_server');

        try {
            Log::info('Creating WHM account', [
                'server' => $server,
                'username' => $data['username'],
                'domain' => $data['domain'],
            ]);

            $params = [
                'username' => $data['username'],
                'domain' => $data['domain'],
                'plan' => $data['plan'] ?? 'default',
                'contactemail' => $data['email'] ?? '',
                'password' => $data['password'] ?? $this->generatePassword(),
            ];

            $response = $this->makeRequest($server, 'createacct', $params);

            Log::info('WHM account created successfully', [
                'server' => $server,
                'username' => $data['username'],
                'response' => $response,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to create WHM account', [
                'server' => $server,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a random password
     */
    private function generatePassword(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * List all accounts on a server
     */
    public function listAccounts(string $server): array
    {
        try {
            $response = $this->makeRequest($server, 'listaccts');

            // WHM returns accounts directly in 'acct', not wrapped in 'data'
            return $response['acct'] ?? [];
        } catch (\Throwable $e) {
            Log::error('Failed to list WHM accounts', [
                'server' => $server,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

