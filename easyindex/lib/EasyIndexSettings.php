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
 * Class EasyIndexSettings
 *
 * Handles  EasyIndex settings
 */
class EasyIndexSettings {

    const HELP_PAGE = '/settings';

    private static $defaultSettings = array(
        'licenseKey'       => '',
        'isActivated'      => false,
        'defaultSlug'      => 'indexes',
        'defaultTitleTag'  => 'h2',
        'indexSlugs'       => array(),
        'customTemplates'  => '',
        'pluginVersion'    => '',
        'customPixCss'     => '',
        'customSixCss'     => '',
        'thumbLocation'    => null,
        'bgGeneration'     => true,
        'disablePhoton'    => false,
        'generatorTimeout' => 2,
        'displayHelp'      => true,
        'debugLog'         => false,
        'thumbMemory'      => 384
    );
    /** @var EasyIndexSettings */
    private static $instance = null;


    public $licenseKey;

    public $isActivated;

    /** @var  bool TRUE (default) if we should generate thumbs in the background */
    public $bgGeneration;

    /** @var  bool FALSE (default) if we should disable the Jetpack photo CDN for index thumbs */
    public $disablePhoton;

    /** @var  int The number of minutes the thumbnail generator can run before giving up */
    public $generatorTimeout;

    public $defaultSlug;

    /**
     * @var array The list of slugs used in all indexes
     */
    public $indexSlugs;

    /**
     * @var string The directory where custom templates live
     */
    public $customTemplates;
    /**
     * @var string The version of the plugin that last updated these settings
     */
    public $pluginVersion;

    /**
     * @var string Primary index custom CSS
     */
    public $customPixCss;

    /**
     * @var string Secondary index custom CSS
     */
    public $customSixCss;

    /**
     * @var array An array of thumnails Urls for each term, indexed by taxonomy name
     */
    public $termThumbSource = array();


    /**
     * @var object The absolute path and url of the thumbnail directory
     */
    public $thumbLocation;

    /**
     * @var string The base Url for thumbnails
     */
    // public $thumbBaseUrl;


    public $defaultFonts = array(
        'Arial, Helvetica, sans-serif',
        '"Arial Black", Gadget, sans-serif',
        '"Comic Sans MS", cursive',
        '"Courier New", monospace',
        'Georgia, serif',
        'Impact, Charcoal, sans-serif',
        '"Lucida Console", Monaco, monospace',
        '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
        '"Palatino Linotype", "Book Antiqua", Palatino, serif',
        'Tahoma, Geneva, sans-serif',
        '"Times New Roman", serif',
        '"Trebuchet MS", sans-serif',
        'Verdana, Geneva, sans-serif'
    );

    /** @var array Stylesheet IDs of CSS to exclude on EasyIndex admin pages */
    public $excludeCss = array();

    /** @var  boolean TRUE if we should redirect the admin dashboard to the help page when the admin page is displayed. Will get set to TRUE on activation, false if redirected */
    public $displayHelp;

    /** @var  boolean Turns on debug loggging when true */
    public $debugLog;

    /** @var  int The PHP memory limit (Mb) we'll try to set for creating thumbs */
    public $thumbMemory;

    /**
     * @static
     * @return EasyIndexSettings
     */
    static function getInstance() {

        $updateOptions = false;
        if (!self::$instance) {
            self::$instance = get_option('EasyIndex', false);


            if (!(self::$instance instanceof EasyIndexSettings)) {
                self::$instance = new EasyIndexSettings();
                $updateOptions = true;
            }
            /**
             * Add any defaults which are new to this (latest) plugin version
             */
            foreach (self::$defaultSettings as $setting => $default) {
                if (!isset(self::$instance->{$setting})) {
                    self::$instance->{$setting} = $default;
                    $updateOptions = true;
                }
            }
            /**
             * Get the thumb directory and its url
             */
            if (empty(self::$instance->thumbLocation)) {
                self::$instance->thumbLocation = self::$instance->getThumbLocation();
                $updateOptions = true;
            }
            /**
             * Background generation is only available in the Plus and Beta versions
             */
            self::$instance->bgGeneration = false;
        }

        /**
         * Is this a new version of the plugin?
         */
        if (self::$instance->pluginVersion != EasyIndex::$pluginVersion) {
            self::$instance->pluginVersion = EasyIndex::$pluginVersion;
            $updateOptions = true;
        }
        /**
         * Update the settings if we changed something during construction
         */
        if ($updateOptions) {
            update_option('EasyIndex', self::$instance);
        }

        return self::$instance;
    }

    

