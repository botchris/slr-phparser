<?php
namespace Phparser;

/**
 * Represents a single token for the grammar.
 *
 */
class Token
{
    /**
     * Token's id. e.g. `TK_ADD`, `TK_SUB`, etc.
     *
     * @var string
     */
    protected $_id;

    /**
     * Token's value. e.g. "6" for token `TK_NUMBER`
     *
     * @var string
     */
    protected $_value;

    /**
     * Creates a new token.
     *
     * @param string $id Token's id
     * @param string $value Token's value
     */
    public function __construct($id, $value)
    {
        $this->_id = $id;
        $this->_value = $value;
    }

    /**
     * Gets token's id.
     *
     * @return string
     */
    public function id()
    {
        return $this->_id;
    }

    /**
     * Gets/sets token's value.
     *
     * @param mixed $val The value to set
     * @return string
     */
    public function value($val = null)
    {
        if ($val !== null) {
            $this->_value = $val;
        }
        return $this->_value;
    }

    /**
     * Magic method, returns token's id when echoed.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->_id;
    }
}
