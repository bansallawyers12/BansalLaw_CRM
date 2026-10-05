<?php

namespace App\Http\Middleware;

use App\Services\PersonalDocumentVideoUploadService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */ 
    protected $except = [
        'api/*',
        'webhooks/sms/*',
    ];

    /**
     * Handle an incoming request.
     *
     * If the incoming payload exceeded PHP's post_max_size, PHP empties $_POST and $_FILES.
     * Intercept this condition here to throw PostTooLargeException (HTTP 413) instead of
     * failing CSRF token verification with a misleading HTTP 419 (Page Expired).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Http\Exceptions\PostTooLargeException
     */
    public function handle($request, \Closure $next)
    {
        if ($request->isMethod('POST') && empty($_POST) && empty($_FILES)) {
            $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
            $postMaxBytes = PersonalDocumentVideoUploadService::parseIniBytes((string) ini_get('post_max_size'));
            // JSON/API bodies leave $_POST empty — only treat as post_max_size when payload actually exceeds PHP limit.
            if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength >= $postMaxBytes) {
                throw new \Illuminate\Http\Exceptions\PostTooLargeException;
            }
        }

        return parent::handle($request, $next);
    }
}
