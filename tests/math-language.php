<?php

require __DIR__ . '/config/bootstrap.php';

use Phparser\Grammar;
use Phparser\Lexer;
use Phparser\Parser;

$RESULT = 0;
$productions = [
    ['S' => 'exp_a', function ($info) {
        global $RESULT;
        $info = $info[0];
        $RESULT = $info;
    }],
    ['exp_a' => 'exp_a T_PLS exp_a', function (&$info) {
       $info = $info[0] + $info[2];
    }],   
    ['exp_a' => 'exp_a T_SUB exp_a', function (&$info) {
       $info = $info[0] - $info[2];
    }],   
    ['exp_a' => 'exp_a T_MUL exp_a', function (&$info) {
        $info = $info[0] * $info[2];
    }],   
    ['exp_a' => 'exp_a T_DIV exp_a', function (&$info) {
        $info = $info[0] / $info[2];
    }],   
    ['exp_a' => 'T_LP exp_a T_RP', function (&$info) {
        $info = $info[1];
    }],
    ['exp_a' => 'T_SQRT T_LP exp_a T_RP', function (&$info) {
        $info = sqrt($info[2]);
    }],   
    ['exp_a' => 'T_NUM', function (&$info) {
        $info = intval($info[0]->value());
    }],
];

$tokens = [
    '\*' => 'T_MUL',
    '\+' => 'T_PLS',
    '\-' => 'T_SUB',
    '\/' => 'T_DIV',
    'sqrt' => 'T_SQRT',
    '[0-9]+' => 'T_NUM',
    '\(' => 'T_LP',
    '\)' => 'T_RP',
    '\s+|\n+|\t+' => '',
];

$lexer = new Lexer($tokens);
$grammar = new Grammar($productions);
$parser = new Parser($lexer, $grammar);
$exp = '50 + (20 * 2) + 1 - (1 / 2) + sqrt(90)';

$parser->run($exp);

echo "<code><p><b>{$exp}</b> = {$RESULT}</p></code><hr/>";

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
