<?php
namespace Phparser;

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
    protected $_productions = [];

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
     * First set for each variable.
     *
     * @var array
     */
    protected $_first = [];

    /**
     * Used to speed up some methods.
     *
     * @var array
     */
    protected $_cachedFirst = null;

    /**
     * Follow set for each variable.
     *
     * @var array
     */
    protected $_follow = [];


    /**
     * Used to speed up some methods.
     *
     * @var array
     */
    protected $_cachedFollow = null;

    /**
     * The parsing table used by the parser.
     *
     * @var array
     */
    protected $_table = null;

    /**
     * Canonical collection of items.
     *
     * @var array
     */
    protected $_canonicalCollection = [];

    /**
     * Acceptance production, used internally.
     *
     * @var array
     */
    protected $_acceptanceRule = [];

    /**
     * Start variable (axiom) must be at top of productions.
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
     * @param array $productions List of productions
     */
    public function __construct($productions)
    {
        $this->productions($productions);
    }

    /**
     * Gets the list of production variables.
     *
     * @return array
     */
    public function variables()
    {
        return $this->_variables;
    }

    /**
     * Gets the list of production terminals.
     *
     * @return array
     */
    public function terminals()
    {
        return $this->_terminals;
    }

    /**
     * Gets the canonical collection of items.
     *
     * @return array
     */
    public function canonicalCollection()
    {
        return $this->_canonicalCollection;
    }

    /**
     * Gets/sets a set of productions.
     *
     * @param array|null $productions Set of productions
     * @return array
     */
    public function productions($productions = null)
    {
        if ($productions !== null) {
            $this->_productions = $productions;

            foreach ($this->_productions as $i => $p) {
                $var = $this->_getLHS($p);
                array_unshift($this->_variables, $var);
                if ($i == 0) {
                    $this->_startVariable = $var;
                }
            }
            $this->_variables = array_unique($this->_variables);

            foreach ($this->_productions as $p) {
                $rhs = $this->_getRHS($p);
                $rhs = explode(' ', $rhs);
                $rhs = array_diff($rhs, $this->_variables);
                $this->_terminals = array_merge($this->_terminals, $rhs);
            }
            $this->_terminals = array_unique($this->_terminals);
        }
        return $this->_productions;
    }

    /**
     * Performs a reduction by the given production rule.
     *
     * @param int $pNum Production number to reduce by
     * @param array $args Arguments for the rule's semantic routine.
     * @return void
     */
    public function reduceBy($pNum, &$args)
    {
        $production = $this->_getProductionByNum($pNum);
        $routine = $this->_getRoutine($production);
        $routine($args);
    }

    /**
     * Generates the automate used by the parser.
     *
     * @return array
     */
    public function parseTable()
    {
        if ($this->_table !== null) {
            return (array)$this->_table;
        }

        $first = $this->first();
        $follow = $this->follow();
        $collection = $this->_canonicalCollection();
        $regex = implode('|', array_merge($this->_variables, $this->_terminals));

        foreach ($collection as $i => $item) {
            $this->_table[$i] = $this->_generateTableRow();

            // for A -> alpha . beta
            foreach ($item as $rule) {
                $lhs = $this->_getLHS($rule);
                $rhs = $this->_getRHS($rule);

                // if beta = a gamma
                if (!preg_match('/\.$/', $rhs)) {
                    preg_match('/\.\b(' . $regex . ')\b/', $rhs, $matches);
                    $a = str_replace('.', '', $matches[1]);

                    if (in_array($a, $this->_variables)) {
                        $this->_table[$i][$matches[1]] = $this->_gotoIndex($item, $matches[1]);
                    } elseif (in_array($a, $this->_terminals)) {
                        $this->_table[$i][$matches[1]] = 's' . $this->_gotoIndex($item, $matches[1]);
                    }
                } else {
                    if (!empty($follow[$lhs])) {
                        foreach ($follow[$lhs] as $terminal) {
                            $this->_table[$i][$terminal] = 'r' . $this->_getProductionNum($rule);
                        }
                    }
                }
            }

            if (in_array($this->_acceptanceRule, $item)) {
                $this->_table[$i]['$'] = 'acc';
            }
        }

        return $this->parseTable();
    }

    /**
     * Calculates the "goto" set for a given $item and $symbol.
     *
     * @param array $item From canonical collection
     * @param string $symbol A valid variable or terminal
     * @return array
     */
    protected function _goto($item, $symbol)
    {
        $forClosure = [];
        foreach ($item as $rule) {
            $rhs = $this->_getRHS($rule);
            if (preg_match('/\.\b(' . $symbol . ')\b/', $rhs, $matches)) {
                $lhs = $this->_getLHS($rule);
                $rhs = str_replace(".{$matches[1]}", "{$matches[1]}.", $rhs);
                $rhs = str_replace('. ', ' .', $rhs);
                $newRule = [$lhs => $rhs];
                $forClosure[] = $newRule;
            }
        }
        $closure = $this->_closure($forClosure);
        return $closure;
    }

    /**
     * Gets the "goto" set's index within the canonical collection.
     *
     * @param array $item From canonical collection
     * @param string $symbol A valid variable or terminal
     * @return int
     */
    protected function _gotoIndex($item, $symbol)
    {
        $goto = $this->_goto($item, $symbol);
        foreach ($this->_canonicalCollection as $k => $item) {
            if ($item == $goto) {
                return $k;
            }
        }
    }

    /**
     * Calculates the canonical collection, used for create the parsing table.
     *
     * @return array
     */
    protected function _canonicalCollection()
    {
        $productions = $this->_productions;
        $oldAxiom = $this->_startVariable;
        $newAxiom = "{$oldAxiom}'";
        array_unshift($productions, [$newAxiom => $oldAxiom]);
        $this->_acceptanceRule = [$newAxiom => "{$oldAxiom}."];
        $this->productions($productions);
        $this->_startVariable = $oldAxiom;

        $collection = [
            $this->_closure([
                [$newAxiom => ".{$oldAxiom}"]
            ])
        ];

        $hasChanged = true;
        while ($hasChanged) {
            $before = $collection;
            $hasChanged = false;
            foreach ($collection as $item) {
                $symbols = array_merge($this->_variables, $this->_terminals);
                foreach ($symbols as $symbol) {
                    $goto = $this->_goto($item, $symbol);
                    if ($goto) {
                        $closure = $this->_closure($goto);

                        if (!in_array($closure, $collection)) {
                            $collection[] = $this->_closure($goto);
                        }
                    }
                }
            }

            if ($before != $collection) {
                $hasChanged = true;
            }
        }

        $this->_canonicalCollection = $collection;
        return $collection;
    }

    /**
     * Calculates the closure set for the given set of productions
     *
     * @param array $productions Set of production rules
     * @return array
     */
    protected function _closure($productions)
    {
        $hasChanged = true;
        while ($hasChanged) {
            $before = $productions;
            $hasChanged = false;

            foreach ($productions as $rule) {
                $regex = implode('|', $this->_variables);
                $result = preg_match('/\.\b(' . $regex . ')\b/', $this->_getRHS($rule), $matches);

                if ($result) {
                    $variable = str_replace('.', '', $matches[1]);
                    foreach ($this->productions() as $p) {
                        $lhs = $this->_getLHS($p);
                        if ($lhs == $variable) {
                            $newRHS = '.' . $this->_getRHS($p);
                            $newPro = [$variable => $newRHS];
                            if (!in_array($newPro, $productions)) {
                                $productions[] = [$variable => $newRHS];
                            }
                        }
                    }
                }
            }

            if ($before != $productions) {
                $hasChanged = true;
            }
        }
        
        return $productions;
    }

    /**
     * Calculates the First set.
     *
     * @return array
     */
    public function first()
    {
        if ($this->_cachedFirst !== null) {
            return (array)$this->_cachedFirst;
        }

        $first = [];

        // Put the terminals in the array
        foreach ($this->_terminals as $t) {
            $first[$t] = [$t];
        }

        // Put the variables in the array as empty sets.
        foreach ($this->_variables as $v) {
            $first[$v] = [];
        }

        $hasChanged = true;
        $productions = $this->_productions;

        while ($hasChanged) {
            $hasChanged = false;
            foreach ($productions as $p) {
                $variable = $this->_getLHS($p);
                $rhs = $this->_getRHS($p);
                $firstRhs = $this->_first($first, $rhs);

                $before = $first;
                $first[$variable] = array_merge($first[$variable], $firstRhs);
                $first[$variable] = array_unique($first[$variable]);
                if ($before != $first) {
                    $hasChanged = true;
                }
            }
        };

        $this->_cachedFirst = $first;
        return $this->first();
    }

    /**
     * Calculates the Follow set.
     *
     * @return array
     */
    public function follow()
    {
        if ($this->_cachedFollow !== null) {
            return (array)$this->_cachedFollow;
        }
        $follow = [];
        $follow[$this->_startVariable] = ['$'];

        // Make every follow mapping empty for now.
        foreach ($this->_variables as $variable) {
            if ($variable != $this->_startVariable) {
                $follow[$variable] = [];
            }
        }

        $firstSets = $this->first();
        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;
            $before = $follow;

            foreach ($this->_productions as $p) {
                $variable = $this->_getLHS($p);
                $rhs = $this->_getRHS($p);

                $parts = explode(' ', trim($rhs));
                foreach ($parts as $k => $rhsVariable) {
                    if (!in_array($rhsVariable, $this->_variables)) {
                        continue;
                    }

                    if (isset($parts[$k + 1])) {
                        $firstFollowing = $this->_first($firstSets, $parts[$k + 1]);
                    } else {
                        $firstFollowing = [];
                        $firstFollowing[] = '';
                    }

                    // Is lambda in that following the variable? For
                    // A->aBb where lambda is in FIRST(b), everything
                    // in FOLLOW(A) is in FOLLOW(B).
                    if (in_array('', $firstFollowing)) {
                        foreach ($firstFollowing as $key => $val) {
                            if ($val === '') {
                                unset($firstFollowing[$key]);
                            }
                        }
                        $follow[$rhsVariable] = array_merge(
                            $follow[$rhsVariable],
                            $follow[$variable]
                        );
                        $follow[$rhsVariable] = array_unique($follow[$rhsVariable]);
                    }

                    // For A->aBb, everything in FIRST(b) except
                    // lambda is put in FOLLOW(B).
                    $follow[$rhsVariable] = array_merge(
                        $follow[$rhsVariable],
                        $firstFollowing
                    );
                    $follow[$rhsVariable] = array_unique($follow[$rhsVariable]);
                }
            }

            if ($before != $follow) {
                $hasChanged = true;
            }
        }

        $this->_cachedFollow = $follow;
        return $this->follow();
    }

    /**
     * Given a first map as returned by first() and a sequence of symbols,
     * return the first for that sequence.
     *
     * @param array $firstSets The map of single symbols to a map
     * @param array $sequence A string of symbols
     * @return array The first set for that sequence of symbols
     */
    protected function _first(&$firstSet, $sequence)
    {
        $first = [];
        $sequence = trim($sequence);

        if (empty($sequence)) {
            $first[] = '';
        }

        $parts = explode(' ', $sequence);
        $limit = count($parts);

        for ($j = 0; $j < $limit; $j++) {
            $s = $firstSet[$parts[$j]];
            if (!in_array('', $s)) {
                // Doesn't contain lambda. Add it and get the hell out of dodge.
                $first = array_merge($first, $s);
                break;
            }

            // Does contain lambda. Damn it.
            if ($j != (count($parts) - 1)) {
                foreach ($s as $key => $val) {
                    if ($val == '') {
                        unset($s[$key]);
                    }
                }
            }
            $first = array_merge($first, $s);
            if ($j != (count($parts) - 1)) {
                $s[] = '';
            }
        }

        return array_unique($first);
    }

    /**
     * Gets a production rule given its index.
     *
     * @return array
     */
    protected function _getProductionByNum($num)
    {
        foreach ($this->_productions as $k => $p) {
            if ($k == $num) {
                return $p;
            }
        }

        throw new \Exception(sprintf('Production #%d not found', $num));
    }

   /**
    * Given a productions returns its index
    *
    * @param array $pro Production
    * @return int
    */
    protected function _getProductionNum($pro)
    {
        $lhs = $this->_getLHS($pro);
        $rhs = trim(str_replace('.', '', $this->_getRHS($pro)));
        foreach ($this->_productions as $k => $p) {
            $lhs1 = $this->_getLHS($p);
            $rhs1 = $this->_getRHS($p);

            if ($lhs == $lhs1 && $rhs == $rhs1) {
                return $k;
            }
        }

        throw new \Exception(sprintf('Production "%s" not found', $pro));
    }

    /**
     * Generates an empty row for the parser table.
     *
     * @return array Indexed by column names
     */
    protected function _generateTableRow()
    {
        $row = [];
        foreach ([$this->_terminals, ['$'], $this->_variables] as $group) {
            foreach ($group as $columnName) {
                $row[$columnName] = '';
            }
        }

        return $row;
    }

    /**
     * Returns the right-hand side of the given production.
     *
     * @param array $production The production from where to extract the right-hand side
     * @return string
     */
    protected function _getRHS($production)
    {
        foreach ($production as $k => $v) {
            if (is_string($k)) {
                return trim($v);
            }
        }

        throw new \Exception("Unable to get rule's RHS");
    }

    /**
     * Returns the left-hand side of the given production.
     *
     * @param array $production The production from where to extract the left-hand side
     * @return string
     */
    protected function _getLHS($production)
    {
        foreach ($production as $k => $v) {
            if (is_string($k)) {
                return trim($k);
            }
        }

        throw new \Exception("Unable to get rule's LHS");
    }


   /**
    * Gets the semantic routine of the given production.
    *
    * @param array $production Production
    * @return callable
    */
    protected function _getRoutine($production)
    {
        foreach ($production as $k => $v) {
            if (is_integer($k)) {
                return $v;
            }
        }

        return function ($ars) {
            // not implemented
        };
    }
}
