<?php

/**
 * Copyright 2012 Alex Kennberg (https://github.com/kennberg/php-less)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

define('LESSC_PATH', LIB_DIR . 'third-party/lessphp/lessc.inc.php');
require_once(LESSC_PATH);


/**
 * PHP wrapper for the lessc compiler.
 *
 * Handles caching and recompilation of sources.  A recompilation will occur
 * whenever a source file, or the script calling the compiler, changes.
 *
 * The class will handle the Last-Modified and 304 redirects for you, but if
 * you should set Cache-Control headers appropriate for your needs.
 *
 * Example usage:
 *
 * define('LIB_DIR', getcwd() . 'lib/');
 *
 * include(LIB_DIR . 'third-party/php-less.php');
 *
 * $c = new PhpLess();
 * $c->add('main.less')
 *   ->addDir('/less/')
 *   ->cacheDir('/tmp/css-cache/')
 *   ->write();
 * 
 */
class PhpLess {

  var $_srcs = array();
  var $_debug = true;
  var $_cache_dir = "";

  function PhpLess() { }

  /**
   * Adds a source file to the list of files to compile.  Files will be
   * concatenated in the order they are added.
   */
  function add($file) {
    $this->_srcs[] = $file;
    return $this;
  }

  /**
   * Search directory for source files and add them automatically.
   * Not recursive.
   */
  function addDir($directory) {
    $iterator = new DirectoryIterator($directory);
    foreach ($iterator as $fileinfo) {
      if (!$fileinfo->isFile())
        continue;

      // Skip backup files that start with "._".
      if (substr($fileinfo->getFilename(), 0, 2) == '._')
        continue;

      // Make sure extension is valid.
      $ext = $fileinfo->getFilename();
      $i = strrpos($ext, '.');
      if ($i >= 0)
        $ext = substr($ext, $i + 1);
      if ($ext != 'css' && $ext != 'less')
        continue;

      $this->add($fileinfo->getPathname());
    }
    return $this;
  }

  /**
   * Sets the directory where the compilation results should be cached, if
   * not set then caching will be disabled and the compiler will be invoked
   * for every request (NOTE: this will hit ratelimits pretty fast!)
   */
  function cacheDir($dir) {
    $this->_cache_dir = $dir;
    return $this;
  }

  /**
   * Turns of the debug info.
   * By default statistics, errors and warnings are logged to the console.
   */
  function hideDebugInfo() {
    $this->_debug = false;
    return $this;
  }

  /**
   * Writes the compiled response.  Reading from either the cache, or
   * invoking a recompile, if necessary.
   */
  function write() {
    header("Content-Type: text/css");

    // No cache directory so just dump the output.
    if ($this->_cache_dir == "") {
      echo $this->_compile();

    }
    else {
      $cache_file = $this->_getCacheFileName();
      if ($this->_isRecompileNeeded($cache_file)) {
        $result = $this->_compile();
        if ($result !== false)
          file_put_contents($cache_file, $result);
        echo $result;
      }
      else {
        // No recompile needed, but see if we can send a 304 to the browser.
        $cache_mtime = filemtime($cache_file);
        $etag = md5_file($cache_file);
        header("Last-Modified: ".gmdate("D, d M Y H:i:s", $cache_mtime)." GMT"); 
        header("Etag: $etag"); 
        if (@strtotime(@$_SERVER['HTTP_IF_MODIFIED_SINCE']) == $cache_mtime || 
            @trim(@$_SERVER['HTTP_IF_NONE_MATCH']) == $etag) { 
          header("HTTP/1.1 304 Not Modified"); 
            }
        else {
          // Read the cache file and send it to the client.
          echo file_get_contents($cache_file);
        }
      }
    }
  }

  // ----- Privates -----

  function _isRecompileNeeded($cache_file) {
    // If there is no cache file, we obviously need to recompile.
    if (!file_exists($cache_file)) return true;

    $cache_mtime = filemtime($cache_file);

    // If the source files are newer than the cache file, recompile.
    foreach ($this->_srcs as $src) {
      if (filemtime($src) > $cache_mtime) return true;
    }

    // If this script calling the compiler is newer than the cache file,
    // recompile.  Note, this might not be accurate if the file doing the
    // compilation is loaded via an include().
    if (filemtime($_SERVER["SCRIPT_FILENAME"]) > $cache_mtime) return true;

    // If the compiler is newer.
    if (filemtime(LESSC_PATH) > $cache_mtime) return true;

    // Cache is up to date.
    return false;
  }

  function _exec($cmd, &$stdout, &$stderr) {
    $process = proc_open($cmd, array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w')), $pipes); 

    fclose($pipes[0]); 
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]); 
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]); 
    proc_close($process);
  }

  function _compile() {
    $result = '';

    $buffer = '';
    foreach ($this->_srcs as $src) {
      $buffer .= file_get_contents($src) . "\n\n";
    }

    try {
      $lessc = new lessc;
      $result = $lessc->compile($buffer);
    }
    catch (Exception $e) {
      error_log('Exception: ' . $e->getMessage());
      return false;
    }

    return $result;
  }
    
  function _getCacheFileName() {
    return $this->_cache_dir . $this->_getHash() . ".css";
  }

  function _getHash() {
    return md5(implode(",", $this->_srcs) . "-" .
        $this->_debug);
  }
}
