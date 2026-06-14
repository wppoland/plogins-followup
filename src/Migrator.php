<?php

declare(strict_types=1);

namespace Followup;

defined('ABSPATH') || exit;

/**
 * Idempotent schema/version migrations, run on every boot. Compares a stored
 * option against VERSION and applies forward steps as needed.
 */
final class Migrator
{
    private const OPTION = 'followup_db_version';

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, VERSION, '>=')) {
            return;
        }

        // Example: create tables / seed defaults here.
        // $this->createWaitlistTable();

        update_option(self::OPTION, VERSION, false);
    }
}
