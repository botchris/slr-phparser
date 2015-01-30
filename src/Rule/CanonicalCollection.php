<?php
namespace Phparser\Rule;

use Phparser\Rule\Rule;
use Phparser\Rule\RulesCollection;
use \Iterator;

/**
 * A collection of RulesCollection.
 *
 */
class CanonicalCollection implements Iterator
{

    /**
     * Used for iterator interface.
     *
     * @var int
     */
    protected $_position = 0;

    /**
     * Set of sets of RulesCollection.
     *
     * @var array
     */
    protected $_items = [];

    /**
     * Creates a new token.
     *
     * @param string $id Token's id
     * @param string $value Token's value
     */
    public function __construct(array $collection = [])
    {
        if (!empty($collection)) {
            $this->_items = $collection;
        }
    }

    public function push(RulesCollection $set)
    {
        if ($set->count() > 0 && !$this->exists($set)) {
            $this->_items[] = $set;
            return true;
        }

        return false;
    }

    public function pop()
    {
        return array_pop($this->_items);
    } 

    public function exists(RulesCollection $set)
    {
        foreach ($this->_items as $s) {
            if ("{$s}" == "{$set}") {
                return true;
            }
        }

        return false;
    }


    public function count()
    {
        return count($this->_items);
    }

    public function __toString() {
        $out = [];
        foreach ($this->_items as $set) {
            $out[] = "\t{$set}";
        }

        return "{\n" . implode(",\n", $out) . "\n}";
    }

    public function rewind()
    {
        $this->_position = 0;
    }

    public function current()
    {
        return $this->_items[$this->_position];
    }

    public function key()
    {
        return $this->_position;
    }

    public function next()
    {
        $this->_position++;
    }

    public function valid() {
        return isset($this->_items[$this->_position]);
    }
}
