<?php

/** Installation states; none of these perform a migration. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

enum InstallationStatus: string
{
    case Ready = 'ready';
    case Fresh = 'fresh';
    case Incomplete = 'incomplete';
    case UpgradeRequired = 'upgrade_required';
    case NewerSchema = 'newer_schema';
}
