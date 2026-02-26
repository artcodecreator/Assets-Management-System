<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = str_replace('\\', '/', dirname($scriptName));

    if ($base === '/' || $base === '\\' || $base === '.') {
        $base = '';
    }

    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base === '' ? '/' . $path : $base . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function require_valid_csrf_or_fail(): void
{
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh and try again.');
    }
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function selected(string|int|null $value, string|int|null $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function valid_status(string $status): bool
{
    return in_array($status, ASSET_STATUSES, true);
}

