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
 * Class EasyIndexIndexEdit
 */
class EasyIndexIndexEdit {

    const HELP_PAGE = '/add-edit';
    /**
     * @var array Possible values for title tags
     */
    static private $tags = array(
        'h1'  => 'Heading 1',
        'h2'  => 'Heading 2',
        'h3'  => 'Heading 3',
        'h4'  => 'Heading 4',
        'h5'  => 'Heading 5',
        'h6'  => 'Heading 6',
        'div' => 'Div',
        'p'   => 'Paragraph',
        'li'  => 'List Item');

    static private $sequences = array(
        array('alpha', 'Alphabetic'),
        array('datedesc', 'Date descending'),
        array('dateasc', 'Date ascending')
    );

    /**
     * Show the index add/edit page
     *
     * @param $post
     */
    static function show($post) {
        global $pagenow;

        $settings = EasyIndexSettings::getInstance();

        $data = new stdClass();
        $data->postID = $post->ID;

        $data->isNew = $pagenow == 'post-new.php';

        $data->helpurl = EasyIndex::HELP_URL . self::HELP_PAGE;

        $data->eiplus = '';

        $data->wpurl = get_bloginfo('wpurl');
        $data->pluginversion = EasyIndex::$pluginVersion;
        $data->pluginUrl = EasyIndex::$EasyIndexUrl;

        /**
         * Get the index data.
         */
        /** @var $indexData EasyIndexData */
        $indexData = get_post_meta($post->ID, 'easyindex', true);
        $easyindexData = new EasyIndexData($settings->defaultSlug);
        if (empty($indexData)) {
            $indexData = $easyindexData; // new EasyIndexData($settings->defaultSlug);
        } else {

        }

        /**
         * For convenience
         */
        $data->indexSlug = $indexData->indexSlug;

        $data->primaryStyleID = $indexData->primary->ixStyleID;
        $data->secondaryStyleID = $indexData->secondary->ixStyleID;

        $data->IXTYPES = array();


        /**
         * Get the styles
         */
        $allStyles = new EasyIndexStyles();
        $nStyles = new stdClass();
        $styleDefaults = new stdClass();


        $groupNames = array(
            'Single image per term - Image from most recent post',
            'Single image per term - Preset images',
            'Sample of posts',
            'Sample of posts with excerpts',
            'Galleries',
            'Text Indexes'
        );

        foreach (array('primary', 'secondary') as $ixType) {
            /**
             * $ixIndex is for the style select dialog
             */
            $ixIndex = new stdClass();
            $ixIndex->type = $ixType;

            $ixItem = new stdClass();
            $ixItem->GROUPS = array();
            $ixItem->ixType = $ixType;
            $ixItem->IXType = ucfirst($ixType);

            /** @var EasyIndexDataIndex $index */
            $index = $indexData->$ixType;
            $style = EasyIndexStyles::getStyle($index->ixStyleID);
            $ixItem->styleName = $style->name;
            $ixItem->styleID = $style->id;


            $ixItem->hideBlankChecked = $index->ixHideBlank ? 'checked' : '';
            $ixItem->autoMenuChecked = $indexData->ixAutoMenu ? 'checked' : '';

            if ($indexData->showDuplicates) {
                $ixItem->yesselected = 'selected';
                $ixItem->noselected = '';
            } else {
                $ixItem->noselected = 'selected';
                $ixItem->yesselected = '';
            }
            $ixItem->isSecondary = $ixType == 'secondary';

            if ($ixItem->isSecondary) {
                if (empty($indexData->ixSequence)) {
                    $indexData->ixSequence = 'alpha';
                }
                $ixItem->SEQUENCE = array();
                foreach (self::$sequences as $sequence) {
                    $item = new stdClass();
                    $item->value = $sequence[0];
                    $item->label = $sequence[1];
                    $item->selected = $item->value == $indexData->ixSequence ? 'selected' : '';
                    $ixItem->SEQUENCE[] = $item;
                }
            }
            $ixItem->ixTitle = $indexData->ixTitle;

            $styles = $allStyles->getStyles($ixType);
            $nStyles->$ixType = count($styles);
            $IXSTYLES = array();

            $groups = array();

            /** @var EasyIndexStyle $style */
            foreach ($styles as $style) {
                $styleDefaults->{$style->id} = isset($style->defaults) ? $style->defaults : new stdClass();
                /**
                 * To save having to duplicate the same info in the style spec, generate the thumbSize default from height & width
                 */
                if (!isset($styleDefaults->{$style->id}->ixThumbSize)) {
                    $styleDefaults->{$style->id}->ixThumbSize = "{$styleDefaults->{$style->id}->ixThumbWidth}x{$styleDefaults->{$style->id}->ixThumbHeight}";
                }
                /**
                 * Work out the group
                 */
                if ($style->isText) {
                    $groupIX = 5;
                } else if ($style->styleType == 'onesample') {
                    $groupIX = 0;
                } else if ($style->styleType == 'single') {
                    $groupIX = 1;
                } else if ($style->styleType == 'gallery') {
                    $groupIX = 4;
                } else if (empty($style->hasExcerpts)) {
                    $groupIX = 2;
                } else {
                    $groupIX = 3;
                }

                if (empty($groups[$groupIX])) {
                    $groups[$groupIX] = new stdClass();
                    $groups[$groupIX]->name = $groupNames[$groupIX];
                    $groups[$groupIX]->STYLES = array();
                }

                $item = new stdClass();
                $item->id = $style->id;
                $item->name = $style->name;
                $item->description = htmlspecialchars($style->description);
                $item->img = EasyIndex::$EasyIndexUrl . "/styles/$style->id/style.jpg";
                $item->isIncluded = $style->isIncluded;
                $item->plusonly = $item->isIncluded ? '' : 'ei-plusonly';
                $item->styleType = $style->styleType;
                /**
                 * If this style is the current style, enable or disable the appropriate index settings fields
                 */
                if ($style->id == $index->ixStyleID) {
                    $item->selected = 'selected';
                    $item->display = '';
                    if ($style->styleType == 'sample') {
                        $ixItem->ixHasSamples = '';
                        $ixItem->sampledisable = '';
                        $ixItem->ixHasTitles = '';
                    } else {
                        $ixItem->ixHasSamples = 'ei-disabled';
                        $ixItem->sampledisable = 'disabled';
                        $ixItem->ixHasTitles = 'ei-disabled';
                    }

                    if ($style->styleType == 'gallery') {
                        $ixItem->ixIsGallery = '';
                        $ixItem->sampledisable = '';
                        $ixItem->ixHasTerms = 'ei-disabled';
                        /**
                         * Some galleries have titles, others don't
                         */
                        $ixItem->ixHasTitles = $style->hasTitles ? '' : 'ei-disabled';
                    } else {
                        $ixItem->ixIsGallery = 'ei-disabled';
                        $ixItem->gallerydisable = 'disabled';
                        /**
                         * Non gallery primary indexes have terms
                         */
                        $ixItem->ixHasTerms = '';
                    }

                    if ($style->styleType == 'single' && !$style->isText) {
                        $ixItem->ixIsSingle = '';
                        $ixItem->singledisable = '';
                    } else {
                        $ixItem->ixIsSingle = 'ei-disabled';
                        $ixItem->singledisable = 'disabled';
                    }

                    if ($style->hasExcerpts) {
                        $ixItem->ixHasExcerpts = '';
                        $ixItem->excerptdisable = '';
                    } else {
                        $ixItem->ixHasExcerpts = 'ei-disabled';
                        $ixItem->excerptdisable = 'disabled';
                    }
                    if (!$style->isText) {
                        $ixItem->ixIsText = '';
                        $ixItem->thumbnaildisable = '';
                    } else {
                        $ixItem->ixIsText = 'ei-disabled';
                        $ixItem->thumbnaildisable = 'disabled';
                    }


                } else {
                    $item->selected = '';
                    $item->display = 'ei-display-none';
                }
                $IXSTYLES[] = $item;
                $groups[$groupIX]->STYLES[] = $item;
            }

            ksort($groups);
            foreach ($groups as $group) {
                $ixItem->GROUPS[] = $group;
            }

            $ixItem->IXSTYLES = $IXSTYLES;
            $ixItem->fonts = json_encode($settings->defaultFonts);

            $ixItem->ixNumberPosts = $index->ixNumberPosts;
            $ixItem->ixPostsPerScroll = $index->ixPostsPerScroll;
            $ixItem->ixMoreText = $index->ixMoreText;
            $ixItem->ixExcerptLength = !empty($index->ixExcerptLength) ? $index->ixExcerptLength : '';

            $ixItem->ixThumbSize = $index->ixThumbSize;

            /**
             * Get the user supplied "structured" CSS (i.e. CSS that was set via the formatting popup)
             */
            $ixItem->ixCSS = !empty($indexData->pixCSS) ? $indexData->pixCSS : '{}';

            /**
             * Get the user "arbitrary" CSS (i.e. entered by the user as a string of raw CSS)
             */
            $ixItem->ixCustomCss = $index->ixCustomCss;

            $data->IXTYPES[] = $ixItem;
        }

        /**
         * Get the available page templates provided by the theme
         */
        $pageTemplates = get_page_templates();
        ksort($pageTemplates);
        $pageTemplates = array_merge(array(__('Default') => 'default'), $pageTemplates);


        $data->IXTYPES[0]->IXTEMPLATES = array();
        $data->IXTYPES[1]->IXTEMPLATES = array();

        foreach ($pageTemplates as $name => $template) {
            $item = new stdClass();
            $item->template = $template;
            $item->templateName = $name;
            $item->selected = $indexData->primary->ixTemplate == $template ? 'selected' : '';
            $data->IXTYPES[0]->IXTEMPLATES[] = $item;

            $item = clone $item;
            $item->selected = $indexData->secondary->ixTemplate == $template ? 'selected' : '';
            $data->IXTYPES[1]->IXTEMPLATES[] = $item;
        }

        $data->IXTYPES[0]->IXTITLETAGS = array();
        $data->IXTYPES[1]->IXTITLETAGS = array();
        $data->IXTYPES[0]->IXTERMTAGS = array();
        $data->IXTYPES[1]->IXTERMTAGS = array();

        foreach (self::$tags as $tag => $tagName) {
            $item = new stdClass();
            $item->tag = $tag;
            $item->tagName = $tagName;

            $item->selected = $indexData->primary->ixTermTag == $tag ? 'selected' : '';
            $data->IXTYPES[0]->IXTERMTAGS[] = $item;

            $item = clone $item;
            $item->selected = $indexData->primary->ixTitleTag == $tag ? 'selected' : '';
            $data->IXTYPES[0]->IXTITLETAGS[] = $item;

            $item = clone $item;
            $item->selected = $indexData->secondary->ixTitleTag == $tag ? 'selected' : '';
            $data->IXTYPES[1]->IXTITLETAGS[] = $item;

        }

        $taxonomies = EasyIndexTaxonomies::getInstance();

        $data->TAXONOMIES = array();
        foreach ($taxonomies->taxonomies as $key => $taxonomy) {
            $item = new stdClass();
            $item->value = $taxonomy->name;
            $item->label = $key;
            $item->selected = $taxonomy->name == $indexData->indexTaxonomy ? 'selected' : '';
            $data->TAXONOMIES[] = $item;
        }


        $data->TAXONOMYTERMS = array();
        $taxTerms = $taxonomies->getTermsHierachy();

        foreach ($taxTerms as $key => $terms) {
            $item = new stdClass();
            $item->taxName = $taxonomies->taxonomies[$key]->name;
            $item->TERMS1 = array();
            $item->TERMS2 = array();

            if ($indexData->indexTaxonomy == $item->taxName) {
                $item->taxonomyVis = '';
                $item->indexAllTermsChecked = $indexData->indexAllTerms ? 'checked' : '';
            } else {
                $item->taxonomyVis = 'EISTaxonomyHide';
            }

            $nTerms = count($terms);
            $halfWay = ceil($nTerms / 2);
            $n = 0;
            foreach ($terms as $term) {
                $itemTerm = new stdClass();
                $itemTerm->termID = $term->termID;
                $itemTerm->termName = $term->name;
                $itemTerm->termDepth = $term->depth;
                $itemTerm->taxName = $taxonomies->taxonomies[$key]->name;
                $itemTerm->termSelected = !empty($indexData->indexTerms) ? in_array($term->termID, $indexData->indexTerms) : false;
                if ($n < $halfWay) {
                    $item->TERMS1[] = $itemTerm;
                } else {
                    $item->TERMS2[] = $itemTerm;
                }
                $n++;
            }
            $data->TAXONOMYTERMS[] = $item;
        }

        $version = EasyIndex::$pluginVersion;
        $pluginUrl = EasyIndex::$EasyIndexUrl;

        $styles = json_encode($allStyles->allStyles);
        $defaultFonts = json_encode($settings->defaultFonts);
        $gFonts = file_get_contents(dirname(__FILE__) . '/../fonts/gfonts.json');

        $pixFormatCss = json_encode((object)$indexData->primary->ixFormatCss);
        $sixFormatCss = json_encode((object)$indexData->secondary->ixFormatCss);

        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-formatbasic-dialog.html');
        $html = $template->getTemplateHTML(EasyIndexTemplate::PRESERVECOMMENTS | EasyIndexTemplate::PRESERVEWHITESPACE);
        $html = json_encode($html);

        $bgGeneration = $settings->bgGeneration ? 'true' : 'false';
        $script = <<<EOD
<script type="text/javascript">
  window.EASYINDEX = window.EASYINDEX || {};
  EASYINDEX.pluginVersion = '$version';
  EASYINDEX.pluginUrl = '$pluginUrl';
  EASYINDEX.nPrimary = $nStyles->primary;
  EASYINDEX.nSecondary = $nStyles->secondary;
  EASYINDEX.styles = $styles;
  EASYINDEX.pixStyleID = '{$indexData->primary->ixStyleID}';
  EASYINDEX.sixStyleID = '{$indexData->secondary->ixStyleID}';
  EASYINDEX.pixFormatCss = $pixFormatCss;
  EASYINDEX.sixFormatCss = $sixFormatCss;
  EASYINDEX.defaultFonts = $defaultFonts;
  EASYINDEX.gFonts = $gFonts;
  EASYINDEX.template = $html;
  EASYINDEX.bgGeneration = $bgGeneration;
</script>

EOD;
        $data->script = $script;


        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-styleselect.html');
        $data->styledialog = $template->replace($data);

        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-generatethumbs.html');
        $data->generatethumbsdialog = $template->replace($data);

        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-upgrade.html');
        $data->upgradeDialog = $template->getTemplateHTML();
        $template = new EasyIndexTemplate(EasyIndex::$EasyIndexDir . '/templates/easyindex-index-edit.html');
        echo $template->replace($data, EasyIndexTemplate::PRESERVEWHITESPACE | EasyIndexTemplate::PRESERVECOMMENTS);

    }

