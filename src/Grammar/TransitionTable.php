<?php
namespace Phparser\Grammar;

use Phparser\Rule\RulesCollection;
use \Iterator;

/**
 * Grammar which describes a language.
 *
 */
class TransitionTable implements Iterator
{

    /**
     * Used for iterator interface.
     *
     * @var int
     */
    protected $_position = 0;

    /**
     * List of table's columns
     * 
     * @var array
     */
    protected $_columns = [];

    /**
     * The table.
     *
     * @var array
     */
    protected $_table = null;

    /**
     * Constructor.
     * 
     * @param \Phparser\Rule\RulesCollection $rules Rules of the grammar, used to
     *  extract all valid symbols (variables and terminals) which are the columns
     *  of this table.
     */
    public function __construct(RulesCollection $rules)
    {
        $this->_columns = array_merge(
            $rules->terminals(),
            ['$'],
            $rules->variables()
        );
    }

    public function set($row, $column, $value)
    {
        if (!isset($this->_table[$row])) {
            $this->_table[$row] = $this->_newRow();
        }
        $this->_table[$row][$column] = $value;
    }

    public function get($row, $column)
    {
        if (isset($this->_table[$row][$column])) {
            return $this->_table[$row][$column];
        }
        return null;
    }

    public function toArray()
    {
        $table = $this->_table;
        $i = 0;
        foreach ($table as &$row) {
            foreach ($row as $column => $value) {
                $row[$column] = empty($row[$column]) ? '-' : $row[$column];
            }
            $row = ['#State' => $i] + $row;
            $i++;
        }
        return $table;
    }

    /**
     * Generates an empty row for the parser table.
     *
     * @return array Indexed by column names
     */
    protected function _newRow()
    {
        $row = [];
        foreach ($this->_columns as $columnName) {
            $row[$columnName] = '';
        }

        return $row;
    }

    public function rewind()
    {
        $this->_position = 0;
    }

    public function current()
    {
        return $this->_table[$this->_position];
    }

    public function key()
    {
        return $this->_position;
    }

    public function next()
    {
        ++$this->_position;
    }

    public function valid() {
        return isset($this->_table[$this->_position]);
    }
}
