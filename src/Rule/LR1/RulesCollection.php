<?php
namespace Phparser\Rule\LR1;

use Phparser\Rule\CanonicalCollection;
use Phparser\Rule\CollectionInterface;
use Phparser\Rule\FirstFollowTrait;
use Phparser\Rule\LR0\RulesCollection as LR0;
use Phparser\Rule\Rule;
use \Iterator;

/**
 * A collection of rules.
 *
 */
class RulesCollection extends LR0 implements Iterator, CollectionInterface
{
    use FirstFollowTrait;

    public function __construct(array $collection = [])
    {
        parent::__construct($collection);
        $this->_terminals[] = '$';
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
        $initialRule = new Rule("{$newAxiom} -> .{$oldAxiom}");
        $initialRule->lookahead('$');
        $initialSet = $this->_closure(
            new RulesCollection([$initialRule])
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
                $newRule = new Rule("{$lhs} -> {$rhs}");
                $newRule->lookahead($rule->lookahead());
                $forClosure->push($newRule);
            }
        }
        $closure = $this->_closure($forClosure);
        return $closure;
    }

    /**
     * Calculates the closure set for the given set of rules
     *
     * @param \Phparser\Rule\RulesCollection $set Set of rules
     * @return array
     */
    protected function _closure(RulesCollection $set)
    {
        $regex = implode('|', $this->variables());
        $first = $this->first();

        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;
            foreach ($set as $rule) {
                $rhs = $rule->rhs();
                $result = preg_match('/\.\b(' . $regex . ')\b/', $rhs, $matches);

                if ($result) {
                    $rightSymbols = end(explode($matches[1], $rhs));
                    $variable = str_replace('.', '', $matches[1]);

                    foreach ($this->_rules as $r) {
                        $lhs = $r->lhs();
                        if ($lhs == $variable) {
                            $seq = trim($rightSymbols . ' ' . $rule->lookahead());
                            foreach ($this->_first($first, $seq) as $lookahead) {
                                $newRHS = '.' . $r->rhs();
                                $newPro = new Rule("{$variable} -> {$newRHS}");
                                $newPro->lookahead($lookahead);
                                if ($set->push($newPro)) {
                                    $hasChanged = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $set;
    }
}
