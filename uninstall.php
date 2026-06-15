<?php
/**
 * Uninstall cleanup for Followup.
 *
 * Runs when the plugin is deleted from wp-admin. Removes the options Followup
 * creates. There is no per-post or per-user data to remove.
 *
 * @package Followup
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('followup_settings');
delete_option('followup_db_version');
