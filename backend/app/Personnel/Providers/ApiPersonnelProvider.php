<?php

namespace App\Personnel\Providers;

use App\Personnel\PersonnelProviderInterface;
use App\Personnel\PersonnelRecordData;
use App\Personnel\PersonnelSourceUnavailableException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Verifies Service Numbers against the real NIS Personnel REST API.
 *
 * Base URL + API key come from config/personnel.php (env). No secrets in code.
 */
class ApiPersonnelProvider implements PersonnelProviderInterface
{
    /**
     * @param  array{base_url: ?string, key: ?string, timeout: int}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {
    }

    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData
    {
        $baseUrl = $this->config['base_url'] ?? null;

        if (empty($baseUrl)) {
            throw new PersonnelSourceUnavailableException('Personnel API base URL is not configured.');
        }

        try {
            $response = $this->http
                ->baseUrl(rtrim($baseUrl, '/'))
                ->withToken((string) ($this->config['key'] ?? ''))
                ->timeout($this->config['timeout'] ?? 10)
                ->acceptJson()
                ->get('/personnel/'.rawurlencode($serviceNumber));
        } catch (Throwable $e) {
            throw new PersonnelSourceUnavailableException('Personnel API unreachable.', 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new PersonnelSourceUnavailableException('Personnel API returned '.$response->status());
        }

        return $this->mapPayload($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapPayload(?array $payload): ?PersonnelRecordData
    {
        if (! $payload || empty($payload['service_number'])) {
            return null;
        }

        return new PersonnelRecordData(
            serviceNumber: (string) $payload['service_number'],
            surname: (string) ($payload['surname'] ?? ''),
            firstName: (string) ($payload['first_name'] ?? ''),
            otherName: $payload['other_name'] ?? null,
            rank: $payload['rank'] ?? null,
            directorate: $payload['directorate'] ?? null,
            department: $payload['department'] ?? null,
            zone: $payload['zone'] ?? null,
            command: $payload['command'] ?? null,
            formation: $payload['formation'] ?? null,
            unit: $payload['unit'] ?? null,
            posting: $payload['posting'] ?? null,
            officialEmail: $payload['official_email'] ?? null,
            status: (string) ($payload['status'] ?? 'active'),
            photoUrl: $payload['photo_url'] ?? null,
        );
    }
}
