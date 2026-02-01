<?php
namespace Agenda\Helpers;

use PDOException;

function isQaModeNotReady(): bool
{
    $mode = getenv('QA_MODE') ?: '';
    return strcasecmp($mode, 'not_ready') === 0;
}

function isConnectionFailure(PDOException $exception): bool
{
    $message = $exception->getMessage();
    $patterns = [
        'Connection refused',
        'No such file or directory',
        'Access denied',
        'SQLSTATE[HY000] [2002]',
    ];
    foreach ($patterns as $pattern) {
        if (stripos($message, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

function shouldTreatAsNotReady(PDOException $exception): bool
{
    return isQaModeNotReady() && isConnectionFailure($exception);
}
