<?php
/**
 * Constants PHPStan needs to analyse the plugin without bootstrapping WordPress.
 * These are defined for real in followup.php at runtime.
 *
 * @package Followup
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('FOLLOWUP_DIR')) {
        define('FOLLOWUP_DIR', '/tmp/followup/');
    }
    if (! defined('FOLLOWUP_URL')) {
        define('FOLLOWUP_URL', 'https://example.test/wp-content/plugins/followup/');
    }
}

namespace Followup {
    if (! defined('Followup\\VERSION')) {
        define('Followup\\VERSION', '0.1.0');
    }
}
