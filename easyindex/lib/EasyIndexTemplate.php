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

/** @noinspection PhpInconsistentReturnPointsInspection */

/**
 * Class EasyIndexTemplate
 */
class EasyIndexTemplate {
    const TEXT = 1;
    const PAGE = 2;
    const FILE = 3;
    const PAGETEXT = 4;

    const VARIABLEREPLACE = 1;
    const CLASSREPLACE = 2;
    const REPEATREPLACE = 3;
    const INCLUDEIF = 4;
    const STARTSTRIP = 5;
    const ENDSTRIP = 6;

    const PRESERVEWHITESPACE = 1;
    const PRESERVECOMMENTS = 2;
    private $inText;
    private $opText;
    private static $translate = false;
    private static $textDomain = '';

    /**
     * @var array Whitespace strip positions in the output string
     * +ve is a start position and -ve is an end position
     */
    private $stripWhitespace = array();

    /**
     * Process a template file or text doing variable replacements and repeated elements
     * @param string $file
     * @param int $type
     * @param string $pageFile
     */
    function __construct($file = '', $type = self::PAGE, $pageFile = '') {
        if ($file == '') {
            $this->inText = '';
            return;
        }
        /**
         * Figure out what sort of input we have
         * DOS line endings are converted to UNIX
         */
        switch ($type) {
            /**
             * TEXT is just a string
             */
            case self::TEXT :
                $this->inText = $file;
                break;

            /**
             * FILE is a text file
             */
            case self::FILE :
                $this->inText = @file_get_contents($file);
                if ($this->inText === false) {
                    trigger_error("Can't open file: '$file'", E_USER_NOTICE);
                    $this->inText = '';
                    return;
                }
                break;

            /**
             * PAGE is a V4 template file which should have <!-- START PAGE --> and <!-- END PAGE --> lines
             */
            case self::PAGE :
                $page = @file_get_contents($file);
                if ($page === false) {
                    trigger_error("Can't open page: '$file'", E_USER_NOTICE);
                    $this->inText = '';
                    return;
                }

                $this->processPage($page, $file);

                break;

            /**
             * text is passed in and process like a PAGE
             */
            case self::PAGETEXT :
                $this->processPage($file, $pageFile);
                break;
        }
        /**
         * Normalize up line breaks
         */
        $this->inText = str_replace("\r\n", "\n", $this->inText);
        $this->inText = str_replace("\r", "\n", $this->inText);
    }

    /**
     * Extract the actual template from $page
     * @param string $page HTML text containing a PAGE
     * @param string $file The original filename
     */
    private function processPage($page, $file) {
        /**
         * Figure out where the start and end of the page is
         */

        $startPage = '<!-- START PAGE -->';
        $endPage = '<!-- END PAGE -->';
        $startPosition = strpos($page, $startPage);
        if ($startPosition === false) {
            trigger_error("START PAGE not found in $file", E_USER_NOTICE);
            return;
        }
        $endPosition = strpos($page, $endPage);
        if ($endPosition === false) {
            trigger_error("END PAGE not found in $file", E_USER_NOTICE);
            return;
        }
        $startPosition += strlen($startPage);
        $page = substr($page, $startPosition, $endPosition - $startPosition);
        /**
         * Remove anything between <!-- START IGNORE --> and <!-- END IGNORE -->
         */
        $startIgnore = '<!-- START IGNORE -->';
        $endIgnore = '<!-- END IGNORE -->';
        $endIgnoreLength = strlen($endIgnore);
        while (($startPosition = strpos($page, $startIgnore)) !== false) {
            $endPosition = strpos($page, $endIgnore);
            if ($endPosition === false) {
                trigger_error("END IGNORE not found in $file", E_USER_NOTICE);
                return;
            }
            $page = substr($page, 0, $startPosition) . substr($page, $endPosition + $endIgnoreLength);
        }

        $this->inText = $page;

    }

    /**
     * @param $textDomain
     */
    static function setTranslate($textDomain) {
        self::$translate = true;
        self::$textDomain = $textDomain;
    }

