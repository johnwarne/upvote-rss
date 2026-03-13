<?php

namespace fivefilters\Readability\Nodes\DOM;

use fivefilters\Readability\Nodes\NodeTrait;

class DOMElement extends \DOMElement
{
    use NodeTrait;

    /**
     * Returns the child elements of this element.
     *
     * To get all child nodes, including non-element nodes like text and comment nodes, use childNodes.
     */
    public function children(): DOMNodeList
    {
        $newList = new DOMNodeList();
        foreach ($this->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $newList->add($node);
            }
        }
        return $newList;
    }

    /**
     * Returns the Element immediately prior to the specified one in its parent's children list, or null if the specified element is the first one in the list.
     *
     * @deprecated Use previousElementSibling instead - introduced in PHP 8.0.
     */
    public function previousElementSibling(): ?DOMElement
    {
        return $this->previousElementSibling;
    }
}