    /**
     * Echo the html needed for the admin settings page
     * The tabs are each done on their own template for no other reason than it makes it a lot less error prone when designing
     */
    public function showPage() {

        $errors = false;

        if (isset($_POST['action']) && $_POST['action'] == 'save') {
            $errors = $this->save($_POST['EasyIndex']);
        }

        $data = new stdClass();
        $generalData = new stdClass();

        $data->nonce = wp_create_nonce('EasyIndex-settings');

        foreach (self::$defaultSettings as $setting => $default) {
            $data->{$setting} = isset($this->{$setting}) ? $this->{$setting} : $default;
        }

        $data->eiplus = '';

        $data->license = $this->licenseKey;

        if (!empty($errors)) {
            $generalData->hasError = true;
            $generalData->ERRORS = array();
            foreach ($errors as $error) {
                $item = new stdClass();
                $item->error = $error;
                $generalData->ERRORS[] = $item;
            }
        }
        $data->settingsname = 'EasyIndex';
        $data->wpurl = get_bloginfo('wpurl');
        $data->pluginversion = EasyIndex::$pluginVersion;
        $data->pluginUrl = EasyIndex::$EasyIndexUrl;

        $data->fonts = json_encode($this->defaultFonts);

        $generalData->settingsname = 'EasyIndex';
        $generalData->licenseKey = $this->licenseKey;
        $generalData->active = $this->isActivated ? 'ei-is-active' : 'ei-not-active';
        if (empty($this->licenseKey)) {
            $this->isActivated = false;
            $generalData->displayactivate = 'EISDisplayNone';
            $generalData->displaydeactivate = 'EISDisplayNone';
        } else {
            if ($this->isActivated) {
                $generalData->displayactivate = 'EISDisplayNone';
                $generalData->displaydeactivate = '';
            } else {
                $generalData->displayactivate = '';
                $generalData->displaydeactivate = 'EISDisplayNone';
            }
        }
        $generalData->defaultSlug = $this->defaultSlug;
        $generalData->pluginversion = EasyIndex::$pluginVersion;
        $generalData->customTemplates = $this->customTemplates;
        $generalData->thumbDirectory = $this->thumbLocation->directory;
        $generalData->thumbUrl = $this->thumbLocation->url;

        if ($this->bgGeneration) {
            $generalData->bgTODisplay = '';
            $generalData->fgTODisplay = 'EISDisplayNone';
            $generalData->bgGenerationChecked = 'checked';
        } else {
            $generalData->fgTODisplay = '';
            $generalData->bgTODisplay = 'EISDisplayNone';
            $generalData->bgGenerationChecked = '';
        }
        $generalData->generatorTimeout = $this->generatorTimeout;

        $generalData->thumbMemory = $this->thumbMemory;

        $generalData->disablePhotonChecked = $this->disablePhoton ? "checked" : "";

        $generalData->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;
        $generalData->pluginname = 'EasyIndex';
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings-general.html');
        $data->general = $template->replace($generalData);


        $taxonomies = EasyIndexTaxonomies::getInstance();

        $thumbData = new stdClass();
        $thumbData->TAXONOMIES = array();

        foreach ($taxonomies->taxonomies as $key => $taxonomy) {
            $item = new stdClass();
            $item->value = $taxonomy->name;
            $item->label = $key;
            $item->selected = $taxonomy->name == 'category' ? 'selected' : '';
            $thumbData->TAXONOMIES[] = $item;
        }

        /**
         * Get all the terms for each taxonomyh unhierachically and display the term thumbs
         */

        $thumbData->TAXONOMYTHUMBS = array();
        $taxTerms = $taxonomies->getTermsFlat();
        foreach ($taxTerms as $key => $terms) {
            $item = new stdClass();
            $item->taxName = $taxonomies->taxonomies[$key]->name;
            $item->TERMS = array();
            /**
             * Only show "category" thumbs at the start
             */
            $item->taxonomyVis = $item->taxName != 'category' ? 'EISTaxonomyHide' : '';

            foreach ($terms as $term) {
                $itemTerm = new stdClass();
                $itemTerm->termname = $term->name;
                $itemTerm->termid = $term->termID;
                $itemTerm->taxName = $item->taxName;
                if (!empty($this->termThumbSource[$item->taxName][$term->termID])) {
                    $itemTerm->termthumb = $this->termThumbSource[$item->taxName][$term->termID];
                    $thumb = new EasyIndexThumbnail(0, $itemTerm->termthumb);
                    $itemTerm->thumburl = $thumb->getUrl('100x100', "{$item->taxName}-{$term->termID}");
                }
                if (empty($itemTerm->thumburl)) {
                    $itemTerm->thumburl = EasyIndex::$EasyIndexUrl . '/images/noimage100.png';
                    $itemTerm->termthumb = '';
                }
                $itemTerm->settingsname = 'EasyIndex';

                $item->TERMS[] = $itemTerm;
            }
            $thumbData->TAXONOMYTHUMBS[] = $item;
        }
        $thumbData->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings-thumbs.html');
        $data->indexthumbs = $template->replace($thumbData);

        $supportData = new stdClass();
        $supportData->wpurl = get_bloginfo('wpurl');
        $supportData->pluginUrl = EasyIndex::$EasyIndexUrl;
        $supportData->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings-support.html');
        $data->support = $template->replace($supportData);

        $nerdData = new stdClass();
        $nerdData->excludeCss = join(', ', $this->excludeCss);
        $nerdData->debugLogChecked = $this->debugLog ? 'checked' : '';
        $nerdData->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings-nerdy.html');
        $data->nerdy = $template->replace($nerdData);

        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings-upgrade.html');
        $data->upgrade = $template->replace();

        $data->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;
        $data->pluginname = 'EasyIndex';
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-settings.html');
        $html = $template->replace($data);

        echo $html;
    }


    

