<?php

namespace App\Personnel\Providers;

use App\Personnel\PersonnelProviderInterface;
use App\Personnel\PersonnelRecordData;
use App\Personnel\PersonnelSourceUnavailableException;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Verifies Service Numbers against a read-only NIS personnel database
 * connection. The connection name + table come from config/personnel.php.
 */
class DatabasePersonnelProvider implements PersonnelProviderInterface
{
    /**
     * @param  array{connection: string, table: string}  $config
     */
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly array $config,
    ) {
    }

    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData
    {
        try {
            $row = $this->db->connection($this->config['connection'])
                ->table($this->config['table'])
                ->where('service_number', $serviceNumber)
                ->first();
        } catch (Throwable $e) {
            throw new PersonnelSourceUnavailableException('Personnel database unreachable.', 0, $e);
        }

        if (! $row) {
            return null;
        }

        return new PersonnelRecordData(
            serviceNumber: (string) $row->service_number,
            surname: (string) ($row->surname ?? ''),
            firstName: (string) ($row->first_name ?? ''),
            otherName: $row->other_name ?? null,
            rank: $row->rank ?? null,
            directorate: $row->directorate ?? null,
            department: $row->department ?? null,
            zone: $row->zone ?? null,
            command: $row->command ?? null,
            formation: $row->formation ?? null,
            unit: $row->unit ?? null,
            posting: $row->posting ?? null,
            officialEmail: $row->official_email ?? null,
            status: (string) ($row->status ?? 'active'),
            photoUrl: $row->photo_url ?? null,
        );
    }
}
