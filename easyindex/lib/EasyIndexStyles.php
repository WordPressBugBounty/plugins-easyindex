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
 * Class EasyIndexStyles
 *
 * Handles style processing
 */
class EasyIndexStyles {

    public $allStyles = array();

    static private $styles = array();

    public function getAllStyles() {
        /**
         * First get the style directory names (the directory name is a style's id)
         */
        $styleIDs = array();
        $settings = EasyIndexSettings::getInstance();

        if ($settings->customTemplates) {
            $styleIDs = $this->getStyleDirectories($settings->customTemplates, true);
        }

        $styleIDs = array_merge($styleIDs, self::getStyleDirectories(EasyIndex::$EasyIndexDir . '/styles'));

        sort($styleIDs);

        /**
         * Now read each style's json file & create and store a Style object for each
         */
        foreach ($styleIDs as $styleID) {
            $style = new EasyIndexStyle($styleID);
            if ($style->isValid()) {
                $this->allStyles[$styleID] = $style;
            }
        }
    }

    /**
     * Returns a EasyIndexStyle object given a style ID and caches it in a static array
     *
     * @param string $styleID The id of the style to get
     *
     * @return EasyIndexStyle
     */
    static function getStyle($styleID) {
        if (!isset(self::$styles[$styleID])) {
            self::$styles[$styleID] = new EasyIndexStyle($styleID);
        }
        return self::$styles[$styleID];
    }


    /**
     * @param $filter
     *
     * @return array
     */
    function getStyles($filter) {
        if (empty($this->allStyles)) {
            $this->getAllStyles();
        }
        $styles = array();
        /**
         * @var  EasyIndexStyle $style
         */
        foreach ($this->allStyles as $id => $style) {
            if ($style->indexType == $filter) {
                $styles[] = $style;
            }
        }
        return $styles;
    }

    /**
     * @return array
     */
    function getStyleNames() {
        if (empty($this->allStyles)) {
            $this->getAllStyles();
        }
        $names = array();
        /**
         * @var  EasyIndexStyle $style
         */
        foreach ($this->allStyles as $directory => $style) {
            $names[] = $style->name;
        }
        return $names;
    }


    /**
     * @param      $directory
     * @param bool $isCustom
     *
     * @return array
     */
    private function getStyleDirectories($directory, $isCustom = false) {
        $names = array();
        try {
            $stylesDir = new DirectoryIterator($directory);
        } catch (Exception $e) {
            return $names;
        }

        foreach ($stylesDir as $dir) {
            /** @var $dir DirectoryIterator */
            if (!$dir->isDir() || $dir->isDot()) {
                continue;
            }
            $name = $dir->getFilename();
            if (!file_exists("$directory/$name/style.json")) {
                continue;
            }

            $names[] = $isCustom ? "_$name" : $name;
        }

        return $names;
    }


}

