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
 * Class EasyIndexIndex
 *
 * Generate index pages
 *
 * 
 */
class EasyIndexIndex {

    private $indexData;
    private $isSecondaryIndex;
    private $postID;
    private $bodyClassAdded = false;
    private $term = null;

    private $excerptLength;
    private $termName;

    private $secondaryTitle = '';

    /** @var bool TRUE when we are trialling a style */
    private $isStyleOverride;

    /** @var EasyIndexDataIndex */
    private $index;

    /** @var string */
    private $styleID;

    /** @var EasyIndexStyle */
    private $indexStyle;

    /** @var  EasyIndex */
    private $easyindex;

    /** @var string Comma separated active Polylang languages */
    private $languages;

    private $lastKey;

    private $guid;
    private $usePermalinks;
    private $isPreview;
    private $delim;


    /**
     * @param EasyIndexData $indexData
     * @param bool $isSecondaryIndex
     * @param integer $postID
     * @param $easyindex
     */
    function __construct(EasyIndexData $indexData, $isSecondaryIndex, $postID, $easyindex) {
        /* @var $wp_rewrite WP_Rewrite */
        global $wp_rewrite;

        $this->indexData = $indexData;
        $this->isSecondaryIndex = $isSecondaryIndex;
        $this->postID = $postID;
        $this->easyindex = $easyindex;
        $this->index = $isSecondaryIndex ? $this->indexData->secondary : $this->indexData->primary;
        $this->isStyleOverride = !empty($_REQUEST['eistyle']) && $_REQUEST['eistyle'] != $this->index->ixStyleID;
        $this->styleID = $this->isStyleOverride ? $_REQUEST['eistyle'] : $this->index->ixStyleID;
        $this->indexStyle = EasyIndexStyles::getStyle($this->styleID);
        $this->lastKey = !empty($_GET['eilast']) ? $_GET['eilast'] : -1;

        /**
         * Work out what url structure to use for links to secondary indexes
         */
        $guid = get_permalink($postID);
        $this->isPreview = is_preview();

        $this->usePermalinks = $wp_rewrite->using_permalinks() && !$this->isPreview;

        if ($this->usePermalinks) {
            $this->guid = trailingslashit($guid);
        } else {
            $this->guid = untrailingslashit($guid);
        }
        $this->delim = strpos($this->guid, '?') === false ? '?' : '&';
        if ($this->isPreview) {
            $this->guid .= $this->delim . 'preview=true';
            $this->delim = '&';
        }

        /**
         * If the Polylang plugin is installed, then make sure it doesn't translate our taxonomy terms
         */
        if (function_exists('PLL')) {
            /**
             * Get the Polylang active languages as an array of slugs, convert to comma separated string
             */
            $PLL_languages = PLL()->model->get_languages_list();
            $languages = wp_list_pluck(wp_list_filter($PLL_languages, array('active' => false), 'NOT'), 'slug');
            $this->languages = join(',',$languages);
            /**
             * Hook into the get_terms_args filter very early so we can set the 'lang' argument
             */
            add_filter( 'get_terms_args', array( $this, 'getTermsArgs' ), 1, 2 );
        }

        /**
         * We might use the WP object cache to prevent re-generating the index if "the_content" is called multiple times
         * We don't want this to be persistent, so make a non-persistent cache group
         */
        wp_cache_add_non_persistent_groups("easyindex");
    }

    /**
     * Add a 'lang' argument to the get_terms() parameters so terms are not translated for all active Polylang languages
     * @param $args
     * @param $taxonomies
     * @return mixed
     */
    function getTermsArgs($args, $taxonomies) {
        $args['lang'] = $this->languages;
        return $args;
    }

    

    /**
     * Generate a primary index page
     *
     * @return string
     */
    function displayPrimary() {

        $cachekey = "easyindexP" . $this->postID;

        $html = wp_cache_get($cachekey,"easyindex");
        if ($html) {
            return $html;
        }

        /**
         * At this point we should have added our classes to the <body> tag.  If we haven't (the theme didn't call the "body_class" filter)
         * set up a hook to add some JS in the footer to add the classes at runtime
         */
        if (!$this->bodyClassAdded) {
            add_action('wp_footer', array($this, 'doFooter'));
        }

        if ($this->indexStyle->styleType == 'single') {
            $html = $this->primarySingle();
        } else {
            $isGallery = $this->indexStyle->styleType == 'gallery';
            if ($isGallery) {
                $html = $this->primaryGallery();
            } else {
                $html = $this->primarySample();
            }
        }
        wp_cache_add($cachekey, $html,"easyindex");
        return $html;

        /**
         *
         * $data->hasMore = $hasMore;
         * $data->nexturl = "$guid{$delim}eilast=$lastKey";
         * $template = $indexStyle->getTemplate($tags);
         * return $template->replace($data);
         **/
    }

