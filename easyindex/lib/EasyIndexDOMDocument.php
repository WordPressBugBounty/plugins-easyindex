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
 * Class EasyIndexDOMDocument
 */
class EasyIndexDOMDocument extends DOMDocument {
    private $isValidHTML = false;

    /**
     * EasyIndexDOMDocument constructor.
     * @param string $content
     * @param bool $load
     * @param string $encoding
     */
    public function __construct($content, $load = true, $encoding = "UTF-8") {
        parent::__construct("1.0", $encoding);

        libxml_use_internal_errors(true);

        if ($load && !@$this->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>' . $content)) {
            return;
        }

        $this->isValidHTML = true;
    }

    /**
     * Returns TRUE if we successfully load the content
     *
     * @return bool
     */
    function isValid() {
        return $this->isValidHTML;
    }

    
    
    /**
     * @param DOMElement $node
     * @param string $className
     * @return bool
     */
    public function hasClass($node, $className) {
        $item = $node->attributes->getNamedItem('class');
        if ($item) {
            $classes = explode(" ", $item->nodeValue);
            for ($j = 0; $j < count($classes); $j++) {
                if ($classes[$j] == $className) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Removes class $class from $element
     *
     * @param DOMElement $element
     * @param string $class
     */
    public function removeClass($element, $class) {
        $newClass = trim(preg_replace("/ *(?:$class)/i", '', $element->getAttribute('class')));
        if ($newClass != '') {
            $element->setAttribute('class', $newClass);
        } else {
            $element->removeAttribute('class');
        }
    }

    /**
     * Removes elements that have class $className
     *
     * @param string $className
     * @param string $tag
     * @param DOMNode $node
     */
    public function removeElementsByClassName($className, $tag = '*', $node = null) {

        if ($node == null) {
            $node = $this;
        }
        $elements = $node->getElementsByClassName($className, $tag);
        /** @var DOMNode $element */
        foreach ($elements as $element) {
            $element->parentNode->removeChild($element);
        }

    }

    /**
     * @param DOMElement $node
     * @return bool|string
     */
    public function innerHTML($node) {
        if (!isset($node->firstChild)) {
            return false;
        }
        $value = '';
        for ($child = $node->firstChild; $child; $child = $child->nextSibling) {
            $value .= $this->saveXML($child);
        }
        return $value;
    }

    /**
     * Get all elements that have a tag of $tag and class of $className
     *
     * @param string $className sThe class name to search for
     * @param string $tag Tag of the items to search
     * @param DOMElement $node
     *
     * @return array
     */

    public function getElementsByClassName($className, $tag = "*", $node = null) {
        $classNames = explode('|', str_replace(' ', '', $className));
        $nodes = array();
        $domNodeList = ($node == null) ? $this->getElementsByTagName($tag) : $node->getElementsByTagName($tag);

        for ($i = 0; $i < $domNodeList->length; $i++) {
            /** @var DOMElement $element */
            $element = $domNodeList->item($i);
            if ($element->hasAttributes() && $element->hasAttribute('class')) {
                for ($j = 0; $j < count($classNames); $j++) {
                    if ($this->hasClass($element, $classNames[$j])) {
                        $nodes[] = $domNodeList->item($i);
                        break;
                    }
                }
            }
        }

        return $nodes;
    }

    /**
     * Convenience method to return a single element by class name when we know there's only going to be one
     * If there is actually more than 1, return the first
     *
     * @param string $className
     * @param string $tag
     * @param DOMNode $node
     *
     * @return null
     */
    public function getElementByClassName($className, $tag = "*", $node = null) {
        $nodes = $this->getElementsByClassName($className, $tag, $node);
        return count($nodes) > 0 ? $nodes[0] : null;
    }

    /**
     * @param string $tag
     * @param string $propertyName
     * @param string $propertyValue
     * @param DOMDocument $node
     *
     * @return array
     */
    public function getElementsByProperty($tag, $propertyName, $propertyValue, $node = null) {
        $nodes = $node == null ? $this->getElementsByTagName($tag) : $node->getElementsByTagName($tag);
        $result = array();
        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            if ($node->hasAttribute($propertyName)) {
                if ($node->getAttribute($propertyName) == $propertyValue) {
                    $result[] = $node;
                }
            }
        }
        return $result;
    }

    /**
     * @param string $tag
     * @param string $propertyName
     * @param string $propertyValue
     * @param DOMNode $node
     * @return array
     */
    public function getElementValuesByProperty($tag, $propertyName, $propertyValue, $node = null) {
        $result = array();
        $nodes = $this->getElementsByProperty($tag, $propertyName, $propertyValue, $node);
        foreach ($nodes as $node) {
            $result[] = $this->innerHTML($node);
        }
        return $result;
    }

    /**
     * @param string $tag
     * @param string $propertyName
     * @param string $propertyValue
     * @param DOMNode $node
     * @return mixed|null
     */
    public function getElementValueByProperty($tag, $propertyName, $propertyValue, $node = null) {
        $result = $this->getElementValuesByProperty($tag, $propertyName, $propertyValue, $node);
        return count($result) > 0 ? $result[0] : null;
    }

    /**
     * @param string $className
     * @param string $tag
     * @param DOMNode $node
     * @return array
     */
    public function getElementValuesByClassName($className, $tag = "*", $node = null) {
        $nodes = $this->getElementsByClassName($className, $tag, $node);
        $result = array();
        foreach ($nodes as $node) {
            $result[] = $this->innerHTML($node);
        }
        return $result;
    }

    /**
     * @param string $className
     * @param string $tag
     * @param DOMNode $node
     * @return bool|string
     */
    public function getElementValueByClassName($className, $tag = "*", $node = null) {
        $node = $this->getElementByClassName($className, $tag, $node);
        return $this->innerHTML($node);
    }

    /**
     * @param string $className
     * @param string $attributeName
     * @param string $tag
     * @param DOMNode $node
     * @return bool|mixed
     */
    public function getElementAttributeByClassName($className, $attributeName, $tag = "*", $node = null) {
        $nodes = $this->getElementsByClassName($className, $tag, $node);
        $result = array();
        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            if (($attributeValue = $node->getAttribute($attributeName)) != '') {
                $result[] = $attributeValue;
            }
        }
        return count($result) > 0 ? $result[0] : false;
    }

    /**
     * @param DOMNode $node
     * @param string $tag
     *
     * @return array
     */
    public function getChildrenByTagName($node, $tag = "*") {
        $nodes = array();
        for ($child = $node->firstChild; $child; $child = $child->nextSibling) {
            if ($child instanceof DOMElement) {
                if ($tag == "*" || $tag == $child->tagName) {
                    $nodes[] = $child;
                }
            }
            $childNodes = $this->getChildrenByTagName($child, $tag);
            $nodes = array_merge($nodes, $childNodes);
        }
        return $nodes;
    }

    

    /**
     * @param string $tag
     * @param string $attributeName
     * @param string $selector
     * @param string $value
     * @param DOMNode $baseNode
     * @return array
     */
    public function getElementAttributesByTagName($tag, $attributeName, $selector = "", $value = "", $baseNode = null) {
        $nodes = $baseNode == null ? $this->getElementsByTagName($tag) : $this->getChildrenByTagName($baseNode, $tag);
        $result = array();
        /** @var DOMElement $node */
        foreach ($nodes as $node) {
//            if ($baseNode != null) {
//            }
            if ($selector != "") {
                if ($node->getAttribute($selector) != $value) {
                    continue;
                }
            }
            if (($attributeValue = $node->getAttribute($attributeName)) != '') {
                $result[] = $attributeValue;
            }
        }
        return $result;
    }

    /**
     * @param string $tag
     * @param string $attributeName
     * @param string $selector
     * @param string $value
     * @param DOMNode $baseNode
     * @return bool|mixed
     */
    public function getElementAttributeByTagName($tag, $attributeName, $selector = "", $value = "", $baseNode = null) {
        $result = $this->getElementAttributesByTagName($tag, $attributeName, $selector, $value, $baseNode);
        return count($result) > 0 ? $result[0] : false;
    }

    /**
     * Sets the text value for elements of class $className
     * The $currentValue both explicitly identifies an ambigous element, and the actual part of the text to be replaced by $value
     *
     * @param string $className The class name of the element(s) to adjust
     * @param string $value The value to set
     * @param string $currentValue Disambiguator and also the part of the text that is to be replaced by $value
     */
    public function setValueByClassName($className, $value, $currentValue = "") {
        $nodes = $this->getElementsByClassName($className);
        for ($i = 0; $i < count($nodes); $i++) {
            if ($currentValue == "") {
                $nodes[$i]->nodeValue = $value;
            } else {
                if (preg_match("/^$currentValue(.*)$/", $nodes[$i]->firstChild->nodeValue, $regs)) {
                    $nodes[$i]->firstChild->nodeValue = $value . $regs[1];
                }
            }
        }
    }

    /**
     * Gets the styles of $element as an associative array of style property/value pairs
     *
     * @param DOMElement $element The element for which to get the styles
     *
     * @return array An associative array of style property/values
     */
    public function getStyles(DOMElement $element) {
        $result = array();
        $styleString = $element->getAttribute("style");
        if ($styleString == "") {
            return $result;
        }
        $styles = explode(";", $styleString);
        for ($i = 0; $i < count($styles); $i++) {
            if ($styles[$i] != "") {
                $styleEntry = explode(":", $styles[$i]);
                $result[trim($styleEntry[0])] = trim($styleEntry[1]);
            }
        }
        return $result;
    }

    /**
     * Set style property $style to $value on $element
     *
     * @param DOMElement $element The elemnt to set the style for
     * @param string $style The style property name
     * @param string $value The value to set
     */
    public function setStyle(DOMElement $element, $style, $value) {
        $styles = $this->getStyles($element);
        $styles[$style] = $value;
        $styleString = "";
        foreach ($styles as $property => $value) {
            $styleString .= $property . ":" . $value . ";";
        }
        $element->setAttribute("style", rtrim($styleString, ";"));
    }

    /**
     * Remove $style from $element
     *
     * @param DOMElement $element The elemnt to remove the style from
     * @param string $style The style property to remove
     */
    public function removeStyle(DOMElement $element, $style) {
        $styles = $this->getStyles($element);
        if (!isset($styles[$style])) {
            return;
        }
        unset($styles[$style]);
        $styleString = "";
        foreach ($styles as $property => $value) {
            $styleString .= $property . ":" . $value . ";";
        }
        if ($styleString == "") {
            $element->removeAttribute("style");
        } else {
            $element->setAttribute("style", rtrim($styleString, ";"));
        }
    }

    /**
     * Adds the class $class to the element $element
     *
     * @param DOMElement $element The element to use
     * @param string $class The class to add
     */
    public function addClass(DOMElement $element, $class) {
        $classes = $element->getAttribute("class");
        $classes .= " $class";
        $element->setAttribute("class", trim($classes));
    }


    /**
     * Get the processed html for the post.
     * Needs to remove the extra stuff saveHTML adds, and wrap it in the original surrounding code
     *
     * @param bool $bodyOnly
     *
     * @return bool|string
     */
    public function getHTML($bodyOnly = false) {
        if ($bodyOnly) {
            $body = $this->getElementsByTagName('body');
            return $this->innerHTML($body->item(0));
        }

        return $this->saveHTML();
    }

    /**
     * Retrieves a list of microdata items.
     *
     * @param string $schema
     *
     * @return DOMNodeList A DOMNodeList containing all top level microdata items.
     */
    public function getItems($schema = "") {
        if (empty($schema)) {
            return $this->xpath()->query('//*[@itemscope and not(@itemprop)]');
        } else {
            return $this->xpath()->query("//*[@itemscope and @itemtype='$schema' and not(@itemprop)]");
        }
    }

    /**
     * Creates a DOMXPath to query this document.
     *
     * @return DOMXPath object.
     */
    public function xpath() {
        return new DOMXPath($this);
    }

    /**
     * @param DOMNode $node
     * @param int $offset
     */
    private function dumpNode($node, $offset = 0) {
        $class = get_class($node);
        $id = '';
        if ($class == 'DOMElement') {
            foreach ($node->attributes as $attribute) {
                $id .= " $attribute->name=$attribute->value";
            }
        }

        $nodeName = isset($node->nodeName) ? $node->nodeName : 'noname';
        echo str_pad("", $offset) . "&lt;$nodeName$id&gt;\n";

        if ($class == 'DOMText') {
            $val = trim($node->nodeValue);
            if ($val != "\n") {
                echo str_pad("", $offset) . "'$val'\n";
            }
        }
        for ($n = $node->firstChild; $n; $n = $n->nextSibling) {
            $this->dumpNode($n, $offset + 2);
        }
    }

    /**
     * @param DOMNode $node
     */
    public function dump($node = null) {
        echo "<pre>\n";
        $this->dumpNode($node ? $node : $this);
        echo "</pre>\n";
    }
}

