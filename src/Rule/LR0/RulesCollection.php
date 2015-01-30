<?php
namespace Phparser\Rule\LR0;

use Phparser\Rule\CanonicalCollection;
use Phparser\Rule\CollectionInterface;
use Phparser\Rule\FirstFollowTrait;
use Phparser\Rule\Rule;
use \Iterator;

/**
 * A collection of rules.
 *
 */
class RulesCollection implements Iterator, CollectionInterface
{
    use FirstFollowTrait;

    /**
     * Used for iterator interface.
     *
     * @var int
     */
    protected $_position = 0;

    /**
     * The collection.
     *
     * @var array
     */
    protected $_rules = [];

    /**
     * Axiom of the grammar.
     *
     * @var string
     */
    protected $_startVariable = null;

    /**
     * List of production variables.
     *
     * @var array
     */
    protected $_variables = [];

    /**
     * List of production terminals.
     *
     * @var array
     */
    protected $_terminals = [];

     /**
     * Canonical collection of items.
     *
     * @var \Phparser\Rule\CanonicalCollection
     */
    protected $_canonicalCollection = null;

    /**
     * Creates a new collection of rules.
     *
     * You must provide a list of rules, the following formats are accepted:
     *
     * #### Rules as arrays
     * 
     * ``php
     * [
     *     ['S -> a A', function (&$info) { }],
     *     ['A -> b B', function (&$info) { }],
     *     ['B -> c'], // semantic routine is optional
     *     ....
     * ]
     * ```
     *
     * #### Rules as a single string
     *
     * In this case no semantic rule is given for each rule
     * 
     * ```php
     * [
     *     'S -> a A',
     *     'A -> b B',
     *     'C -> c',
     * ]
     * ```
     *
     * #### Rules as a rule objects
     *
     * ```php
     * [
     *     new Rule('S -> a A', function (&$info) { }),
     *     new Rule('A -> b B', function (&$info) { }),
     *     new Rule('B -> c'), // semantic routine is optional
     * ]
     * ```
     *
     * ---
     *
     * Of course you can combine formats:
     *
     * ```php
     * [
     *     ['S -> a A', function($info) { }]
     *     'A -> b B',
     *     new Rule('B -> c'),
     * ]
     * ```
     *
     * @param array $collection List of rules given as an array
     * @return void
     */
    public function __construct(array $collection = [])
    {
        if (!empty($collection)) {
            foreach ($collection as $i => $rule) {
                if (!is_object($rule)) {
                    // rule given as an array
                    if (is_array($rule)) {
                        if (count($rule) === 2) {
                            if (is_string($rule[0])) {
                                $rule = new Rule($rule[0], $rule[1]);
                            } else {
                                $rule = new Rule($rule[1], $rule[0]);
                            }
                        } else {
                            $rule = new Rule($rule[0]);
                        }
                    } elseif (is_string($rule)) {
                        $rule = new Rule($rule);
                    }
                }

                array_unshift($this->_variables, $rule->lhs());
                if ($i == 0) {
                    $this->_startVariable = $rule->lhs();
                }
                $this->_rules[] = $rule;
            }
            $this->_variables = array_unique($this->_variables);

            foreach ($this->_rules as $rule) {
                $rhs = $rule->rhs();
                $rhs = explode(' ', $rhs);
                $rhs = array_diff($rhs, $this->_variables);
                $this->_terminals = array_merge($this->_terminals, $rhs);
            }
            $this->_terminals = array_unique($this->_terminals);
        }
    }

    public function startVariable()
    {
        return $this->_startVariable;
    }

    public function variables()
    {
        return $this->_variables;
    }

    public function terminals()
    {
        return $this->_terminals;
    }

    public function push(Rule $rule)
    {
        if (!$this->exists($rule)) {
            $this->_rules[] = $rule;
            return true;
        }

        return false;
    }

    /**
     * Inserts a new rule at the beginning of this collection.
     *
     * @return int The new number of rules of this collection
     */
    public function unshift(Rule $rule)
    {
        return array_unshift($this->_rules, $rule);
    }

    public function pop()
    {
        return array_pop($this->_rules);
    }

    /**
     * Given a rule gets its position within this collection.
     *
     * @param \Phparser\Rule\Rule $rule The rule to find
     * @return int Rule's index
     */
    public function getRuleIndex(Rule $rule)
    {
        $rule = preg_replace('/, (.+)$/', '', (string)$rule);
        $rule = str_replace('.', '', $rule);
        foreach ($this->_rules as $index => $r) {
            if ($rule == str_replace('.', '', "{$r}")) {
                return $index;
            }
        }

        throw new \Exception(sprintf('Rule "%s" was not found in the collection.', "{$rule}"));
    }

