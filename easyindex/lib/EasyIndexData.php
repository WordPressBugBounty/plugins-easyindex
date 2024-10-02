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
 * Class EasyIndexData
 *
 * A class to hold an index's settings
 * It is saved (as a class) in the index's post meta
 * Same class name for Plus, Beta and Free versions else we'll have trouble unserializing when we switch from one version to another
 *
 * We shouldn't have duplicate class problems if more than one version of EasyIndex is active since only one EasyIndex actually sets itself up if others are active
 *
 */

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
class EasyIndexData {

    /**
     * @var string The slug used in the index's URL
     */
    public $indexSlug;
    /** @var  EasyIndexDataIndex */
    public $primary;

    /** @var  EasyIndexDataIndex */
    public $secondary;

    /** @var  string The title string - applies to the secondary index only */
    public $ixTitle = '%term%';

    /** @var bool If TRUE, secondary indexes will automatically be included as sub items under the correspondig primary menu in menus
     */
    public $ixAutoMenu = false;

    /**
     * @var int The taxonomy for this isnex
     */
    public $indexTaxonomy = 'category';

    /**
     * @var bool If TRUE, then all terms (and future new terms) will be included
     */
    public $indexAllTerms = true;
    /**
     * @var array Array of the term IDs of the terms to be included in this index
     */
    public $indexTerms = array();

    /**
     * @var string  The sequence of posts on a secondary index
     *              "alpha", "datedesc" or "dateasc"
     */
    public $ixSequence = 'alpha';


    /** @var bool TRUE to show the same sample in multiple terms. Only applies to primary sample indexes */
    public $showDuplicates = false;

    /**
     * Create a new IndexData object.  Use the default styles' default data to override initial values.
     *
     * The constructor only ever gets called once in the life of an index
     * After an index is created (and saved) it will only ever be instantiated by unserializing the data
     *
     * @param $indexSlug
     */
    function __construct($indexSlug) {

        $this->indexSlug = $indexSlug;

        $index = new EasyIndexDataIndex();
        $style = EasyIndexStyles::getStyle($index->ixStyleID);

        foreach ($style->defaults as $property => $value) {
            $index->$property = $value;
        }

        $this->primary = $index;


        $index = new EasyIndexDataIndex();
        $index->ixStyleID = 'style501';
        $index->ixLiveCss = array();
        $index->ixFormatCss = array();

        $style = EasyIndexStyles::getStyle($index->ixStyleID);

        foreach ($style->defaults as $property => $value) {
            $index->$property = $value;
        }

        /**
         * Override the default for the title tag if not explicitly set in the style
         */
        if (empty($style->defaults->ixTitleTag)) {
            $index->ixTitleTag = 'h4';
        }
        $this->secondary = $index;

    }
}

/**
 * Class EasyIndexDataIndex
 *
 * A class to hold an index's primary or secondary settings
 * Not actually required logically but makes PHPStorm inspections and autocompletes work nicely
 * The initial values are only set as a hint to the actual data - not actually relied on
 *
 * This doesn't need to be in its own source file to satisfy the autoload() requirements because it will only ever be instantiated in conjunction with EasyIndexData
 *
 */

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
class EasyIndexDataIndex {

    public $ixStyleID = 'style001';

    /** @var int The mumber of posts to show on a sample index */
    public $ixNumberPosts = 4;
    /** @var int The number of posts to show per scroll on a gallery index */
    public $ixPostsPerScroll = 25;

    public $ixMoreText = 'See more ...';

    public $ixThumbSize = '150x150';
    public $ixThumbWidth = 150;
    public $ixThumbHeight = 150;

    public $ixTermTag = 'h2';
    public $ixTitleTag = 'h3';

    public $ixExcerptLength = 100;

    /** @var string The theme template to use */
    public $ixTemplate = 'default';

    /**
     * @var bool If TRUE, terms on a primary index that have not had a thumbnail loaded will not be displayed
     *           Would normally be better to default to TRUE, but FALSE means an index will show something "out of the box"
     */
    public $ixHideBlank = false;

    /**
     * @var array A list of Google fonts used by this index in basic formatting
     */
    public $ixFormatGFonts = array();

    /**
     * @var array A list of Google fonts used by this index in live formatting
     */
    public $ixLiveGFonts = array();

    /**
     * @var array The CSS created when the simple formatting popup is used
     *             Will override the index's standard CSS.
     *             This needs to be an array of selectors so we can easily get at each one separately in Simple Formatting
     */
    public $ixFormatCss = array();
    /**
     * @var string User CSS entered into the "Custom CSS" field on the insert/edit index page.
     *             Will override the index's "format CSS". JSON encoded
     *             Simply a string of (unvalidated) CSS
     */
    public $ixCustomCss = '';

    /**
     * @var array The user modified CSS set via the format popup on the index insert/edit page and via live formatting on the index page.
     *            Will override the custom CSS.
     *            This needs to be an array of selectors so we can easily get at each one separately in Live Formatting
     */
    public $ixLiveCss = array();



}


