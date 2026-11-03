<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Migration;

/** Stable error codes only: database messages and row values must not reach reports. */
final class MigrationException extends \RuntimeException
{
}
