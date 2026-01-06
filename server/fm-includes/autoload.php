<?php

/**
 * PSR-4 compliant class autoloader
 *
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
 * @link https://www.php.net/manual/en/function.spl-autoload-register.php
 * @param string $class fully-qualified class name
 * @return void
 */

spl_autoload_register(function ($class) {
    global $fm_name;

    // echo "<p>Autoloading: $fm_name -> $class</p>\n";
    // Base directory where all modules are stored
    $base_dir = dirname(__DIR__) . '/fm-modules/';

    // Remove $fm_name prefix if it's a module class
    // Count number of \ in $class
    $numBackslashes = substr_count($class, '\\');
    if ($numBackslashes > 1) {
        $parts = explode('\\', $class);
        if ($parts[0] === $fm_name) {
            array_shift($parts); // Remove the first part
            $class = implode('\\', $parts); // Rebuild the class name without the first part
        }
    }

    // Convert the fully qualified class name into a file path
    $class_path = str_replace('\\', '/', $class);

    // Add classes directory to class path
    $class_path = str_replace('/', '/classes/', $class_path);

    $file = $base_dir . $class_path . '.php';
    // echo "<p>$class <br />$file</p>\n";

    // Require the file if it exists
    if (file_exists($file)) {
        require $file;
    }
});

// Include Composer autoload
if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    include(dirname(__DIR__, 2) . '/vendor/autoload.php');
}
