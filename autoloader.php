<?php
function autoloader($class) {
    set_include_path(get_include_path().PATH_SEPARATOR.'src/');

    $classParts = explode('\\', $class);

    $namespaceRoot = array_shift($classParts);

    $fileName = array_pop($classParts);

    $namespace = implode(DIRECTORY_SEPARATOR, $classParts);

    require_once strtolower($namespace) . DIRECTORY_SEPARATOR . $fileName . '.php';
}

spl_autoload_register('autoloader');