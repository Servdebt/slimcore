<?php

namespace Servdebt\SlimCore\Utils;

class Session
{

    public static function start(array $settings = []): void
    {
        if (!array_key_exists('name', $settings)) $settings['name'] = 'app';
        if (!array_key_exists('lifetime', $settings)) $settings['lifetime'] = 3600;
        if (!array_key_exists('save_handler', $settings)) $settings['save_handler'] = 'files';

        session_name($settings['name']);
        ini_set('session.gc_maxlifetime', $settings['lifetime']);
        ini_set('session.save_handler', $settings['save_handler'] ?? 'files');

        if (isset($settings['save_path']) || isset($settings['filesPath'])) {
            session_save_path($settings['save_path'] ?? $settings['filesPath']);

            if (ini_get('session.save_handler') == 'files' && !is_dir(ini_get('session.save_path'))) {
                mkdir(ini_get('session.save_path'), 0777, true);
            }
        }
        session_start();

        if (ini_get("session.use_cookies")) {
            setcookie(
                $settings['name'],
                session_id(),
                time() + $settings['lifetime'],
                $settings['path'] ?? '/',
                $settings['domain'] ?? '',
                $settings['secure'] ?? false,
                $settings['httponly'] ?? false,
            );
        }
    }


    /**
     * Get one session var
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        }

        return $default;
    }

    /**
     * Set one session var
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Delete one session var by key
     */
    public static function delete(string $key): void
    {
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Clear all session vars
     */
    public static function clearAll(): void
    {
        $_SESSION = [];
    }

    /**
     * Regenerate current session id
     */
    public static function regenerate(): void
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destroy current session and delete session cookie
     */
    public static function destroy(): bool
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 7200,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            return session_destroy();
        }

        return false;
    }

}