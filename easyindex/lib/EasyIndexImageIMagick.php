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
 * Class EasyIndexImageImagick
 */
class EasyIndexImageIMagick extends EasyIndexImage {
    /** @var  Imagick */
    private $im;

    /**
     * @param Imagick $im An image to clone
     */
    function __construct($im = null) {
        $this->im = $im instanceof Imagick ? clone $im : $im;
    }

    /**
     * @param string $blob
     * @return boolean
     */
    public function createFromBlob($blob) {
        try {
            $this->im = new Imagick();
            $this->im->readImageBlob($blob);
        } catch (ImagickException $e) {
            return false;
        }
        return true;
    }

    /**
     * @return int
     */
    public function getImageWidth() {
        return $this->im->getImageWidth();
    }

    /**
     * @return int
     */
    public function getImageHeight() {
        return $this->im->getImageHeight();
    }

    /**
     * @return EasyIndexImage
     */
    public function cloneImage() {
        return new EasyIndexImageIMagick($this->im);
    }

    /**
     * Strip off EXIF etc to reduce size
     *
     * @return bool
     */
    public function stripImage() {
        return $this->im->stripImage();
    }

    /**
     * Scales the size of the image to the given dimensions. The other parameter will be calculated if 0 is passed as either param.
     *
     * @param int $rows
     * @param int $cols
     * @param bool $bestfit
     * @return bool
     */

    public function scaleImage($rows, $cols, $bestfit = false) {
        return $this->im->scaleImage($rows, $cols, $bestfit);
    }

    /**
     * Crops image cropped to the parameters given
     *
     * @param int $width Width of the cropped image
     * @param int $height Height of the cropped image
     * @param int $x X coordinate of top left in the orignial image
     * @param int $y Y coordinate of top left in the orignial image
     * @return bool
     */
    public function cropImage($width, $height, $x, $y) {
        return $this->im->cropImage($width, $height, $x, $y);
    }

    /**
     * @param $width
     * @param $height
     * @param $bgColor
     *
     * @return mixed
     */
    public function createImage($width, $height, $bgColor) {

    }

    /**
     * @param $filename
     *
     * @return mixed
     */
    public function writeImage($filename) {
        try {
            return $this->im->writeImage($filename);
        } catch (ImagickException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return string
     */
    public  function getLastError() {
        return $this->lastError;
    }
}

