<?php

namespace App\Services\Messaging;

use App\Models\Student;

/**
 * One phone a bulk SMS goes to: a family (through one of their children)
 * or a member of staff.
 */
final readonly class MessageRecipient
{
    public function __construct(
        public string $phone,
        public string $name,
        public ?Student $student = null,
    ) {}
}
