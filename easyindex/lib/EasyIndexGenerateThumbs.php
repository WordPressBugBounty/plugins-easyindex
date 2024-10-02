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
 * Class EasyIndexGenerateThumbs
 *
 * Handles generating thumbs and also the interface to the progress bar on the index edit page
 * The generation can be done one image at a time by repeated ajax calls from the index edit page (slow)
 * OR it can be run as a background job that will process all images in a single call and use ajax calls from index edit to get the generator progress/status
 *
 * This class is instantiated by reading from an existing postmeta data
 * If there is no postmeta record,
 *      OR the postmeta data is totally invalid (wrong postID)
 *      OR it's from a run earlier than the run we're running AND that run has either finished or timed out
 * then a new instance is created and saved to postmeta
 */
class EasyIndexGenerateThumbs {

    const STATUS_NEW = 1;
    const STATUS_CREATED = 2;
    const STATUS_RUNNING = 3;
    const STATUS_FINSISHED = 4;

    /** @var array An array of counts of the number of thumbs generated, indexed by taxonomy ID so we can tell when we have enough for each taxonomy */
    private $termItems;

    /** @var  Object */
    private $primaryItems;
    /** @var  Object */
    private $secondaryItems;

    private $thumbsDone;
    private $postID;
    private $totalItems;

    private $regenerate;

    /** @var bool TRUE if the primary index is a single pre-set image per term */
    private $isSingle;
    /** @var  string The taxonomy of a single index */
    private $taxonomy;
    /** @var array An array of the terms in a single index */
    private $termTaxIDs;

    public $lastError = false;

    /** @var int Aid to debugging. The time that this run was instantiated */
    private $started = 0;

    /** @var int Aid to debugging. The time that the most recent thumb was generated */
    private $lastGenerated = 0;

    /** @var int Aid to debugging. The most recent thumb ID that was processed */
    private $lastThumb = 0;

    /** @var  int The time (in msecs) that this run should timeout at. Only applicable if running in background */
    private $endTime;

    /** @var  int The number of seconds we can run the generation if we are not running in background (from Settings) */
    private $timeout;

    /** @var  int The timestamp (in msecs) from javascript of the run */
    public $timestamp;

    /** @var int The status of the current run */
    public $status;




    /**
     * Ensure we never construct except from getInstance()
     * Set the post ID, timestamp and status and write it to postmeta
     * @param int $postID
     * @param int $timestamp
     */
    private function __construct($postID, $timestamp) {
        $postID = (int)$postID;
        $this->postID = $postID;
        $this->timestamp = (int)$timestamp;
        $this->status = self::STATUS_NEW;
        update_post_meta($postID, 'easyindexGenerate', $this);
    }

    /**
     * Get either an existing generator or create a new instance
     *
     * @param int $postID
     * @param int $timestamp
     * @return EasyIndexGenerateThumbs
     */
    static function getInstance($postID, $timestamp) {
        $generator = get_post_meta((int)$postID, 'easyindexGenerate', true);
        if ($generator instanceof EasyIndexGenerateThumbs) {
            /**
             * This should never happen, but ....
             */
            if ($generator->postID != $postID) {
                $generator = new EasyIndexGenerateThumbs($postID, $timestamp);
            } else {
                /**
                 * If the timestamp is not the same, then check to see if the run has finsihed or its timeout has expired
                 * If so, instantiate a new generator
                 */
                if ($timestamp != $generator->timestamp) {
                    $settings = EasyIndexSettings::getInstance();
                    $mins = $settings->bgGeneration ? 60 : 1;
                    if ($generator->status == self::STATUS_FINSISHED || time() * 1000 > $generator->timestamp + $settings->generatorTimeout * $mins * 1000) {
                        $generator = new EasyIndexGenerateThumbs($postID, $timestamp);
                    }
                }
            }
        } else {
            $generator = new EasyIndexGenerateThumbs($postID, $timestamp);
        }
        return $generator;
    }

