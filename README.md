php-less
======================

LESS is a dynamic stylesheet language created by Alexis Sellier. It makes developing systems-based CSS faster, easier, and more fun. Visit the official website at http://lesscss.org to learn more.

This is a wrapper around the less compiler to help compile the resources and cache results until resources are changed. The caching code is based on php-closure by Daniel Pupius.


How to use
======================

PHP wrapper for the lessc compiler.

Handles caching and recompilation of sources.  A recompilation will occur
whenever a source file, or the script calling the compiler, changes.

The class will handle the Last-Modified and 304 redirects for you, but if
you should set Cache-Control headers appropriate for your needs.

Example usage:

    define('LIB_DIR', getcwd());

    include(LIB_DIR . 'php-less.php');

    $c = new PhpLess();
    $c->add('main.less')
     ->addDir('/less/')
     ->cacheDir('/tmp/css-cache/')
     ->write();

License
======================
Apache v2. See the LICENSE file.
