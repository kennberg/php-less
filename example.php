<?php

define('LIB_DIR', getcwd());

include(LIB_DIR . 'php-less.php');

$c = new PhpLess();
$c->add('main.less')
 ->addDir('/less/')
 ->cacheDir('/tmp/css-cache/')
 ->write();
