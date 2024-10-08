<?php
/**
 * EasyIndexMicrodata
 *
 * Derived from MicrodataPHP
 *
 * http://github.com/linclark/MicrodataPHP
 * Copyright (c) 2011 Lin Clark
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 *
 * Based on MicrodataJS
 * http://gitorious.org/microdatajs/microdatajs
 * Copyright (c) 2009-2011 Philip J�genstedt
 *
 * Copyright (c) 2010-2013 Box Hill LLC
 *
 */

/**
 * Extracts microdata from HTML.
 *
 * Currently supported formats:
 * - PHP object
 * - JSON
 */
class EasyIndexMicrodata {
    private $dom;

    /**
     * @param $content
     */
    public function __construct($content) {
        $dom = new EasyIndexDOMDocument("", false);
        $dom->registerNodeClass('DOMDocument', 'EasyIndexDOMDocument');
        $dom->registerNodeClass('DOMElement', 'EasyIndexMicrodataDOMElement');
        $dom->preserveWhiteSpace = false;
        @$dom->loadHTML($content);

        $this->dom = $dom;
    }

    /**
     * @return bool TRUE if this instance was  instantiated with valid HTML
     */
    public function isValid() {
        return $this->dom->isValid();
    }

    /**
     * @param string $schema
     * @return stdClass An object with an 'items' property, which is an array of top level
     *         microdata items as objects with the following properties:
     *         - type: An array of itemtype(s) for the item, if specified.
     *         - id: The itemid of the item, if specified.
     *         - properties: An array of itemprops. Each itemprop is keyed by the
     *         itemprop name and has its own array of values. Values can be strings
     *         or can be other items, represented as objects.
     *
     * @todo MicrodataJS allows callers to pass in a selector for limiting the
     *       parsing to one section of the document. Consider adding such
     *       functionality.
     */
    public function getItems($schema = '') {
        $result = new stdClass();
        $result->items = array();
        foreach ($this->dom->getItems($schema) as $item) {
            array_push($result->items, $this->getObject($item, array()));
        }
        return $result;
    }

    /**
     * Retrieve microdata in JSON format.
     *
     * @return string JSON string of getItems().
     *
     * @todo MicrodataJS allows callers to pass in a function to format the JSON.
     *       Consider adding such functionality.
     */
    public function json() {
        return json_encode($this->getItems());
    }

    /**
     * Helper function.
     *
     * In MicrodataJS, this is handled using a closure. PHP 5.3 allows closures,
     * but cannot use $this within the closure. PHP 5.4 reintroduces support for
     * $this. When PHP 5.3/5.4 are more widely supported on shared hosting,
     * this function could be handled with a closure.
     * @param object $item
     * @param $memory
     * @return stdClass
     */
    protected function getObject($item, $memory) {
        $result = new stdClass();
        $result->properties = array();

        // Add itemtype.
        if (($itemtype = $item->itemType()) != null) {
            $result->type = $itemtype;
        }
        // Add itemid.
        if (($itemid = $item->itemid()) != null) {
            $result->id = $itemid;
        }
        $result->nodeValue = $item->nodeValue;
        // Add properties.
        foreach ($item->properties() as $elem) {
            if ($elem->itemScope()) {
                if (in_array($elem, $memory)) {
                    $value = 'ERROR';
                } else {
                    $memory[] = $item;
                    $value = $this->getObject($elem, $memory);
                    array_pop($memory);
                }
            } else {
                $value = $elem->itemValue();
            }
            foreach ($elem->itemProp() as $prop) {
                $result->properties[$prop][] = $value;
            }

        }

        return $result;
    }

    /**
     * Returns the EasyIndexDOMDocument
     *
     * @return EasyIndexDOMDocument
     */
    public function getDom() {
        return $this->dom;
    }

}

/**
 * Extend the DOMElement class with the Microdata API functions.
 */
class EasyIndexMicrodataDOMElement extends DOMElement {

    /**
     * Determine whether the itemscope attribute is present on this element.
     *
     * @return boolean TRUE if this is an item, FALSE if it is not.
     */
    public function itemScope() {
        return $this->hasAttribute('itemscope');
    }

    /**
     * Retrieve this item's itemtypes.
     *
     * @return array An array of itemtype tokens.
     */

