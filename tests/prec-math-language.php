<?php
require __DIR__ . '/config/bootstrap.php';

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
 * Print some debug information.
 */
echo "<code><p><b>{$exp}</b> = {$RESULT}</p></code>Precedence: '*' > '/' > '+' > '-'<hr/>";
echo "<h2>Paser trace:</h2>";
debug($parser->trace());
echo "<h2>First set:</h2>";
debug($grammar->rules()->first());
echo "<h2>Follow set:</h2>";
debug($grammar->rules()->follow());
echo "<h2>Parsing table:</h2>";
debug(draw_table($grammar->transitionTable()->toArray()));
echo "<h2>Syntax Tree:</h2>";
debug($parser->treeAsString());
