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
 * Class EasyIndexImageGD
 */
class EasyIndexImageGD extends EasyIndexImage {

    private $im;

    /**
     * @param resource $im Image
     */
    function __construct($im = null) {
        $this->im = $im;
    }

    /**
     * Create an image from a string
     *
     * @param string $blob
     * @return boolean
     */
    public function createFromBlob($blob) {
        $this->im = @imagecreatefromstring($blob);
        return $this->im != false;
    }

    /**
     * Get the image width
     *
     * @return integer
     */
    public function getImageWidth() {
        /** @noinspection PhpUndefinedMethodInspection */
        return @imagesx($this->im);
    }

    /**
     * Get the image height
     *
     * @return integer
     */

    public function getImageHeight() {
        /** @noinspection PhpUndefinedMethodInspection */
        return @imagesy($this->im);
    }

    /**
     * Return a clone of the image
     *
     * @return EasyIndexImage
     */
    public function cloneImage() {
        $clone = @imagecreatetruecolor($this->getImageWidth(), $this->getImageHeight());
        @imagecopy($clone, $this->im, 0, 0, 0, 0, $this->getImageWidth(), $this->getImageHeight());
        return new EasyIndexImageGD($clone);
    }

    /**
     * Strip off EXIF etc to reduce size
     * Does nothing ATM - TODO - implement this
     *
     * @return bool
     */
    public function stripImage() {
        return true;
    }

    /**
     * Scales the size of the image to the given dimensions. The other parameter will be calculated if 0 is passed as either param.
     *
     * @param int $rows
     * @param int $cols
     * @param bool $bestfit Unused
     * @return bool
     */
    public function scaleImage($rows, $cols, $bestfit = false) {
        $scaled = @imagecreatetruecolor($rows, $cols);
        $result = @imagecopyresampled($scaled, $this->im, 0, 0, 0, 0, $rows, $cols, $this->getImageWidth(), $this->getImageHeight());
        $this->im = $scaled;
        return $result;
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
        $crop = @imagecreatetruecolor($width, $height);
        $result = @imagecopy($crop, $this->im, 0, 0, $x, $y, $width, $height);
        $this->im = $crop;
        return $result;
    }

    /**
     * @param $width
     * @param $height
     * @param $bgColor
     *
     * @return mixed
     */
    public function createImage($width, $height, $bgColor) {
        $im = @imagecreate(110, 20);
        @imagecolorallocate($im, 0, 0, 0);
    }

    /**
     * @param $filename
     *
     * @return mixed
     */
    public function writeImage($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'png':
                $result = @imagepng($this->im, $filename);
                break;
            case 'gif':
                $result = @imagegif($this->im, $filename);
                break;

            default:
                $result = @imagejpeg($this->im, $filename);
                break;
        }

        return $result;

    }

    /**
     * @return string
     */
    public function getLastError() {
        return $this->lastError;
    }

}

