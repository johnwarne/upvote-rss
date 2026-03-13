<?php

namespace fivefilters\Readability\Nodes;

use fivefilters\Readability\Nodes\DOM\DOMDocument;
use fivefilters\Readability\Nodes\DOM\DOMElement;
use fivefilters\Readability\Nodes\DOM\DOMNode;
use fivefilters\Readability\Nodes\DOM\DOMText;

/**
 * @property ?DOMNode $firstChild
 * @property ?DOMNode $lastChild
 * @property ?DOMNode $parentNode
 * @property ?DOMNode $nextSibling
 * @property ?DOMNode $previousSibling
 */
trait NodeTrait
{
    /**
     * Content score of the node. Used to determine the value of the content.
     */
    public float $contentScore = 0.0;

    /**
     * Flag for initialized status.
     */
    private bool $initialized = false;

    /**
     * Flag for data tables.
     */
    private bool $readabilityDataTable = false;

    private static array $DIV_TO_P_ELEMENTS = [
        'blockquote',
        'dl',
        'div',
        'img',
        'ol',
        'p',
        'pre',
        'table',
        'ul'
    ];

    /**
     * The commented out elements qualify as phrasing content but tend to be
     * removed by readability when put into paragraphs, so we ignore them here.
     */
    private static array $PHRASING_ELEMS = [
        // 'CANVAS', 'IFRAME', 'SVG', 'VIDEO',
        'abbr', 'audio', 'b', 'bdo', 'br', 'button', 'cite', 'code', 'data',
        'datalist', 'dfn', 'em', 'embed', 'i', 'img', 'input', 'kbd', 'label',
        'mark', 'math', 'meter', 'noscript', 'object', 'output', 'progress', 'q',
        'ruby', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub',
        'sup', 'textarea', 'time', 'var', 'wbr'
    ];

    /**
     * Is initialized getter.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Check if this is a data table.
     */
    public function isReadabilityDataTable(): bool
    {
        /*
         * This is a workaround that I'd like to remove in the future.
         * Seems that although we are extending the base DOMElement and adding custom properties (like this one,
         * 'readabilityDataTable'), these properties get lost when you search for elements with getElementsByTagName.
         * This means that even if we mark the tables in a previous step, when we want to retrieve that information,
         * all the custom properties are in their default values. Somehow we need to find a way to make these properties
         * permanent across the whole DOM.
         *
         * @see https://stackoverflow.com/questions/35654709/php-registernodeclass-and-reusing-variable-names
         */
        return $this->hasAttribute('readabilityDataTable')
            && $this->getAttribute('readabilityDataTable') === '1';
//        return $this->readabilityDataTable;
    }

    /**
     * Set data table flag.
     */
    public function setReadabilityDataTable(bool $param): void
    {
        $this->setAttribute('readabilityDataTable', $param ? '1' : '0');
//        $this->readabilityDataTable = $param;
    }

    /**
     * Initializer. Calculates the current score of the node and returns a full Readability object.
     *
     * @ TODO: I don't like the weightClasses param. How can we get the config here?
     *
     * @param bool $weightClasses Weight classes?
     */
    public function initializeNode(bool $weightClasses): static
    {
        if (!$this->isInitialized()) {
            $contentScore = 0;

            switch ($this->nodeName) {
                case 'div':
                    $contentScore += 5;
                    break;

                case 'pre':
                case 'td':
                case 'blockquote':
                    $contentScore += 3;
                    break;

                case 'address':
                case 'ol':
                case 'ul':
                case 'dl':
                case 'dd':
                case 'dt':
                case 'li':
                case 'form':
                    $contentScore -= 3;
                    break;

                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                case 'th':
                    $contentScore -= 5;
                    break;
            }

            $this->contentScore = $contentScore + ($weightClasses ? $this->getClassWeight() : 0);

            $this->initialized = true;
        }

        return $this;
    }

    /**
     * Override for native getAttribute method. Some nodes have the getAttribute method, some don't, so we need
     * to check first the existence of the attributes property.
     */
    public function getAttribute(string $attributeName): string
    {
        if (!is_null($this->attributes)) {
            return parent::getAttribute($attributeName);
        }

        return '';
    }

    /**
     * Override for native hasAttribute.
     *
     * @see getAttribute
     */
    public function hasAttribute(string $attributeName): bool
    {
        if (!is_null($this->attributes)) {
            return parent::hasAttribute($attributeName);
        }

        return false;
    }

    /**
     * Get the ancestors of the current node.
     *
     * @param int|bool $maxLevel Max amount of ancestors to get. False for all of them
     */
    public function getNodeAncestors(int|bool $maxLevel = 3): array
    {
        $ancestors = [];
        $level = 0;

        $node = $this->parentNode;

        while ($node && !($node instanceof DOMDocument)) {
            $ancestors[] = $node;
            $level++;
            if ($level === $maxLevel) {
                break;
            }
            $node = $node->parentNode;
        }

        return $ancestors;
    }