    /**
     * Do some sanity checks before we start and then generate all thumbnails in one pass
     *
     * @param int $postID The post ID of the index
     * @param int $timestamp The (millisecond) timestamp of when the "pregenerate" javascript call was made
     */
    public function start($postID, $timestamp) {
//        register_shutdown_function(array($this, 'shutdown'));  

        /**
         * The post ID makes no sense so just exit
         */
        if ($postID != $this->postID) {
            return;
        }

        /**
         * Make sure the instance we got is the one that the background task should be processing
         */
        if ($timestamp != $this->timestamp) {
            return;
        }

        /**
         * It doesn't make any sense for the status to be anything other than CREATED
         */
        if ($this->status != self::STATUS_CREATED) {
            return;
        }

        /**
         * Register a shutdown function so we can absolutely definitely mark this run as finsihed whether we terminate normally or not
         */
        register_shutdown_function(array($this, 'shutdown'));

        $settings = EasyIndexSettings::getInstance();


        /**
         * Set the job as running so it doesn't get started again while it runs
         * Only let it run for "generatorTimeout" minutes
         */
        $this->status = self::STATUS_RUNNING;
        update_post_meta($postID, 'easyindexGenerate', $this);

        /**
         * Make sure we don't run forever
         */
        $timeout = $settings->generatorTimeout * 60;
        $endTime = time() + $timeout;

        /**
         * Allow a fudge factor so we are more likley to pickup a timeout cleanly rather than have the script abort
         */
        set_time_limit($timeout + 8);

        /**
         * Generate all the thumbnails required
         */
        $next = $this->getNextItem();
        while ($next->nextID != 0) {
            /**
             * Generate one thumb. $this->generate() returns false if no error occurred. If an error occurred, terminate
             */
            if ($this->generate($next)) {
            } else if (time() > $endTime) {
                $this->lastError = "Generator timeout expired";
            } else {
                $next = $this->getNextItem();
            }
            /**
             * If we got an error, save the instance so the error is saved before returning
             */
            if ($this->lastError) {

                update_post_meta($postID, 'easyindexGenerate', $this);
                return;
            }
        }

    }