    /**
     * Display a primary index with a single thumb per term
     * @return string
     */
    private function primarySingle() {
        $settings = EasyIndexSettings::getInstance();
        $index = $this->index;
        $indexStyle = $this->indexStyle;
        $terms = self::getTerms($this->indexData);
        $data = new stdClass();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $tags = array('termtag' => $index->ixTermTag, 'titletag' => $index->ixTitleTag);

        if ($this->isStyleOverride) {
            $tags = array('termtag' => $indexStyle->defaults->ixTermTag, 'titletag' => '');
            $thumbSize = $indexStyle->defaults->ixThumbWidth . "x" . $indexStyle->defaults->ixThumbHeight;
        } else {
            $thumbSize = $index->ixThumbSize;
            $tags = array('termtag' => $index->ixTermTag, 'titletag' => '');
        }

        $data->style = sanitize_key($this->styleID);
        $data->TERMS = array();

        foreach ($terms as $term) {

            $item = new stdClass();

            /**
             * Don't display if there's no posts
             */
            if ($term->count == 0) {
                continue;
            }

            $item->term = $term->name;

            $item->pluginUrl = EasyIndex::$EasyIndexUrl;
            $item->url = $this->getTermUrl($term);

            $item->imgTitle = '';

            if (!$indexStyle->isText) {
                $thumbSource = !empty($settings->termThumbSource[$this->indexData->indexTaxonomy][$term->term_id]) ? $settings->termThumbSource[$this->indexData->indexTaxonomy][$term->term_id] : '';
                /**
                 * Don't display if we have no thumb and we're hiding those
                 * If we aren't hiding blanks, then generate/get a "No Image" thumbnail
                 */
                if (empty($thumbSource)) {
                    if ($index->ixHideBlank) {
                        continue;
                    }
                    $thumb = new EasyIndexThumbnail(0, EasyIndex::$EasyIndexDir . '/images/noimage150.png');
                    $item->imgUrl = $thumb->getUrl($thumbSize, "noimage", EasyIndexThumbnailBase::NOCROP);
                } else {
                    $thumb = new EasyIndexThumbnail(0, $thumbSource);
                    $item->imgUrl = $thumb->getUrl($thumbSize, "{$this->indexData->indexTaxonomy}-{$term->term_id}");
                    /**
                     * If we can't generate a thumb, rewrite the meta stuff, flag the URL as dodgy so we don't keep retrying and ignore this item
                     * It can be VERY slow to keep trying to create a thumb from a bad source, particularly if the soutce is a non-existent URL
                     */
                    if (empty($item->imgUrl)) {
                        
                        continue;
                    }

                }
            }
            $data->TERMS[] = $item;
        }

        $template = $indexStyle->getTemplate($tags);
        return $template->replace($data);
    }

