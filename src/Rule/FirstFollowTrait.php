<?php
namespace Phparser\Rule;

trait FirstFollowTrait
{

    /**
     * Used to speed up some methods.
     *
     * @var array
     */
    protected $_cachedFirst = null;

    /**
     * Used to speed up some methods.
     *
     * @var array
     */
    protected $_cachedFollow = null;
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
        foreach ($this->terminals() as $t) {
            $first[$t] = [$t];
        }

        // Put the variables in the array as empty sets.
        foreach ($this->variables() as $v) {
            $first[$v] = [];
        }

        $hasChanged = true;

        while ($hasChanged) {
            $hasChanged = false;
            foreach ($this->_rules as $rule) {
                $variable = $rule->lhs();
                $rhs = $rule->rhs();
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
        foreach ($this->variables() as $variable) {
            if ($variable != $this->_startVariable) {
                $follow[$variable] = [];
            }
        }

        $firstSets = $this->first();
        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;
            $before = $follow;

            foreach ($this->_rules as $rule) {
                $variable = $rule->lhs();
                $rhs = $rule->rhs();

                $parts = explode(' ', trim($rhs));
                foreach ($parts as $k => $rhsVariable) {
                    if (!in_array($rhsVariable, $this->variables())) {
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
    protected function _first($firstSet, $sequence)
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
} 