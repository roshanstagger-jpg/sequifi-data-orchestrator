<?php

// Create writable directories in /tmp for Vercel's read-only serverless filesystem.
// Laravel writes packages.php, services.php, and compiled views on every boot.
$tmpBase = '/tmp/laravel';
foreach ([
    "$tmpBase/storage/app/public",
    "$tmpBase/storage/framework/cache/data",
    "$tmpBase/storage/framework/sessions",
    "$tmpBase/storage/framework/views",
    "$tmpBase/storage/logs",
    "$tmpBase/bootstrap/cache",
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

putenv("LARAVEL_STORAGE_PATH=$tmpBase/storage");
putenv("LARAVEL_BOOTSTRAP_PATH=$tmpBase/bootstrap");

$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
require __DIR__ . '/../public/index.php';
