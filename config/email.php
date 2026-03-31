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
    'host'       => 'mailhog',
    'port'       => 1025,
    'username'   => null,
    'password'   => null,
    'encryption' => null,
    'from_email' => 'no-reply@local.test',
    'from_name'  => 'Unreal Game (Local)',
];