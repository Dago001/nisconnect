<?php

namespace App\Personnel\Providers;

use App\Models\PersonnelRecord;
use App\Personnel\PersonnelProviderInterface;
use App\Personnel\PersonnelRecordData;
use App\Personnel\PersonnelSourceUnavailableException;
use Throwable;

/**
 * Verifies Service Numbers against the officers onboarded in NISconnect itself
 * (the `personnel_records` table, managed at /admin/personnel).
 *
 * This is the provider for a deployment whose authorised roster is maintained
 * in the admin portal rather than read from a separate NIS system: only an
 * officer an administrator has already onboarded can create an account, and
 * every other Service Number is refused.
 *
 * It differs from DatabasePersonnelProvider, which reads an external,
 * read-only NIS personnel database over its own connection.
 */
class RosterPersonnelProvider implements PersonnelProviderInterface
{
    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData
    {
        try {
            $record = PersonnelRecord::where('service_number', $serviceNumber)->first();
        } catch (Throwable $e) {
            // Never let a database blip read as "this officer is not authorised";
            // the caller turns this into a retryable error instead.
            throw new PersonnelSourceUnavailableException('Personnel roster unreachable.', 0, $e);
        }

        if (! $record) {
            return null;
        }

        // A record that exists but is retired or suspended is found-but-not-
        // authorised: the real status is carried through so the caller's
        // isAuthorised() refuses registration, rather than being flattened
        // to "active" here.
        return new PersonnelRecordData(
            serviceNumber: $record->service_number,
            surname: (string) $record->surname,
            firstName: (string) $record->first_name,
            otherName: $record->other_name,
            rank: $record->rank,
            directorate: $record->directorate,
            department: $record->department,
            zone: $record->zone,
            command: $record->command,
            formation: $record->formation,
            unit: $record->unit,
            posting: $record->posting,
            officialEmail: $record->official_email,
            status: (string) ($record->status ?: 'active'),
            photoUrl: $record->photo_path,
        );
    }
}