    /**
     * @return string
     */
    private function primarySample() {
        /** @var wpdb $wpdb */
        global $wpdb;

        $index = $this->index;
        $indexStyle = $this->indexStyle;
        $terms = self::getTerms($this->indexData);

        $hasMore = false;

        $this->excerptLength = $index->ixExcerptLength ? $index->ixExcerptLength : 100;
        $lastPostID = 0;

        $data = new stdClass();
        $data->TERMS = array();

        /**
         * This is a "sample" index i.e. each term has images and/or excerpts from a sample of the term's posts
         * OR a "onesample" index i.e. there is one sample per term
         */

        if ($this->isStyleOverride) {
            if ($indexStyle->styleType == 'onesample') {
                $limit = 1;
            } else {
                $limit = !empty($index->ixNumberPosts) ? $index->ixNumberPosts : $indexStyle->defaults->ixNumberPosts;
            }
            $titleTag = !empty($indexStyle->defaults->ixTitleTag) ? $indexStyle->defaults->ixTitleTag : '';
            $tags = array('termtag' => $indexStyle->defaults->ixTermTag, 'titletag' => $titleTag);
            $thumbSize = $indexStyle->defaults->ixThumbWidth . "x" . $indexStyle->defaults->ixThumbHeight;
        } else {
            $limit = $indexStyle->styleType == 'onesample' ? 1 : $index->ixNumberPosts;
            $thumbSize = $index->ixThumbSize;
            $tags = array('termtag' => $index->ixTermTag, 'titletag' => $index->ixTitleTag);
        }

        /**
         * Allow for descriptive style IDs
         */
        $data->style = sanitize_key($this->styleID);

        /**
         * Read through the terms and build arrays of all the stuff we'll need later
         */
        $termTaxIDs = array();
        foreach ($terms as $term) {
            $termTaxIDs[] = $term->term_taxonomy_id;
        }
        $tIDs = implode(',', $termTaxIDs);

        //$postIDs = array();

        if ($indexStyle->isText) {
            $orderBy = 'post_title';
            $sortField = 'post_title >';
        } else {
            $orderBy = 'post_date DESC';
            $sortField = 'post_date <';
        }
        $lastKey = $wpdb->_real_escape($this->lastKey);

        $postData = self::getIndexItems(true, $this->indexData->indexTaxonomy, $tIDs, false, $orderBy, $lastKey, $sortField);
        /**
         * If we are indexing categories, AND we are not showing duplicates, AND we have Yoast SEO installed,
         * we need to find any posts that have a "primary" category specified and remove those from any "non-primary" categories.
         * This will make posts appear in their primary category's index and ensure that categories aren't unexpectedly blank in the case
         * where all the posts in the category have already appeared in another (non-primary) category
         *
         * We are only interested in gathering the "primary category" for a post when the post actually HAS a primary category,
         * and that category was one of the categories included in this index
         */
        $primaryCats = array();
        if ($this->indexData->indexTaxonomy == "category" && !$this->indexData->showDuplicates && EasyIndex::$yoastActive) {
            $q = "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_yoast_wpseo_primary_category'";
            $pcs = $wpdb->get_results($q);
            foreach ($pcs as $pcat) {
                if (!empty($pcat->meta_value) && in_array($pcat->meta_value, $termTaxIDs)) {
                    $primaryCats[$pcat->post_id] = $pcat->meta_value;
                }
            }
        }

        /**
         * Save an array of post IDs for each term, indexed by taxID
         * Check for non-primary categories as above
         */
        $termPosts = array();

        foreach ($postData as $pd) {
            if (!isset($primaryCats[$pd->ID]) || $primaryCats[$pd->ID] == $pd->term_taxonomy_id) {
                $termPosts[$pd->term_taxonomy_id][] = $pd->ID;
            }
        }

        /**
         * Get the post IDs that we want to actually display thumbs for (i.e. the first $limit posts in each term)
         * and set them as unknowns in Thumbnail so it can read the meta for all of them at once
         */
        if (!$indexStyle->isText) {
            foreach ($termPosts as $termIDs) {
                if ($limit == 0) {
                    EasyIndexThumbnail::setUnknownIDs($termIDs);
                } else {
                    EasyIndexThumbnail::setUnknownIDs(array_slice($termIDs, 0, $limit));
                }
            }
        }

        $usedIDs = array();
        $postID = 0;

        foreach ($terms as $term) {
            /** @var string $termTaxID */
            $termTaxID = $term->term_taxonomy_id;

            $termItem = new stdClass();
            $termItem->POSTS = array();
            $termItem->term = $term->name;


            $termItem->pluginUrl = EasyIndex::$EasyIndexUrl; 
            $termItem->url = $this->getTermUrl($term);

            $nPosts = 0;

            if (!empty($termPosts[$termTaxID])) {
                $termPostsIDs = $termPosts[$termTaxID];
                foreach ($termPostsIDs as $postID) {
                    /**
                     * Don't use a post if it's already been used
                     */
                    if (!empty($usedIDs[$postID])) {
                        continue;
                    }
                    $nPosts++;
                    $item = new stdClass();
                    if (!$indexStyle->isText) {
                        $thumb = new EasyIndexThumbnail($postID);
                        $item->imgUrl = $thumb->getUrl($thumbSize, "thumb-$postID");
                        /**
                         * If we can't generate a thumb, rewrite the meta stuff, flag the URL as dodgy so we don't keep retrying and ignore this item
                         * It can be VERY slow to keep trying to create a thumb from a bad source, particularly if the soutce is a non-existent URL
                         */
                        if (empty($item->imgUrl)) {
                            
                            continue;
                        }
                    }
                    /**
                     * Keep a record of which posts we actually used so we can post process the posts' titles and permalinks
                     */
                    $item->postID = $postID;
                    if (!$this->indexData->showDuplicates) {
                        $usedIDs[$postID] = true;
                    }

                    $item->posturl = '';
                    $item->title = '';
                    $item->imgTitle = '';
                    $item->url = $termItem->url;
                    $termItem->POSTS[] = $item;
                    if ($limit > 0 && count($termItem->POSTS) == $limit) {
                        break;
                    }
                }
                $lastPostID = $postID;
                if ($nPosts < count($termPostsIDs) && !empty($index->ixMoreText)) { 
                    $termItem->hasMore = true;
                    $this->termName = $term->name;
                    $termItem->more = preg_replace_callback('/%(?:lc)?term%/i', array($this, 'replaceTerm'), $index->ixMoreText);
                }
                /**
                 * Don't include terms that ultimately have no posts with usuable images
                 */
                if (count($termItem->POSTS) > 0) {
                    $data->TERMS[] = $termItem;
                }
            }
        }

        /**
         * Find the titles and permalinks.
         * This is done here rather than on a post by post basis above because WP has no efficient way of getting these for multiple posts
         * We only need do the permalink stuff if the permalink contains %term%
         */
        $ids = implode(',', array_keys($usedIDs));
        if ($ids == '') {
            $ids = 0;
        }

        /**
         * Cache the posts and category/terms in the WP cache, using a single DB read for all
         */
        $this->cachePosts($ids);
        /**
         * If the permalink uses categories, cache the terms and relationships so we don't have to do a (very expensive) 3 way join for each post
         */
        $this->cacheCategoryTerms($ids);

        /**
         * Update the template data with title and post urls
         */

        foreach ($data->TERMS as $term) {
            /** @var stdClass $post */
            foreach ($term->POSTS as $post) {
                $post->title = get_the_title($post->postID);  

                if ($indexStyle->hasExcerpts) {
                    $post->excerpt = $this->getExcerpt($post->postID);
                } else {
                    $post->excerpt = '';
                }
                $post->posturl = get_permalink($post->postID);
            }
        }

        $lastKey = -1;
        if ($hasMore) {
            if ($lastPostID) {
                /** @var WP_Post $post */
                $post = get_post($lastPostID);
                if ($indexStyle->isText) {
                    $lastKey = urlencode($post->post_title);
                } else {
                    $lastKey = urlencode($post->post_date);
                }
            }
        }

        $data->hasMore = $hasMore;
        $data->nexturl = "$this->guid{$this->delim}eilast=$lastKey/";
        if ($this->isStyleOverride) {
            $data->nexturl .= "&eistyle=$indexStyle->id";
        }
        $template = $indexStyle->getTemplate($tags);
        return $template->replace($data);

    }

