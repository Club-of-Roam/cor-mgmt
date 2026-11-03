<?php

/** Read-only legacy schema recognition; schema transformations belong to block 3. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Domain\InstallationStatus;
use wpdb;

final class SchemaInspector
{
    public const LEGACY_SCHEMA = '2.8';
    public const TABLES = ['auto_responses','invitations','races','routes','stages','sponsors','teams','teammates','xchange'];

    public function __construct(private readonly wpdb $database)
    {
    }

    public function status(): InstallationStatus
    {

        $version = get_option('h3_mgmt_db_version', false);
        if (is_string($version) && version_compare($version, self::LEGACY_SCHEMA, '>')) {
            return InstallationStatus::NewerSchema;
        }
        $existing = $this->database->get_col($this->database->prepare('SHOW TABLES LIKE %s', $this->database->esc_like($this->database->prefix . 'h3_mgmt_') . '%'));
        if ($this->database->last_error !== '') {
            return InstallationStatus::Incomplete;
        }
        if (!$existing && $version === false) {
            return InstallationStatus::Fresh;
        }
        $expected = array_map(fn(string $suffix): string => $this->database->prefix . 'h3_mgmt_' . $suffix, self::TABLES);
        if (array_diff($expected, $existing) || !is_string($version)) {
            return InstallationStatus::Incomplete;
        }
        return version_compare($version, self::LEGACY_SCHEMA, '==') ? InstallationStatus::Ready : InstallationStatus::UpgradeRequired;
    }
}
