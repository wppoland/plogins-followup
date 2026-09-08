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

    /**
     * The English templates that shipped as packaged defaults up to 1.0.9 and
     * were written into the option whenever the settings screen was saved.
     *
     * Keys are dotted paths into the nested settings array.
     *
     * @var array<string, string>
     */
    private const LEGACY_TEXTS = [
        'emails.thank_you.subject' => 'Thanks for your order, {customer}!',
        'emails.thank_you.body'    => "Hi {customer},\n\nThank you for shopping with {site}. We hope you love order {order}.\n\nIf you need anything at all, just reply to this email.\n\nWarm regards,\n{site}",
        'emails.review.subject'    => 'How did we do, {customer}?',
        'emails.review.body'       => "Hi {customer},\n\nYou received order {order} a little while ago. Would you take a moment to leave a review? It helps us a lot and helps other shoppers too.\n\nThank you,\n{site}",
    ];

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, VERSION, '>=')) {
            return;
        }

        $this->clearUntranslatableTexts();

        update_option(self::OPTION, VERSION, false);
    }

    /**
     * Clear a stored subject or body that is byte for byte the old English
     * default.
     *
     * Those values could never be translated: the settings screen substituted
     * the packaged English whenever a field was left blank, so the option held
     * an English sentence no language pack could reach, and the shop mailed its
     * customers in English however complete the translation was. Empty means
     * "use the translated default", which is what the screen now shows in grey.
     *
     * Only an exact match is cleared, so a merchant's own wording, including a
     * hand translation of the English one, survives untouched.
     */
    private function clearUntranslatableTexts(): void
    {
        $stored = get_option(Settings::OPTION, null);
        if (! is_array($stored) || ! isset($stored['emails']) || ! is_array($stored['emails'])) {
            return;
        }

        $changed = false;

        foreach (self::LEGACY_TEXTS as $path => $legacy) {
            [, $type, $field] = explode('.', $path);

            if (! isset($stored['emails'][ $type ]) || ! is_array($stored['emails'][ $type ])) {
                continue;
            }

            $value = $stored['emails'][ $type ][ $field ] ?? null;

            if (is_string($value) && $value === $legacy) {
                $stored['emails'][ $type ][ $field ] = '';
                $changed                             = true;
            }
        }

        if ($changed) {
            // null keeps the option's existing autoload flag. Passing false here
            // would quietly move the settings out of the autoloaded set on every
            // shop that took this update, which is not a change a text sweep gets
            // to make.
            update_option(Settings::OPTION, $stored, null);
        }
    }
}
