<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Adding this student or login would take the school past its plan.
 * Shown to the user as a notification (see AppServiceProvider).
 */
class PlanLimitReached extends RuntimeException
{
}
