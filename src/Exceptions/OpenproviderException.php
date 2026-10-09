<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exceptions;

use RuntimeException;

/**
 * Every exception this package throws extends this one, so a single
 * `catch (OpenproviderException $e)` covers them all.
 */
class OpenproviderException extends RuntimeException {}
