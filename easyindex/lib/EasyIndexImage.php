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

abstract class EasyIndexImage {

    static private $useGD;

    /** @var  string The most recent error message */
    protected $lastError = '';

    /**
     * @return EasyIndexImage
     */
    static function getNewInstance() {
        if (!isset(self::$useGD)) {
            if (extension_loaded('imagick') && class_exists('Imagick', false)) {
                self::$useGD = false;
            } else {
                self::$useGD = true;
            }
        }
        return self::$useGD ? new EasyIndexImageGD() : new EasyIndexImageIMagick();
    }

    /**
     * @param string $blob
     * @return boolean
     */
    abstract public function createFromBlob($blob);

    abstract public function getImageWidth();

    abstract public function getImageHeight();

    /**
     * @return EasyIndexImage
     */

    abstract public function cloneImage();

    abstract public function stripImage();

    /**
     * @param      $rows
     * @param      $cols
     * @param bool $bestfit
     *
     * @return mixed
     */
    abstract public function scaleImage($rows, $cols, $bestfit = false);

    /**
     * @param $width
     * @param $height
     * @param $x
     * @param $y
     *
     * @return mixed
     */
    abstract public function cropImage($width, $height, $x, $y);

    /**
     * @param $filename
     *
     * @return mixed
     */
    abstract public function writeImage($filename);

    /**
     * @param $width
     * @param $height
     * @param $bgColor
     *
     * @return mixed
     */
    abstract public function createImage($width, $height, $bgColor);

    /**
     * @return string
     */
    abstract public function getLastError();

}

