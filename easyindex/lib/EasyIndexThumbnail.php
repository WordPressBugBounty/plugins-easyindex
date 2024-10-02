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
 * Class EasyIndexThumbnail
 *
 * EasyIndex's thumbnail class
 *
 * This is a wrapper around ThumbnailBase which adds the capability to find an image source Url from a postID
 * Source images are identified by looking at the post's meta data and if there's nothing there, searching the post for likely images
 *
 * It has the capability to read and store the meta data for an array of postIDs in one SELECT
 * It's much more efficient to do this than to process each post individually. Processing one by one can be a problem on
 * indexes and index widgets where there may be dozens (or even hundreds) of posts to look up
 */


class EasyIndexThumbnail extends EasyIndexThumbnailBase {

    /** @var EasyIndexSettings */
    private static $thumbSettings = null;
    /**
     * @var array An array of postIDs for which we have not yet read meta data (which tells us the image source)
     */
    private static $unknownSourceIDs = array();

    /**@var array An array of IDs that must have their thumbs re-generated */
    private static $invalidatedIDs = array();
    /**
     * @var array An array of post meta data (key: 'easyindexSourceUrl') indexed on postID
     */
    private static $metaData = array();

    /** @var  int The memory limit to set for thumb creation */
    private static $thumbMemory;

    private $postID;

    /**
     * @param integer $postID The post ID to get a thumbnnail for
     * @param string $source The image to create the thumbnail from if we know it
     */
    function __construct($postID, $source = '') {
        /** @var wpdb $wpdb */
        global $wpdb;

        /**
         * Get the settings just once
         * Also get the IDs of any posts that must have thumbs regenerated (because the post changed and the thumb source might be different)
         */
        if (self::$thumbSettings == null) {
            $settings                            = EasyIndexSettings::getInstance();
            self::$thumbSettings                 = new stdClass();
            self::$thumbSettings->thumbDirectory = $settings->thumbLocation->directory;
            self::$thumbSettings->thumbBaseUrl   = $settings->thumbLocation->url;
            self::$thumbMemory                   = $settings->thumbMemory;
            $q                                   = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'easyindexInvalidate'";
            $invalids                            = $wpdb->get_col($q);
            foreach ($invalids as $id) {
                self::$invalidatedIDs[$id] = true;
            }

        }

        $this->postID = $postID;
        /**
         * If we already know the source location, set it
         * If we don't know it, ThumbnailBase will call our findSource() if it needs to (it may not need to if a thumb already exists).
         * Delaying the source lookup may save a not insignificant amount of processing
         */
        if ($source != '') {
            $this->setSource($source);
        }

        parent::__construct(self::$thumbSettings);
    }

    /**
     * @param string $size
     * @param string $thumbName
     * @param int $options
     * @return bool|null
     */
    function makeThumb($size, $thumbName, $options = 0) {

        if (self::$thumbMemory != 0) {
            @ini_set('memory_limit', self::$thumbMemory . 'M');
            self::$thumbMemory = 0;
        }
        if (isset(self::$invalidatedIDs[$this->postID])) {
            $options |= EasyIndexThumbnailBase::REGENERATETHUMB;
        }
        $result = parent::makeThumb($size, $thumbName, $options);
        if (!empty($result) && ($options & EasyIndexThumbnailBase::REGENERATETHUMB) && !empty(self::$invalidatedIDs[$this->postID])) {
            delete_post_meta($this->postID, 'easyindexInvalidate');
            unset(self::$invalidatedIDs[$this->postID]);
        }
        return $result;
    }

    /**
     * Save an array of postIDs that we may have to find meta data for.
     * Do it this way because it's often going to be significantly more efficient to do it in one SELECT rather than one by one
     *
     * At this point, we only save the IDs. The actual read is postponed until we actually have to do it (it may not be necessary)
     *
     * This may conceivably be called more than once before we use the IDs so add to the stored array rather than just save
     *
     * @param array $postIDs An array of postIDs for which we don't yet have metadata
     */
    static function setUnknownIDs($postIDs) {
        foreach ($postIDs as $postID) {
            self::$unknownSourceIDs[$postID] = true;
        }
    }

