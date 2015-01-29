<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', realpath('./../'));

require ROOT . DS . 'vendor' . DS . 'autoload.php';

/**
 * Prints out debug information about given variable.
 *
 * @param mixed $var Variable to dump
 * @return void Echo
 */
function debug($var)
{
    echo '<pre>';
        print_r($var);
    echo '</pre>';
}
