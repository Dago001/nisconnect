<?php

namespace App\Personnel\Providers;

use App\Personnel\PersonnelProviderInterface;
use App\Personnel\PersonnelRecordData;
use RuntimeException;

/**
 * Development-only personnel provider with sample records.
 *
 * Production mode must NEVER use demo records: this provider throws when
 * APP_ENV=production so a misconfiguration fails loudly instead of silently
 * authorising fake officers.
 */
class DemoPersonnelProvider implements PersonnelProviderInterface
{
    /**
     * @param  string  $environment  The current application environment.
     */
    public function __construct(
        private readonly string $environment = 'local',
        private readonly bool $acceptAny = false,
    ) {
        if ($this->environment === 'production') {
            throw new RuntimeException(
                'DemoPersonnelProvider must not be used in production. '
                .'Set PERSONNEL_PROVIDER=api or database.'
            );
        }
    }

    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData
    {
        $records = $this->records();

        if (isset($records[$serviceNumber])) {
            return $records[$serviceNumber];
        }

        return $this->acceptAny ? $this->placeholder($serviceNumber) : null;
    }

    /**
     * Synthetic active record for test deployments (PERSONNEL_DEMO_ACCEPT_ANY).
     */
    private function placeholder(string $serviceNumber): PersonnelRecordData
    {
        return new PersonnelRecordData(
            serviceNumber: $serviceNumber,
            surname: $serviceNumber,
            firstName: 'Officer',
            rank: 'Test Officer',
            directorate: 'Test Directorate',
            department: 'Testing',
            zone: 'Zone A',
            command: 'Service Headquarters',
            formation: 'Headquarters',
            unit: 'Test Unit',
            posting: 'Service Headquarters, Abuja',
            status: 'active',
        );
    }

    /**
     * Sample records for development and automated tests.
     * Includes a leading-zero example ("001234") to prove zeroes are preserved.
     *
     * @return array<string, PersonnelRecordData>
     */
    private function records(): array
    {
        $seed = [
            new PersonnelRecordData(
                serviceNumber: '123456',
                surname: 'Doe',
                firstName: 'John',
                otherName: 'A.',
                rank: 'Assistant Superintendent of Immigration',
                directorate: 'ICT/Cyber Security',
                department: 'Software & Data',
                zone: 'Zone A',
                command: 'Service Headquarters',
                formation: 'Headquarters',
                unit: 'Applications Unit',
                posting: 'Service Headquarters, Abuja',
                officialEmail: 'john.doe@immigration.gov.ng',
                status: 'active',
                photoUrl: null,
            ),
            new PersonnelRecordData(
                serviceNumber: '001234',
                surname: 'Bello',
                firstName: 'Amina',
                otherName: null,
                rank: 'Deputy Comptroller of Immigration',
                directorate: 'Border Management',
                department: 'Operations',
                zone: 'Zone F',
                command: 'Seme Border Command',
                formation: 'Seme Border Post',
                unit: 'Passenger Control',
                posting: 'Seme Border, Lagos',
                officialEmail: 'amina.bello@immigration.gov.ng',
                status: 'active',
                photoUrl: null,
            ),
            new PersonnelRecordData(
                serviceNumber: '654321',
                surname: 'Okafor',
                firstName: 'Chidi',
                otherName: 'E.',
                rank: 'Superintendent of Immigration',
                directorate: 'ICT/Cyber Security',
                department: 'Infrastructure',
                zone: 'Zone A',
                command: 'Service Headquarters',
                formation: 'Headquarters',
                unit: 'Network Unit',
                posting: 'Service Headquarters, Abuja',
                officialEmail: 'chidi.okafor@immigration.gov.ng',
                status: 'active',
                photoUrl: null,
            ),
            // A retired officer: found, but not authorised for a new account.
            new PersonnelRecordData(
                serviceNumber: '999999',
                surname: 'Retired',
                firstName: 'Sunday',
                rank: 'Comptroller of Immigration',
                directorate: 'Administration',
                department: 'Welfare',
                status: 'retired',
            ),
        ];

        $indexed = [];
        foreach ($seed as $record) {
            $indexed[$record->serviceNumber] = $record;
        }

        return $indexed;
    }
}
