<?php

declare(strict_types=1);

namespace Followup;

defined('ABSPATH') || exit;

/**
 * Central read access to the plugin's stored settings, merged over the packaged
 * defaults. Both the admin page and the cron sender read through this so they
 * never disagree about defaults or shape.
 */
final class Settings
{
    public const OPTION = 'followup_settings';

    /**
     * Stored settings deep-merged over packaged defaults. Per-email arrays are
     * merged key-by-key so a partially-saved type still has every field.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $defaults = self::defaults();

        $merged = array_merge($defaults, $stored);

        $emails = [];
        foreach ($defaults['emails'] as $key => $defaultEmail) {
            $savedEmail   = isset($stored['emails'][ $key ]) && is_array($stored['emails'][ $key ])
                ? $stored['emails'][ $key ]
                : [];
            $emails[ $key ] = array_merge($defaultEmail, $savedEmail);
        }
        $merged['emails'] = $emails;

        return $merged;
    }

    /**
     * Configuration for a single follow-up type, or null when unknown.
     *
     * @return array<string, mixed>|null
     */
    public function email(string $type): ?array
    {
        $all = $this->all();

        return isset($all['emails'][ $type ]) && is_array($all['emails'][ $type ])
            ? $all['emails'][ $type ]
            : null;
    }

    /**
     * Packaged defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require FOLLOWUP_DIR . 'config/defaults.php';

        return $defaults;
    }
}