    public function itemType() {
        $itemtype = $this->getAttribute('itemtype');
        if (!empty($itemtype)) {
            return $this->tokenList($itemtype);
        }
        // Return NULL instead of the empty string returned by getAttributes so we
        // can use the function for boolean tests.
        return NULL;
    }

    /**
     * Retrieve this item's itemid.
     *
     * @return string A string with the itemid.
     */
    public function itemId() {
        $itemid = $this->getAttribute('itemid');
        if (!empty($itemid)) {
            return $itemid;
        }
        // Return NULL instead of the empty string returned by getAttributes so we
        // can use the function for boolean tests.
        return NULL;
    }

    /**
     * Retrieve this item's itemprops.
     *
     * @return array An array of itemprop tokens.
     */
    public function itemProp() {
        $itemprop = $this->getAttribute('itemprop');
        if (!empty($itemprop)) {
            return $this->tokenList($itemprop);
        }
        return array();
    }

    /**
     * Retrieve the ids of other items which this item references.
     *
     * @return array An array of ids as contained in the itemref attribute.
     */
    public function itemRef() {
        $itemref = $this->getAttribute('itemref');
        if (!empty($itemref)) {
            return $this->tokenList($itemref);
        }
        return array();
    }

    /**
     * Retrieve the properties
     *
     * @return array An array of EasyIndexDOMElements which are properties of this
     *         element.
     */
    public function properties() {
        $props = array();

        if ($this->itemScope()) {
            $toTraverse = array($this);

            foreach ($this->itemRef() as $itemref) {
                $children = $this->ownerDocument->xpath()->query('//*[@id="' . $itemref . '"]');
                foreach ($children as $child) {
                    $this->traverse($child, $toTraverse, $props, $this);
                }
            }
            while (count($toTraverse)) {
                $this->traverse($toTraverse[0], $toTraverse, $props, $this);
            }
        }

        return $props;
    }

    /**
     * Retrieve the element's value, determined by the element type.
     *
     * @return string|Object The string value if the element is not an item, or $this if it is an item.
     */
    public function itemValue() {
        $itemprop = $this->itemProp();
        if (empty($itemprop)) {
            return null;
        }
        if ($this->itemScope()) {
            return $this;
        }
        switch (strtoupper($this->tagName)) {
            case 'META' :
                return $this->getAttribute('content');
            case 'AUDIO' :
            case 'EMBED' :
            case 'IFRAME' :
            case 'IMG' :
            case 'SOURCE' :
            case 'TRACK' :
            case 'VIDEO' :
                // @todo Should this test that the URL resolves?
                return $this->getAttribute('src');
            case 'A' :
            case 'AREA' :
            case 'LINK' :
                // @todo Should this test that the URL resolves?
                return $this->getAttribute('href');
            case 'OBJECT' :
                // @todo Should this test that the URL resolves?
                return $this->getAttribute('data');
            case 'DATA' :
                return $this->getAttribute('value');
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'TIME' :
                $datetime = $this->getAttribute('datetime');
                if (!empty($datetime)) {
                    return $datetime;
                }
            default :
                return $this->textContent;
        }
    }

    /**
     * Parse space-separated tokens into an array.
     *
     * @param string $string
     *            A space-separated list of tokens.
     *
     * @return array An array of tokens.
     */
    protected function tokenList($string) {
        return explode(' ', trim($string));
    }

    /**
     * Traverse the tree.
     *
     * In MicrodataJS, this is handled using a closure.
     * See comment for MicrodataPhp:getObject() for an explanation of closure use
     * in this library.
     * @param $node
     * @param $toTraverse
     * @param $props
     * @param $root
     */
    protected function traverse($node, &$toTraverse, &$props, $root) {
        foreach ($toTraverse as $i => $elem) {
            if ($elem->isSameNode($node)) {
                unset($toTraverse[$i]);
            }
        }
        if (!$root->isSameNode($node)) {
            $names = $node->itemProp();
            if (count($names)) {
                // @todo Add support for property name filtering.
                $props[] = $node;
            }
            if ($node->itemScope()) {
                return;
            }
        }
        if (isset($node)) {
            // An xpath expression is used to get children instead of childNodes
            // because childNodes contains DOMText children as well, which breaks on
            // the call to getAttributes() in itemProp().
            $children = $this->ownerDocument->xpath()->query($node->getNodePath() . '/*');
            foreach ($children as $child) {
                $this->traverse($child, $toTraverse, $props, $root);
            }
        }
    }
}