    /**
     * @return string
     */
    private function primaryGallery() {
        /** @var wpdb $wpdb */
        global $wpdb;

        $index = $this->index;
        $indexStyle = $this->indexStyle;
        $terms = self::getTerms($this->indexData);

        $this->excerptLength = $index->ixExcerptLength ? $index->ixExcerptLength : 100;

        $data = new stdClass();
        $data->TERMS = array();

        /**
         * This is a gallery, which displays one item for every post in a masonry layout
         */
        if ($this->isStyleOverride) {
            $titleTag = !empty($indexStyle->defaults->ixTitleTag) ? $indexStyle->defaults->ixTitleTag : '';
            $tags = array('termtag' => $indexStyle->defaults->ixTermTag, 'titletag' => $titleTag);
            $thumbSize = $indexStyle->defaults->ixThumbWidth . "x" . $indexStyle->defaults->ixThumbHeight;
        } else {
            $thumbSize = $index->ixThumbSize;
            $tags = array('termtag' => $index->ixTermTag, 'titletag' => $index->ixTitleTag);
        }

        $limit = $index->ixPostsPerScroll;


        $data = new stdClass();
        $data->TERMS = array();
        /**
         * Allow for descriptive style IDs
         */
        $data->style = sanitize_key($this->styleID);

        /**
         * Read through the terms and build arrays of all the stuff we'll need later
         */
        $termTaxIDs = array();
        foreach ($terms as $term) {
            $termTaxIDs[] = $term->term_taxonomy_id;
        }
        $tIDs = implode(',', $termTaxIDs);


        if ($indexStyle->isText) {
            $orderBy = 'post_title';
            $sortField = 'post_title >';
        } else {
            $orderBy = 'post_date DESC';
            $sortField = 'post_date <';
        }
        $lastKey = $wpdb->_real_escape($this->lastKey);

        $postIDs = self::getIndexItems(true, $this->indexData->indexTaxonomy, $tIDs, true, $orderBy, $lastKey, $sortField);

        EasyIndexThumbnail::setUnknownIDs(array_slice($postIDs, 0, $limit));

        $usedIDs = array();
        $postID = 0;

        $nPosts = 0;
        $data->POSTS = array();
        foreach ($postIDs as $postID) {
            $nPosts++;
            $item = new stdClass();
            if (!$indexStyle->isText) {
                $thumb = new EasyIndexThumbnail($postID);
                $item->imgUrl = $thumb->getUrl($thumbSize, "thumb-$postID");
                /**
                 * If we can't generate a thumb, rewrite the meta stuff, flag the URL as dodgy so we don't keep retrying and ignore this item
                 * It can be VERY slow to keep trying to create a thumb from a bad source, particularly if the soutce is a non-existent URL
                 */
                if (empty($item->imgUrl)) {
                    
                    continue;
                }
            }
            /**
             * Keep a record of which posts we actually used so we can post process the posts' titles and permalinks
             */
            $item->postID = $postID;
            if (!$this->indexData->showDuplicates) {
                $usedIDs[$postID] = true;
            }

            $item->posturl = '';
            $item->title = '';
            $item->imgTitle = '';
            $data->POSTS[] = $item;
            if ($limit > 0 && count($data->POSTS) == $limit) { 
                break;
            }
        }
        $lastPostID = $postID;
        $hasMore = $nPosts < count($postIDs);

        /**
         * Find the titles and permalinks.
         * This is done here rather than on a post by post basis above because WP has no efficient way of getting these for multiple posts
         * We only need do the permalink stuff if the permalink contains %term%
         */
        $ids = implode(',', array_keys($usedIDs));
        if ($ids == '') {
            $ids = 0;
        }

        /**
         * Cache the posts and category/terms in the WP cache, using a single DB read for all
         */
        $this->cachePosts($ids);
        /**
         * If the permalink uses categories, cache the terms and relationships so we don't have to do a (very expensive) 3 way join for each post
         */
        $this->cacheCategoryTerms($ids);

        /**
         * Update the template data with title and post urls
         */

        /** @var stdClass $post */
        foreach ($data->POSTS as $post) {
            $post->title = get_the_title($post->postID);  

            if ($indexStyle->hasExcerpts) {
                $post->excerpt = $this->getExcerpt($post->postID);
            } else {
                $post->excerpt = '';
            }
            $post->posturl = get_permalink($post->postID);
        }


        $lastKey = -1;
        if ($hasMore) {
            if ($lastPostID) {
                /** @var WP_Post $post */
                $post = get_post($lastPostID);
                if ($indexStyle->isText) {
                    $lastKey = urlencode($post->post_title);
                } else {
                    $lastKey = urlencode($post->post_date);
                }
            }
        }

        $data->hasMore = $hasMore;
        $data->nexturl = "$this->guid{$this->delim}eilast=$lastKey/";
        if ($this->isStyleOverride) {
            $data->nexturl .= "&eistyle=$indexStyle->id";
        }

        $template = $indexStyle->getTemplate($tags);
        return $template->replace($data);

    }

