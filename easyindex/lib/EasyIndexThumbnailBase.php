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
 * Class EasyIndexThumbnailBase
 *
 * A general purpose thumbnail class for Wordpress
 *
 */
class EasyIndexThumbnailBase {
    /**
     * Force thumb generation
     */
    const REGENERATETHUMB = 1;
    /**
     * Regenerate thumb if the source has a greater modification time than the existing thumb
     */
    const CHECKMTIME = 2;
    /**
     * Append the thumb file's mod time to the thumbnail URL
     */
    const APPENDMTIME = 4;
    /**
     * Use the full width of the source and adjust the height of the thumb with a vertical offset of zero
     */
    const FULLWIDTH = 8;

    /**
     * Don't crop the image - just resize the entire image to the thumb dimensions
     */
    const NOCROP = 16;
    /**
     * @var string The thumbnail base directory (including the size directory)
     */
    private $thumbnailDirectory;

    /**
     * @var string The base Url for thumbnails
     */
    private $thumbnailUrl;
    /**
     * @var string The full path to the thumbnail file
     */
    private $thumbPath;

    /**
     * @var string The name of the thumbnail file
     */
    private $thumbName;

    /**
     * @var string The thumb type (extension) defaultgs to jpg
     */
    private $thumbnailType;

    /** @var EasyIndexImage */
    private $thumbImage = null;

    /** @var  int */
    private $thumbWidth;
    /** @var  int */
    private $thumbHeight;

    /** @var  int */
    private $thumbFilesize;
    /** @var  string */
    private $size;

    /** @var  int */
    private $thumbMtime;

    /** @var  int The actual width when the width supplied is zero */
    private $thumbActualWidth;

    /** @var  int The actual heightwhen the height supplied is zero */
    private $thumbActualHeight;

    /**
     * Path to the source image file (e.g.could be a photo or a video splash image)
     *
     * @var string
     */
    protected $sourcePath = '';
    private $isUrl = false;

    /** @var EasyIndexImage */
    private $sourceImage;

    private $sourceWidth;
    private $sourceHeight;
    private $sourceMtime;

    /**
     * @var integer If TRUE, use the entire source
     */
    private $nocrop;
    /**
     * These are the co-ordinates of the original of the area to create the thumbnail from
     */
    private $selectionTop;
    private $selectionWidth;
    private $selectionLeft;

    private $http;

    /** @var  string The last error message returned by the image handler code */
    private $error;

    /**
     * @param object $settings Must include at least $thumbDirectory and $thumbBaseUrl.
     * @param string $sourcePath
     */
    function __construct($settings, $sourcePath = '') {

        $this->thumbnailDirectory = $settings->thumbDirectory;
        $this->thumbnailUrl = $settings->thumbBaseUrl;
        $this->thumbnailType = !empty($settings->thumbType) ? $settings->thumbType : 'jpg';

        if (!empty($sourcePath)) {
            $this->setSource($sourcePath);
        }
        $this->error = '';
    }

    function clearSource() {
        $this->sourcePath = '';
    }

    /**
     * Sets the source of the image to create the thumbnail from
     * @param $sourcePath
     */
    function setSource($sourcePath) {
        $this->sourcePath = '';
        unset($this->sourceImage);
        /**
         * If the source is a url, is it on this site and if it is, can we find the appropriate file?
         * First make relative Urls absolute
         */
        $homeUrl = get_home_url();
        if (!empty($sourcePath) && $sourcePath[0] == '.') {
            $sourcePath = "$homeUrl/$sourcePath";
        }
        /**
         * If this is a URL, and it's from this site, try to find the source file
         * A URL starts with http://, https:// or just //
         */
        if (preg_match('%^(?:https?:)?//(.*)%', $sourcePath, $regs)) {
            $this->isUrl = true;
            $source = $regs[1];
            /**
             * Is it from this site?
             */
            preg_match('%^(?:https?:)?//(.*)%', $homeUrl, $regs);
            $home = $regs[1];
            if (strpos($source, $home) === 0) {
                /**
                 * Try to get a source file name - drop any query parameteres (after "?")
                 */
                $source = rtrim(ABSPATH, '/') . substr($source, strlen($home));
                $source = explode('?',$source);
                $source = $source[0];
                if (@file_exists($source)) {
                    $this->sourcePath = $source;
                    $this->isUrl = false;
                }
            }
        }
        if ($this->sourcePath == '') {
            $this->sourcePath = $sourcePath;
        }
    }