    /**
     * Given an index gets the rule at that position within this collection.
     * 
     * @param int $index Rule's index
     * @return \Phparser\Rule\Rule The rule
     */
    public function getRuleByIndex($index)
    {
        if (isset($this->_rules[$index])) {
            return $this->_rules[$index];
        }

        throw new \Exception(sprintf('Rule #%d was not found in the collection.', $index));
    }

    /**
     * Calculates the canonical collection, used for create the parsing table.
     *
     * @return array
     */
    public function canonicalCollection()
    {
        $oldAxiom = $this->_startVariable;
        $newAxiom = "{$oldAxiom}'";
        $this->unshift(new Rule("{$newAxiom} -> {$oldAxiom}"));
        $this->_startVariable = $oldAxiom;

        $collection = new CanonicalCollection();
        $initialSet = $this->_closure(
            new RulesCollection([
                new Rule("{$newAxiom} -> .{$oldAxiom}")
            ])
        );
        $collection->push($initialSet);

        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;
            foreach ($collection as $set) {
                $symbols = array_merge($this->variables(), $this->terminals());
                foreach ($symbols as $symbol) {
                    $goto = $this->gotoSet($set, $symbol);
                    if ($goto) {
                        $closure = $this->_closure($goto);
                        if ($collection->push($closure)) {
                            $hasChanged = true;
                        }
                    }
                }
            }
        }

        $this->_canonicalCollection = $collection;
        return $collection;
    }    

    /**
     * Calculates the "goto" set for a given $item and $symbol.
     *
     * @param array $set A set (of rules) within the canonical collection
     * @param string $symbol A valid variable or terminal.
     * @return array
     */
    public function gotoSet($set, $symbol)
    {
        $forClosure = new RulesCollection();
        foreach ($set as $rule) {
            $rhs = $rule->rhs();
            if (preg_match('/\.\b(' . $symbol . ')\b/', $rhs, $matches)) {
                $lhs = $rule->lhs();
                $rhs = str_replace(".{$matches[1]}", "{$matches[1]}.", $rhs);
                $rhs = str_replace('. ', ' .', $rhs);
                $forClosure->push(new Rule("{$lhs} -> {$rhs}"));
            }
        }
        $closure = $this->_closure($forClosure);
        return $closure;
    }

    /**
     * Gets the "goto" set's index within the canonical collection.
     *
     * @param array $set A set (of rules) within the canonical collection
     * @param string $symbol A valid variable or terminal
     * @return int
     */
    public function gotoIndex($set, $symbol)
    {
        $goto = $this->gotoSet(clone $set, $symbol);
        foreach (clone $this->_canonicalCollection as $k => $subset) {
            if ("{$subset}" == "{$goto}") {
                return $k;
            }
        }
    }

    /**
     * Calculates the closure set for the given set of rules
     *
     * @param \Phparser\Rule\RulesCollection $set Set of rules
     * @return array
     */
    protected function _closure(CollectionInterface $set)
    {
        $regex = implode('|', $this->variables());
        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;

            foreach ($set as $rule) {
                $result = preg_match('/\.\b(' . $regex . ')\b/', $rule->rhs(), $matches);

                if ($result) {
                    $variable = str_replace('.', '', $matches[1]);
                    foreach ($this->_rules as $r) {
                        $lhs = $r->lhs();
                        if ($lhs == $variable) {
                            $newRHS = '.' . $r->rhs();
                            $newPro = new Rule("{$variable} -> {$newRHS}");
                            if ($set->push($newPro)) {
                                $hasChanged = true;
                            }
                        }
                    }
                }
            }
        }

        return $set;
    }

    public function count()
    {
        return count($this->_rules);
    }

    public function exists(Rule $rule)
    {
        foreach ($this->_rules as $r) {
            if ("{$rule}" == "{$r}") {
                return true;
            }
        }

        return false;
    }

    public function __toString()
    {
        $out = [];
        foreach ($this->_rules as $rule) {
            $out[] = "{$rule}";
        }

        return '{' . implode('; ', $out) . '}';
    }

    public function rewind()
    {
        $this->_position = 0;
    }

    public function current()
    {
        return $this->_rules[$this->_position];
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
        return isset($this->_rules[$this->_position]);
    }
}
