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
 * Class EasyIndexWalker
  */
class EasyIndexWalker extends Walker {
    var $db_fields = array('parent' => 'parent', 'id' => 'term_id');


    /**
     * @param string $output
     * @param object $object
     * @param int    $depth
     * @param array  $args
     * @param int    $current_object_id
     */
    function start_el(&$output, $object, $depth = 0, $args = array(), $current_object_id = 0) {
        $object->depth = $depth;
        if ($output == '') {
            $output = array();
        }
        $output[] = $object;
    }

    /**
     * @param string $output
     * @param object $object
     * @param int    $depth
     * @param array  $args
     */
    function end_el(&$output, $object, $depth = 0, $args = array()) {
    }

}