    /**
     * Generate a secondary index page
     *
     * @return string
     */
    public function displaySecondary() {

        $cachekey = "easyindexS" . $this->postID;
        $html = wp_cache_get($cachekey,"easyindex");
        if ($html) {
            return $html;
        }

        $index = $this->index;
        $this->excerptLength = $index->ixExcerptLength ? $index->ixExcerptLength : 100;

        /**
         * At this point we should have added our class to the <body> tag.  If we haven't (the theme didn't call the body class filter)
         * set up a hook to add some JS in the footer to add class
         */
        if (!$this->bodyClassAdded) {
            add_action('wp_footer', array($this, 'doFooter'));
        }


        /**
         * $this->term has been set by customizeTitle() which will always be called for secondary indexes
         */

        $indexStyle = $this->indexStyle;

            $thumbSize = $index->ixThumbSize;
            $tags = array('titletag' => $index->ixTitleTag, 'termtag' => '');
        $postIDs = array();

        /**
         * Normally, we get the term when "the_title" filter is called (used to customize the title) but some themes don't call it
         * In that case, pick it up from the base class
         */
        if (empty($this->term)) {
            $this->term = get_term_by('slug', $this->easyindex->getTerm(), $this->indexData->indexTaxonomy);
        }
        $termTaxID = !empty($this->term->term_taxonomy_id) ? $this->term->term_taxonomy_id : '0';


        
        
        
        
        
        if ($termTaxID != 0) {
            $orderBy = 'post_title';
            /**
             * We will have to read wp_posts for each item in the index later (to get the permalink and the title)
             * This can end up being a LOT of single row DB accesses if done using standard WP functions
             * It will be much more efficient to read them all in one SELECT, but we need to get the distinct IDs first
             */

            $postIDs = EasyIndexIndex::getIndexItems(false, $this->indexData->indexTaxonomy, $termTaxID, false, $orderBy);

            if (!empty($postIDs)) {
                EasyIndexThumbnail::setUnknownIDs($postIDs);
            }
        }

        $data = new stdClass();
        $data->style = sanitize_key($this->styleID);
        //ifdef NOTDEFINED
//////      $data->navbar = $navbar !== null ? $navbar->make($nRows) : '';
/////        $data->hasNavbar = $data->navbar != '';
//////        $data->indexIX = $this->indexIX;

        $data->POSTS = array();
        $usedIDs = array();
        /**
         * Go through all the posts we got and get the image Url, either from the meta data, or from the post itself
         */
        /** @var int $postID */
        foreach ($postIDs as $postID) {
            $item = new stdClass();
            if (!$indexStyle->isText) {
                $thumb = new EasyIndexThumbnail($postID);
                $item->imgUrl = $thumb->getUrl($thumbSize, "thumb-$postID");
                if (empty($item->imgUrl)) {
                    
                    continue;
                }
            }
            /**
             * Keep a record of which posts we actually used so we can post process the posts' titles and permalinks
             */
            $item->postID = $postID;
            if (!$this->indexData->showDuplicates) {
                $usedIDs[$postID] = true;
            }

            $item->posturl = '';
            $item->title = '';
            $item->imgTitle = '';

            $data->POSTS[] = $item;
        }

        /**
         * Find the titles and permalinks.
         * This is done here rather than on a post by post basis above because WP has no efficient way of getting these for multiple posts
         * We only need do the permalink stuff if the permalink contains %category%
         */
        $ids = implode(',', array_keys($usedIDs));
        if ($ids == '') {
            $ids = 0;
        }

        /**
         * Cache the posts and category/terms using a single DB read for each
         */
        $this->cachePosts($ids);
        $this->cacheCategoryTerms($ids);

        /**
         * Update the template data with title and post urls
         */

        /** @var stdClass $post */
        foreach ($data->POSTS as $post) {
            $post->title = get_the_title($post->postID);
            if ($indexStyle->hasExcerpts) {
                $post->excerpt = $this->getExcerpt($post->postID);
            } else {
                $post->excerpt = '';
            }

            $post->posturl = get_permalink($post->postID);
        }


        $template = $indexStyle->getTemplate($tags);
        $html = $template->replace($data);
        wp_cache_add($cachekey, $html,"easyindex");

        return $html;
    }

