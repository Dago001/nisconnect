<?php

namespace App\Personnel;

use RuntimeException;

/**
 * Thrown when the personnel source cannot be reached or errors internally.
 * Surfaced to the client only as a generic "temporarily unavailable" message.
 */
class PersonnelSourceUnavailableException extends RuntimeException
{
}
