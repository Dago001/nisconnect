<?php

namespace App\Services\Push;

/** Immutable push payload. */
final class PushMessage
{
    /** @param array<string, string> $data */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}
}
