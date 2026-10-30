<?php

/**
 * Deleting the plugin on a network must clean every site, not only the one
 * the uninstall runs in. The user meta is global and is deleted once.
 *
 * Run: php tests/uninstall-multisite-check.php
 */

declare(strict_types=1);

define('WP_UNINSTALL_PLUGIN', 'followup/sekvo.php');

$blog    = 1;
$deleted = [];

function is_multisite(): bool { return true; }
function get_sites(array $args): array { return [1, 2, 3]; }
function switch_to_blog(int $id): void { $GLOBALS['blog'] = $id; }
function restore_current_blog(): void { $GLOBALS['blog'] = 1; }
function delete_option(string $name): void { $GLOBALS['deleted'][] = $GLOBALS['blog'] . ':' . $name; }
function delete_transient(string $name): void { $GLOBALS['deleted'][] = $GLOBALS['blog'] . ':transient:' . $name; }
function delete_metadata(string $type, int $id, string $key, string $value = '', bool $all = false): void { $GLOBALS['deleted'][] = 'user:' . $key; }

require __DIR__ . '/../uninstall.php';

$expected = ['user:followup_pro_banner_dismissed'];
foreach ([1, 2, 3] as $site) {
    array_push($expected, "{$site}:followup_settings", "{$site}:followup_db_version", "{$site}:followup_install_floor", "{$site}:followup_reactivated_at", "{$site}:followup_scan_offset");
}
sort($expected);
sort($deleted);

if ($deleted !== $expected) {
    echo 'FAIL: uninstall deleted ' . implode(', ', $deleted) . "\n";
    exit(1);
}

echo "OK: uninstall cleans every site of the network\n";
