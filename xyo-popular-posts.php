<?php

/*
  Plugin Name: Popular Posts by Xyo
  Plugin URI: https://xyo.dev/
  Description: Flexible plugin to show most popular posts based on hits and IP
  Version: 2.0.0
  Author: Patrick Petit (Xyo)
  Author URI: https://xyo.dev/
  License: GPL2

  Copyright 2011 Patrick Petit (Webeine) (email: hello@webeine.com)
  Inspired by Matt Geri plugin

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

use Xyo\MostRead\Api;
use Xyo\MostRead\Core;
use Xyo\MostRead\Rollup;
use Xyo\MostRead\Schema;

define('XYO_MOSTREAD_PATH', dirname(__FILE__) . '/');

/**
 * Minimal PSR-4 autoloader: Xyo\MostRead\ => src/. No Composer/vendor needed —
 * the plugin ships no external dependencies, so a ~10-line loader keeps it fully
 * self-contained with zero build step.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Xyo\\MostRead\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $file = XYO_MOSTREAD_PATH . 'src/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Lifecycle: build the schema/cron on activation, stop the jobs on deactivation,
// and self-heal on every load (the table pre-dates any activation hook).
register_activation_hook(__FILE__, [Schema::class, 'activate']);
register_deactivation_hook(__FILE__, [Schema::class, 'deactivate']);
add_action('init', [Schema::class, 'maybeUpgrade']);

// Scheduled jobs.
add_action(Schema::CRON_ROLLUP, [Rollup::class, 'run']);
add_action(Schema::CRON_PRUNE, [Rollup::class, 'prune']);

// Tracking beacon (emitted in <head>, recorded via admin-ajax).
add_action('wp_head', [Core::class, 'javascript']);
add_action('wp_ajax_track_post', [Core::class, 'track']);
add_action('wp_ajax_nopriv_track_post', [Core::class, 'track']);
if (is_admin()) {
    add_action('init', [Core::class, 'admin']);
}

// Widget + REST API.
add_action('widgets_init', [Core::class, 'widget']);
add_action('rest_api_init', function () {
    new Api();
});
