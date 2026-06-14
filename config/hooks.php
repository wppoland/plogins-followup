<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Followup\Contract\HasHooks. Admin-only services are skipped gracefully when
 * absent from the container (outside wp-admin).
 *
 * @package Followup
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Followup\Admin\SettingsPage;
use Followup\Service\Scheduler;

defined('ABSPATH') || exit;

return [
    Scheduler::class,
    SettingsPage::class,
];
