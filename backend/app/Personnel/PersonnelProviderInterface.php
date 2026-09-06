<?php

namespace App\Personnel;

/**
 * Contract for an authorised NIS personnel source.
 *
 * Implementations must NEVER be called from the mobile client directly — only
 * the NISconnect backend resolves and uses a provider.
 */
interface PersonnelProviderInterface
{
    /**
     * Find an authorised personnel record by its numeric Service Number.
     *
     * @param  string  $serviceNumber  Digits only (leading zeroes significant).
     * @return PersonnelRecordData|null Null when no matching record exists.
     *
     * @throws PersonnelSourceUnavailableException When the source cannot be reached.
     */
    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData;
}
