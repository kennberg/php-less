<?php

define('LIB_DIR', getcwd() . 'lib/');

include(LIB_DIR . 'third-party/php-less.php');

$c = new PhpLess();
$c->add('main.less')
 ->addDir('/less/')
 ->cacheDir('/tmp/css-cache/')
 ->write();
