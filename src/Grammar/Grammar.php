<?php
namespace Phparser\Grammar;

use Phparser\Rule\Rule;
use Phparser\Rule\RulesCollection;
use Phparser\Rule\CanonicalCollection;

/**
 * Grammar which describes a language.
 *
 */
class Grammar
{

    /**
     * Grammar productions.
     *
     * @var array
     */
    protected $_rules = [];

    /**
     * The parsing table used by the parser.
     *
     * @var array
     */
    protected $_table = null;

    /**
     * Acceptance production, used internally.
     *
     * @var \Phparser\Rule\Rule
     */
    protected $_acceptanceRule = null;

    /**
     * Start variable (axiom) must be at top of the rules list.
     *
     * ```php
     * [
     *     'S' => 'expression', // start variable (axiom)
     *     'expression' => 'T_NUM T_PLUS T_NUM',
     *     'expression' => 'T_NUM T_MINUS T_NUM',
     *     ...
     * ]
     * ```
     *
     * @param array $rules List of productions
     */
    public function __construct($rules)
    {
        $this->rules($rules);
    }

    /**
     * Performs a reduction by the given production rule.
     *
     * @param int $index Production number to reduce by
     * @param array $args Arguments for the rule's semantic routine.
     * @return mixed
     */
    public function reduceBy($index, &$args)
    {
        $rule = $this->_rules->getRuleByIndex($index);
        return $rule->routine($args);
    }

    /**
     * Gets/sets a set of rules.
     *
     * @param null|array|\Phparser\Rule\RulesCollection $productions Set of
     *  productions given as array or collection
     * @return array
     */
    public function rules($rules = null)
    {
        if ($rules !== null) {
            if (is_array($rules)) {
                $this->_rules = new RulesCollection($rules);
            } elseif (is_object($rules)) {
                $this->_rules = $rules;
            }
        }
        return $this->_rules;
    }

    /**
     * Generates the automate used by the parser.
     *
     * @return array
     */
    public function transitionTable()
    {
        if ($this->_table !== null) {
            return $this->_table;
        }

        $this->_table = new TransitionTable($this->rules());
        $first = $this->_rules->first();
        $follow = $this->_rules->follow();
        $collection = $this->_rules->canonicalCollection();
        $regex = implode('|', array_merge(
            $this->_rules->variables(),
            $this->_rules->terminals()
        ));

        foreach ($collection as $i => $set) {
            // for A -> alpha . beta
            foreach ($set as $rule) {
                $lhs = $rule->lhs();
                $rhs = $rule->rhs();

                // if beta = a gamma
                if (!preg_match('/\.$/', $rhs)) {
                    preg_match('/\.\b(' . $regex . ')\b/', $rhs, $matches);
                    $a = str_replace('.', '', $matches[1]);

                    if (in_array($a, $this->_rules->variables())) {
                        $this->_table->set($i, $matches[1], $this->_rules->gotoIndex($set, $matches[1]));
                    } elseif (in_array($a, $this->_rules->terminals())) {
                        $this->_table->set($i, $matches[1], 's' . $this->_rules->gotoIndex($set, $matches[1]));
                    }
                } else {
                    if (!empty($follow[$lhs])) {
                        foreach ($follow[$lhs] as $terminal) {
                            $this->_table->set($i, $terminal, 'r' . $this->_rules->getRuleIndex($rule));
                        }
                    }
                }
            }

            if ($set->exists($this->_acceptanceRule())) {
                $this->_table->set($i, '$', 'acc');
            }
        }

        return $this->transitionTable();
    }

    protected function _acceptanceRule()
    {
        if (!$this->_acceptanceRule) {
            $oldAxiom = $this->_rules->startVariable();
            $newAxiom = "{$oldAxiom}'";
            $this->_acceptanceRule = new Rule("{$newAxiom} -> {$oldAxiom}.");
        }
        return $this->_acceptanceRule;
    }
}
