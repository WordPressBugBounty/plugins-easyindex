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
 * Class EasyIndex
 */
class EasyIndex {

    const VERSIONCHECKURL = 'https://order.easyindexplugin.com';
    const DIAGNOSTICS_URL = 'https://easyindexplugin.com/api/support.php';
    const HELP_URL = 'https://easyindexplugin.com/kb';

    const EASYINDEX_GENERATE = 'easyindex_generate';
    const EASYINDEX_GENERATEALL = 'easyindex_generateall';
    const META_KEY = 'easyindex';


    public static $EasyIndexDir;
    public static $EasyIndexUrl;

    public static $pluginVersion;

    /** @var boolean TRUE if Yoast SEO plugin is active */
    public static $yoastActive;

    /** @var  string The plugin name for auto updates */
    private static $pluginName;

    /** @var EasyIndexSettings */
    private $settings;


    private $log;

    private $isIndex = false;
    private $isSecondaryIndex = false;

    /** @var  EasyIndexIndex */
    private $index;
    /**
     * @var EasyIndexData
     */
    private $indexData;

    private $indexID;
    private $term;

    private $uiVersion = '1.11.4';
//    private $mceVersion = '';
//    private $wpVersion = '';

    private $navMenuItems = array();


    /**
     * EasyIndex constructor.
     */
    function __construct() {


        /**
         * For convenience
         * The standard WP functions for getting the plugin's dir name and URL won't work if we symlink the latest version into the plugin directory like our test suite does
         */
        self::$pluginVersion = "1.1.1704";
        self::$pluginName = "easyindex/easyindex.php";
        self::$EasyIndexDir = trailingslashit(WP_PLUGIN_DIR) . "easyindex";

        $pluginURL = WP_PLUGIN_URL . "/easyindex";
        /**
         * Fix up HTTP protocol for admin using SSL
         */
        self::$EasyIndexUrl = is_ssl() ? preg_replace('%^http://%i', 'https://', $pluginURL) : $pluginURL;


        add_action('plugins_loaded', array($this, 'pluginsLoaded'));
    }


    /**
     *
     */
    function pluginsLoaded() {

        /** @noinspection PhpUndefinedClassInspection */
        $this->log = EasyIndexLogger::getLog('easyindex');


        /**
         * If EasyIndex Plus is installed and active, this plugin can be uninstalled
         * If EasyIndex Beta is installed and active, then don't do any more processing for the free plugin
         */
        $plugins = get_option('active_plugins', array());
        if (in_array("easyindexplus/easyindexplus.php", $plugins)) {
            add_action('admin_notices', array($this, 'showPlusActive'));
            spl_autoload_unregister("EasyIndexAutoload");
            return;
        }
        if (in_array("easyindexbeta/easyindexbeta.php", $plugins)) {
            add_action('admin_notices', array($this, 'showBetaActive'));
            spl_autoload_unregister("EasyIndexAutoload");
            return;
        }


        if (is_admin()) {
            add_action('admin_menu', array($this, 'addMenu'));
            add_action('admin_init', array($this, 'initialiseAdmin'));
        }


        add_action('init', array($this, 'initialise'));
        EasyIndex::$yoastActive = defined("WPSEO_VERSION");
    }

    /**
     *
     * @param $slug
     */
    function registerPostType($slug) {
        $labels = array(
            'name'          => __('Indexes', 'easyindex'),
            'singular_name' => __('Index', 'easyindex'),
            'add_new'       => __('Add New', 'easyindex'),
            'add_new_item'  => __('Add New Index', 'easyindex'),
            'all_items'     => __('All Indexes', 'easyindex'),
            'edit_item'     => __('Edit Index', 'easyindex'),
            'new_item'      => __('New Index', 'easyindex'),
            'view_item'     => __('View Index', 'easyindex'),
            'not_found'     => __('No indexes found', 'easyindex'),
            'menu_name'     => __('Indexes', 'easyindex'),
        );
        $args = array(
            'labels'              => $labels,
            'hierarchical'        => true,
            'description'         => __('EasyIndex index page'),
            'supports'            => array('title', 'genesis-layouts'),
            'taxonomies'          => array(),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 20,
            'show_in_nav_menus'   => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'can_export'          => true,
            'rewrite'             => array('slug' => $slug, 'with_front' => true),

        );

        add_filter('easyindex_rewrite_rules', array($this, 'filterRewriteRules'));
        register_post_type('easyindex', $args);
    }

