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
 * Run: php tests/scheduler-test.php
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
    $GLOBALS['test_claim_at_send'] = [];

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
        $GLOBALS['test_claim_at_send'] = [];

        $settings = new Followup\Settings();

        return new Followup\Service\Scheduler(
            new Followup\Service\SequenceSteps($settings),
            new Followup\Service\Mailer($settings),
        );
    }

    $now  = time();
    $day  = 86400;
    $meta = '_followup_sent_thank_you';

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

    echo "a retry that crashes again\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $stuck     = new WC_Order(5, 'completed', $now - (8 * $day), $now - (8 * $day));
    $stuck->meta[ $meta ] = 'pending';
    $GLOBALS['test_orders']     = [$stuck];
    $GLOBALS['test_mail_fails'] = true;
    $scheduler->run();
    check('the second attempt is the last one', $stuck->meta[ $meta ] === 'failed');
    $thankYous = static fn (): int => count(array_filter(
        $GLOBALS['test_mail'],
        static fn (array $mail): bool => str_contains($mail['subject'], 'Thanks'),
    ));
    $before = $thankYous();
    $scheduler->run();
    check('so the customer is not mailed every day forever', $thankYous() === $before);

    echo "a first attempt wp_mail refuses\n";
    $scheduler = reset_world(['followup_install_floor' => (string) ($now - (10 * $day))]);
    $order     = new WC_Order(6, 'completed', $now - (8 * $day), $now - (8 * $day));
    $GLOBALS['test_orders']     = [$order];
    $GLOBALS['test_mail_fails'] = true;
    $scheduler->run();
    check('the claim is rolled back so tomorrow can retry', ! isset($order->meta[ $meta ]));

    echo "an install that predates the floor option\n";
    $scheduler = reset_world([]);
    $GLOBALS['test_orders'] = [
        new WC_Order(7, 'completed', $now - (400 * $day), $now - (400 * $day)),
        new WC_Order(8, 'completed', $now - (3 * $day), $now - (3 * $day)),
    ];
    $scheduler->run();
    check('the floor is seeded at the longest delay', (int) ($GLOBALS['test_options']['followup_install_floor'] ?? 0) === $now - (7 * $day));
    check('the backlog stays unmailed', count($GLOBALS['test_mail']) === 1);
    check('and only the order still inside the window is mailed', $GLOBALS['test_orders'][1]->meta[ $meta ] !== '');

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
    check('at most 200 orders per type per run', count($GLOBALS['test_mail']) === 400);

    if ($failures > 0) {
        echo "\nscheduler-test: FAIL ({$failures})\n";
        exit(1);
    }

    echo "\nscheduler-test: OK\n";
}
