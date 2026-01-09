<?php
declare(strict_types=1);

/*
 * Bootstrap for TYPO3 Functional Tests
 */

// Define ORIGINAL_ROOT constant required by FunctionalTestCase
if (!defined('ORIGINAL_ROOT')) {
    define('ORIGINAL_ROOT', dirname(__DIR__) . '/');
}

// Set up autoloading
$autoloadFile = dirname(__DIR__) . '/.Build/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    throw new RuntimeException('Composer autoload file not found. Run "composer install" first.');
}

require_once $autoloadFile;
