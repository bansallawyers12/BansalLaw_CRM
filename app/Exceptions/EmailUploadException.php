<?php

namespace App\Exceptions;

/**
 * Email upload failure carrying a machine-readable error code and the raw
 * technical error, so the UI can show the actual cause instead of a
 * genericized message.
 */
class EmailUploadException extends \Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $userMessage,
        public readonly ?string $technicalError = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($userMessage, 0, $previous);
    }
}