    function initialise() {
        /** @var $wp WP */
        global $wp;

        $this->settings = EasyIndexSettings::getInstance();
        /**
         * Turn on PHP logging if needed
         */
        if ($this->settings->debugLog) {
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
        }

        $this->registerPostType($this->settings->defaultSlug);
        add_filter('post_type_link', array($this, 'postLink'), 100, 2);

        /**
         * Ignore ajax requests that don't concern us (after we've registered the index post type)
         */
        if (defined('DOING_AJAX')) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            if (strncmp($action, 'easyindex', 9) !== 0) {
                $widgetID = isset($_REQUEST['widget-id']) ? $_REQUEST['widget-id'] : '';
                if (strncmp($widgetID, 'easyindex', 9) != 0) {
                    return;
                }
            }
        }

        wp_register_style('easyindexUI', self::$EasyIndexUrl . "/ui/$this->uiVersion/easyindexUI.css", array(), self::$pluginVersion);


        add_action('wp_ajax_easyindexMore', array($this, 'getMore'));
        add_action('wp_ajax_nopriv_easyindexMore', array($this, 'getMore'));


        if (preg_match('%/easyindex-diagnostics(?:/([^?/]+))?%', $_SERVER['REQUEST_URI'])) {
            add_action('wp_headers', array($this, 'showDiagnostics'), 0);
        }

        /**
         * Everything past here is not needed on admin pages, but may be required for ajax requests (which are always "in_admin")
         */
        if (!defined('DOING_AJAX') && is_admin()) {
            return;
        }

        $wp->add_query_var('term');
        add_filter('template_include', array($this, 'templateInclude'));
        add_filter('posts_where', array($this, 'checkWhere'), 0, 2);
        add_filter('the_content', array($this, 'theContent'));
        add_filter('wp_setup_nav_menu_item', array($this, 'setupNavMenuItem'));
        add_filter('wp_get_nav_menu_items', array($this, 'getNavMenuItems'), 0, 3);
        add_action('easyindex-cache-posts', array($this, 'cachePosts'));
        add_action('easyindex-category-terms', array($this, 'cacheCategoryTerms'));