    /**
     * Make the secondary index title
     * Also get and save the term data
     *
     * This may be called more than once (the title may apear in more than one place) so cache the result
     *
     * @param $term
     *
     * @return string
     */
    function customiseTitle($term) {
        if (empty($this->secondaryTitle)) {
            $this->term = get_term_by('slug', $term, $this->indexData->indexTaxonomy);

            /**
             * Really not much we can do here if we can no longer find the term?
             */
            if (empty($this->term)) {
                return $term;
            }
            $this->secondaryTitle = preg_replace('/%term%/i', $this->term->name, $this->indexData->ixTitle);
        }
        return $this->secondaryTitle;

    }

    /**
     * Replaces the "more" term
     *
     * @param array $matches
     *
     * @return string
     */
    function replaceTerm($matches) {
        return $matches[0] == '%term%' ? $this->termName : strtolower($this->termName);
    }

    /**
     * Gets an excerpt.
     *
     * We need to do this ourselves because of the difficulty in getting an excerpt using the standard WP functions from a post that's not the current post.
     *
     * Some plugins (e.g. yuzo related posts) alter the state of the global $post variable and the WP_Query during the_content excerpt processing (called
     * during the_excerpt processing) so it's not possible to just call setup_postdata() and then get_the_excerpt()
     *
     * It also allows us to get an excerpt based on a number of characters rather than a number of words - excerpts based on word counts can
     * lead to significantly different excerpt column lengths if the excerpt is displayed in a relatively narrow column and that looks crappy.
     *
     * @param $postID
     *
     * @return string
     */
    function getExcerpt($postID) {
        /**
         * The post will be retrieved from the WP cache because all the required posts have been cached by cachePosts()
         */
        /** @var WP_Post $post */
        $post = get_post($postID);

        $excerpt = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
        // Process shortcodes instead of strip?
        $excerpt = strip_shortcodes($excerpt);

        $excerpt = strip_tags($excerpt);

        $excerpt = preg_replace('/\s+/i', ' ', $excerpt);
        $excerpt = trim($excerpt);

        $words = explode(' ', $excerpt);

        $excerpt = '';
        $tooLong = false;
        foreach ($words as $word) {
            $excerpt .= $word . ' ';
            if (strlen($excerpt) > $this->excerptLength - 1) {
                $tooLong = true;
                break;
            }
        }

        if ($tooLong) {
            $excerpt = substr($excerpt, 0, strrpos($excerpt, ' ')) . ' ...';
        }

        return $excerpt;

    }

