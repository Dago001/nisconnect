<?php

namespace App\Personnel;

/**
 * Immutable DTO for an authorised NIS personnel record.
 *
 * Carries only NIS-approved fields. The service number is always a digit-only
 * string so leading zeroes (e.g. "001234") are preserved end to end.
 */
final class PersonnelRecordData
{
    public function __construct(
        public readonly string $serviceNumber,
        public readonly string $surname,
        public readonly string $firstName,
        public readonly ?string $otherName = null,
        public readonly ?string $rank = null,
        public readonly ?string $directorate = null,
        public readonly ?string $department = null,
        public readonly ?string $zone = null,
        public readonly ?string $command = null,
        public readonly ?string $formation = null,
        public readonly ?string $unit = null,
        public readonly ?string $posting = null,
        public readonly ?string $officialEmail = null,
        public readonly string $status = 'active',
        public readonly ?string $photoUrl = null,
    ) {}

    /**
     * Rebuild a DTO from an array (e.g. a cached verification session).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArrayLike(array $data): self
    {
        return new self(
            serviceNumber: (string) $data['service_number'],
            surname: (string) ($data['surname'] ?? ''),
            firstName: (string) ($data['first_name'] ?? ''),
            otherName: $data['other_name'] ?? null,
            rank: $data['rank'] ?? null,
            directorate: $data['directorate'] ?? null,
            department: $data['department'] ?? null,
            zone: $data['zone'] ?? null,
            command: $data['command'] ?? null,
            formation: $data['formation'] ?? null,
            unit: $data['unit'] ?? null,
            posting: $data['posting'] ?? null,
            officialEmail: $data['official_email'] ?? null,
            status: (string) ($data['status'] ?? 'active'),
            photoUrl: $data['photo_url'] ?? null,
        );
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->firstName,
            $this->otherName,
            $this->surname,
        ])));
    }

    /**
     * Is this officer currently authorised to hold a NISconnect account?
     */
    public function isAuthorised(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_number' => $this->serviceNumber,
            'surname' => $this->surname,
            'first_name' => $this->firstName,
            'other_name' => $this->otherName,
            'rank' => $this->rank,
            'directorate' => $this->directorate,
            'department' => $this->department,
            'zone' => $this->zone,
            'command' => $this->command,
            'formation' => $this->formation,
            'unit' => $this->unit,
            'posting' => $this->posting,
            'official_email' => $this->officialEmail,
            'status' => $this->status,
            'photo_url' => $this->photoUrl,
        ];
    }

    /**
     * Return only the NIS-approved fields configured for client display.
     *
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    public function toClientArray(array $allowed): array
    {
        return array_intersect_key($this->toArray(), array_flip($allowed));
    }
}
