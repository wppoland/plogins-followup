<?php

declare(strict_types=1);

namespace Followup;

use Followup\Service\Texts;

defined('ABSPATH') || exit;

/**
 * Central read access to the plugin's stored settings, merged over the packaged
 * defaults. Both the admin page and the cron sender read through this so they
 * never disagree about defaults or shape.
 *
 * Two ways in, on purpose: {@see self::all()} resolves the customer-facing
 * templates through {@see Texts} and is what the sender reads, while
 * {@see self::raw()} returns exactly what is stored and is what the settings
 * screen edits. Editing the resolved text would save one language into the
 * option and shut every other language out.
 */
final class Settings
{
    public const OPTION = 'followup_settings';

    /**
     * Stored settings deep-merged over packaged defaults, with any empty subject
     * or body resolved to its translated default. This is the rendering path.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Texts::apply($this->raw());
    }

    /**
     * Stored settings deep-merged over packaged defaults, exactly as stored. An
     * empty template stays empty. Per-email arrays are merged key-by-key so a
     * partially-saved type still has every field.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
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
     * Configuration for a single follow-up type, or null when unknown. Resolved,
     * so the caller always has a template to render.
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