    /**
     * Add a couple of classes to the body classes so we can tweak index pages' CSS for elements outside of the index itself
     * and also so we can make our CSS as specific as we can to try to override any too-specific theme CSS
     * Flag that we've done this so that we can check if the theme does NOT call the "body_class" filter, and output a JS kludge later to fix it
     *
     * @param array $class
     *
     * @return array
     */
    function addBodyClass($class) {
        $this->bodyClassAdded = true;
        $class[] = 'ei-index';
        $class[] = $this->isSecondaryIndex ? 'ei-secondary' : 'ei-primary';
        return $class;
    }

    /**
     * The theme is brain dead and didn't call the "body_class" filter
     * Insert a bit of javascript to add the appropriate ei classes at runtime
     * Can't use jQuery because some themes don't load it! (e.g. thesis185)
     */
    function doFooter() {
        $class = 'ei-index ' . ($this->isSecondaryIndex ? 'ei-secondary' : 'ei-primary');
        echo <<<EOD
<script type="text/javascript">
    (function () {
        document.body.className += ' $class';
    }());
</script>
EOD;

    }



    /**
     * Hooked into wp_head (late) to display any custom and live CSS for the index
     */
    function addCSS() {

        /**
         * Don't use any index specific CSS is we're trialling a style
         */
        if ($this->isStyleOverride) {
            return;
        }

        /**
         * Output any customized CSS for the current index.
         * Writing CSS directly to <head> is not ideal in terms of browser cache optimization,
         * but the alternatives (e.g. having a separate php script that outputs CSS) are almost certainly worse.
         */
        $index = $this->index;
        if ($this->isSecondaryIndex) {
            $cssPrefix = "body.ei-index.ei-secondary";
        } else {
            $cssPrefix = "body.ei-index.ei-primary";
        }

        /**
         * Make our CSS quite specific so we have the best chance of overriding theme and other plugins
         */
        $cssPrefix .= " #easyindex-index";

        /**
         * If the thumb width is not the default for the style, then add an override for the container width
         */

        $css = '';
        /**
         * Get CSS customizations
         *  ixFormatCSS is from the simple format popup on the index edit and is an array keyed on selector
         *  ixCustomCSS is from the CSS text field on the index edit and is a string (will override format CSS)
         *  ixLiveCSS is from Live Formatting and is an array keyed on selector (will override format & custom CSS)
         *
         * Output in separate <style> tags to make it easier for support to handle formatting issues
         */
        $formatCss = $index->ixFormatCss;
        $customCss = $index->ixCustomCss;
        $liveCss = $index->ixLiveCss;

        $indexStyle = $this->indexStyle;

        /**
         * If the thumb width is different from the default width, we'll need to set the term container width
         * Prepend it to the custom CSS so that it will be overridden by any explicitly entered CSS or Live Formatting
         * Make the selector very specific to try override theme and other plugins' CSS
         */
        if ($indexStyle->defaults->ixThumbWidth != $index->ixThumbWidth && $index->ixThumbWidth != 0 && $indexStyle->itemWidth != 'auto') {

            $css = <<<EOD
$cssPrefix $indexStyle->itemSelector {
  width: {$index->ixThumbWidth}px;
}

EOD;
        }


        /**
         * Add in any basic formatting
         */
        foreach ($formatCss as $selector => $properties) {
            $css .= "$cssPrefix $selector {\n";

            foreach ($properties as $property => $value) {
                if (is_numeric($value) && $property != 'line-height') {
                    $value .= 'px';
                }
                $css .= "  $property : $value;\n";
            }
            $css .= "}";

        }

        $css = trim($css);
        if (!empty($css)) {
            echo <<<EOD
<style id="eiFormatCss" type="text/css">
$css
</style>

EOD;
        }

        /**
         * Output any custom CSS
         */
        $customCss = trim($customCss);
        if (!empty($customCss)) {
            echo <<<EOD
<style id="eiCustomCss" type="text/css">
$customCss
</style>

EOD;
        }


    }

