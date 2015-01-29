<?php

require __DIR__ . '/config/bootstrap.php';

use Phparser\Grammar;
use Phparser\Lexer;
use Phparser\Parser;

$productions = [
    ['S' => 'exp_b', function ($info) {

    }],
    ['exp_b' => 'exp_b exp_b', function ($info) {

    }],
    ['exp_b' => 'exp_b T_OR exp_b', function ($info) {

    }],
    ['exp_b' => 'exp_b T_AND exp_b', function ($info) {

    }],
    ['exp_b' => 'T_NOT exp_b', function ($info) {

    }],
    ['exp_b' => 'T_LP exp_b T_RP', function ($info) {

    }],
    ['exp_b' => 'expression', function ($info) {

    }],
    ['expression' => 'statement', function ($info) {

    }],
    ['expression' => 'command', function ($info) {

    }],
    ['statement' => 'T_LITERAL', function ($info) {

    }],
    ['statement' => 'T_WORD', function ($info) {

    }],
    ['command' => 'T_WORD T_CMD command_arg', function ($info) {

    }],
    ['command_arg' => 'T_LITERAL', function ($info) {

    }],
    ['command_arg' => 'T_WORD', function ($info) {

    }],
];

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

$lexer = new Lexer($tokens);
$grammar = new Grammar($productions);
$parser = new Parser($lexer, $grammar);
$exp = 'this and "this phrase" -negated created:"2013..2015"';

$parser->run($exp);

echo "<code><p>String parsed: <b>{$exp}</b></p></code><hr/>";


echo "<h2>Paser stack:</h2>";
debug($parser->trace());

echo "<h2>First set:</h2>";
debug($grammar->first());

echo "<h2>Follow set:</h2>";
debug($grammar->follow());

echo "<h2>Parsing table:</h2>";
debug($grammar->parseTable());

echo "<h2>Syntax Tree:</h2>";
debug($parser->tree());
