<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Extend limits for large personal document video uploads (before bootstrap)
|--------------------------------------------------------------------------
|
| max_input_time must be raised before PHP parses the request body. Config
| values are mirrored in public/.user.ini for hosts that ignore index.php ini_set.
|
*/
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (
    str_contains($requestUri, '/documents/upload-edu-document')
    || str_contains($requestUri, '/documents/bulk-upload-personal')
) {
    $uploadTimeLimit = max(300, (int) ($_ENV['PERSONAL_VIDEO_UPLOAD_EXECUTION_TIME'] ?? $_SERVER['PERSONAL_VIDEO_UPLOAD_EXECUTION_TIME'] ?? 1800));
    $inputTimeLimit = max(300, (int) ($_ENV['PERSONAL_VIDEO_UPLOAD_MAX_INPUT_TIME'] ?? $_SERVER['PERSONAL_VIDEO_UPLOAD_MAX_INPUT_TIME'] ?? 1800));

    if (function_exists('set_time_limit')) {
        @set_time_limit($uploadTimeLimit);
    }

    @ini_set('max_execution_time', (string) $uploadTimeLimit);
    @ini_set('max_input_time', (string) $inputTimeLimit);
    @ini_set('default_socket_timeout', '600');
    @ignore_user_abort(true);
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);