    /**
     * Sets the size of the thumbnail to be created
     * May be called repeatedly with different values to create different sized thumbnails from the
     * same object
     *
     * @param string $size The size in the format "(width)x(height)" e.g. "110x110"
     */
    function setSize($size) {

        $this->size = $size;
        if (preg_match('/^(\d+)x(\d+)$/', $size, $regs)) {
            $this->thumbWidth = $regs[1];
            $this->thumbHeight = $regs[2];
        } else {
            $this->thumbWidth = 50;
            $this->thumbHeight = 50;
            $this->size = "50x50";
        }
        if ($this->thumbHeight == 0 || $this->thumbWidth == 0) {
            $this->nocrop = true;
        }
        $this->thumbActualWidth = $this->thumbWidth;
        $this->thumbActualHeight = $this->thumbHeight;
    }

    /**
     * Get the image content
     * If it's a file, read the file else get it from the net
     *
     * @return string|bool
     */
    function getSourceBlob() {
        /**
         * If we don't have a source, then try to find it if there's a find method
         * If still no source, then bail
         */
        if (empty($this->sourcePath)) {
            if (method_exists($this, 'findSource')) {
                $this->findSource();
            }
            if (empty($this->sourcePath)) {
                return false;
            }
        }

        if (!$this->isUrl) {
            if (file_exists($this->sourcePath)) {
                return @file_get_contents($this->sourcePath);
            }
            return false;
        }
        if (!isset($this->http)) {
            $this->http = new WP_Http();
        }
        $response = $this->http->get($this->sourcePath, array('timeout' => 10));

        if ($response instanceof WP_Error) {
            /** @var $response WP_Error */
            $message = $response->get_error_message();
            trigger_error($message);
            
            return false;
        }
        /** @var $response array */
        if (!$response || $response['response']['code'] != 200 || strpos($response['headers']['content-type'], 'image') !== 0) {
            return false;
        }
        return $response['body'];
    }

    /**
     * Reads the source file or Url and creates a GD image
     *
     * @return EasyIndexImage|null
     */
    function loadSource() {
        if (!isset($this->sourceImage)) {
            $blob = $this->getSourceBlob();

            if (!$blob) {
                return null;
            }

            $this->sourceImage = EasyIndexImage::getNewInstance();
            $result = $this->sourceImage->createFromBlob($blob);

            if (!$result) {
                return null;
            }

            $this->sourceWidth = $this->sourceImage->getImageWidth();
            $this->sourceHeight = $this->sourceImage->getImageHeight();
        }
        return $this->sourceImage;
    }

    /**
     * Get the source image width
     *
     * @return int The width of the source image
     */
    function getSourceWidth() {
        if (!isset($this->sourceImage)) {
            $this->loadSource();
        }
        return $this->sourceWidth;
    }

    /**
     * Get the source image height
     *
     * @return int The height of the source image
     */

    function getSourceHeight() {
        if (!isset($this->sourceImage)) {
            $this->loadSource();
        }
        return $this->sourceHeight;
    }

    /**
     * returns the width of the thumbnail to be created
     */
    function getWidth() {
        return $this->thumbWidth;
    }

    /**
     * returns the height of the thumbnail to be created
     */
    function getHeight() {
        return $this->thumbHeight;
    }


    /**
     * Get the mod time of the input.
     * This may be a file or a Url.
     *
     * Beware: this can take a LONG time for Urls - even fast Urls will take 0.1 ~ 0.5 seconds
     *
     * @return integer/boolean mtime of the source file/url if possible.
     */
    function getSourceMtime() {
        /**
         * Try to find the source if it's not set
         */
        if ($this->sourcePath == '') {
            if (method_exists($this, 'findSource')) {
                $this->findSource();
            }
            if ($this->sourcePath == '') {
                return 0;
            }
        }
        if (isset($this->sourceMtime)) {
            return $this->sourceMtime;
        }
        /**
         * If it's not a Url then it's a file
         */
        if (!$this->isUrl) {
            $this->sourceMtime = @filemtime($this->sourcePath);
            return $this->sourceMtime;
        }
        /**
         * Use HEAD to get the last modified header
         */
        $result = wp_remote_head($this->sourcePath);

        
        if ($result instanceof WP_Error) {
            /** @var $result WP_Error */
            trigger_error($result->get_error_message());
            return false;
        }
        /** @var $result array */
        if (isset($response['headers']['Last-Modified'])) {
            $this->sourceMtime = strtotime($response['headers']['Last-Modified']);
            return $this->sourceMtime;
        }

        $this->sourceMtime = time() + 1;
        return $this->sourceMtime;
    }

