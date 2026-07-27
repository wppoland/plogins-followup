<?php
/**
 * PRO upsell content, generated from the plogins.com registry by
 * scripts/gen-pro-upsell.mjs. The admin upsell renders this; curate the
 * feature list to fit this plugin's settings screen (do not invent features).
 *
 * @package plogins-followup-pro
 */

defined('ABSPATH') || exit;

return [
    'name'       => 'Followup Pro',
    'url'        => 'https://plogins.com/plogins-followup-pro/pricing/',
    'sellable'   => true,
    'price_from' => 29,
    'currency'   => 'EUR',
    'price_pln'  => 129,
    'lead'       => [
        'en' => 'Branded HTML, custom sequences, coupons, reporting, scheduling and open/click tracking are available today.',
        'pl' => 'Markowe HTML, własne sekwencje, kupony, raporty, harmonogram i śledzenie otwarć/kliknięć są już dostępne.',
    ],
    'features'   => [
        [
            'en' => ['title' => 'Branded HTML emails', 'desc' => 'Wrap Followup messages in an HTML template with a heading, intro text, footer and accent colour.'],
            'pl' => ['title' => 'Markowe e-maile HTML', 'desc' => 'Owijaj wiadomości Followup w szablon HTML z nagłówkiem, intro, stopką i kolorem akcentu.'],
        ],
        [
            'en' => ['title' => 'Custom post-purchase sequences', 'desc' => 'Build up to eight post-purchase email steps with their own status, delay, subject and body on WooCommerce → Followup Sequence.'],
            'pl' => ['title' => 'Własne sekwencje po zakupie', 'desc' => 'Zbuduj do ośmiu kroków e-mail z własnym statusem, opóźnieniem, tematem i treścią na WooCommerce → Followup Sequence.'],
        ],
        [
            'en' => ['title' => 'Coupon blocks', 'desc' => 'Create single-use WooCommerce coupons per order. Use the {coupon} placeholder or auto-append the offer to every email.'],
            'pl' => ['title' => 'Bloki kuponów', 'desc' => 'Twórz jednorazowe kupony WooCommerce per zamówienie. Użyj placeholdera {coupon} lub auto-dopisz ofertę w każdym e-mailu.'],
        ],
        [
            'en' => ['title' => 'Send reporting', 'desc' => 'Track how many follow-ups were sent per sequence step on WooCommerce → Followup Reports.'],
            'pl' => ['title' => 'Raporty wysyłek', 'desc' => 'Śledź liczbę wysłanych follow-upów per krok sekwencji na WooCommerce → Followup Reports.'],
        ],
        [
            'en' => ['title' => 'Send-time scheduling', 'desc' => 'Send at a chosen store-timezone hour or on chosen weekdays via WooCommerce → Followup Schedule.'],
            'pl' => ['title' => 'Harmonogram godzin wysyłki', 'desc' => 'Wysyłka o wybranej godzinie lub w wybrane dni tygodnia w strefie czasowej sklepu.'],
        ],
        [
            'en' => ['title' => 'Open and click reporting', 'desc' => 'Track aggregate opens and link clicks per sequence step on WooCommerce → Followup Reports.'],
            'pl' => ['title' => 'Raporty otwarć i kliknięć', 'desc' => 'Zliczaj otwarcia i kliknięcia per krok follow-up na WooCommerce → Followup Reports.'],
        ],
    ],
];
