<?php

declare(strict_types=1);

namespace Followup\Service;

defined('ABSPATH') || exit;

/**
 * The customer-facing email templates a merchant may override, in the language
 * of the site.
 *
 * The subject and body of each follow-up used to be English sentences sitting
 * in config/defaults.php. A string in a config array is never wrapped in a
 * gettext call, so it never reaches the .pot and no translator can touch it: a
 * shop running in Polish still mailed its customers in English, however complete
 * the language pack was. Worse, the settings screen substituted that same
 * English back in whenever a field was left blank, freezing it into the option.
 *
 * The packaged default is now empty, meaning "use the string below". A merchant
 * who writes their own template still wins, and what they wrote is stored as
 * they wrote it.
 *
 * Keys are dotted paths into the nested settings array, because the templates
 * live under `emails.<type>`. {@see self::apply()} walks those paths explicitly;
 * it does not merely look at the top level.
 */
final class Texts
{
    /**
     * Dotted setting path => the translated default.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'emails.thank_you.subject' => __('Thanks for your order, {customer}!', 'plogins-followup'),
            'emails.thank_you.body'    => __("Hi {customer},\n\nThank you for shopping with {site}. We hope you love order {order}.\n\nIf you need anything at all, just reply to this email.\n\nWarm regards,\n{site}", 'plogins-followup'),
            'emails.review.subject'    => __('How did we do, {customer}?', 'plogins-followup'),
            'emails.review.body'       => __("Hi {customer},\n\nYou received order {order} a little while ago. Would you take a moment to leave a review? It helps us a lot and helps other shoppers too.\n\nThank you,\n{site}", 'plogins-followup'),
        ];
    }

    /**
     * Fill every empty template with its translated default.
     *
     * Applied on the way OUT, where the template is about to be rendered and
     * mailed, and never on the way in: writing the resolved text back to the
     * option would freeze one language into the database, which is the bug this
     * class exists to fix.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function apply(array $settings): array
    {
        foreach (self::defaults() as $path => $text) {
            if ('' === trim((string) self::read($settings, $path))) {
                self::write($settings, $path, $text);
            }
        }

        return $settings;
    }

    /**
     * Read a dotted path out of the nested settings array.
     *
     * @param array<string, mixed> $settings
     */
    private static function read(array $settings, string $path): string
    {
        $node = $settings;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return '';
            }

            $node = $node[ $segment ];
        }

        return is_scalar($node) ? (string) $node : '';
    }

    /**
     * Write a value at a dotted path, creating the intermediate arrays when a
     * stored option is missing a whole follow-up type.
     *
     * @param array<string, mixed> $settings
     */
    private static function write(array &$settings, string $path, string $value): void
    {
        $segments = explode('.', $path);
        $last     = (string) array_pop($segments);
        $node     = &$settings;

        foreach ($segments as $segment) {
            if (! isset($node[ $segment ]) || ! is_array($node[ $segment ])) {
                $node[ $segment ] = [];
            }

            $node = &$node[ $segment ];
        }

        $node[ $last ] = $value;
        unset($node);
    }
}
