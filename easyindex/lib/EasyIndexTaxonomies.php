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
 * Class EasyIndexTaxonomies
 *
 * Handles  taxonomy processing
 */
class EasyIndexTaxonomies {

    private static $instance;

    public $taxonomies = array();
    public $termsFlat;
    public $termsHierachy;

    /**
     * @static
     * @return EasyIndexTaxonomies
     */
    static function getInstance() {
        if (!self::$instance) {
            self::$instance = new EasyIndexTaxonomies();
        }
        return self::$instance;
    }

    /**
     *
     */
    private function __construct() {
        global $wp_taxonomies;

        /**
         * Get the public taxonomies
         */
        $taxonomies = array();
        foreach ($wp_taxonomies as $wpTaxonomy) {
            if ($wpTaxonomy->public) {
                $taxonomies[$wpTaxonomy->label][] = $wpTaxonomy;
            }
        }
        /**
         * Get a unique name for each taxonomy by combining label and name if necessary
         */
        foreach ($taxonomies as $taxonomy) {
            if (count($taxonomy) == 1) {
                $this->taxonomies[$taxonomy[0]->label] = $taxonomy[0];
            } else {
                for ($t = 0; $t < count($taxonomy); $t++) {
                    $this->taxonomies[$taxonomy[$t]->label . " ({$taxonomy[$t]->name})"] = $taxonomy[$t];
                }
            }
        }
        ksort($this->taxonomies);

    }

    /**
     * Re count the objects in all taxonomies
     */
    public function countTerms() {
        $taxonomies = get_taxonomies();
        $args = array('hide_empty' => false);
        $terms = get_terms($taxonomies, $args);
        foreach ($terms as $term) {
            wp_update_term_count_now(array($term->term_id), $term->taxonomy);
        }
        wp_send_json('OK');
    }

    /**
     * Get the terms hierachically
     *
     * @param bool $withHierachy
     *
     * @return array
     */
    private function getTerms($withHierachy = true) {
        $terms = array();
        foreach ($this->taxonomies as $key => $taxonomy) {
            $termsList = get_terms(array($taxonomy->name), array('hide_empty' => false));
            $terms[$key] = array();
            if ($withHierachy && $taxonomy->hierarchical && count($termsList) > 0) {
                $walker = new EasyIndexWalker();
                $termsList = $walker->walk($termsList, 0);
            }
            /** @var array $termsList */
            foreach ($termsList as $term) {
                $item = new stdClass();
                $item->name = $term->name;
                $item->termID = $term->term_id;
                $item->termTaxonomyID = $term->term_taxonomy_id;
                $item->depth = isset($term->depth) ? $term->depth : 0;
                $terms[$key][] = $item;
            }
        }
        return $terms;
    }

    /**
     * @return array
     */
    function getTermsHierachy() {
        if (!isset($this->termsHierachy)) {
            $this->termsHierachy = $this->getTerms(true);
        }
        return $this->termsHierachy;
    }

    /**
     * Find terms without bothering with hierachy, sorted by term name
     *
     * @return array
     */
    function getTermsFlat() {
        if (!isset($this->termsFlat)) {
            $this->termsFlat = $this->getTerms(false);
        }
        return $this->termsFlat;
    }

}

