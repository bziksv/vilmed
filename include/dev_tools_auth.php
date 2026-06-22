<?php
/**
 * Проверка доступа к служебным dev/maintenance-скриптам.
 */
function vilmedRequireAdmin(): void
{
    global $USER;
    if (!is_object($USER) || !$USER->IsAuthorized() || !$USER->IsAdmin()) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
        }
        die('Access denied');
    }
}
