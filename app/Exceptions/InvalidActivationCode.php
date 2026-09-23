<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A school typed a code that does not exist for it, or has already been
 * used, revoked, or expired.
 */
class InvalidActivationCode extends RuntimeException {}