    /**
     * Add all the posts in $postIDs to the cache so we don't have to do a separate read for each post
     * There is no clean way to instantiate a WP_Post (and thus cache the data) without WP re-reading the row, even if you have all the data !
     * So sanitise the post ourselves and don't bother using WP_Post
     *
     * @param string $postIDs
     */
    private function cachePosts($postIDs) {
        /** @var wpdb $wpdb */
        global $wpdb;

        $q = "SELECT * FROM $wpdb->posts WHERE ID in ($postIDs)";
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
    private function cacheCategoryTerms($postIDs) {
        /** @var wpdb $wpdb */
        global $wpdb;

        $permalink = get_option('permalink_structure');
        if (strpos($permalink, '%category%') !== false) {
            $q = "SELECT object_id, t.*, tt.* FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt ON tt.term_id = t.term_id ";
            $q .= "INNER JOIN wp_term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id ";
            $q .= "WHERE tt.taxonomy IN ('category') AND tr.object_id IN ($postIDs)";
            $terms = $wpdb->get_results($q);
            $idTerms = array();
            foreach ($terms as $term) {
                /** @var int $id */
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
     * Get the items for an index. Centralized so we can also call it when generating thumbnails
     * @param bool $isPrimary
     * @param string $indexTaxonomy
     * @param string $taxonomies
     * @param bool|false $isGallery
     * @param string|int $lastKey
     * @param string $sortField
     * @param string $orderBy
     * @return array
     */
    static public function getIndexItems($isPrimary = true, $indexTaxonomy = '', $taxonomies = '', $isGallery = false, $orderBy = '', $lastKey = -1, $sortField = '') {
        /** @var wpdb $wpdb */
        global $wpdb;

        /**
         * It's possible for there to be no taxonomies - if so, set it so the SQL isn't invalid
         */
        if (empty($taxonomies)) {
            $taxonomies = '0';
        }
        /**
         * It's possible for the Revision Manager TMC plugin to create "revision" post types that are published
         * We need to exlude them else we get what appears to be duplicate items
         */
        if ($isGallery || !$isPrimary) {
            $q = "SELECT DISTINCT ID FROM $wpdb->posts JOIN $wpdb->term_relationships ON id = object_id ";
            $q .= "JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id ";
            $q .= "WHERE $wpdb->term_taxonomy.term_taxonomy_id IN  ($taxonomies) AND post_status = 'publish' AND taxonomy = '$indexTaxonomy' ";
            $q .= "AND post_type <> 'revision' ";
            $q .= $lastKey <> "-1" ? "AND $sortField '$lastKey' " : '';
            $q .= $orderBy != '' ? "ORDER BY $orderBy" : '';
            /** @noinspection PhpUnusedLocalVariableInspection */
            $data = $wpdb->get_col($q);
        } else {
            $q = "SELECT DISTINCT ID, $wpdb->term_taxonomy.term_taxonomy_id FROM $wpdb->posts ";
            $q .= "JOIN $wpdb->term_relationships ON id = object_id ";
            $q .= "JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id ";
            $q .= "WHERE $wpdb->term_taxonomy.term_taxonomy_id IN  ($taxonomies) AND post_status = 'publish' AND taxonomy = '$indexTaxonomy' ";
            $q .= "AND post_type <> 'revision' ";
            $q .= $lastKey <> "-1" ? "AND $sortField '$lastKey' " : '';
            $q .= $orderBy != '' ? "ORDER BY $orderBy" : '';
            /** @noinspection PhpUnusedLocalVariableInspection */
            $data = $wpdb->get_results($q);
        }

        return $data;
    }

    /**
     * Get a link to the secondary index for $term
     * If we are using permalinks and we aren't previewing, it will be /primary-guid/term
     * otherwise it will be /primary-guid&term=slug
     * The trailing slash on the guid will have been set (or cleared) appropriately in the constructor above
     *
     * @param $term
     * @return string
     */
    private function getTermUrl($term) {
        return $this->usePermalinks ? $this->guid . $term->slug : "$this->guid{$this->delim}term=$term->slug";
    }

    /**
     * @param EasyIndexData $indexData
     * @return array|WP_Error
     */
    static public function getTerms($indexData) {
        $args = array('orderby' => 'name');
        if (!$indexData->indexAllTerms) {
            if (!empty($indexData->indexTerms)) {
                $args['include'] = $indexData->indexTerms;
            }

        }
        return get_terms(array($indexData->indexTaxonomy), $args);

    }

}