    /**
     * A simple non-significant whitespace remover
     *
     * @param $html
     * @param $options
     * @return string
     */
    private function cleanWhitespace($html, $options) {
        if (($options & self::PRESERVECOMMENTS) == 0) {
            $html = preg_replace('/<!-- .*? -->/', '', $html);
        }
        if (($options & self::PRESERVEWHITESPACE) == 0) {
            $lines = explode("\n", $html);
            $html = '';
            foreach ($lines as $line) {
                if (($trimmed = trim($line)) != '') {
                    $html .= "$trimmed ";
                }
            }
        }
        return $html;
    }

    /**
     * @param int $options
     * @return string
     */
    function getTemplateHTML($options = 0) {
        return $this->cleanWhitespace($this->inText, $options);
    }

    /**
     * Process the template text
     *
     * @param object $data
     * @param int $options
     * @param object $data0 The base data for global lookups
     * @return string
     */
    function replace($data = null, $options = 0, $data0 = null) {
        /**
         * If we have replacements, pre-process the template and remove conditionally INCLUDEd stuff
         */
        if ($data === null) {
            $data = new stdClass();
        }

        /**
         * Store the base data for global variable lookup
         */
        if ($data0 == null) {
            $data0 = $data;
        }

        $currentPosition = 0;
        $this->opText = '';
        $inText = $this->inText;
        $firstType = null;
        /**
         * We return from within this loop when we have nothing left to process
         */
        while (true) {
            /**
             * Look for stuff to replace and find the first of them
             */
            $firstPosition = strlen($inText);
            $varPosition = strpos($inText, '#', $currentPosition);
            if ($varPosition !== false) {
                $firstPosition = $varPosition;
                $firstType = self::VARIABLEREPLACE;
            }
            $repeatPosition = strpos($inText, '<!-- START REPEAT ', $currentPosition);
            if ($repeatPosition !== false && $repeatPosition < $firstPosition) {
                $firstPosition = $repeatPosition;
                $firstType = self::REPEATREPLACE;
            }

            $includeifPosition = strpos($inText, '<!-- START INCLUDEIF ', $currentPosition);
            if ($includeifPosition !== false && $includeifPosition < $firstPosition) {
                $firstPosition = $includeifPosition;
                $firstType = self::INCLUDEIF;
            }

            $startStripPosition = strpos($inText, '<!-- START STRIP WHITESPACE ', $currentPosition);
            if ($startStripPosition !== false && $startStripPosition < $firstPosition) {
                $firstPosition = $startStripPosition;
                $firstType = self::STARTSTRIP;
            }

            $endStripPosition = strpos($inText, '<!-- END STRIP WHITESPACE ', $currentPosition);
            if ($endStripPosition !== false && $endStripPosition < $firstPosition) {
                $firstPosition = $endStripPosition;
                $firstType = self::ENDSTRIP;
            }

            /**
             * If there's nothing to do, just return what we've got
             */
            if ($firstPosition == strlen($inText)) {
                /**
                 * Copy any remaining input over to the output
                 */
                $this->opText .= substr($inText, $currentPosition);

                /*
                 * If there's any block to whitespace strip, do it
                 * Tidy up the start/stop positions first to make it easy to process
                 * This allows overlapping and unterminated stip blocks
                 */
                if (count($this->stripWhitespace) > 0) {
                    $stripBlocks = array();
                    $startPosition = -1;
                    foreach ($this->stripWhitespace as $position) {
                        /**
                         * End strip?
                         */
                        if ($position < 0) {
                            /**
                             * If there's no previous unterminated START STRIP, ignore this END
                             */
                            if ($startPosition == -1) {
                                continue;
                            }
                            $stripBlocks[] = array($startPosition, abs($position));
                            $startPosition = -1;
                        } else {
                            /**
                             * If there's a previous unterminated START STRIP, ignore this one
                             */
                            if ($startPosition != -1) {
                                continue;
                            }
                            $startPosition = $position;
                        }
                    }
                    /**
                     * Allow for a missing END STRIP
                     */
                    if ($startPosition != -1) {
                        $stripBlocks[] = array($startPosition, strlen($this->opText));
                    }
                    /**
                     * Actually strip out whitespace in the strip blocks
                     */
                    $currentPosition = 0;
                    $strippedText = '';
                    foreach ($stripBlocks as $stripBlock) {
                        $strippedText .= substr($this->opText, $currentPosition, $stripBlock[0] - $currentPosition);
                        $text = substr($this->opText, $stripBlock[0], $stripBlock[1] - $stripBlock[0]);
                        $text = preg_replace('/>\s+</', '><', $text);
                        $strippedText .= trim($text);
                        $currentPosition = $stripBlock[1];
                    }
                    $strippedText .= substr($this->opText, $currentPosition, strlen($this->opText) - $currentPosition);

                    $this->opText = $strippedText;
                }
                /**
                 * If we aren't translating, then just (optionally) clean up whitespace and return
                 */
                if (!self::$translate || !class_exists('EasyIndexDOMDocument')) {
                    return $this->cleanWhitespace($this->opText, $options);
                }
                $doc = new EasyIndexDOMDocument($this->opText, true);
                if (!$doc) {
                    return $this->opText;
                }

                $xlates = $doc->getElementsByClassName('xlate');

                if (count($xlates) == 0) {
                    return $this->cleanWhitespace($this->opText, $options);
                }

                
                foreach ($xlates as $xlate) {
                    $original = $doc->innerHTML($xlate);
                    $translation = __($original, self::$textDomain);
                    if ($translation != $original) {
                        $xlate->nodeValue = $translation;
                    }
                }
                $html = $doc->getHTML(true);
                return $this->cleanWhitespace($html, $options);
            }

            /**
             * Copy over everything up to the first thing we need to process
             */
            $length = $firstPosition - $currentPosition;
            $this->opText .= substr($inText, $currentPosition, $length);
            $currentPosition = $firstPosition;
            /**
             * Get the thing to be replaced
             */
            switch ($firstType) {
                /**
                 * INCLUDEIF includes the code up to the matching END INCLUDEIF:
                 *  IF the condition variable exists and it's not false or null
                 * Note the "continue 2" statements. the "switch" is condidered a loop so "contine" inside a switch acts like a break
                 * "continue 2" contiues the outer "while" iteration here
                 * As it turns out, "continue" will actually work here since the switch is the last block in the "while" loop
                 * however it will generate a warning in PHP 7.3
                 */
                case self::INCLUDEIF :
                    /**
                     * Get the conditional.
                     * Only check a smallish substring for efficiency
                     * This limits include condition names to 20 characters
                     */
                    $subString = substr($inText, $currentPosition, 60);

                    if (preg_match('/<!-- START INCLUDEIF (!?)([_a-z][_0-9a-z]{0,31}) -->/i', $subString, $regs)) {
                        $negate = $regs[1];
                        $trueFalse = $negate != '!';
                        $includeCondition = $regs[2];
                    } else {
                        trigger_error("Malformed START INCLUDEIF at $currentPosition ($subString)", E_USER_NOTICE);
                        $this->opText .= "<";
                        $currentPosition++;
                        continue 2;
                    }
                    $endInclude = "<!-- END INCLUDEIF $negate$includeCondition -->";

                    $endIncludeLength = strlen($endInclude);
                    $endPosition = strpos($inText, $endInclude, $currentPosition);
                    if ($endPosition == false) {
                        trigger_error(htmlspecialchars("'$endInclude'") . " not found", E_USER_NOTICE);
                        $this->opText .= "<";
                        $currentPosition++;
                        break;
                    }

                    /**
                     * If the condition is met, just remove the INCLUDEIF comments
                     * If the condition isn't met, remove everything up to the END INCLUDEIF
                     * The condition must be present, and NOT false or NULL
                     */
                    $condition = isset($data->$includeCondition) && $data->$includeCondition !== false && $data->$includeCondition !== null;

                    if ($condition === $trueFalse) {
                        $startInclude = "<!-- START INCLUDEIF $negate$includeCondition -->";
                        $startIncludeLength = strlen($startInclude);
                        $inText = substr($inText, 0, $currentPosition) . substr($inText, $currentPosition + $startIncludeLength, $endPosition - $currentPosition - $startIncludeLength) . substr($inText, $endPosition + $endIncludeLength);
                    } else {
                        $inText = substr($inText, 0, $currentPosition) . substr($inText, $endPosition + $endIncludeLength);
                    }
                    break;

                /**
                 * Remove whitespace between tags.
                 * Useful to remove unwanted significant HTML whitespace that may have been introduced by auto formatting the source template
                 */
                case self::STARTSTRIP :
                    $currentPosition += 31;
                    $this->stripWhitespace[] = strlen($this->opText);

                    break;

                /**
                 * Save the output position at which to stop stripping. -ve indicates that it's an end position
                 */
                case self::ENDSTRIP :
                    $currentPosition += 29;
                    $this->stripWhitespace[] = -strlen($this->opText);
                    break;

                /**
                 * A variable is a valid PHP variable name (limited to 20 chars) between #'s
                 * If we don't find a valid name, copy over the # and continue
                 * FIXME - fall back to caller's vars if it doesn't exist
                 */
                case self::VARIABLEREPLACE :
                    $s = substr($inText, $currentPosition, 34);
                    if (!preg_match("%^#([/_a-z][_0-9a-z]{0,31})#%si", $s, $regs)) {
                        $this->opText .= '#';
                        $currentPosition++;
                        continue 2;
                    }
                    /**
                     * If we don't have a match for the variable, just assume it's not what we wanted to do
                     * so put the string we matched back into the output and continue from the trailing #
                     */
                    $varName = $regs[1];
                    /**
                     * Allow variables to refer to the base data
                     * TODO - make this a proper recursive search?
                     */
                    if ($varName[0] == '/') {
                        $varName = substr($varName, 1);
                        if (!isset($data0->$varName)) {
                            $this->opText .= '#' . $varName;
                            $currentPosition += strlen($varName) + 1;
                            continue 2;
                        }
                        $this->opText .= $data0->$varName;
                        $currentPosition += strlen($varName) + 3;

                    } else {
                        if (!isset($data->$varName)) {
                            $this->opText .= '#' . $varName;
                            $currentPosition += strlen($varName) + 1;
                            continue 2;
                        }

                        /**
                         * Got a match - replace the "#...#" with the vars stuff
                         * We *could* pass this on for recursive processing, but it's not something we would normally want to do
                         * Maybe have a special naming convention for vars we want to do recursively?
                         */
                        $this->opText .= $data->$varName;
                        $currentPosition += strlen($varName) + 2;
                    }
                    break;

                /**
                 * We've seen a start repeat.
                 * Find the name of the repeat (limited to 20 chars)
                 * If we can't find the name, assume it's not what we want and continue
                 *
                 * Look for a valid START REPEAT in the next 45 characters
                 */
                case self::REPEATREPLACE :
                    $s = substr($inText, $currentPosition, 45);
                    if (!preg_match('/<!-- START REPEAT ([_a-zA-Z][_0-9a-zA-Z]{0,19}) -->/m', $s, $regs)) {
                        $this->opText .= '<';
                        $currentPosition++;
                        continue 2;
                    }
                    $rptName = $regs[1];
                    /**
                     * Make sure we have a matching key and it's an array
                     */
                    if (!isset($data->$rptName) || !is_array($data->$rptName)) {
                        $this->opText .= '<';
                        $currentPosition++;
                        continue 2;
                    }

                    /**
                     * Now try to find the end of this repeat
                     */
                    $currentPosition += strlen($rptName) + 22;
                    $rptEnd = strpos($inText, "<!-- END REPEAT $rptName -->", $currentPosition);
                    if ($rptEnd === false) {
                        $this->opText .= '<!-- START REPEAT $rptName -->';
                        trigger_error("END REPEAT not found for $rptName", E_USER_NOTICE);
                        continue 2;
                    }

                    /**
                     * Do the repeat processing.
                     * For each item in the repeated array, process as a new template
                     */
                    $rptLength = $rptEnd - $currentPosition;
                    $rptString = substr($inText, $currentPosition, $rptLength);
                    $rptVars = $data->$rptName;
                    for ($i = 0; $i < count($rptVars); $i++) {
                        $saveTranslate = self::$translate;
                        self::$translate = false;
                        $rpt = new EasyIndexTemplate($rptString, self::TEXT);
                        $this->opText .= $rpt->replace($rptVars[$i], $options, $data0);
                        self::$translate = $saveTranslate;
                    }

                    /**
                     * Step over the end repeat
                     */
                    $currentPosition += strlen($rptName) + $rptLength + 20;
                    break;
            }
        }
        return '';
    }

}

