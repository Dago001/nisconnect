<?php

namespace App\Services\Auth;

use App\Models\PersonnelRecord;
use App\Personnel\PersonnelProviderInterface;
use App\Personnel\PersonnelRecordData;
use Illuminate\Support\Carbon;

/**
 * Verifies a numeric Service Number against the configured NIS personnel source
 * and mirrors the authorised record locally for account creation.
 */
class PersonnelVerificationService
{
    public function __construct(private readonly PersonnelProviderInterface $provider)
    {
    }

    /**
     * Look up a record. May throw PersonnelSourceUnavailableException upstream.
     */
    public function lookup(string $serviceNumber): ?PersonnelRecordData
    {
        return $this->provider->findByServiceNumber($serviceNumber);
    }

    /**
     * Create or update the local mirror of an authorised personnel record.
     */
    public function mirror(PersonnelRecordData $data): PersonnelRecord
    {
        return PersonnelRecord::updateOrCreate(
            ['service_number' => $data->serviceNumber],
            [
                'surname' => $data->surname,
                'first_name' => $data->firstName,
                'other_name' => $data->otherName,
                'rank' => $data->rank,
                'directorate' => $data->directorate,
                'department' => $data->department,
                'zone' => $data->zone,
                'command' => $data->command,
                'formation' => $data->formation,
                'unit' => $data->unit,
                'posting' => $data->posting,
                'official_email' => $data->officialEmail,
                'status' => $data->status,
                'source' => config('personnel.provider', 'demo'),
                'source_synced_at' => Carbon::now(),
            ],
        );
    }
}
