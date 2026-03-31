<?php
/**
 * SMTP email configuration.
 * Values are read from environment variables so they can be set
 * in docker-compose.yml (or a .env file) without touching source code.
 */

$env = static function (string $key, string $default = ''): string {
    return $_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) ?: $default);
};

return [
    'host'       => $env('MAIL_HOST',       'smtp.example.com'),
    'port'       => (int) $env('MAIL_PORT', '587'),
    'username'   => $env('MAIL_USERNAME',   'no-reply@example.com'),
    'password'   => $env('MAIL_PASSWORD',   ''),
    'encryption' => $env('MAIL_ENCRYPTION', 'tls'),
    'from_email' => $env('MAIL_FROM_EMAIL', 'no-reply@example.com'),
    'from_name'  => $env('MAIL_FROM_NAME',  'Unreal Game'),
];