    /**
     * An EasyIndex post has changed - save the settings in the post meta if it's an index page
     * This function should only ever get called if this IS saving an EasyIndex page
     *
     * @param         $postID
     * @param WP_Post $post
     */
    function save($postID, $post = null) {
        /**
         * Not interested in auto-drafts or anything else that's not an explicit save
         */
        if ($post->post_status == 'auto-draft' || empty($_POST['EasyIndex'])) {  
            return;
        }

        $settings = EasyIndexSettings::getInstance();
        $indexData = get_post_meta($postID, 'easyindex', true);
        if (!$indexData) {
            $indexData = new EasyIndexData($settings->defaultSlug);
        }

        $oldSlug = $indexData->indexSlug;

        $data = $_POST['EasyIndex'];
        foreach ($indexData as $field => $value) {
            if ($field == 'indexAllTerms') {
                $indexData->indexAllTerms = !empty($data['indexAllTerms'][$data['indexTaxonomy']]);
                if ($indexData->indexAllTerms) {
                    $indexData->indexTerms = array();
                    unset($data['indexTerms']);
                }
            } else if ($field == 'primary' || $field == 'secondary') {
                /** @var EasyIndexDataIndex $indexDataField */
                $indexDataField = $indexData->$field;
                $dataField = $data[$field];
                foreach ($indexDataField as $indexField => $indexValue) {
                    if ($indexField == 'ixHideBlank') {
                        $indexDataField->ixHideBlank = !empty($dataField['ixHideBlank']);
                    } else if ($indexField == 'ixFormatCss') {
                        $indexDataField->ixFormatCss = array();
                        $formatCss = json_decode(stripslashes($dataField['ixFormatCss']));
                        foreach ($formatCss as $target => $css) {
                            $indexDataField->ixFormatCss[$target] = $css;
                        }
                    } else if ($indexField == 'ixFormatGFonts') {
                        $indexDataField->ixFormatGFonts = json_decode(stripslashes($dataField['ixGFonts']));
                    } else if (isset($dataField[$indexField])) {
                        $indexDataField->$indexField = $dataField[$indexField];
                    }
                }
                /**
                 * If we have a style that doesn't have thumbs, set the thumb size to zero
                 */
                if (!isset($dataField['ixThumbSize'])) {
                    /**
                     * Some silliness because PHP Code Sniffer PHPCompatability checks report '0x0' as a possible error
                     */
                    $indexDataField->ixThumbSize = '0' . 'x0';
                }

            } else if ($field == 'ixAutoMenu') {
                $indexData->ixAutoMenu = !empty($data['ixAutoMenu']);
            } else if ($field == 'indexTerms') {
                if (!empty($data['indexTerms'])) {
                    $indexData->indexTerms = $data['indexTerms'][$data['indexTaxonomy']];
                }
            } else if ($field == 'showDuplicates') {
                $indexData->showDuplicates = $data[$field] == 'YES';
            } else if (isset($data[$field])) {
                $indexData->$field = $data[$field];
            }
        }

        /**
         * Decode the thumb sizes so it doesn't have to be done at index display time
         */
        /** @var EasyIndexDataIndex $ix */
        foreach (array($indexData->primary, $indexData->secondary) as $ix) {
            preg_match('/^(\d+)x(\d+)$/', $ix->ixThumbSize, $regs);
            $ix->ixThumbWidth = $regs[1];
            $ix->ixThumbHeight = $regs[2];
        }

        /**
         * Insert (or update if unique insert fails) the post metadata
         * Need to clone the data for updating because the insert attempt modifies the data by stripping slashes (that's awful design!)
         */
        $indexDataClone = clone $indexData;
        add_post_meta($postID, 'easyindex', $indexData, true) || update_post_meta($postID, 'easyindex', $indexDataClone);

        /** @var WP_Theme $theme */
        $theme = wp_get_theme();

        /**
         * Thesis does their template selection in a weird non-standard way
         */
        
        if (strpos($theme->get('Name'), 'Thesis') !== false) {
            $template = basename($indexData->pixTemplate);
            add_post_meta($postID, '_wp_page_template', $template, true) || update_post_meta($postID, '_wp_page_template', $template);
        }

        /**
         * If we changed the post type rewrite slug, then possibly add it to the plugin settings
         */
        if ($oldSlug != $indexData->indexSlug) {
            $settings->adjustSlugs($oldSlug, $indexData->indexSlug);
        }
    }

}

