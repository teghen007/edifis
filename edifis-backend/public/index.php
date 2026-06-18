<?php

// Local dev on PHP 8.5: silence the framework's PDO::MYSQL_ATTR_SSL_CA deprecation
// so responses (incl. API JSON) stay clean. Harmless; remove on PHP 8.3.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
