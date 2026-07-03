<?php

namespace App\Http\Middleware;

use App\Services\PersonalDocumentVideoUploadService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendVideoUploadLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        PersonalDocumentVideoUploadService::extendPhpLimits();

        return $next($request);
    }
}