    /**
     * Make a thumbnail from the image at $sourcePath of size $this->size.
     * Sets the mtime of the thumbnail to the source mtime
     * If the thumbnail already exists as a file, AND the thumb mtime is the same as the souce mtime, do nothing
     * If an appropriate output path exists, save the thumbnail there
     *
     * TODO - use basename of source if no thumbName specified
     *
     * @param string $size Size of thumb as "WxH"
     * @param string $thumbName The base filename
     * @param int $options REGENERATETHUMB | CHECKMTIME | APPENDMTIME
     *
     * @return bool|null TRUE if a thumb was created, FALSE if the thumb existed or NULL if an error ocurred
     */
    function makeThumb($size, $thumbName, $options = 0) {

        $regenerate = $options & self::REGENERATETHUMB;
        $checkMtime = $options & self::CHECKMTIME;
        $fullWidth = $options & self::FULLWIDTH;
        $this->nocrop = $options & self::NOCROP;

        $this->setSize($size);
        $thumbDirectory = $this->thumbnailDirectory . "/$this->size";
        $this->thumbName = "$thumbName.$this->thumbnailType";
        $this->thumbPath = "$thumbDirectory/$this->thumbName";

        $sourceMtime = 0;
        /*
         * If the thumbnail has already been created and saved, check that it is current if $checkMtime is TRUE
         * If it's current or we aren't checking the times, return
         */
        if (!$regenerate && @file_exists($this->thumbPath)) {
            $this->thumbMtime = @filemtime($this->thumbPath);
            $this->thumbFilesize = @filesize($this->thumbPath);
            if ($checkMtime) {
                $sourceMtime = $this->getSourceMtime();
                if ($sourceMtime === false) {
                    return null;
                }
                if ($this->thumbMtime >= $sourceMtime) {
                    return false;
                }
            } else {
                return false;
            }
        }

        /**
         * If the thumb directory doesn't exit, try to create it
         */
        $thumbDirectory = dirname($this->thumbPath);
        if (!@file_exists($thumbDirectory)) {
            $old_umask = umask(0);
            $sts = @mkdir($thumbDirectory, 0777, true);
            umask($old_umask);
            if (!$sts) {
                return null;
            }
        }

        /**
         * If we don't have a source, then try to find it if there's a find method
         * If still no source, then bail
         */
        if (empty($this->sourcePath)) {
            if (method_exists($this, 'findSource')) {
                $this->findSource($options);
            }
            if (empty($this->sourcePath)) {
                return null;
            }
        }
        /**
         * The thumbnail doesn't exist, or is older than the source
         * Load the new source - trash any exising source image instance we might have first
         */
        unset($this->sourceImage);
        if (null === $this->loadSource()) {
            return null;
        }

        /**
         * Make a copy of the source
         */
        $this->thumbImage = $this->sourceImage->cloneImage();

        /**
         * Remove any profile stuff from the image
         */
        $this->thumbImage->stripImage();
        /**
         * If nocrop is set - we are going to use the entire souce, else find the largest area of the right aspect ratio
         */
        if (!$this->nocrop) {
            /**
             * Find the largest area in the source that has the same aspect ratio as the required thumb
             * OR use the full width and adjust the height
             */
            $thumbArea = $this->calculateSize($this->thumbWidth, $this->thumbHeight, $this->sourceWidth, $this->sourceHeight, $fullWidth);


            /**
             * Crop to the area calculated for the thumb
             */
            if (!$this->thumbImage->cropImage($thumbArea->newSrcWidth, $thumbArea->newSrcHeight, $thumbArea->srcX, $thumbArea->srcY)) {
                return null;
            }
        }
        /**
         * Now resize it to the actual thumbnail size
         * Calculate width or height if they are zero
         */
        if ($this->thumbHeight == 0) {
            $this->thumbActualHeight = round($this->sourceHeight * $this->thumbWidth / $this->sourceWidth);
        } else {
            $this->thumbActualHeight = $this->thumbHeight;
        }

        if ($this->thumbWidth == 0) {
            $this->thumbActualWidth = round($this->sourceWidth * $this->thumbHeight / $this->sourceHeight);
        } else {
            $this->thumbActualWidth = $this->thumbWidth;
        }

        if (!$this->thumbImage->scaleImage($this->thumbActualWidth, $this->thumbActualHeight)) {
            return null;
        }

        $this->thumbMtime = time();

        /**
         * If we have a directory for this size, write the new thumb into it
         */

        if (!$this->thumbImage->writeImage($this->thumbPath)) {
            $this->error = $this->thumbImage->getLastError();
            return null;
        }

        $fs = filesize($this->thumbPath);
        if ($fs == 0) {
            @unlink($this->thumbPath);
            return null;
        }

        $oldMask = umask(0);
        chmod($this->thumbPath, 0666);
        umask($oldMask);
        if ($sourceMtime != 0) {
            if (!@touch($this->thumbPath, $this->thumbMtime)) {
                
            }
            @filemtime($this->thumbPath);
        }


        return true;
    }