    /**
     * Returns all links from the current element.
     */
    public function getAllLinks(): array
    {
        return iterator_to_array($this->getElementsByTagName('a'));
    }

    /**
     * Get the density of links as a percentage of the content
     * This is the amount of text that is inside a link divided by the total text in the node.
     */
    public function getLinkDensity(): float
    {
        $textLength = mb_strlen($this->getTextContent(true));
        if ($textLength === 0) {
            return 0;
        }

        $linkLength = 0;

        $links = $this->getAllLinks();

        if ($links) {
            /** @var DOMElement $link */
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $coefficient = ($href && preg_match(NodeUtility::$regexps['hashUrl'], $href)) ? 0.3 : 1;
                $linkLength += mb_strlen($link->getTextContent(true)) * $coefficient;
            }
        }

        return $linkLength / $textLength;
    }

    /**
     * Calculates the weight of the class/id of the current element.
     */
    public function getClassWeight(): int
    {
        $weight = 0;

        // Look for a special classname
        $class = $this->getAttribute('class');
        if (trim($class)) {
            if (preg_match(NodeUtility::$regexps['negative'], $class)) {
                $weight -= 25;
            }

            if (preg_match(NodeUtility::$regexps['positive'], $class)) {
                $weight += 25;
            }
        }

        // Look for a special ID
        $id = $this->getAttribute('id');
        if (trim($id) !== '') {
            if (preg_match(NodeUtility::$regexps['negative'], $id)) {
                $weight -= 25;
            }

            if (preg_match(NodeUtility::$regexps['positive'], $id)) {
                $weight += 25;
            }
        }

        return $weight;
    }

    /**
     * Returns the full text of the node.
     *
     * @param bool $normalize Normalize white space?
     */
    public function getTextContent(bool $normalize = true): string
    {
        $nodeValue = trim($this->textContent);
        if ($normalize) {
            $nodeValue = preg_replace(NodeUtility::$regexps['normalize'], ' ', $nodeValue);
        }

        return $nodeValue;
    }

    /**
     * Return an array indicating how many rows and columns this table has.
     */
    public function getRowAndColumnCount(): array
    {
        $rows = $columns = 0;
        $trs = $this->getElementsByTagName('tr');
        foreach ($trs as $tr) {
            /** @var \DOMElement $tr */
            $rowspan = $tr->getAttribute('rowspan');
            $rows += ($rowspan || 1);

            // Now look for column-related info
            $columnsInThisRow = 0;
            $cells = $tr->getElementsByTagName('td');
            foreach ($cells as $cell) {
                /** @var \DOMElement $cell */
                $colspan = $cell->getAttribute('colspan');
                $columnsInThisRow += ($colspan || 1);
            }
            $columns = max($columns, $columnsInThisRow);
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    /**
     * Creates a new node based on the text content of the original node.
     */
    public function createNode(DOMNode $originalNode, string $tagName): DOMElement
    {
        $text = $originalNode->getTextContent(false);
        $newNode = $originalNode->ownerDocument->createElement($tagName, $text);

        return $newNode;
    }

    /**
     * Check if a given node has one of its ancestor tag name matching the
     * provided one.
     */
    public function hasAncestorTag(string $tagName, int $maxDepth = 3, ?callable $filterFn = null): bool
    {
        $depth = 0;
        $node = $this;

        while ($node->parentNode) {
            if ($maxDepth > 0 && $depth > $maxDepth) {
                return false;
            }

            if ($node->parentNode->nodeName === $tagName && (!$filterFn || $filterFn($node->parentNode))) {
                return true;
            }

            $node = $node->parentNode;
            $depth++;
        }

        return false;
    }

    /**
     * Check if this node has only whitespace and a single element with given tag
     * or if it contains no element with given tag or more than 1 element.
     */
    public function hasSingleTagInsideElement(string $tag): bool
    {
        // There should be exactly 1 element child with given tag
        if (count($children = NodeUtility::filterTextNodes($this->childNodes)) !== 1 || $children->item(0)->nodeName !== $tag) {
            return false;
        }

        // And there should be no text nodes with real content
        return array_reduce(iterator_to_array($children), function ($carry, $child) {
            if (!$carry === false) {
                return false;
            }

            /* @var DOMNode $child */
            return !($child->nodeType === XML_TEXT_NODE && preg_match(NodeUtility::$regexps['hasContent'], $child->textContent));
        });
    }

    /**
     * Check if the current element has a single child block element.
     * Block elements are the ones defined in the DIV_TO_P_ELEMENTS array.
     */
    public function hasSingleChildBlockElement(): bool
    {
        $result = false;
        if ($this->hasChildNodes()) {
            foreach ($this->childNodes as $child) {
                if (in_array($child->nodeName, self::$DIV_TO_P_ELEMENTS)) {
                    $result = true;
                } else {
                    // If any of the hasSingleChildBlockElement calls return true, return true then.
                    /** @var $child DOMElement */
                    $result = ($result || $child->hasSingleChildBlockElement());
                }
            }
        }

        return $result;
    }

    /**
     * Determines if a node has no content or it is just a bunch of dividing lines and/or whitespace.
     */
    public function isElementWithoutContent(): bool
    {
        return $this instanceof DOMElement &&
            mb_strlen(preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $this->textContent)) === 0 &&
            ($this->childNodes->length === 0 ||
                $this->childNodes->length === $this->getElementsByTagName('br')->length + $this->getElementsByTagName('hr')->length
                /*
                 * Special PHP DOMDocument case: We also need to count how many DOMText we have inside the node.
                 * If there's an empty tag with an space inside and a BR (for example "<p> <br/></p>) counting only BRs and
                 * HRs will will say that the example has 2 nodes, instead of one. This happens because in DOMDocument,
                 * DOMTexts are also nodes (which doesn't happen in JS). So we need to also count how many DOMText we
                 * are dealing with (And at this point we know they are empty or are just whitespace, because of the
                 * mb_strlen in this chain of checks).
                 */
                + count(array_filter(iterator_to_array($this->childNodes), function ($child) {
                    return $child instanceof DOMText;
                }))

            );
    }

    /**
     * Determine if a node qualifies as phrasing content.
     * https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Phrasing_content
     */
    public function isPhrasingContent(): bool
    {
        return $this->nodeType === XML_TEXT_NODE || in_array($this->nodeName, self::$PHRASING_ELEMS) !== false ||
            (!is_null($this->childNodes) &&
                ($this->nodeName === 'a' || $this->nodeName === 'del' || $this->nodeName === 'ins') &&
                array_reduce(iterator_to_array($this->childNodes), function ($carry, $node) {
                    return $node->isPhrasingContent() && $carry;
                }, true)
            );
    }

    /**
     * In the original JS project they check if the node has the style display=none, which unfortunately
     * in our case we have no way of knowing that. So we just check for the attribute hidden or "display: none".
     */
    public function isProbablyVisible(): bool
    {
        return !preg_match('/display:( )?none/i', $this->getAttribute('style')) &&
                !$this->hasAttribute('hidden') &&
                //check for "fallback-image" so that wikimedia math images are displayed
                (!$this->hasAttribute('aria-hidden') || $this->getAttribute('aria-hidden') !== 'true' || str_contains($this->getAttribute('class'), 'fallback-image'));
    }

    /**
     * Check if node is whitespace.
     */
    public function isWhitespace(): bool
    {
        return ($this->nodeType === XML_TEXT_NODE && $this->isWhitespaceInElementContent()) ||
            ($this->nodeType === XML_ELEMENT_NODE && $this->nodeName === 'br');
    }

    /**
     * This is a hack that overcomes the issue of node shifting when scanning and removing nodes.
     *
     * In the JS version of getElementsByTagName, if you remove a node it will not appear during the
     * foreach. This does not happen in PHP DOMDocument, because if you remove a node, it will still appear but as an
     * orphan node and will give an exception if you try to do anything with it.
     *
     * Shifting also occurs when converting parent nodes (like a P to a DIV), which in that case the found nodes are
     * removed from the foreach "pool" but the internal index of the foreach is not aware and skips over nodes that
     * never looped over. (index is at position 5, 2 nodes are removed, next one should be node 3, but the foreach tries
     * to access node 6)
     *
     * This function solves this by searching for the nodes on every loop and keeping track of the count differences.
     * Because on every loop we call getElementsByTagName again, this could cause a performance impact and should be
     * used only when the results of the search are going to be used to remove the nodes.
     *
     * @param string $tag
     */
    public function shiftingAwareGetElementsByTagName(string $tag): \Generator
    {
        $nodes = $this->getElementsByTagName($tag);
        $count = $nodes->length;

        for ($i = 0; $i < $count; $i = max(++$i, 0)) {
            yield $nodes->item($i);

            // Search for all the nodes again
            $nodes = $this->getElementsByTagName($tag);

            // Subtract the amount of nodes removed from the current index
            $i -= $count - $nodes->length;

            // Subtract the amount of nodes removed from the current count
            $count -= ($count - $nodes->length);
        }
    }

    /**
     * Git first element child or null
     */
    public function getFirstElementChild(): ?DOMElement
    {
        if ($this->nodeType === XML_ELEMENT_NODE || $this->nodeType === XML_DOCUMENT_NODE) {
            return $this->firstElementChild;
        }

        return null;
    }
}
