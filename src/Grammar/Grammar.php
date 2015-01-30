<?php
namespace Phparser\Grammar;

use Phparser\Rule\Rule;
use Phparser\Rule\CollectionInterface;

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

    protected $_prec = [];

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
    public function __construct(CollectionInterface $rules)
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
    public function rules(CollectionInterface $rules = null)
    {
        if ($rules !== null) {
            $this->_rules = $rules;
        }
        return $this->_rules;
    }

    /**
     * Sets operator precedence.
     * 
     * @param string $token The operator
     * @param int $priority It's priority. Higher the number, higher its priority.
     * @return int Priority for the given operator
     */
    public function prec($token, $priority = null)
    {
        if (is_integer($priority)) {
            $this->_prec[$token] = $priority;
        }

        if (isset($this->_prec[$token])) {
            return $this->_prec[$token];
        }

        return 0;
    }

    /**
     * Sets associativity to use to solve priority conflicts.
     * 
     * @param int $precLvl Precedence level
     * @param string $leftOrRight Associativity symbol (>, <, <=, >=) 
     * @return string The associativity symbol for the given precedence level
     */
    public function assoc($precLvl, $assoc = null)
    {
        if (in_array($assoc, ['<', '>', '<=', '>='])) {
            $this->_assoc[$precLvl] = $assoc;
        }

        if (isset($this->_assoc[$precLvl])) {
            return $this->_assoc[$precLvl];
        }

        return '<';
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
                $lookahead = $rule->lookahead();
                $lhs = $rule->lhs();
                $rhs = $rule->rhs();

                // if beta = a gamma
                if (!preg_match('/\.$/', $rhs)) {
                    preg_match('/\.\b(' . $regex . ')\b/', $rhs, $matches);
                    $a = str_replace('.', '', $matches[1]);

                    if (in_array($a, $this->_rules->variables())) {
                        $this->_table->set($i, $matches[1], $this->_rules->gotoIndex($set, $matches[1]));
                    } elseif (in_array($a, $this->_rules->terminals())) {
                        $actual = $this->_table->get($i, $matches[1]);
                        $new = 's' . $this->_rules->gotoIndex($set, $matches[1]);
                        $final = $actual ? "{$new}/{$actual}" : $new;

                        // conflict resolution
                        if (preg_match('/^s\d\/r(\d)$/', $final, $m)) {
                            $cRule = $this->_rules->getRuleByIndex(intval($m[1]));
                            $invert = false;
                            $rt = end(array_intersect(
                                explode(' ', $cRule->rhs()),
                                $this->_rules->terminals()
                            ));

                            if ($this->prec($matches[1]) < $this->prec($rt)) {
                                $invert = true;
                            } elseif ($this->prec($matches[1]) == $this->prec($rt)) {
                                switch ($this->assoc($this->prec($matches[1]))) {
                                    case '<':
                                        $invert = $this->prec($matches[1]) < $this->prec($rt);
                                        break;
                                    case '>':
                                        $invert = $this->prec($matches[1]) > $this->prec($rt);
                                        break;
                                    case '<=':
                                        $invert = $this->prec($matches[1]) <= $this->prec($rt);
                                        break;
                                    case '>=':
                                        $invert = $this->prec($matches[1]) >= $this->prec($rt);
                                        break;
                                }
                            }

                            if ($invert) {
                                list($p1, $p2) = explode('/', $final);
                                $final = "{$p2}/{$p1}";
                            }
                        }

                        $final = implode('/', array_unique(explode('/', $final)));
                        $this->_table->set($i, $matches[1], $final);

                    }
                } else {
                    if (!empty($follow[$lhs])) {
                        foreach ($follow[$lhs] as $terminal) {
                            $actual = $this->_table->get($i, $terminal);
                            $new = 'r' . $this->_rules->getRuleIndex($rule);
                            $this->_table->set($i, $terminal, $new);
                        }
                    }
                }
            }

            $extendedAcceptance = clone $this->_acceptanceRule();
            $extendedAcceptance->lookahead('$');
            if ($set->exists($this->_acceptanceRule()) || $set->exists($extendedAcceptance)) {
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
