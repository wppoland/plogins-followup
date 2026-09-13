<?php
/**
 * Behavioural check for the daily sender: run it against a fake order store and
 * assert who gets mailed.
 *
 * There is no WordPress here on purpose. The file stubs the handful of WP and
 * WooCommerce functions the sender touches, including a wc_get_orders that
 * really applies the arguments it is given (status, dates, meta), then loads the
 * production classes unchanged. That is what makes it evidence: drop the install
 * floor out of the query, or the pending claim out of the send path, and this
 * fails.
 *
 * Named `*-check.php` on purpose: that is the glob scripts/release/wporg-release.sh
 * walks before it will build a package, so this runs on the path a release
 * actually takes. Under the old name nothing ran it, and the install floor could
 * have been deleted with every automated check still green.
 *
 * Run: php tests/scheduler-check.php
 *
 * @package Followup
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('FOLLOWUP_DIR', dirname(__DIR__) . '/');
    define('FOLLOWUP_URL', 'https://example.test/');
    define('DAY_IN_SECONDS', 86400);
    define('HOUR_IN_SECONDS', 3600);

    /** @var array<string, mixed> $GLOBALS['test_options'] */
    $GLOBALS['test_options'] = [];
    /** @var array<int, WC_Order> $GLOBALS['test_orders'] */
    $GLOBALS['test_orders'] = [];
    /** @var array<int, array<string, mixed>> $GLOBALS['test_mail'] */
    $GLOBALS['test_mail'] = [];
    $GLOBALS['test_mail_fails'] = false;
    /** @var array<int, string> $GLOBALS['test_mail_fails_to'] */
    $GLOBALS['test_mail_fails_to'] = [];
    $GLOBALS['test_claim_at_send'] = [];
    /** @var array<int, int> $GLOBALS['test_veto'] */
    $GLOBALS['test_veto'] = [];

    function get_option(string $name, $default = false)
    {
        return array_key_exists($name, $GLOBALS['test_options']) ? $GLOBALS['test_options'][ $name ] : $default;
    }
    function add_option(string $name, $value, $deprecated = '', $autoload = true): bool
    {
        if (array_key_exists($name, $GLOBALS['test_options'])) {
            return false;
        }
        $GLOBALS['test_options'][ $name ] = $value;
        return true;
    }
    function update_option(string $name, $value, $autoload = null): bool
    {
        $GLOBALS['test_options'][ $name ] = $value;
        return true;
    }
    function delete_option(string $name): bool
    {
        $existed = array_key_exists($name, $GLOBALS['test_options']);
        unset($GLOBALS['test_options'][ $name ]);
        return $existed;
    }
    function sanitize_key(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key) ?? '');
    }
    function absint($value): int
    {
        return abs((int) $value);
    }
    function sanitize_text_field(string $value): string
    {
        return trim($value);
    }
    function sanitize_textarea_field(string $value): string
    {
        return trim($value);
    }
    function sanitize_email(string $value): string
    {
        return trim($value);
    }
    function is_email(string $value)
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
    function esc_url_raw(string $url): string
    {
        return $url;
    }
    function wp_specialchars_decode(string $value, $flags = null): string
    {
        return $value;
    }
    function get_bloginfo(string $what): string
    {
        return 'Test Shop';
    }
    function apply_filters(string $hook, $value, ...$args)
    {
        // Stands in for a PRO add-on vetoing a send, which is the only thing
        // that hooks this filter. Vetoed orders are named by id.
        if ('followup/should_send' === $hook && [] !== $GLOBALS['test_veto']) {
            $order = $args[0] ?? null;
            if ($order instanceof WC_Order && in_array($order->id, $GLOBALS['test_veto'], true)) {
                return false;
            }
        }
        return $value;
    }
    function do_action(string $hook, ...$args): void
    {
    }
    function add_action(string $hook, $cb, int $priority = 10, int $args = 1): void
    {
    }
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
    function wp_mail(string $to, string $subject, string $body, array $headers = []): bool
    {
        // A transport that refuses some addresses and takes others, which is
        // what a shop with a population of dead addresses actually looks like.
        if (in_array($to, $GLOBALS['test_mail_fails_to'], true)) {
            return false;
        }
        $GLOBALS['test_mail'][] = ['to' => $to, 'subject' => $subject];
        return ! $GLOBALS['test_mail_fails'];
    }

    /** Minimal stand-in for the parts of WC_Order the sender touches. */
    class WC_Order
    {
        /** @var array<string, string> */
        public array $meta = [];

        public function __construct(
            public int $id,
            public string $status,
            public int $created,
            public int $modified,
            public string $email = 'buyer@example.test',
        ) {
        }

        public function get_id(): int
        {
            return $this->id;
        }
        public function get_meta(string $key, bool $single = true): string
        {
            return $this->meta[ $key ] ?? '';
        }
        public function update_meta_data(string $key, $value): void
        {
            $this->meta[ $key ] = (string) $value;
        }
        public function delete_meta_data(string $key): void
        {
            unset($this->meta[ $key ]);
        }
        public function save_meta_data(): void
        {
        }
        public function get_billing_email(): string
        {
            return $this->email;
        }
        public function get_billing_first_name(): string
        {
            return 'Ada';
        }
        public function get_order_number(): string
        {
            return (string) $this->id;
        }
    }

    /**
     * wc_get_orders, honouring the arguments the sender passes. Anything the
     * sender stops passing stops filtering, which is the point.
     *
     * @param array<string, mixed> $args
     * @return array<int, WC_Order>
     */
    function wc_get_orders(array $args): array
    {
        $out = [];

        foreach ($GLOBALS['test_orders'] as $order) {
            if (isset($args['status']) && $order->status !== $args['status']) {
                continue;
            }
            if (isset($args['date_created']) && ! date_matches($args['date_created'], $order->created)) {
                continue;
            }
            if (isset($args['date_modified']) && ! date_matches($args['date_modified'], $order->modified)) {
                continue;
            }
            if (isset($args['meta_query']) && ! meta_matches($args['meta_query'], $order)) {
                continue;
            }
            $out[] = $order;
        }

        if (($args['orderby'] ?? '') === 'modified') {
            usort($out, static fn (WC_Order $a, WC_Order $b): int => $a->modified <=> $b->modified);
        }

        $offset = max(0, (int) ($args['offset'] ?? 0));
        if ($offset > 0) {
            $out = array_slice($out, $offset);
        }

        $limit = (int) ($args['limit'] ?? -1);

        return $limit >= 0 ? array_slice($out, 0, $limit) : $out;
    }

    /**
     * WooCommerce reads a numeric operand as an exact UTC timestamp and a date
     * string at day precision. Both are accepted here so the check can also be
     * run against a build that passes a string.
     */
    function date_matches(string $expr, int $timestamp): bool
    {
        if (! preg_match('/^(>=|<=|>|<)(.+)$/', $expr, $m)) {
            throw new RuntimeException("unsupported date arg: {$expr}");
        }

        if (is_numeric($m[2])) {
            $value = (int) $m[2];
        } else {
            $parsed = strtotime($m[2] . ' UTC');
            if (false === $parsed) {
                throw new RuntimeException("unsupported date arg: {$expr}");
            }
            $day   = (int) strtotime(gmdate('Y-m-d', $parsed) . ' UTC');
            $value = in_array($m[1], ['<=', '>'], true) ? $day + 86399 : $day;
        }

        return match ($m[1]) {
            '>=' => $timestamp >= $value,
            '<=' => $timestamp <= $value,
            '>'  => $timestamp > $value,
            default => $timestamp < $value,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $clauses
     */
    function meta_matches(array $clauses, WC_Order $order): bool
    {
        foreach ($clauses as $clause) {
            if (! is_array($clause)) {
                continue;
            }
            $key     = (string) ($clause['key'] ?? '');
            $compare = (string) ($clause['compare'] ?? '=');
            $current = $order->meta[ $key ] ?? null;

            if ('NOT EXISTS' === $compare) {
                if (null !== $current) {
                    return false;
                }
                continue;
            }
            if ($current !== (string) ($clause['value'] ?? '')) {
                return false;
            }
        }

        return true;
    }
}

namespace Followup {
    define('Followup\\VERSION', '0.0.0-test');
}

namespace {
    require_once FOLLOWUP_DIR . 'src/Contract/HasHooks.php';
    require_once FOLLOWUP_DIR . 'src/FollowupTypes.php';
    require_once FOLLOWUP_DIR . 'src/Settings.php';
    require_once FOLLOWUP_DIR . 'src/Service/Texts.php';
    require_once FOLLOWUP_DIR . 'src/Service/Mailer.php';
    require_once FOLLOWUP_DIR . 'src/Service/SequenceSteps.php';
    require_once FOLLOWUP_DIR . 'src/Service/Scheduler.php';

    $failures = 0;

    function check(string $what, bool $ok): void
    {
        global $failures;
        if ($ok) {
            echo "  ok   {$what}\n";
            return;
        }
        echo "  FAIL {$what}\n";
        ++$failures;
    }

    function reset_world(array $options = []): Followup\Service\Scheduler
    {
        $GLOBALS['test_options']       = $options;
        $GLOBALS['test_orders']        = [];
        $GLOBALS['test_mail']          = [];
        $GLOBALS['test_mail_fails']    = false;
        $GLOBALS['test_mail_fails_to'] = [];
        $GLOBALS['test_claim_at_send'] = [];
        $GLOBALS['test_veto']          = [];

        $settings = new Followup\Settings();

        return new Followup\Service\Scheduler(
            new Followup\Service\SequenceSteps($settings),
            new Followup\Service\Mailer($settings),
        );
    }

    $now  = time();
    $day  = 86400;
    $hour = 3600;
    $meta = '_followup_sent_thank_you';

    /** How many emails went to one address. */
    $mailedTo = static fn (string $address): int => count(array_filter(
        $GLOBALS['test_mail'],
        static fn (array $mail): bool => $address === $mail['to'],
    ));

    echo "install floor\n";
    $scheduler = reset_world(['followup_install_floor' => (string) $now]);
    $GLOBALS['test_orders'] = [
        new WC_Order(1, 'completed', $now - (900 * $day), $now - (900 * $day)),
        new WC_Order(2, 'completed', $now - (30 * $day), $now - (30 * $day)),
    ];
    $scheduler->run();
    check('an order placed before the install is never mailed', [] === $GLOBALS['test_mail']);
    check('and it is not claimed either', [] === $GLOBALS['test_orders'][0]->meta);

    echo "orders placed after the install\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $GLOBALS['test_orders'] = [new WC_Order(3, 'completed', $now - (8 * $day), $now - (8 * $day))];
    $scheduler->run();
    check('both enabled types send once', count($GLOBALS['test_mail']) === 2);
    $scheduler->run();
    check('and a second run sends nothing more', count($GLOBALS['test_mail']) === 2);
    check('the send is recorded as a timestamp, not a claim', (bool) strtotime($GLOBALS['test_orders'][0]->meta[ $meta ]));

    echo "a send that never returned\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $stuck     = new WC_Order(4, 'completed', $now - (8 * $day), $now - (8 * $day));
    // What a fatal error or max_execution_time inside wp_mail leaves behind.
    $stuck->meta[ $meta ] = 'pending';
    $GLOBALS['test_orders'] = [$stuck];
    $scheduler->run();
    $thankYou = array_values(array_filter(
        $GLOBALS['test_mail'],
        static fn (array $mail): bool => str_contains($mail['subject'], 'Thanks'),
    ));
    check('the crashed follow-up is retried', count($thankYou) === 1);
    check('and recorded as sent', (bool) strtotime($stuck->meta[ $meta ]));

    echo "a retry that crashed too\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $stuck     = new WC_Order(5, 'completed', $now - (8 * $day), $now - (8 * $day));
    // What the second attempt leaves behind when it dies the same way.
    $stuck->meta[ $meta ] = 'failed';
    $GLOBALS['test_orders'] = [$stuck];
    $scheduler->run();
    $thankYous = static fn (): int => count(array_filter(
        $GLOBALS['test_mail'],
        static fn (array $mail): bool => str_contains($mail['subject'], 'Thanks'),
    ));
    check('the customer is not mailed every day forever', $thankYous() === 0);
    check('and the claim is left exactly as it was', $stuck->meta[ $meta ] === 'failed');

    echo "a retry wp_mail refuses\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $stuck     = new WC_Order(51, 'completed', $now - (8 * $day), $now - (8 * $day));
    $stuck->meta[ $meta ] = 'pending';
    $GLOBALS['test_orders']     = [$stuck];
    $GLOBALS['test_mail_fails'] = true;
    $scheduler->run();
    // false is a definite non-send: the transport refused it, so there is
    // nothing to be idempotent about and tomorrow may try again. Marking it
    // spent here would bury a follow-up over one SMTP hiccup.
    check('a refused retry is rolled back, not buried', ! isset($stuck->meta[ $meta ]));
    $GLOBALS['test_mail_fails'] = false;
    $scheduler->run();
    check('so the next run sends it', (bool) strtotime($stuck->meta[ $meta ] ?? 'x'));

    echo "a first attempt wp_mail refuses\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $order     = new WC_Order(6, 'completed', $now - (8 * $day), $now - (8 * $day));
    $GLOBALS['test_orders']     = [$order];
    $GLOBALS['test_mail_fails'] = true;
    $scheduler->run();
    check('the claim is rolled back so tomorrow can retry', ! isset($order->meta[ $meta ]));

    echo "a site that has never run the sender\n";
    // Core fires the activation hook once, on the blog the click happened on,
    // so on a network activation every other site arrives here with no floor at
    // all. Its floor is this moment: any earlier value mails orders the shop
    // took before the plugin reached it.
    $scheduler = reset_world([]);
    $GLOBALS['test_orders'] = [
        new WC_Order(7, 'completed', $now - (400 * $day), $now - (400 * $day)),
        new WC_Order(8, 'completed', $now - (3 * $day), $now - (3 * $day)),
    ];
    $scheduler->run();
    $seeded = (int) ($GLOBALS['test_options']['followup_install_floor'] ?? 0);
    check('the floor is seeded at this moment', $seeded >= $now && $seeded <= $now + 5);
    check('no order placed before the plugin got here is mailed', [] === $GLOBALS['test_mail']);
    check('not even one inside the longest delay', [] === $GLOBALS['test_orders'][1]->meta);

    echo "re-activation after a gap\n";
    // Installed a year ago, switched off, switched on 12 hours ago. Nothing
    // went out while it was off, and the deactivation hook cleared the cron
    // event, so a floor pinned to the first install hands the first run a year
    // of orders. The thank-you waits one day, so its window after the gap is
    // the 24 hours of orders before the moment it was switched back on.
    $scheduler = reset_world([
        'followup_install_floor'  => (string) ($now - (400 * $day)),
        'followup_reactivated_at' => (string) ($now - (12 * $hour)),
    ]);
    $GLOBALS['test_orders'] = [
        new WC_Order(10, 'completed', $now - (300 * $day), $now - (300 * $day), 'year-old@example.test'),
        new WC_Order(11, 'completed', $now - (30 * $hour), $now - (30 * $hour), 'inside@example.test'),
        new WC_Order(12, 'completed', $now - (40 * $hour), $now - (40 * $hour), 'outside@example.test'),
    ];
    $scheduler->run();
    check('the year of orders that piled up is not mailed', $mailedTo('year-old@example.test') === 0);
    check('an order still inside the thank-you window is', $mailedTo('inside@example.test') === 1);
    check('one older than that window is not', $mailedTo('outside@example.test') === 0);
    check('the install floor is left where it was', $GLOBALS['test_options']['followup_install_floor'] === (string) ($now - (400 * $day)));
    check('and the activation moment is kept, because every step applies its own delay to it', $GLOBALS['test_options']['followup_reactivated_at'] === (string) ($now - (12 * $hour)));

    echo "the longest delay does not widen the shortest one's reach-back\n";
    // One window per step, not one window for the plugin. The review waits 7
    // days, and while that window was shared this order was thanked as well:
    // a customer who ordered a week ago got "thanks for your order" today. A
    // PRO step waiting 30 days made it a month of them.
    $scheduler = reset_world([
        'followup_install_floor'  => (string) ($now - (400 * $day)),
        'followup_reactivated_at' => (string) ($now - (12 * $hour)),
    ]);
    $GLOBALS['test_orders'] = [
        new WC_Order(14, 'completed', $now - (7 * $day) - (3 * $hour), $now - (7 * $day) - (3 * $hour), 'week@example.test'),
    ];
    $scheduler->run();
    check('the review goes out, because the order is inside the review window', $mailedTo('week@example.test') === 1);
    check('and it is the review, not a thank-you for a week-old order', str_contains($GLOBALS['test_mail'][0]['subject'] ?? '', 'How did we do'));

    echo "re-activation on a young install\n";
    // Switched off and on again two days after installing. No step may reach
    // back past the install, however long its delay.
    $scheduler = reset_world([
        'followup_install_floor'  => (string) ($now - (2 * $day)),
        'followup_reactivated_at' => (string) $now,
    ]);
    $GLOBALS['test_orders'] = [new WC_Order(15, 'completed', $now - (5 * $day), $now - (5 * $day))];
    $scheduler->run();
    check('the floor is not pulled backwards', (int) ($GLOBALS['test_options']['followup_install_floor'] ?? 0) === $now - (2 * $day));
    check('so an order from before the install stays unmailed', [] === $GLOBALS['test_orders'][0]->meta);

    echo "the order leaves the trigger status between claim and retry\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $refunded  = new WC_Order(13, 'refunded', $now - (8 * $day), $now - (8 * $day));
    $refunded->meta[ $meta ] = 'pending';
    $GLOBALS['test_orders'] = [$refunded];
    $scheduler->run();
    check('a refunded order is not thanked because a crash claimed it', [] === $GLOBALS['test_mail']);
    check('and its claim is left alone', $refunded->meta[ $meta ] === 'pending');

    echo "a claim the should_send filter vetoes\n";
    // A veto is "not now". Left standing, a vetoed claim is re-read on every
    // run out of a set that nothing drains.
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (100 * $day))]);
    $vetoed    = [];
    for ($i = 0; $i < 200; $i++) {
        $order = new WC_Order(2000 + $i, 'completed', $now - (8 * $day), $now - (8 * $day), 'held@example.test');
        $order->meta[ $meta ]           = 'pending';
        $order->meta['_followup_sent_review'] = 'pending';
        $vetoed[]                       = $order->id;
        $GLOBALS['test_orders'][]       = $order;
    }
    // Oldest, so nothing can claim it was simply sorted out of the batch.
    $fresh = new WC_Order(2999, 'completed', $now - (9 * $day), $now - (9 * $day), 'fresh@example.test');
    $GLOBALS['test_orders'][] = $fresh;
    $GLOBALS['test_veto']     = $vetoed;
    $scheduler->run();
    check('a vetoed claim is released, not left standing', ! isset($GLOBALS['test_orders'][0]->meta[ $meta ]));
    check('and nothing is mailed to a vetoed order', $mailedTo('held@example.test') === 0);
    check('an order that is due is reached in the same run, not blocked by them', $mailedTo('fresh@example.test') === 2);
    $scheduler->run();
    check('and the run after that does not mail it again', $mailedTo('fresh@example.test') === 2);

    echo "a vetoed population that was never claimed\n";
    // The same wall of held-back orders, with no claim on any of them, so
    // nothing is released and nothing leaves the query. The order that is due
    // sits behind all 200 of them. While the ceiling counted orders read, this
    // sent nothing, on this run and on every run after it.
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (100 * $day))]);
    $vetoed    = [];
    for ($i = 0; $i < 200; $i++) {
        $order = new WC_Order(3000 + $i, 'completed', $now - (9 * $day), $now - (9 * $day), 'held@example.test');
        $vetoed[]                 = $order->id;
        $GLOBALS['test_orders'][] = $order;
    }
    $GLOBALS['test_orders'][] = new WC_Order(3999, 'completed', $now - (8 * $day), $now - (8 * $day), 'due@example.test');
    $GLOBALS['test_veto']     = $vetoed;
    $scheduler->run();
    check('the held-back orders are still not mailed', $mailedTo('held@example.test') === 0);
    check('and the one behind them is, on the first run', $mailedTo('due@example.test') === 2);
    $scheduler->run();
    check('a second run does not mail it twice', $mailedTo('due@example.test') === 2);

    echo "a population wp_mail refuses\n";
    // Dead addresses, not a veto: wp_mail says no, the claim is rolled back and
    // the order stays exactly where it was in the queue. Same starvation, a
    // shop can reach it without any add-on installed.
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (100 * $day))]);
    for ($i = 0; $i < 200; $i++) {
        $GLOBALS['test_orders'][] = new WC_Order(4000 + $i, 'completed', $now - (9 * $day), $now - (9 * $day), 'dead@example.test');
    }
    $GLOBALS['test_orders'][]      = new WC_Order(4999, 'completed', $now - (8 * $day), $now - (8 * $day), 'live@example.test');
    $GLOBALS['test_mail_fails_to'] = ['dead@example.test'];
    $scheduler->run();
    check('an undeliverable order does not spend the ceiling', $mailedTo('live@example.test') === 2);
    check('and nothing was recorded as sent for it', ! isset($GLOBALS['test_orders'][0]->meta[ $meta ]));

    echo "more held-back orders than one run may read\n";
    // 1200 of them, past the 1000-order read ceiling, so one run cannot get to
    // the end. Where it stopped is remembered, and the next run starts there
    // rather than reading the same first 1000 again for ever.
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (100 * $day))]);
    $vetoed    = [];
    for ($i = 0; $i < 1200; $i++) {
        $order = new WC_Order(5000 + $i, 'completed', $now - (9 * $day), $now - (9 * $day), 'held@example.test');
        $vetoed[]                 = $order->id;
        $GLOBALS['test_orders'][] = $order;
    }
    $GLOBALS['test_orders'][] = new WC_Order(6999, 'completed', $now - (8 * $day), $now - (8 * $day), 'patient@example.test');
    $GLOBALS['test_veto']     = $vetoed;
    $scheduler->run();
    check('the first run cannot reach the order behind them', $mailedTo('patient@example.test') === 0);
    check('and it records how far it read', ($GLOBALS['test_options']['followup_scan_offset']['thank_you'] ?? 0) === 1000);
    $scheduler->run();
    check('the next run carries on from there and sends it', $mailedTo('patient@example.test') === 2);
    check('and clears the mark once it has read to the end', ! isset($GLOBALS['test_options']['followup_scan_offset']['thank_you']));

    echo "the delay is honoured to the second\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    // Placed 23 hours ago, so the one-day thank-you is not due yet.
    $GLOBALS['test_orders'] = [new WC_Order(9, 'completed', $now - 82800, $now - 82800)];
    $scheduler->run();
    check('an order younger than the delay waits', [] === $GLOBALS['test_mail']);

    echo "the batch ceiling\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (100 * $day))]);
    $orders    = [];
    for ($i = 0; $i < 250; $i++) {
        $orders[] = new WC_Order(1000 + $i, 'completed', $now - (9 * $day), $now - (9 * $day));
    }
    $GLOBALS['test_orders'] = $orders;
    $scheduler->run();
    check('at most 200 emails per type per run', count($GLOBALS['test_mail']) === 400);

    if ($failures > 0) {
        echo "\nscheduler-check: FAIL ({$failures})\n";
        exit(1);
    }

    echo "\nscheduler-check: OK\n";
}