    /**
     * Trap errors and terminate the run if there was an error
     */
    public function shutdown() {


        $error = error_get_last();
        /**
         * If it's a fatal error, not much more we can do except return it
         */
        if ($error['type'] == E_ERROR) {
            $this->lastError = $error['message'];
            $response = new stdClass();
            $response->status = 'ERROR';
            $response->error = $this->lastError;
            $next = new stdClass();
            $next->nextID = $this->lastThumb;
            $response->next = $next;
            $response->pcdone = $next->nextID == 0 ? 100 : $this->percentDone();
            $response->memory = memory_get_peak_usage(true);

            @header('HTTP/1.1 200 OK');
            @header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $this->status = self::STATUS_FINSISHED;
        update_post_meta($this->postID, 'easyindexGenerate', $this);

    }


    /**
     * This is the ajax entry point
     * Either set up the generation, generate one thumb (non-background), or check the progress/status of a background generation
     * @param int $timestamp
     */
    public function run($timestamp) {
        $cmd = isset($_POST['cmd']) ? $_POST['cmd'] : '';
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $this->postID = isset($_POST['postID']) ? (int)$_POST['postID'] : 0;

        if (!wp_verify_nonce($nonce, "update-post_$this->postID")) {
            $this->error('Invalid nonce');
        }

        if ($timestamp != $this->timestamp) {
            $remain = floor(($timestamp - $this->timestamp) / 1000);
            $this->error("A previous generate run has not yet finished ($remain)");
        }

        switch ($cmd) {

            case 'pregenerate':
                $this->preGenerate();
                break;

            case 'generate':
                $this->fgGeneration();
                break;

            case 'check':
                $this->checkGeneration();
                break;

            default:
                $this->error('Invalid cmd');
        }
    }

    /**
     * Something bad happened
     * Send the error back to the ajax caller (non-background) or return the error
     * @param string $msg
     * @return bool Returns FALSE if we aren't running via ajax
     */
    private function error($msg = '') {
        if (defined('DOING_AJAX')) {
            $response = new stdClass();
            $response->status = 'FAIL';
            $response->error = $msg;
            wp_send_json($response);
        }
        $this->lastError = $msg;
        return $msg;
    }

    /**
     * Get the post IDs of the posts to get thumbs for
     *
     * @param $isPrimary TRUE if we are looking for the primary index thumbs else FALSE for the secondary index
     * @param array $data The index data
     * @param array $index The primary or secondary index data
     * @return stdClass
     */
    private function getIDs($isPrimary, $data, $index) {
        $styleID = $index['ixStyleID'];
        $style = new EasyIndexStyle($styleID);
        if (!($style instanceof EasyIndexStyle) || $style->id != $styleID) {
            return $this->error("Invalid style '$styleID'");
        }
        $result = new stdClass();
        $result->items = array();
        $result->thumbSize = '';
        /**
         * Text indexes don't have any thumbs!
         */
        if (!$style->isText) {
            $thumbSize = $index['ixThumbSize'];
            $result->thumbSize = $thumbSize;
            $result->numberPosts = isset($index['ixNumberPosts']) ? $index['ixNumberPosts'] : 0;
            /**
             * Check that the thumb size is valid else we'll generate garbage
             * Should have been validated by JS but be paranoid
             */
            if (!preg_match('/^(\d+)x(\d+)$/', $thumbSize, $regs)) {
                return $this->error("Invalid thumb size '$thumbSize'");
            }
            $minHeight = $style->styleType == 'gallery' ? 0 : 20;

            if ($regs[1] < 20 || $regs[2] < $minHeight) {
                return $this->error("Invalid thumb size '$thumbSize'");
            }

            /** @var EasyIndexData $indexData */
            $indexData = new EasyIndexData('');
            $indexData->indexAllTerms = !empty($data['indexAllTerms'][$data['indexTaxonomy']]);
            if ($indexData->indexAllTerms) {
                $indexData->indexTerms = array();
                unset($data['indexTerms']);
            }
            if (!empty($data['indexTerms'])) {
                $indexData->indexTerms = $data['indexTerms'][$data['indexTaxonomy']];
            }
            $indexData->indexTaxonomy = $data['indexTaxonomy'];
            $terms = EasyIndexIndex::getTerms($indexData);


            /**
             * Single indexes use preset images for each term (and can only be primary indexes)
             *
             */
            if ($style->styleType == 'single') {
                $this->isSingle = true;
                $this->taxonomy = $indexData->indexTaxonomy;
                foreach ($terms as $term) {
                    $this->termTaxIDs[] = $term->term_taxonomy_id;
                }
                $result->isGallery = false;
            } else {

                $termTaxIDs = array();
                foreach ($terms as $term) {
                    $termTaxIDs[] = $term->term_taxonomy_id;
                    $this->termItems[$term->term_taxonomy_id] = 0;
                }
                $tIDs = implode(',', $termTaxIDs);

                $result->isGallery = $style->styleType == 'gallery';

                $orderBy = 'post_date DESC';
                $items = EasyIndexIndex::getIndexItems($isPrimary, $indexData->indexTaxonomy, $tIDs, $result->isGallery, $orderBy);
                if (count($items) > 0) {
                    if (is_object($items[0])) {
                        $result->items = $items;
                    } else {
                        $result->items = array();
                        foreach ($items as $item) {
                            $result->items[$item] = true;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Work out which post will be processed next
     * Process all the secondary thumbs first. Ther emau be an overlap with the primary thumbs
     * Once all the secondaries are processed, do whatever's left of the primaries
     *
     * @return stdClass Returns {nextID, isSingle, isPrimary}  nextID is set to zero when no more to do
     */
    function getNextItem() {
        $result = new stdClass();
        $result->nextID = 0;
        $result->isSingle = false;
        if (count($this->secondaryItems->items) > 0) {
            /**
             * Secondary items should always be an array of postIDs
             */
            reset($this->secondaryItems->items);
            $result->nextID = key($this->secondaryItems->items);
            $result->isPrimary = false;
        } else if (count($this->primaryItems->items) > 0) {
            /**
             * Primary items might be an array of postIDs (galleries) or an array of objects of {taxID, postID} (non-galleries)
             */
            if ($this->primaryItems->isGallery) {
                reset($this->primaryItems->items);
                $result->nextID = key($this->primaryItems->items);
            } else {
                reset($this->primaryItems->items);
                $next = current($this->primaryItems->items);
                $result->nextID = $next->ID;
            }
            $result->isPrimary = true;
        } else if (count($this->termTaxIDs) > 0) {
            $result->nextID = $this->termTaxIDs[0];
            $result->isPrimary = true;
            $result->isSingle = true;
        }
        return $result;
    }

    /**
     * Work out what thumbs need to be generated (unless we already did that and the generator is running), and save the details
     * If we are using background generation, then also schedule the run (unless it's aready running)
     */
    private function preGenerate() {
        if (empty($_POST['EasyIndex'])) {
            $this->error('EasyIndex data missing');
        }

        $this->regenerate = isset($_POST['regen']) ? (int)$_POST['regen'] : -1;
        if ($this->regenerate == -1) {
            $this->error('Bad regenerate flag');
        }

        $timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;

        $response = new stdClass();
        /**
         * If we are doing thumbs in the background, check that any previous instance of the generator has finsihed
         */
        $settings = EasyIndexSettings::getInstance();
        if ($settings->bgGeneration) {
            if ($this->timestamp != $timestamp) {
                
            }
        }
        /**
         * If we're here, either the thumbnail generator isn't running yet or we aren't using BG generation
         */
        $this->termItems = array();
        $this->isSingle = false;
        $this->termTaxIDs = array();


        $data = $_POST['EasyIndex'];
        $index = $data['primary'];
        $this->primaryItems = $this->getIDs(true, $data, $index);

        /**
         * If the primary is a gallery, then there is no secondary
         */
        if ($index['isGallery'] == '1') {
            $this->secondaryItems = new stdClass();
            $this->secondaryItems->items = array();
            /**
             * Some silliness because PHP Code Sniffer PHPCompatability checks report '0x0' as a possible error
             */
            $this->secondaryItems->thumbSize = '0' . 'x0';
        } else {
            $index = $data['secondary'];
            $this->secondaryItems = $this->getIDs(false, $data, $index);
        }
        $this->totalItems = count($this->primaryItems->items) + count($this->secondaryItems->items) + count($this->termTaxIDs);
        $this->thumbsDone = array();
        $this->thumbsDone[$this->primaryItems->thumbSize] = array();
        $this->thumbsDone[$this->secondaryItems->thumbSize] = array();

        $this->lastError = false;
        /**
         * Save the # seconds we can run in foreground
         */
        $this->timeout = $settings->generatorTimeout;

        /**
         * If we are running in the background, figure out the time we should stop in case the background run doesn't ever get to terminate or check its own timeout
         */
        $this->endTime = $settings->bgGeneration ? time() * 1000 + $settings->generatorTimeout * 60 * 1000 : 0;

        /**
         * Save the data so we either know where we are up to (not using background generation) or pass the data to the background job
         */
        $this->status = self::STATUS_CREATED;
        update_post_meta($this->postID, 'easyindexGenerate', $this);

        $response->next = $this->getNextItem();
        $response->pcdone = 0;
        $response->status = 'OK';

        /**
         * If we're processing in background, then kick it off with a non-blocking load of the background task
         */
        if ($settings->bgGeneration) {
            $url = EasyIndex::$EasyIndexUrl . "/easyindexgenerate.php";
            $requestArgs = array(
                'timeout'   => 0.01,
                'blocking'  => false,
                'sslverify' => false,
                'body'      => array('postID' => $this->postID, 'timestamp' => $timestamp, 'next' => $response->next)
            );

            wp_remote_post($url, $requestArgs);

        }
        $this->started = time();


        wp_send_json($response);
    }

    /**
     * Called via ajax from the index edit page to get the progress/status of the background  generation run
     */
    function checkGeneration() {
        $response = new stdClass();
        /**
         * Just a sanity check
         */
        $postID = !empty($_POST['postID']) ? (int)$_POST['postID'] : 0;
        if ($postID == 0 || $postID != $this->postID) {
            $this->error("Invalid post ID");
        }

        /**
         * Make sure some diastrous error (like out of memory or runtime limit exceeded) hasn't occurred
         */
        if ($this->lastError) {
            $this->error($this->lastError);
        }

        /**
         * Make sure that we haven't exceeded a background run timeout
         * This might happen if the generate run never got started for example
         * Allow a fudge factor to allow a background run to timeout cleanly
         */
        if ($this->endTime > 0 && $this->endTime < (time() + 10) * 1000) {
            $this->status = self::STATUS_FINSISHED;
            update_post_meta($this->postID, 'easyindexGenerate', $this);
            $this->error("Generator timeout expired");
        }

        $response->next = $this->getNextItem();
        $response->pcdone = $response->next->nextID == 0 ? 100 : $this->percentDone();

        if ($this->status == self::STATUS_RUNNING) {
            $response->status = 'OK';
        } else if ($this->status == self::STATUS_CREATED) {
            $response->status = 'WAIT';
        } else if ($this->status == self::STATUS_FINSISHED) {
            if ($response->next->nextID != 0) {
                $this->error("Generator has stopped but generation is not complete");
            } else {
                $response->status = 'OK';
            }
        }

        /**
         * Has this run timed out if we're running in background?
         * The background generation *should* have set the status to FINSISHED, but it's possible that it didn't
         */
        wp_send_json($response);
    }

    /**
     * Called via ajax if we are NOT doing BG generation
     * Generates thumbs until the timeout (seconds) expires
     */
    private function fgGeneration() {
        register_shutdown_function(array($this, 'shutdown'));
        $next = (Object)$_POST;
        $endtime = time() + $this->timeout;
        $response = new stdClass();

        while (time() < $endtime && $next->nextID > 0) {
            $this->generate($next);
            $next = $this->getNextItem();
        }

        /**
         * Flag as finsihed if no more to do
         */
        if ($next->nextID == 0) {
            $this->status = self::STATUS_FINSISHED;
            update_post_meta($this->postID, 'easyindexGenerate', $this);
        }

        /**
         * Return the status and the next data
         */
        $response->next = $next;
        $response->pcdone = $next->nextID == 0 ? 100 : $this->percentDone();
        $response->status = 'OK';
        $response->memory = memory_get_peak_usage(true);
        wp_send_json($response);
    }

    /**
     * Generate one thumb and return. Called from the background task if we are using bg generation, or via ajax if not using background generation
     *
     * @param stdClass $next If we are running in background, then arguments are passed here, else they are in $_POST from the JS ajax call
     * @return bool|stdClass    If running in background, return FALSE if no error, else return the error. If not running in bg, then returns via json
     */
    public function generate($next) {

        $nextID = !empty($next->nextID) ? (int)$next->nextID : 0;
        if ($nextID == 0) {
            return $this->error('Invalid next id');
        }
        $isPrimary = isset($next->isPrimary) ? (int)$next->isPrimary : -1;
        if ($isPrimary == -1) {
            return $this->error('Invalid isPrimary flag');
        }

        $isSingle = isset($next->isSingle) ? (int)$next->isSingle : -1;
        if ($isSingle == -1) {
            return $this->error('Invalid isSingle flag');
        }

        $thumbSize = $isPrimary ? $this->primaryItems->thumbSize : $this->secondaryItems->thumbSize;

        $options = $this->regenerate ? EasyIndexThumbnail::REGENERATETHUMB : 0;

        if ($isSingle) {
            $settings = EasyIndexSettings::getInstance();
            /**
             * postID is actually the taxonomy ID
             */
            $thumbSource = !empty($settings->termThumbSource[$this->taxonomy][$nextID]) ? $settings->termThumbSource[$this->taxonomy][$nextID] : '';
            if (empty($thumbSource)) {
                $thumb = new EasyIndexThumbnail(0, EasyIndex::$EasyIndexDir . '/images/noimage150.png');
                $thumb->makeThumb($thumbSize, "noimage", EasyIndexThumbnailBase::NOCROP | $options);
            } else {
                $thumb = new EasyIndexThumbnail(0, $thumbSource);
                $thumb->makeThumb($thumbSize, "{$this->taxonomy}-{$nextID}", $options);
            }
            array_shift($this->termTaxIDs);
        } else {
            $thumb = new EasyIndexThumbnail($nextID);
            if ($isPrimary) {
                $thumbnail = false;
                /**
                 * Don't even bother trying if we already generated it
                 */
                if (!isset($this->thumbsDone[$thumbSize][$nextID])) {
                    $thumbnail = $thumb->makeThumb($thumbSize, "thumb-$nextID", $options);
                    /**
                     * If we generated a thumb (or it already existed), remember that so we don't regenerate if it occurs in another term
                     */
                    if ($thumbnail !== null) {
                        $this->thumbsDone[$thumbSize][$nextID] = true;
                    }
                }

                /**
                 * If the index is a gallery, just unset the first item
                 */
                if ($this->primaryItems->isGallery) {
                    unset($this->primaryItems->items[$nextID]);
                } else {
                    /**
                     * Move the item off the array
                     */
                    $item = array_shift($this->primaryItems->items);

                    /**
                     * If a thumbnail exists, or was generated, then bump the number of thumbs we have for a term
                     */
                    if ($thumbnail !== null) {
                        $taxID = $item->term_taxonomy_id;
                        $this->termItems[$taxID]++;

                        /**
                         * If we now have enough thumbs for the term, remove other posts still to be processed for the term
                         */
                        if ($this->termItems[$taxID] == $this->primaryItems->numberPosts) {
                            $i = 0;
                            while ($i < count($this->primaryItems->items)) {
                                if ($this->primaryItems->items[$i]->term_taxonomy_id == $taxID) {
                                    array_splice($this->primaryItems->items, $i, 1);
                                } else {
                                    $i++;
                                }
                            }
                        }
                    }
                }
            } else {
                /**
                 * Make the thumb (if necessary) and remove it from the list to be processed
                 */
                $thumb->makeThumb($thumbSize, "thumb-$nextID", $options);
                $this->thumbsDone[$thumbSize][$nextID] = true;
                unset($this->secondaryItems->items[$nextID]);
            }

        }
        $this->lastGenerated = time();
        $this->lastThumb = $next->nextID;
        update_post_meta($this->postID, 'easyindexGenerate', $this);
        /**
         *  Return FALSE to indicate "no error"
         */
        return false;
    }


    /**
     * Some hosts can't call URL's on their own domain due to bad DNS or misguided security configuratons
     * We rely on being able to make a non-blocking request to the blog domain to run in background
     * This function checks that a request to the background task code succeeds
     */
    public static function backgroundCheck() {
        /**
         * Make a blocking request to the background task URL with a shortish timeout (3 seconds should be ample time to process a same domain request)
         */
        $url = EasyIndex::$EasyIndexUrl . "/easyindexgenerate.php";
        $requestArgs = array(
            'timeout'   => 2,
            'blocking'  => true,
            'sslverify' => false,
            'body'      => array('check' => 1)
        );

        $response = wp_remote_post($url, $requestArgs);
        $result = new stdClass();
        if ($response instanceof WP_Error || !isset($response['body']) || $response['body'] != 'OK') {
            $result->status = 'FAIL';
        } else {
            $result->status = 'OK';
        }

        wp_send_json($result);
    }

    /**
     * Returns the percent done
     *
     * @return int
     */
    private function percentDone() {
        return (int)(($this->totalItems - (count($this->primaryItems->items) + count($this->secondaryItems->items) + count($this->termTaxIDs))) * 100 / $this->totalItems);
    }

}

