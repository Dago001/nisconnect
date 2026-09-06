<?php

namespace App\Http\Resources;

use App\Personnel\PersonnelRecordData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes an authorised personnel record for the client, exposing only the
 * NIS-approved fields configured in config/personnel.php.
 *
 * @property PersonnelRecordData $resource
 */
class PersonnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allowed = (array) config('personnel.fields', []);

        return $this->resource->toClientArray($allowed);
    }
}
