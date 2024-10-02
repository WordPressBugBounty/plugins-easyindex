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
 * Class EasyIndexTranslate
 *
 * Perfom string translations
 */
class EasyIndexTranslate {

    static public $language = '';
    static public $textdomain = '';

    /**
     * Store the language and text domain if we're using something different to en-US (which is what the majority of users will have)
     *
     * @param $language
     * @param $textdomain
     */
    function __construct($language, $textdomain) {
        if ($language != 'en-US') {
            self::$language = $language;
        }
        self::$textdomain = $textdomain;
    }


    /**
     * Translate a string
     * @param string $string
     * @return string
     */
    static function translate($string) {
        if (self::$language == '') {
            return $string;
        }

        return $string;
    }
}