    /**
     * Remove an entry from the unknowns once we know we have the source location
     * It's possibly a little overkill but it just might significantly reduce the amount of stuff we'll have to read from the meta table later
     *
     * @param int $postID
     */
    static function isKnown($postID) {
        unset(self::$unknownSourceIDs[$postID]);
    }


    
    
    /**
     * Try to find an appropriate image for post $postID
     * Looks for (in this order) :
     *  - an attachment post ID in the easyindexSourceUrl metadata (i.e. an explicitly set "Index image")
     *  - a url in the easyindexSourceUrl metadata
     *  - a post thumbnail (featured image)
     *  - an EasyRecipe recipe image
     *  - an image in the post marked up as itemprop="image"
     *  - a thesis "post_image"
     *
     *  If all else fails it will look for any image in the post not less than 150x150
     *
     *  If an image is found, its Url is stored in the post's metadata as 'easyindexSourceUrl'
     *
     * @param int $options The options passed to makeThumbnail
     * @return bool|string Returns the source Url or FALSE if it can't find one
     */

    function findSource($options = 0) {
        global $wp_version;
        /** @var wpdb $wpdb */
        global $wpdb;

        if (!empty($this->sourcePath)) {
            return $this->sourcePath;
        }


        /**
         * If we haven't yet read the meta for any unknown's previously passed, then do it now
         * Pick up any explicitly set images (the easyindexSourceUrl metadata is numeric and points to an attachment) and read those in a block too
         * It's possible (but unlikely?) that we'll add to the unknowns after a first read has been done so allow for that
         */
        if (count(self::$unknownSourceIDs) > 0) {
            $pIDs = implode(',', array_keys(self::$unknownSourceIDs));
            if (!empty($pIDs)) {
                $attachmentIDs = array();
                $q             = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id  IN ($pIDs) AND meta_key = 'easyindexSourceUrl'";
                $meta          = $wpdb->get_results($q, OBJECT_K);
                foreach ($meta as $id => $data) {
                    if (is_numeric($data->meta_value)) {
                        $attachmentIDs[$id] = $data->meta_value;
                    } else {
                        self::$metaData[$id] = $data->meta_value;
                    }
                }
                /**
                 * Pick up the actual url's of any explicitly set images
                 */
                if (!empty($attachmentIDs)) {
                    foreach ($attachmentIDs as $postID => $attachmentID) {
                        self::$metaData[$postID] = get_attached_file($attachmentID);
                    }
                }
            }
            self::$unknownSourceIDs = array();
        }

        if (!empty(self::$metaData[$this->postID])) {
            if (self::$metaData[$this->postID] != 'none') {
                $this->setSource(self::$metaData[$this->postID]);
            }
            return self::$metaData[$this->postID];
        }

        /**
         * Not in the stored metadata - it may be in the postmeta but wasn't passed to "unknowns"
         */
        $source = get_post_meta($this->postID, 'easyindexSourceUrl', true);

        /**
         * If the source is numeric, then it's an attachment
         * Get the attachment file, set this source and return it
         */
        if (is_numeric($source)) {
            $source = get_attached_file($source);
            $this->setSource($source);
            return $source;
        }

        /**
         * If the metadata source == "none" and we are regenerating existing, ignore the "none"
         * This will force a rescan of the content to find a source
         */
        if ($source == "none" && $options & EasyIndexThumbnailBase::REGENERATETHUMB) {
            $source = "";
        }
        if ($source) {
            $this->setSource($source);
            return $source;
        }

        /**
         * First try for a standard post thumbnail
         */
        $image  = wp_get_attachment_image_src(get_post_thumbnail_id($this->postID), 'full');
        $source = ($image && !empty($image[0])) ? $image[0] : '';
        /**
         * If nothing found so far, look inside the post
         */
        if ($source == '') {
            /**
             *
             * Use WP_Post::get_instance - it may already be in the WP cache (if we read it in the widget)
             */

            $post = WP_Post::get_instance($this->postID);


            
            
            

            /**
             * Check for an EasyRecipe.  It isn't formatted or completely marked up in the post itself but we will be able to find a photo if there's one there
             * This should much quicker than expanding the content and parsing microdata
             */
            if (preg_match('/<div class="easyrecipe.*? class="ERIngredients"/s', $post->post_content, $regs)) {
                if (preg_match('/<link\s+itemprop="image".*?href="([^"]+)"/s', $regs[0], $regs1)) {
                    $source = $regs1[1];
                }
            }
        }
        if ($source == '') {
            /**
             * Instantiate a Microdata instance on the off chance we have microdata
             * Only do this if there's an "itemprop" in the content - DOM stuff is very computationally expensive to do
             */
            /** @noinspection PhpUndefinedVariableInspection */
            if (strpos($post->post_content, 'itemprop') !== false) {
                $microData = new EasyIndexMicrodata($post->post_content);
                $items     = $microData->getItems();
                $items     = $items->items;
                foreach ($items as $item) {
                    if (isset($item->properties) && isset($item->properties['image'])) {
                        $source = $item->properties['image'][0];
                        break;
                    }
                }
            }
        }
        /**
         * Can't find anything specifically marked up as itemprop="image"  - look through the post for the first decent sized image
         * Only do this if there are <img> tags in the content. If we find an image, we'll have to load it to check the size. Remember that it's
         * been loaded so we don't do it again
         */
        $isLoaded = false;

        if ($source == '') {
            /** @noinspection PhpUndefinedVariableInspection */
            if (strpos($post->post_content, '<img') !== false) {
                if (!isset($microData)) {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $microData = new EasyIndexMicrodata($post->post_content);
                }
                /** @noinspection PhpUndefinedVariableInspection */
                $dom        = $microData->getDom();
                $postImages = $dom->getChildrenByTagName($dom, 'img');
                /** @var $postImage DOMElement */
                foreach ($postImages as $postImage) {
                    $src = $postImage->getAttribute('src');
                    /**
                     * Found an image - is it big enough?
                     * If we don't check this we can end up with icons and fillers
                     *
                     * Assume it's THE image so we can get the size.
                     * Do it this way so if we DO find an appropriate image we don't have to read it again
                     * (can be very slow if it's a Url not on the site)
                     *
                     * Make sure $src isn't empty else getSourceHeight() will eventually recursively call findSource() and we'll crash
                     */
                    if (!empty($src)) {
                        $this->setSource($src);
                        if ($this->getSourceHeight() >= 150 && $this->getSourceWidth() >= 150) {
                            $isLoaded = true;
                            $source = $src;
                            break;
                        } else {
                            $this->clearSource();
                        }
                    }
                }
            }
        }
        /**
         * Thesis has its own post image
         */
        if ($source == '') {
            if ($wp_version < '3.4') {
                /** @noinspection PhpDeprecationInspection */
                $themeName = get_current_theme();
            } else {
                /** @var $theme WP_Theme */
                $theme     = wp_get_theme();
                $themeName = $theme->get_stylesheet();
            }

            if (stripos($themeName, 'thesis') !== false) {
                $source = get_post_meta($this->postID, 'thesis_post_image', true);
            }
        }

        /**
         * Set the source unles we already did it (else we'll have to re-read the image)
         */
        if (!$isLoaded && $source != '') {
            $this->setSource($source);
        }
        /**
         * Store in the posts's meta data so we won't have to look for it again
         */
        if ($source == '') {
            $source = 'none';
        }

        update_post_meta($this->postID, 'easyindexSourceUrl', $source);
        return $source;

    }
}

