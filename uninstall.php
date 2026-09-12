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
// The install floor: the timestamp the sender refuses to reach back past.
delete_option('followup_install_floor');

// The PRO banner's dismissal is stored per user, so it belongs to the
// plugin rather than to the site content. User meta is global, not
// per-site, which is why this uses delete_metadata's \$delete_all rather
// than a loop over the users of one blog.
delete_metadata('user', 0, 'followup_pro_banner_dismissed', '', true);