    /**
     * Returns the offset and size in the source image of an area suitable for a thumbnail
     * Will return the largest possible area with the same aspect ratio of the requested thumbnail
     *
     * TODO - double check that we're picking an appropriate area for thumbs with a different aspect ratio than the source
     * e.g. pick the top part of the image for portrait source, the middle of the image for landscape source
     *
     * @param integer $width Width of the required thumb
     * @param integer $height Height of the thumb
     * @param integer $srcWidth Width of the source image
     * @param integer $srcHeight Height of the source image
     * @param bool $fullWidth TRUE to use the full width of the source and adjust the height
     *
     * @return object x,y offset and width, height of the area suitable for a thumbnail
     */
    function calculateSize($width, $height, $srcWidth, $srcHeight, $fullWidth = false) {


        $dstRatio = $width / $height;
        $srcRatio = $srcWidth / $srcHeight;

        /**
         * If the thumbnail has a taller/narrower aspect ratio, then we'll use all the source height and pick from the middle vertically
         * If the thumbnail has a shorter/wider aspect ratio, then we'll use all the source width and pick from the middle horizontally
         */
        if ($srcRatio >= $dstRatio && !$fullWidth) {
            $newSrcHeight = $srcHeight;
            $newSrcWidth = floor($srcHeight * $width / $height);
            $srcY = 0;
            $srcX = ($srcWidth - $newSrcWidth) >> 1;
        } else {
            $newSrcWidth = $srcWidth;
            $newSrcHeight = floor($srcWidth * $height / $width);
            $srcX = 0;
            $srcY = $fullWidth ? 0 : ($srcHeight - $newSrcHeight) >> 1;;
        }
        $thumbArea = new stdClass();
        $thumbArea->srcX = $srcX;
        $thumbArea->srcY = $srcY;
        $thumbArea->newSrcWidth = $newSrcWidth;
        $thumbArea->newSrcHeight = $newSrcHeight;

        $this->selectionTop = $srcY;
        $this->selectionLeft = $srcX;
        $this->selectionWidth = $newSrcWidth;
        return $thumbArea;
    }

    /**
     * Returns the Url to a $width x $height thumbnail
     * The thumb is created if it doesn't already exist
     *
     * @param string $size Size as "WxH"
     * @param string $thumbName The base name for the thumb
     * @param int $options REGENERATETHUMB | CHECKMTIME | APPENDMTIME
     *
     * @return string|bool|null The thumb's Url, FALSE if there's no source and null if we couldn't create the thumb
     */
    function getUrl($size, $thumbName, $options = self::APPENDMTIME) {
        /**
         * If we already know there's no source, just bail
         */
        if ($this->sourcePath == 'none') {
            return false;
        }

        /**
         * if we didn't create the thumb and it didn't already exist, return FALSE if it's because we didn't find a source or null if the create failed
         */
        if (null === $this->makeThumb($size, $thumbName, $options)) {
            if ($this->sourcePath == '') {
                return false;
            }
            return null;
        }
        return "$this->thumbnailUrl/$size/$this->thumbName" . ($options & self::APPENDMTIME ? "?mt=$this->thumbMtime" : '');
    }


    /**
     * Get the thumb's mtime - can be used as a querystring param so we bypass browser caches
     *
     * @return int The thumbs mtime
     */
    function getThumbMtime() {
        return $this->thumbMtime;
    }

    /**
     * @return string
     */
    function getError() {

        return $this->error;
    }
}


