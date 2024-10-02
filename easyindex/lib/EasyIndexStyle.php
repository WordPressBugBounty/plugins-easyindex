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
 * Class EasyIndexStyle
 *
 * Style data representation
 */
class EasyIndexStyle {

    /**
     * @var string The style "id". i.e. the style's directory name
     */
    public $id = '';

    /** @var  string The parent style ID if any */
    public $parentID;

    /** @var  EasyIndexStyle The parent style if any */
    public $parent;
    /**
     * @var string User friendly name
     */
    public $name = '';
    /**
     * @var string Appended to css url's so we get around browser caches when the style changes
     */
    public $version = '';
    /**
     * @var string Info only
     */
    public $author = '';
    /**
     * @var array The type of index of this style: primary or secondary
     */
    public $indexType = '';
    /**
     * @var string The style of the index: "single", "sample" or "onesample"
     */
    public $styleType = '';

    /**
     * @var bool TRUE if this style displays excerpts
     */
    public $hasExcerpts = false;

    /**
     * @var bool TRUE if this style has titles.  Only explicitly set for exceptions. If a style has samples, it generally has titles.
     * The exception is a gallery (which is a special case of a sample index) which might or might not have titles.
     * Only used to determine what options get shown on the index edit page - doesn't play any part in the actual index display
     */
    public $hasTitles = false;

    /**
     * @var bool TRUE if this style is text only
     */
    public $isText = false;

    /**
     * @var bool TRUE if this style has a masonry layout
     */
    public $isMasonry = false;

    /** @var string The item width attribute */
    public $itemWidth = '';

    /** @var string The CSS selector of an item */
    public $itemSelector = '.ei-item';

    /**
     * @var string User friendly short (fit on one line in style select) description
     */
    public $summary = '';

    /**
     * @var string User friendly long description
     */
    public $description = '';

    /**
     * @var string Location of a representative thumbnail relative to the "styles" directory (or the custom styles directory)
     */
    public $thumbnail = 'style.jpg';
    /**
     * @var array For future use?
     */
    public $features = array();

    /**
     * @var integer Used to order styles in the style selection
     */
    public $sequence = 0;

    /** @var bool TRUE if this style is included in the current package */
    public $isIncluded = false;

    /**
     * @var string Used to group styles in style selection
     */
    public $group = '';

    /** @var  stdClass Basic formatting definitions */
    public $basic;
    /**
     * @var EasyIndexStyleDefaults  The default settings for the style
     */
    public $defaults;

    /** @var array Live formatting specs for Live Formatting */
    public $formatting;

    public $directory;

    /** @var array Properties that aren't inherited by a child style */
    public $notInherited = array(
        'parentID',
        'parent',
        'directory',
        'description',
        "isIncluded",
        'summary',
        'thumbnail'
    );

    /**
     * Get the style details from the style definition and possibly its parent
     * @param $styleID
     * @param null|string $directory Pass in directory for unit tests
     * @throws Exception
     */
    function __construct($styleID, $directory = null) {
        if ($directory != null) {
            $this->directory = $directory . "/$styleID";
        } else {
            $this->directory = EasyIndex::$EasyIndexDir . '/styles/' . $styleID;
        }
        $json = file_get_contents($this->directory . '/style.json');
        $style = json_decode($json);

        if ($this->isJsonError($style)) {
            
            throw new Exception("Invalid style definition in $styleID");
        }
        /**
         * If this style has a parent, get the parent's settings
         */
        if (!empty($style->parentID)) {
            try {
                $style->parent = new EasyIndexStyle($style->parentID, $directory);
            } catch (Exception $e) {
                return;
            }
            foreach ($style->parent as $property => $value) {
                if (!in_array($property, $this->notInherited)) {
                    $this->$property = $value;
                }
            }
        }
        /**
         * Save (and overwrite any parent) settings
         */
        foreach ($style as $property => $value) {
            $this->$property = $value;
        }


        $this->id = $styleID;
    }

    /**
     * Returns TRUE if this is a valid style
     * @return bool
     */
    public function isValid() {
        return !empty($this->id);
    }

    /**
     * Get the style's template
     * Pre-process it and replace <tag> .. </tag> with the index specific tag
     *
     * @param array $tags Tags that replace <termtag> and <titletag>
     * @return EasyIndexTemplate
     */
    public function getTemplate($tags) {
        $fileName = $this->directory . '/style.html';
        $text = file_exists($fileName) ? @file_get_contents($fileName) : false;
        if ($text === false) {
            if (!empty($this->parent)) {
                return $this->parent->getTemplate($tags);
            }
            trigger_error("Can't open file: '$fileName'", E_USER_NOTICE);
        }
        foreach ($tags as $tag => $value) {
            $regex = "%((</?)$tag([>| ]))%";
            $replace = "\$2$value\$3";
            $text = preg_replace($regex, $replace, $text);
        }
        return new EasyIndexTemplate($text, EasyIndexTemplate::PAGETEXT, $fileName);
    }

    /**
     * Returns the URL of the style's stylesheet
     * CSS is NOT inherited from a parent
     * @return string
     */
    public function getCSSUrl() {
        return EasyIndex::$EasyIndexUrl . "/styles/$this->id/style.css?v=1.1.1704";
    }

    /**
     * Returns the style's CSS in a string
     * @return string
     */
    public function getCSSString() {
        return file_get_contents(EasyIndex::$EasyIndexDir . "/styles/$this->id/style.css");
    }

    /**
     * PHP version safe JSON error test
     * @param $json
     * @return bool
     */
    private function isJsonError($json) {
        /** @var Services_JSON $wp_json */
        global $wp_json;

        if (function_exists('json_last_error')) {
            return json_last_error() != JSON_ERROR_NONE;
        }
        if ($json === null) {
            return true;
        }
        if (!empty($wp_json)) {
            return $wp_json->isError($json);
        }
        return false;

    }
}