        /**
         * If we want to disable Photon for index thumbs, hook into the Photon image skip filter
         */
        if ($this->settings->disablePhoton) {
            add_filter('jetpack_photon_skip_image', array($this, 'skipPhoton'), 10, 3);
        }

    }


    /**
     * EasyIndex Plus is active - show a message
     */
    function showPlusActive() {
        echo <<<EOD
<div id="message" class="updated">
<p>EasyIndex Plus is installed and active. You can now safely uninstall the free version of EasyIndex</p>
</div>
EOD;
    }

    /**
     * EasyIndex Beta is active - show a message
     */
    function showBetaActive() {

        $plus = 'Free';

        echo <<<EOD
<div id="message" class="updated">
<p>EasyIndex Beta is installed and active. EasyIndex $plus is disabled</p>
</div>
EOD;
    }


    /**
     * Initialise stuff we need in admin
     */
    function initialiseAdmin() {
        if ($this->settings->displayHelp) {
            $this->settings->displayHelp = false;
            $this->settings->update();
            wp_redirect('admin.php?page=EasyIndexHelp');

        }

        /**
         * Need to be able to manage options to do any admin stuff
         */
        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('wp_ajax_easyindexCustomCSS', array($this, 'updateCustomCSS'));
        add_action('wp_ajax_easyindexTermThumb', array($this->settings, 'updateTermThumb'));
        add_action('wp_ajax_easyindexResetSlugs', array($this->settings, 'resetSlugs'));
        add_action('wp_ajax_easyindexCountTaxonomies', array($this, 'countTaxonomies'));
        add_action('wp_ajax_easyindexGenerateThumbs', array($this, 'generateThumbs'));
        add_action('wp_ajax_easyindexSaveImage', array($this, 'saveImage'));

        add_filter('plugin_action_links_' . self::$pluginName, array($this, 'pluginActionLinks'), 10);

        add_action('wp_ajax_easyindexSupport', array($this, 'sendSupport'));
        add_action('update-custom_easyindex-update', array($this, 'forceUpdate'));

        add_filter('wp_unique_post_slug', array($this, 'uniquePostSlug'), 10, 6);

        $this->settings = EasyIndexSettings::getInstance();

        add_action('load-post.php', array($this, 'loadPostAdmin'));
        add_action('load-post-new.php', array($this, 'loadPostAdmin'));


        add_action('save_post', array($this, 'savePost'), 10, 2);

        add_action('widgets_init', array($this, "registerSidebar"), 999);
        add_filter('is_protected_meta', array($this, 'protectedMeta'), 10, 2);
    }

    function addMenu() {
        $this->settings = EasyIndexSettings::getInstance();
        $mainHook = add_menu_page('EasyIndex Settings', 'EasyIndex', 'manage_options', 'EasyIndex', array($this->settings, 'showPage'), self::$EasyIndexUrl . '/images/logo17.png');
        add_submenu_page('EasyIndex', 'EasyIndex Settings', 'Settings', 'manage_options', 'EasyIndex', array($this->settings, 'showPage'));
        $helpHook = add_submenu_page('EasyIndex', 'EasyIndex Help', 'Help', 'manage_options', 'EasyIndexHelp', array($this, 'showHelp'));
        add_action("load-$mainHook", array($this, 'loadSettingsPageScripts'));
        add_action("load-$helpHook", array($this, 'loadHelpPageScripts'));
    }


    /**
     * Some plugins and themes have really badly designed CSS that loads on pages that it has no business on
     * and has such explicit specificity that it's almost impossible to override without making absolutely everything !important
     * They shouldn't be loading on our admin pages - so dequeu them
     */
    function dequeueCss() {
        /** @noinspection PhpParamsInspection */
        wp_dequeue_style($this->settings->excludeCss);
    }

    /**
     * Hook into the generated rewrite rules for our custom post type.
     * The generated rules have all sorts of stuff we don't need
     * We only need two rules for each rewrite slug we've used: one for a primary index and one for a secondary.
     * Drop anything else that has been generated
     *
     * @return array
     */
    function filterRewriteRules() {
        global $permalink_structure;

        if (preg_match('#^/([^/%]+)/%#i', $permalink_structure, $regs)) {
            $prefix = $regs[1] . '/';
        } else {
            $prefix = '';
        }

        $settings = EasyIndexSettings::getInstance();
        $rules = array();
        $indexSlugs = $settings->indexSlugs;
        $indexSlugs[] = $settings->defaultSlug;
        foreach ($indexSlugs as $slug) {
            $rules["$prefix$slug/([^/]+)/?\$"] = "index.php?easyindex=\$matches[1]";
            $rules["$prefix$slug/([^/]+)/(.+)/?\$"] = "index.php?easyindex=\$matches[1]&term=\$matches[2]";
        }
        return $rules;
    }

    /**
     * setupNavMenuItem() allows sub menu items that are taxonomy terms which are part of an index to be specified by selecting the term in the admin Nav Menu
     *
     * So if an index is on "Categories", you could specify (when creating a menu in admin) that the "Appetizers" category is a sub menu of the primary index menu entry.
     * Normally  this would link to something like ".../categories/appetizers" but this method tweaks the destination URL so it points to the "Appetizers" secondary index
     * (as long as the menu item's ancestors are all EasyIndex's)
     *
     * The alternative is to manually create "Custom" sub menu items and type in the text and url for each sub menu item you want
     *
     * Pick up any menu items that are sub-items of an easyindex primary index menu item and tweak the url to point to the EasyIndex page.
     *
     * Also picks up any sub menu items which are in an unbroken chain of easyindex menu item descendents
     * This means that any (otherwise valid index) menu items (and their otherwise valid descendents) that are directly
     * descended from an item which isn't a valid index, are also not valid and won't be processed.  This is probably reasonable.
     *
     * Hooks into the wp_setup_nav_menu_item filter
     *
     * @param $menuItem
     *
     * @return mixed
     */
    function setupNavMenuItem($menuItem) {
        /** @var WP_Rewrite $wp_rewrite */
        global $wp_rewrite;

        /**
         * Store the primary easyindex url
         */
        //if ($menuItem->object == $this->settings->defaultSlug) {
        if ($menuItem->object == 'easyindex') {
            $this->navMenuItems[$menuItem->ID] = new stdClass();
            $this->navMenuItems[$menuItem->ID]->url = untrailingslashit($menuItem->url);
            $this->navMenuItems[$menuItem->ID]->postID = $menuItem->object_id;
        } else {
            if ($menuItem->type == 'taxonomy') {
                /**
                 * If this is a taxonomy item
                 *   AND it has a parent which is an EasyIndex
                 *   AND it's the same taxonomy as the parent taxonomy
                 *   AND this term has been selected for inclusion in this index
                 * make the URL point to the EasyIndex page for the term
                 */

                if (isset($this->navMenuItems[$menuItem->menu_item_parent])) {
                    $parentItem = $this->navMenuItems[$menuItem->menu_item_parent];
                    if (!isset($parentItem->indexData)) {
                        $parentItem->indexData = get_post_meta($parentItem->postID, 'easyindex', true);
                    }

                    if ($parentItem->indexData instanceof EasyIndexData) {
                        /** @var EasyIndexData */
                        if ($parentItem->indexData->indexTaxonomy == $menuItem->object) {
                            if ($parentItem->indexData->indexAllTerms || in_array($menuItem->object_id, $parentItem->indexData->indexTerms)) {
                                $terms = get_terms($menuItem->object, array('include' => $menuItem->object_id));
                                if (is_array($terms) && count($terms) == 1) {
                                    if ($wp_rewrite->using_permalinks()) {
                                        $menuItem->url = "$parentItem->url/" . trailingslashit($terms[0]->slug);
                                    } else {
                                        $menuItem->url = "$parentItem->url&term=" . $terms[0]->slug;
                                    }
                                    /**
                                     * Insert this item as a "parent item" but with the base parent's data
                                     */
                                    $this->navMenuItems[$menuItem->ID] = $parentItem;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $menuItem;
    }

    /**
     * Automatically add all the taxonomy terms included in an index as sub menu items under a primary index menu item
     *
     * Only adds if there are items in the taxonomy term (so if if there are no posts in the tem, then the menu item isn't displayed)
     *
     * Somewhat of a hack since there is no clean way to adjust the menu item list at the point it's created by WP
     * It may fail if the theme or other plugins expect perfectly valid data in the menu item list past this point since we have to make up some data.
     * Since there is no actual post for secondary indexes, we have to create something that looks like a real post (but isn't)
     *
     * @param $items
     *
     * @return array
     */
    function getNavMenuItems($items) {
        /** @var WP_Rewrite $wp_rewrite */
        global $wp_rewrite;

        $order = 1;

        $newItems = array();
        /** @var WP_Post $item */
        foreach ($items as $item) {
            $item->menu_order = $order;
            $order++;
            $newItems[] = $item;
            /** @noinspection PhpUndefinedFieldInspection */
            if ($item->object == 'easyindex') {
                $indexID = get_post_meta($item->ID, '_menu_item_object_id', true);
                /** @var EasyIndexData $menuIndex */
                $menuIndex = get_post_meta($indexID, 'easyindex', true);
                if ($menuIndex instanceof EasyIndexData) {
                    if ($menuIndex->ixAutoMenu) {
                        $terms = get_terms(array($menuIndex->indexTaxonomy), array('hide_empty' => true));
                        foreach ($terms as $term) {
                            if ($menuIndex->indexAllTerms || in_array($term->term_id, $menuIndex->indexTerms)) {
                                $newItem = clone $item;
                                $newItem->menu_order = $order;
                                $order++;

                                // object_id?
                                // menu_item_parent
                                // menu_order
                                // The actual value of this isn't important, but it needs to be different from every existing item
                                // check the names below that aren't defined for WP_Post
                                // Can we make it non numeric?
                                $newItem->ID = $item->ID * 1000;
                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->db_id = $newItem->ID;

                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->menu_item_parent = $item->ID;
                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->object = $menuIndex->indexTaxonomy;
                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->type = 'taxonomy';
                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->type_label = $menuIndex->indexTaxonomy;
                                /** @noinspection PhpUndefinedFieldInspection */
                                $newItem->title = $term->name;
                                if ($wp_rewrite->using_permalinks()) {
                                    /** @noinspection PhpUndefinedFieldInspection */
                                    $newItem->url = "$newItem->url/" . trailingslashit($term->slug);
                                } else {
                                    /** @noinspection PhpUndefinedFieldInspection */
                                    $newItem->url = "$newItem->url&term=" . $term->slug;
                                }

                                $newItems[] = $newItem;
                            }
                        }
                    }
                }
            }


        }
        return $newItems;
    }

    /**
     * Pick up whether the page is an EasyIndex page
     * Make it a "single page" since there seems to be no other way to treat a custom post type as a "page"
     *
     * Also remove the post_name restraint (will only be on secondary index pages).
     *
     * This is done really early in the processing chain and we can set all the flags and stuff we need here
     *
     * @param $where
     * @param $query
     *
     * @return mixed
     */
    function checkWhere($where, $query) {
        if (get_query_var('post_type') == 'easyindex') {
            $query->is_page = true;
            $query->is_single = false;

            $this->term = get_query_var('term');
            $this->isIndex = true;
            $this->isSecondaryIndex = !empty($this->term);
        }
        return $where;
    }

    /**
     * WP 4.2+ has a bug when creating unique names for pages with empty titles - it adds a spurious "-2"
     * So remove it on index posts if it's there
     *
     * @param $slug
     * @param $postID
     * @param $postStatus
     * @param $postType
     * @param $postParent
     * @param $originalSlug
     *
     * @return mixed
     */
    //return apply_filters( 'wp_unique_post_slug', $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug );
    function uniquePostSlug(
        $slug, /** @noinspection PhpUnusedParameterInspection */
        $postID, /** @noinspection PhpUnusedParameterInspection */
        $postStatus, $postType, /** @noinspection PhpUnusedParameterInspection */
        $postParent, $originalSlug
    ) {
        if ($postType == 'easyindex' && $slug != $originalSlug && preg_match('/^(\d+)-\d+$/', $slug, $regs)) {
            $slug = $regs[1];
        }
        return $slug;
    }

    /**
     * Tweak an index permalink.
     * Because indexes are custom posts but we want them to behave like pages, we need to change a post-type permalink to a page link (i.e. at the top level without any extra stuff)
     * Also, the permalink WP generates will have the default rewrite slug. Because we allow per-index slugs, we may have to change the slug in the generated permalink
     *
     * @param $permalink
     * @param $post
     *
     * @return string
     */
    function postLink($permalink, $post) {
        /** @var EasyIndexData $indexData */
        $indexData = get_post_meta($post->ID, 'easyindex', true);
        if ($indexData) {
            $parsed = parse_url($permalink);
            $parsed['path'] = preg_replace("%^(.*?)/{$this->settings->defaultSlug}/%i", "$1/$indexData->indexSlug/", $parsed['path']);
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
            $host = isset($parsed['host']) ? $parsed['host'] : '';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $path = $parsed['path'];
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            return "$scheme$host$port$path$query";
        }
        return $permalink;
    }


    /**
     * Called before the post admin page is loaded
     * Queue up all the stuff we need
     * Remove the post from the object cache
     */
    function loadPostAdmin() {
        add_action('add_meta_boxes', array($this, 'addMetaBox'));
        /**
         * If we need to remove badly designed CSS, do it really, really late, hopefully after all the dodgy scripts have been enqueued
         */
        if (!empty($this->settings->excludeCss)) {
            add_action('admin_enqueue_scripts', array($this, 'dequeueCss'), 32767);
        }
    }

    /**
     * Adds the Index settings meta box if this is an index or the Index Image meta box if it's something else
     */
    function addMetaBox() {
        global $post_type;

        /**
         * If the post is an EasyIndex index, setup the Index Details meta
         */
        if (!empty($post_type) && $post_type == 'easyindex') {
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_script('jquery-ui-button');

            wp_enqueue_style('easyindexUI');

            
            wp_enqueue_script('easyindex-indexedit', self::$EasyIndexUrl . '/js/easyindex-indexedit.min.js', array('jquery', 'jquery-ui-dialog', 'jquery-ui-autocomplete', 'jquery-ui-spinner', 'jquery-ui-button', 'jquery-ui-progressbar'), self::$pluginVersion, true);
            wp_enqueue_style('easyindex-indexedit', self::$EasyIndexUrl . '/css/easyindex-indexedit.min.css', array('easyindexUI'), self::$pluginVersion);
            wp_enqueue_script('easyindex-select2', self::$EasyIndexUrl . '/select2/select2.min.js', array('jquery'), self::$pluginVersion, true);
            wp_enqueue_style('easyindex-select2', self::$EasyIndexUrl . '/select2/select2.css', array(), self::$pluginVersion);

            add_meta_box('easyindex-index', 'Index Settings', array('EasyIndexIndexEdit', 'show'), 'easyindex', 'normal', 'high');

        } else {
            wp_enqueue_script('easyindex-editpost', self::$EasyIndexUrl . '/js/easyindex-postedit-min.js', array('media-models'), self::$pluginVersion, true);
            add_meta_box('easyindex-image', 'Index Image', array($this, 'imageMetaBox'), null, 'side', 'default');
        }
    }

    /**
     * Trap the WP template selection ("template_include" hook) and if this post is an EasyIndex
     * then (possibly) replace the normal template with the one selected for this index
     *
     * Also enqueue the JS for masonry layouts if necessary
     *
     * The indexData is also retrieved here
     *
     * @param $wpTemplate
     *
     * @return string
     */
    function templateInclude($wpTemplate) {
        /** @var $post WP_Post */
        global $post;


        if (!$this->isIndex || empty($post)) {
            return $wpTemplate;
        }

        /**
         * Add the index and custom CSS very late so it overrides everything else
         */

        /** @var $indexData EasyIndexData */
        $this->indexData = get_post_meta($post->ID, 'easyindex', true);

        /**
         * This shouldn't happen - but let's be paranoid...
         */
        if (empty($this->indexData)) {
            $this->indexData = new EasyIndexData($this->settings->defaultSlug);
        }

        /**
         * For convenience and code inspection
         */
        $indexData = $this->indexData;

        /**
         * Secondary indexes may have a custom title
         */
        if ($this->isSecondaryIndex) {
            add_filter('the_title', array($this, 'customiseTitle'), 100, 2); 
            add_filter('wpseo_title', array($this, 'customiseTitle'), 100, 1);
            $styleID = empty($_REQUEST['eistyle']) ? $indexData->secondary->ixStyleID : $_REQUEST['eistyle'];
        } else {
            $styleID = empty($_REQUEST['eistyle']) ? $indexData->primary->ixStyleID : $_REQUEST['eistyle'];
        }
        $style = EasyIndexStyles::getStyle($styleID);

        $this->indexID = $post->ID;
        $this->index = new EasyIndexIndex($indexData, $this->isSecondaryIndex, $post->ID, $this);
        add_action('wp_head', array($this->index, 'addCSS'), 100);

        wp_enqueue_style('easyindex-style', $style->getCSSUrl(), array(), self::$pluginVersion . '-' . $style->version);

        $dependencies = array('jquery', 'jquery-ui-widget');
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-widget');

        if ($style->isMasonry || $style->styleType == 'gallery') {
            wp_enqueue_script('easyindex-index', self::$EasyIndexUrl . '/js/easyindex-index.min.js', $dependencies, self::$pluginVersion, true);
        }



        /**
         * Trap the body class hook and add a class so we can tweak CSS outside of the index itself
         */
        add_filter('body_class', array($this->index, 'addBodyClass'));
        /**
         * Select the appropriate template
         */
        if ($this->isSecondaryIndex) {
            $template = $indexData->secondary->ixTemplate == 'default' ? 'page.php' : $indexData->secondary->ixTemplate;
        } else {
            $template = $indexData->primary->ixTemplate == 'default' ? 'page.php' : $indexData->primary->ixTemplate;
        }
        $templateFile = get_template_directory() . "/$template";
        /**
         * Make sure it exists - if the theme was changed, it may not have this template
         * If it's not there - fall back to whatever WP picked
         */
        if (!file_exists($templateFile)) {
            $templateFile = $wpTemplate;
        }

        return $templateFile;
    }




    /**
     * @param $postID
     * @param $thumbID
     *
     * @return string
     */
    private function getSaveImageHtml($postID, $thumbID) {
        $nonce = wp_create_nonce('easyindexSaveImage-' . $postID);
        /**
         * Set up the default html
         */
        $html = <<<EOD
<p class="hide-if-no-js"><a title="Set index image" href="#" class="ei-image-link" data-nonce="$nonce" id="eiSetImage">Set index image</a></p>
EOD;

        if (!empty($thumbID) && is_numeric($thumbID)) {
            $thumbHTML = wp_get_attachment_image($thumbID, array(266, 266));
            if (!empty($thumbHTML)) {
                /**
                 * A little bit of silliness so we don't have to load extra CSS just for one property
                 */
                $thumbHTML = str_replace('<img', '<img style="max-width:100%"', $thumbHTML);
                $html = <<<EOD
<p class="hide-if-no-js"><a title="Remove index image" href="#">$thumbHTML</a></p>
<p class="hide-if-no-js"><a href="#" id="eiRemoveImage" class="ei-image-link" data-nonce="$nonce">Remove index image</a></p>
<input type="hidden" name="ei-has-image" value="1">
EOD;
            }
        }
        return $html;

    }

    /**
     * Display the Index Image meta box to allow image selection
     *
     * @param object|int $post Post object or ID
     */
    function imageMetaBox($post) {

        /** @var WP_Post $post */
        $post = get_post($post);
        $thumbID = get_post_meta($post->ID, 'easyindexSourceUrl', true);

        echo $this->getSaveImageHtml($post->ID, $thumbID);
    }

    /**
     * We have explicitly selected an image in post edit/add to use as the index image
     * Save the attachment ID in the postmeta as the easyindexSourceUrl
     */
    function saveImage() {
        $thumbID = isset($_POST['thumbID']) ? (int)$_POST['thumbID'] : 0;
        $postID = isset($_POST['postID']) ? (int)$_POST['postID'] : 0;
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';

        $result = new stdClass();
        $result->status = 'FAIL';

        if ($postID == 0) {
            $result->error = 'Invalid post ID';
            wp_send_json($result);
        }

        if ($thumbID == 0) {
            $result->error = 'Invalid attachment ID';
            wp_send_json($result);
        }

        if (!wp_verify_nonce($nonce, 'easyindexSaveImage-' . $postID)) {
            $result->error = 'Invalid nonce';
            wp_send_json($result);
        }

        if ($thumbID > 0) {
            update_post_meta($postID, 'easyindexSourceUrl', $thumbID);
        } else {
            delete_post_meta($postID, 'easyindexSourceUrl');
            $result->remove = true;
        }

        /**
         * Invalidate the thumb souce
         */
        update_post_meta($postID, 'easyindexInvalidate', '1');

        $result->html = $this->getSaveImageHtml($postID, $thumbID);
        $result->status = 'OK';
        wp_send_json($result);
    }

    /**
     * Fix for bad design choice. EasyIndex meta keys should have been "protected keys"  by using a leading underscore.
     * Unprotected meta keys are written to the post edit page and updated on publish, potentially overwriting EasyIndex updates
     * Was overwriting the easyindexSourceUrl on Publish when an index image was selected
     *
     * @param boolean $protected Is the key already protected?
     * @param string $meta_key The key - EasyIndex keys begin with "easyindex"
     *
     * @return bool
     */
    function protectedMeta($protected, $meta_key) {
        if (!$protected && strpos($meta_key, "easyindex") === 0) {
            $protected = true;
        }
        return $protected;
    }

    /**
     * @param boolean $value The original value
     * @param string $src An image source
     * @param string $tag
     * @return bool Returns TRUE if we don't want the image served by Photon
     */
    function skipPhoton($value, $src, $tag) {
        if (strpos($src, $this->settings->thumbLocation->url) !== false) {
            return true;
        }
        return $value;
    }

    /**
     * A post has changed - save the settings if it's an index page
     * If it's not an  index page, trash any EasyIndex source Url meta data unless it's been explicitly set in case the image was changed
     * (the easyindexSourceUrl meta data will get recreated automatically if it's needed)
     * In any case, set a flag so the thumb will be re-generated
     *
     * @param integer $postID
     * @param WP_Post $post
     */
    function savePost($postID, $post = null) {
        global $post_type;

        if ($post_type == 'easyindex') {
            $editIndex = new EasyIndexIndexEdit();
            $editIndex->save($postID, $post);
        } else {
            if (!isset($_POST['ei-has-image']) && (!isset($_POST['action']) || $_POST['action'] != 'heartbeat')) {
                delete_post_meta($postID, 'easyindexSourceUrl');
            }
            update_post_meta($postID, 'easyindexInvalidate', '1');
        }
    }





    /**
     * Recount the posts in each taxonomy in case they get out of sync
     */
    function countTaxonomies() {
        $taxonomy = EasyIndexTaxonomies::getInstance();
        $taxonomy->countTerms();

    }

    function generateThumbs() {
        if (isset($_POST['check'])) {
            EasyIndexGenerateThumbs::backgroundCheck();
        }
        $postID = isset($_POST['postID']) ? (int)$_POST['postID'] : 0;
        $timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;
        $generate = EasyIndexGenerateThumbs::getInstance($postID, $timestamp);
        $generate->run($timestamp);
    }

    function showHelp() {
        $help = new EasyIndexHelp();
        $help->show();
    }

    /**
     * Load the help page scripts and styles
     */
    function loadHelpPageScripts() {
        wp_enqueue_style('easyindex-help', self::$EasyIndexUrl . '/css/easyindex-help.min.css', array(), self::$pluginVersion);
    }

    /**
     * Load the EasyIndex settings page scripts and styles
     */
    function loadSettingsPageScripts() {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-ui-button');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-spinner');
        wp_enqueue_script('jquery-ui-widget');

        wp_enqueue_style('thickbox');
        wp_enqueue_script('thickbox');
        wp_enqueue_style('easyindexUI');
        wp_enqueue_media();
        wp_enqueue_style('easyindex-settings', self::$EasyIndexUrl . '/css/easyindex-settings.min.css', array('easyindexUI'), self::$pluginVersion);
        wp_enqueue_script('easyindex-settings', self::$EasyIndexUrl . '/js/easyindex-settings.min.js', array('jquery-ui-dialog', 'jquery-ui-autocomplete', 'jquery-ui-tabs', 'jquery-ui-button', 'jquery-ui-spinner'), self::$pluginVersion, true);

        $this->settings = EasyIndexSettings::getInstance();

        /**
         * If we need to remove badly designed CSS, do it really, really late, hopefully after all the dodgy scripts have been enqueued
         */
        if (!empty($this->settings->excludeCss)) {
            add_action('admin_enqueue_scripts', array($this, 'dequeueCss'), 32767);
        }

    }


    /**
     * Adds a "Settings" link to the EasyIndex action links on the plugins page
     *
     * @param array $links
     *
     * @return array
     */
    function pluginActionLinks($links) {
        $links[] = '<a href="admin.php?page=EasyIndex">' . __('Settings') . '</a>';
        return $links;
    }

    /**
     * Return the term for the secondary index
     * @return mixed
     */
    function getTerm() {
        return $this->term;
    }

    /**
     * This is called in the "the_title" filter and sets the secondary index title
     * It also passes the term to the Index display
     *
     * @param $title  string
     * @param $postID integer
     *
     * @return string
     */
    function customiseTitle($title, $postID = null) {
        if ($postID == $this->indexID || is_null($postID)) {
            $title = $this->index->customiseTitle($this->term);
        }
        return $title;
    }

    /**
     * If this is an EasyIndex, generate the content
     * If the post_content contains the EasyIndex placeholder, replace it with the index HTML (which allows other plugins to add content around ours i.e. the placeholder)
     * If there is no placeholder it's a legacy post so ignore the existing content and return the index HTML
     *
     * @param string $content
     *
     * @return string
     */
    function theContent($content) {
        /** @var WP_Post $post */
        global $post;
        if ($this->isIndex) {
            if ($post->post_type == 'easyindex') {
                $indexHTML = $this->isSecondaryIndex ? $this->index->displaySecondary() : $this->index->displayPrimary();
                if (strpos($content, "%{easyindex}%") !== false) {
                    $content = str_replace("%{easyindex}%", $indexHTML, $content);
                } else {
                    $content = $indexHTML;
                }

            }
        }
        return $content;
    }

    /**
     * Add all the posts in $postIDs to the cache so we don't have to do a separate read for each post
     * There is no clean way to instantiate a WP_Post (and thus cache the data) without WP re-reading the row, even if you have all the data !
     * So sanitise the post ourselves and don't bother using WP_Post. Then we add each post to the WP cache
     *
     * If we don't do something like this, getting the title for every thumb on a page or widget would require a separate DB access to get each post
     *
     * @param array $postIDs
     */
    function cachePosts($postIDs) {
        /** @var wpdb $wpdb */
        global $wpdb;

        $ids = implode(',', $postIDs);
        if ($ids == '') {
            $ids = 0;
        }

        $q = "SELECT * FROM $wpdb->posts WHERE ID in ($ids)";
        $posts = $wpdb->get_results($q);
        foreach ($posts as $post) {
            $post = sanitize_post($post, 'raw');
            wp_cache_add($post->ID, $post, 'posts');
        }
    }

    /**
     * If the permalink uses categories, cache the terms and relationships so we don't have to do a (very expensive) 3 way join for each post
     *
     * @param $postIDs
     */
    function cacheCategoryTerms($postIDs) {
        /** @var wpdb $wpdb */
        global $wpdb;

        $ids = implode(',', $postIDs);
        if ($ids == '') {
            $ids = 0;
        }

        $permalink = get_option('permalink_structure');
        if (strpos($permalink, '%category%') !== false) {
            $q = "SELECT object_id, t.*, tt.* FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt ON tt.term_id = t.term_id ";
            $q .= "INNER JOIN wp_term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id ";
            $q .= "WHERE tt.taxonomy IN ('category') AND tr.object_id IN ($ids)";
            $terms = $wpdb->get_results($q);
            $idTerms = array();
            foreach ($terms as $term) {
                $id = $term->object_id;
                unset($term->object_id);
                $idTerms[$id][] = $_terms[0] = sanitize_term($term, 'category', 'raw');
                update_term_cache($_terms);
            }

            foreach ($idTerms as $id => $terms) {
                wp_cache_add($id, $terms, 'category_relationships');
            }
        }

    }


    /**
     * Send a support ticket
     * Called via ajax from the Support tab on the settings page
     */
    function sendSupport() {
        $diagnostics = new EasyIndexDiagnostics();
        $diagnostics->send(self::DIAGNOSTICS_URL, array('licenseKey' => $this->settings->licenseKey));
    }

    /**
     * Display a page showing what diagnostics data will be sent
     */
    function showDiagnostics() {
        $diagnostics = new EasyIndexDiagnostics();
        $diagnostics->show();

    }


    /**
     * Activate the plugin
     *
     * Make sure we have some kind of graphics processing
     * Register the post type and add it to rewrite rules
     */
    function pluginActivated() {
        if (!extension_loaded('imagick') && !extension_loaded('gd')) {
            $msg = EasyIndexTranslate::translate('EasyIndex requires either the GD or the Imagick PHP graphics extension.');
            wp_die($msg . '<br><a href="/wp-admin/plugins.php">Go back</a>');
            return;
        }
        /**
         * Register our post type and flush the rewrite rules
         */
        $this->registerPostType('easyindex');
        flush_rewrite_rules();
        $settings = EasyIndexSettings::getInstance();
        $settings->displayHelp = true;
        $settings->update();
    }

    

    /**
     * Deactivate the plugin.
     *
     * Only need to remove the custom post type rewrite
     */
    function pluginDeactivated() {
        flush_rewrite_rules();
    }

}

