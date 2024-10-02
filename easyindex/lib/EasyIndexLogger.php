<?php
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

/**
 * Logging module
 *
 * Relies on EasyLogger being installed and active - does nothing if it's not
 */

/**
 * Class EasyLogger
 */
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
class EasyIndexLogger {

    /**
     * @param $logFile
     * @return EasyLoggerLog|EasyIndexDummyLogger
     */
    static function getLog($logFile) {
        $plugins = get_option('active_plugins', array());
        if (in_array("easylogger/easylogger.php", $plugins)) {
            /** @noinspection PhpUndefinedClassInspection */
            return EasyLogger::getLog($logFile);
        }
        return EasyIndexDummyLogger::getinstance();
    }


}


/**
 * Class EasyLoggerLog
 */
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
class EasyIndexDummyLogger {
    static private $instance;

    /**
     * @return EasyIndexDummyLogger
     */
    static function getinstance() {
        if (!self::$instance) {
            self::$instance = new EasyIndexDummyLogger();
        }
        return self::$instance;
    }

    /**
     * @param $msg
     */
    function comment($msg) {
    }

    /**
     * @param $level
     */
    function disable($level) {
    }

    /**
     * @param $level
     */
    function enable($level) {
    }

    /**
     * @param $msg
     */
    function debug($msg) {
    }

    /**
     * @param $msg
     */
    function info($msg) {
    }

    /**
     * @param $msg
     */
    function warn($msg) {
    }

    /**
     * @param $msg
     */
    function error($msg) {
    }

    /**
     * @param $msg
     */
    function fatal($msg) {
    }
}

