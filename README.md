# SLR PHParser

A very simple LR(0) and LR(1) parser generator written in PHP.

## Example:

Simple calculator with operator precedence:

```php
use Phparser\Grammar\Grammar;
use Phparser\Lexer\Lexer;
use Phparser\Parser\Parser;
use Phparser\Rule\LR1\RulesCollection;

/**
 * This will be used to hold the result of the calculator.
 */
$RESULT = 0;

/**
 * Calculator's language definition.
 */
$productions = new RulesCollection([
    'S -> exp_a' => function ($info) {
        global $RESULT;
        $info = $info[0];
        $RESULT = $info;
    },
    'exp_a -> exp_a T_PLS exp_a ' => function (&$info) {
       $info = $info[0] + $info[2];
    },
    'exp_a -> exp_a T_SUB exp_a' => function (&$info) {
       $info = $info[0] - $info[2];
    },
    'exp_a -> exp_a T_MUL exp_a' => function (&$info) {
        $info = $info[0] * $info[2];
    },
    'exp_a -> exp_a T_DIV exp_a' => function (&$info) {
        $info = $info[0] / $info[2];
    },
    'exp_a -> T_LP exp_a T_RP' => function (&$info) {
        $info = $info[1];
    },
    'exp_a -> T_SQRT T_LP exp_a T_RP' => function (&$info) {
        $info = sqrt($info[2]);
    },
    'exp_a -> T_NUM' => function (&$info) {
        $info = intval($info[0]->value());
    },
]);

/**
 * Tokens definition.
 */
$tokens = [
    '\*' => 'T_MUL',
    '\/' => 'T_DIV',
    '\+' => 'T_PLS',
    '\-' => 'T_SUB',
    'sqrt' => 'T_SQRT',
    '[0-9]+' => 'T_NUM',
    '\(' => 'T_LP',
    '\)' => 'T_RP',
    '\s+|\n+|\t+' => '',
];

/**
 * Startup the parser.
 */
$lexer = new Lexer($tokens);
$grammar = new Grammar($productions);
$parser = new Parser($lexer, $grammar);

/**
 * Configure tokens precedence.
 */
$grammar->prec('T_MUL', 4);
$grammar->prec('T_DIV', 3);
$grammar->prec('T_SUB', 2);
$grammar->prec('T_PLS', 1);

/**
 * The expression to parse.
 */
$exp = '50 + 20 * 3 / 2 - 1';

/**
 * Run the parser.
 */
$parser->run($exp);

/**
 * Draw parsing table.
 */
require 'tests/config/drawer.php';
echo "<h2>Parsing table:</h2>";
echo draw_table($grammar->transitionTable()->toArray());
```
