<?php

namespace fivefilters\Readability\Nodes\DOM;

/**
 * Class DOMNodeList.
 *
 * This is a fake DOMNodeList class that allows adding items to the list. The original class is static and the nodes
 * are defined automagically when instantiating it. This fake version behaves exactly the same way but adds the function
 * add() that allows to insert new DOMNodes into the DOMNodeList.
 *
 * It cannot extend the original DOMNodeList class because the functionality behind the property ->length is hidden
 * from the user and cannot be extended, changed, or tweaked.
 */
class DOMNodeList implements \Countable, \IteratorAggregate
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var int
     */
    protected $length = 0;

    /**
     * To allow access to length in the same way that DOMNodeList allows.
     *
     * {@inheritdoc}
     */
    public function __get($name)
    {
        switch ($name) {
            case 'length':
                return $this->length;
            default:
                trigger_error(sprintf('Undefined property: %s::%s', static::class, $name));
        }
    }

    /**
     * Add node to the list.
     */
    public function add(DOMNode|DOMElement|DOMText|DOMComment|DOMProcessingInstruction|DOMCdataSection $node): DOMNodeList
    {
        $this->items[] = $node;
        $this->length++;

        return $this;
    }

    /**
     * Get node.
     */
    public function item(int $offset): DOMNode|DOMElement|DOMText|DOMComment|DOMProcessingInstruction|DOMCdataSection
    {
        return $this->items[$offset];
    }

    /**
     * Number of items.
     */
    public function count(): int
    {
        return $this->length;
    }

    /**
     * To make it compatible with iterator_to_array() function.
     *
     * {@inheritdoc}
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
