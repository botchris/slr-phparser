<?php
namespace Phparser\Rule;

/**
 * Represents s production rule such as:
 *
 *     A -> a B c
 *
 * ### Usage
 *
 * Symbols must be separated using space char, for instance:
 *
 *     A -> ab C
 *
 * This will generate `A`, `ab`, `C` symbols.
 *
 * Left and right side of the rule must be separated using the `->` symbol.
 *
 * Symbols must composed of letters and underscore character `_`, for instance:
 *
 *     exp_a -> exp_a TK_PLUS exp_a
 *
 * Symbols are case-sensitive, `A` != `a`
 */
class Rule
{

    /**
     * String representation.
     *
     * @var string
     */
    protected $_asTring;

    /**
     * Array representation of this rule.
     *
     * @var array
     */
    protected $_rule = [];

    /**
     * Left-hand side of the rule.
     *
     * @var string
     */
    protected $_lhs = '';

    /**
     * Right-hand side of the rule.
     *
     * @var string
     */
    protected $_rhs = '';

    /**
     * Semantic routine.
     *
     * @var callable
     */
    protected $_routine = null;

    /**
     * Lookahead terminal, used by LR(1) parsers.
     *
     * @var string
     */
    protected $_lookahead = '';

    /**
     * Creates a new token.
     *
     * @param string $string Rule given as string. e.g. `A -> b A`
     * @param callable $routine Semantic routine function
     */
    public function __construct($string, $routine = null)
    {
        list($lhs, $rhs) = explode('->', $string);
        if (!is_callable($routine)) {
            $routine = function () {};
        }

        if ($lhs && $rhs) {
            if (preg_match("/[a-z_\s']/i", $lhs) && preg_match('/[a-z_\s\.]/i', $rhs)) {
                $lhs = trim($lhs);
                $rhs = trim($rhs);
                $this->_rule = [$lhs => $rhs];
                $this->_lhs = $lhs;
                $this->_rhs = $rhs;
                $this->_asString = "{$lhs} -> {$rhs}";
                $this->_routine = $routine;
                return;
            }
        }

        throw new \Exception(sprintf('The rule "%s" is invalid.', $string));
    }

    /**
     * Executes the semantic routine of this rule.
     *
     * @param mixed &$info Information for each symbol on the RHS
     * @return mixed
     */
    public function routine(&$info = null)
    {
        $routine = $this->_routine;
        if (is_callable($routine)) {
            return $routine($info);
        }
    }

    /**
     * Gets/sets lookahead symbol.
     *
     * @param string $la A valid terminal symbol
     * @return string
     */
    public function lookahead($la = null)
    {
        if ($la !== null) {
            $this->_lookahead = $la;
        }

        return $this->_lookahead;
    }

    /**
     * Gets left hand side.
     * 
     * @return string
     */
    public function lhs()
    {
        return $this->_lhs;
    }

    /**
     * Gets right hand side.
     * 
     * @return string
     */
    public function rhs()
    {
        return $this->_rhs;
    }

    /**
     * Magic method, returns token's id when echoed.
     *
     * @return string
     */
    public function __toString()
    {
        $suffix = $this->_lookahead ? ", {$this->_lookahead}" : '';
        return $this->_asString . $suffix;
    }
}
