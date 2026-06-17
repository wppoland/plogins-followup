<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Keep services thin; product logic lives in the src/ classes.
 *
 * @package Followup
 */

declare(strict_types=1);

use Followup\Admin\SettingsPage;
use Followup\Container;
use Followup\Migrator;
use Followup\Service\Mailer;
use Followup\Service\Scheduler;
use Followup\Service\SequenceSteps;
use Followup\Settings;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    $c->singleton(Settings::class, static fn (): Settings => new Settings());

    $c->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
        $c->get(Settings::class),
    ));

    $c->singleton(SequenceSteps::class, static fn (Container $c): SequenceSteps => new SequenceSteps(
        $c->get(Settings::class),
    ));

    $c->singleton(Scheduler::class, static fn (Container $c): Scheduler => new Scheduler(
        $c->get(SequenceSteps::class),
        $c->get(Mailer::class),
    ));

    if (is_admin()) {
        $c->singleton(SettingsPage::class, static fn (Container $c): SettingsPage => new SettingsPage(
            $c->get(Settings::class),
        ));
    }
};