    /**
     * Save the settings
     *
     * @param array $settings
     *
     * @return array|bool An array of errors or false/empty array if no errors
     */
    public function save($settings) {

        if (!isset($settings)) {
            return false;
        }

        $errors = array();
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'EasyIndex-settings')) {
            $errors[] = 'Nonce error while saving';
            return $errors;

        }
        $settings = stripslashes_deep($settings);

        $settings['defaultSlug'] = trim($settings['defaultSlug']);
        $settings['thumbDirectory'] = trim($settings['thumbDirectory']);

        /**
         * Check for a new slug but don't allow it if it's invalid
         * We should never get an invalid slug but it will make an awful mess if we do
         */
        $oldSlug = $this->defaultSlug;
        if ($settings['defaultSlug'] != $this->defaultSlug) {
            if (!$this->isValidSlug($settings['defaultSlug'])) {
                $settings['defaultSlug'] = $oldSlug;
            }
        }

        
        /**
         * Convert the thumb directory to absolute and regenerate the url if it changed
         * Make sure we have *something* to check and normalize it
         */
        if (empty($settings['thumbDirectory'])) {
            $location = $this->getThumbLocation();
            $settings['thumbDirectory'] = $location->directory;
            $settings['thumbUrl'] = $location->url;
        }
        $directory = $this->normalize($settings['thumbDirectory']);
        /**
         * Make sure we have an absolute path
         */
        if (!preg_match('%^(/|[a-z]:)%i', $directory)) {
            $directory = '/' . $directory;
        }

        // if ($directory != $this->thumbLocation->directory) {
        /**
         * Check that the thumbBase directory is valid i.e. either it or an ancestor is an existing writeable directory
         */
        $isWriteable = $this->isWriteCreatable($settings['thumbDirectory']);
        if ($isWriteable === true) {
            $settings['thumbDirectory'] = $directory;
            $settings['thumbLocation'] = new stdClass();
            $settings['thumbLocation']->directory = $directory;
            $settings['thumbLocation']->url = $settings['thumbUrl'];
        } else {
            $errors[] = $isWriteable;
            $settings['thumbLocation'] = $this->thumbLocation;
        }
        //}

        
        /**
         * Save simple stuff
         */
        foreach (self::$defaultSettings as $setting => $default) {
            if ($setting == 'bgGeneration') {
                $this->bgGeneration = isset($settings['bgGeneration']);
            } else if ($setting == 'debugLog') {
                $this->debugLog = isset($settings['debugLog']);
            } else if ($setting == 'disablePhoton') {
                $this->disablePhoton = isset($settings['disablePhoton']);
            } else if ($setting == 'generatorTimeout') {
                $this->generatorTimeout = (int)$settings['generatorTimeout'];
                if ($this->generatorTimeout == 0) {
                    $this->generatorTimeout = 2;
                }
            } else {
                if (isset($settings[$setting])) {
                    $this->{$setting} = $settings[$setting];
                }
            }
        }
        /**
         * Clean up spaces, replace spaces and "comma-space" with with commas and convert to an array
         */
        $excludesCss = preg_replace('/\s\s+/', ' ', trim($settings['excludecss']));
        $excludesCss = preg_replace('/\s*,\s*/', ',', $excludesCss);
        $this->excludeCss = explode(',', $excludesCss);


        /**
         * Save the term thumbnail sources. Clear entirely and rebuild
         */
        $this->termThumbSource = array();
        foreach ($settings['termThumbSource'] as $taxonomy => $terms) {
            foreach ($terms as $termID => $thumbUrl) {
                if (!empty($thumbUrl)) {
                    $this->termThumbSource[$taxonomy][$termID] = $thumbUrl;
                }
            }
        }

        if (empty($this->licenseKey)) {
            $this->isActivated = false;
        }
        $this->update();

        /**
         * If the default index slug has changed:
         *  - adjust $indexSlugs
         *  - register the new type
         *  - flush the rewrite rules
         */
        if ($this->defaultSlug != $oldSlug) {
            $this->adjustSlugs($oldSlug, $this->defaultSlug);
        }

        return $errors;
    }

    /**
     * It's possible that much like permalinks, the rewrite rules can get out of sync with the index slugs
     * i.e. either a slug doesn't have an entry in the rules, or a slug becomes redundant but remains in the rewrite rules
     * This should only happen when something unusual occurs (like a crash at a critical point)
     * This function reads all the indexes (including unpublished), rebuilds the index slugs in Settings and flushes the rewrite_rules so all index slugs have an entry
     * It's called via ajax from the Nerdy tab on the settings page
     */
    function resetSlugs() {
        /** @var wpdb $wpdb */
        global $wpdb;
        /** @var $EasyIndex EasyIndex */
        global $EasyIndex;

        /**
         * Include the default slug even if it's not used
         */
        $slugs = array($this->defaultSlug => true);

        /**
         * Read the index data for all indexes and store the slug
         */
        $q = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'easyindex'";
        $metas = $wpdb->get_col($q);
        foreach ($metas as $meta) {
            /** @var EasyIndexData $indexData */
            $indexData = unserialize($meta);
            if ($indexData) {
                $slugs[$indexData->indexSlug] = true;
            }
        }

        /**
         * Reset the slugs in Settings
         */
        $this->indexSlugs = array_keys($slugs);
        /**
         * Save the settings
         */
        $this->update();
        /**
         * And update the rewrite rules
         */
        $EasyIndex->registerPostType($this->defaultSlug);
        flush_rewrite_rules();

    }


    /**
     * The default slug or an index slug has changed
     *
     * If there is no longer any indexes using the old slug, remove it from $indexSlugs
     * If the new slug doesn't exist in $indexSlugs, add it
     *
     * Then check the menus and adjust if needed
     *
     * @param $oldSlug
     * @param $newSlug
     */
    function adjustSlugs($oldSlug, $newSlug) {
        /** @var wpdb $wpdb */
        global $wpdb;
        /** @var $EasyIndex EasyIndex */
        global $EasyIndex;


        $update = false;
        $slugExists = false;
        if ($oldSlug != $this->defaultSlug) {
            $q = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'easyindex'";
            $metas = $wpdb->get_col($q);
            foreach ($metas as $meta) {
                /** @var EasyIndexData $indexData */
                $indexData = unserialize($meta);
                if ($indexData && $indexData->indexSlug == $oldSlug) {
                    $slugExists = true;
                    break;
                }
            }
            if (!$slugExists) {
                $this->indexSlugs = array_diff($this->indexSlugs, array($oldSlug));
                $update = true;
            }
        }
        if ($newSlug != $this->defaultSlug && !in_array($newSlug, $this->indexSlugs)) {
            $this->indexSlugs[] = $newSlug;
            $update = true;
        }

        if ($update) {
            $this->update();
            $EasyIndex->registerPostType($this->defaultSlug);
            flush_rewrite_rules();
        }

    }

    /**
     * Make sure a new slug is valid
     * We should never get an invalid slug (it should be checked in the javascript) but let's be paranoid
     * The consequences of using an invalid slug are gonna be icky and might be really hard to recover from
     *
     * @param $slug
     *
     * @return bool
     */
    private function isValidSlug($slug) {
        return preg_match('/^[a-z\d-]{1,20}$/', $slug);
    }


    /**
     * Update a thumnail for a term in a primary index
     * We get passed the term ID, the taxonomy name and the image src
     *
     * Results returned to the settings page via JS
     */
    function updateTermThumb() {
        /** @var wpdb $wpdb */
        global $wpdb;
        /**
         * Do some sanity checks on the input
         */
        $termID = isset($_POST['termID']) ? (int)$_POST['termID'] : 0;
        $src = isset($_POST['src']) ? $_POST['src'] : '';
        $taxonomy = isset($_POST['taxonomy']) ? $_POST['taxonomy'] : '';
        $result = new stdClass();
        $result->status = 'FAIL';
        $result->error = 'Unknown error';
        if ($termID != 0 && $src != '') {
            /**
             * If we changed the thumbnail, we'll have to trash any existing thumbs for this taxonomy/term
             * Do this before generating the thumb for the settings page - the new settings thumb might just be the same as an index thumb
             */
            if (!empty($this->termThumbSource[$taxonomy][$termID]) && $this->termThumbSource[$taxonomy][$termID] != $src) {
                /**
                 * Get all existing indexes
                 */
                $q = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'easyindex'";
                $indexes = $wpdb->get_results($q);
                foreach ($indexes as $index) {
                    /** @var EasyIndexData $indexData */
                    $indexData = @unserialize($index->meta_value);
                    /**
                     * We can ignore the index if the data is crap, it's not the right taxonomy or if the index doesn't include the term we're setting the thumb for
                     */
                    if (!$indexData) {
                        continue;
                    }
                    if ($indexData->indexTaxonomy != $taxonomy) {
                        continue;
                    }
                    if (!in_array($termID, $indexData->indexTerms) && !$indexData->indexAllTerms) {
                        continue;
                    }
                    /**
                     * There potentially exists a thumbnail of the previous source for this taxonomy/term/index
                     * Delete it if it's there - it will get regenerated from the new source
                     */
                    $thumbPath = $this->thumbLocation->directory . "/{$indexData->primary->ixThumbSize}/{$indexData->indexTaxonomy}-{$termID}.jpg";
                    @unlink($thumbPath);
                }
            }

            /**
             * Get the Url for a thumbnail.
             * Always regenerate the thumb because we can't be sure that the source is the same image even if it's the same name
             */
            $thumb = new EasyIndexThumbnail(0, $src);
            $thumbUrl = $thumb->getUrl('100x100', "$taxonomy-$termID", EasyIndexThumbnail::REGENERATETHUMB | EasyIndexThumbnail::APPENDMTIME);
            if ($thumbUrl == null) {
                $result->error = $thumb->getError();
            } else {
                $result->status = 'OK';
                unset($result->error);
                /**
                 * Save the new thumbnail
                 */
                $result->termID = $termID;
                $result->taxonomy = $taxonomy;
                $result->src = $src;
                $result->thumb = $thumbUrl;
                $this->termThumbSource[$taxonomy][$termID] = $src;
                $this->update();
            }
        }
        wp_send_json($result);
    }

    public function update() {
        update_option('EasyIndex', $this);
    }


    /**
     * Get default thumbnail the directory ({wordpress upload directory}/easyindex)
     * If there's no Wordpress upload directory, the Wordpress config has serious issues than we can't do much about it here
     *
     * @return stdClass
     */
    private function getThumbLocation() {
        $uploadDirectory = wp_upload_dir();
        if ($uploadDirectory['error'] !== false) {
            
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return '';
        }
        $location = new stdClass();
        $location->directory = $this->normalize($uploadDirectory['basedir']) . "/easyindex";
        $location->url = $uploadDirectory['baseurl'] . "/easyindex";

        return $location;
    }


    /**
     * Prepends the Wordpress install path to create an absolute path
     *
     * @param string $relative The path relative to ABSPATH. We expect it to begin with a directory separator
     *
     * @return string
     */

    /**
     * public function getAbsoluteThumbDirectory($relative = null) {
     * if (empty($relative)) {
     * $relative = $this->thumbDirectory;
     * }
     * return rtrim(ABSPATH, '/\\') . $relative;
     * }
     **/

    /**
     * Check that a directory exists and is writable OR that if it doesn't exist, then its parent exists and is writeable
     *
     * @param string $directory The directory to check (must always have a leading directory separator)
     *
     * @return bool|string  Returns TRUE if OK or an error message
     */
    private function isWriteCreatable($directory) {
        if (file_exists($directory)) {
            if (is_dir($directory)) {
                if (is_writeable($directory)) {
                    return true;
                } else {
                    return "$directory is not writeable";
                }
            } else {
                return "$directory is not a directory";
            }
        } else {
            $parent = dirname($directory);
            if (!file_exists($parent)) {
                return "Cannot create $directory because $parent does not exist";
            }
            if (is_writeable($parent)) {
                return true;
            }
            return "Cannot create $directory because $parent is not writeable";
        }
    }

    /**
     * Change backslashes to forward slashes and remove trailing slash
     *
     * @param $path
     *
     * @return string
     */
    private function normalize($path) {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}

