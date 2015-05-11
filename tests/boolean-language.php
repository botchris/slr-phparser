<?php
require __DIR__ . '/config/bootstrap.php';

use Phparser\Grammar\Grammar;
use Phparser\Lexer\Lexer;
use Phparser\Parser\Parser;
use Phparser\Rule\LR0\RulesCollection;

/**
 * Boolean language definition.
 */
$productions = new RulesCollection([
    'S -> exp_b',
    'exp_b -> exp_b exp_b',
    'exp_b -> exp_b T_OR exp_b',
    'exp_b -> exp_b T_AND exp_b',
    'exp_b -> T_NOT exp_b',
    'exp_b -> T_LP exp_b T_RP',
    'exp_b -> expression',
    'expression -> statement',
    'expression -> command',
    'statement -> T_LITERAL',
    'statement -> T_WORD',
    'command -> T_WORD T_CMD command_arg',
    'command_arg -> T_LITERAL',
    'command_arg -> T_WORD',
]);

/**
 * Tokens definition.
 */
$tokens = [
    'and' => 'T_AND',
    'or' => 'T_OR',
    '\-' => 'T_NOT',
    '"[^"]+"' => 'T_LITERAL',
    '\w+' => 'T_WORD',
    '\(' => 'T_LP',
    '\)' => 'T_RP',
    ':' => 'T_CMD',
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
$grammar->prec('T_NOT', 3);
$grammar->prec('T_AND', 2);
$grammar->prec('T_OR', 1);

/**
 * The expression to parse.
 */
$exp = 'A and B or C and -negated';

/**
 * Run the parser.
 */
$parser->run($exp);

/**
 * Print some debug information.
 */
echo "<code><p>String parsed: <b>{$exp}</b></p></code><hr/>";
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
