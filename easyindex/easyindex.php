<?php
/*
Plugin Name: EasyIndex
Plugin URI: https://easyindexplugin.com/
Description: WordPress indexes made easy.
Author: Jayce53
Version: 1.1.1704
Author URI: https://easyindexplugin.com
License: GPLv2 or later
*/

/*
Copyright (c) 2010-2019 Box Hill LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/



if (!function_exists('add_action')) {
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit();
}

/**
 * We really don't want go through potentially CPU intensive processing for obvious 404 errors
 * $wp_query isn't set at this point so we can't just do is_404()
 */
$ext = pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_EXTENSION);
if (in_array($ext, array('jpg', 'png', 'gif', 'wmv', 'mov', 'mp4', 'css', 'js'))) {
    return;
}


if (!class_exists('EasyIndex', false)) {

    if (phpversion() < '5') {
        if (!function_exists('EasyIndexPHP5')) {
            function EasyIndexPHP5() {
                wp_die("This plugin requires PHP 5+.  Your server is running PHP" . phpversion() . '<br /><a href="/wp-admin/plugins.php">Go back</a>');
            }
        }
        register_activation_hook(__FILE__, "EasyIndexPHP5");
        return;
    }

    /**
     * Autoload EasyIndex classes
     *
     * @param $class
     */
    function EasyIndexAutoload($class) {
        if (strpos($class, 'EasyIndex') === 0 && strpos($class, 'EasyIndexPlus') === false && strpos($class, 'EasyIndexBeta') === false) {
            /** @noinspection PhpIncludeInspection */
            @include(dirname(__FILE__) . "/lib/$class.php");
        }
    }

    spl_autoload_register("EasyIndexAutoload");

    /**
     * Pass version so gulp doesn't have to re-generate the much larger class file on every build
     */

    $EasyIndex = new EasyIndex();

    /**
     * A little weirdness to handle WP's inability to get the plugin basename correct if wp-content/plugins is a symlink
     * Only required because our own test servers symlink the plugins directory
     */

    $EasyIndexFile = basename(dirname(__FILE__)) . '/' . basename(__FILE__);
    register_activation_hook($EasyIndexFile, array($EasyIndex, "pluginActivated"));
    register_deactivation_hook($EasyIndexFile, array($EasyIndex, "pluginDeactivated"));
    unset($EasyIndexFile);
}

