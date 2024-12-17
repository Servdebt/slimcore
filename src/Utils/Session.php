<?php

namespace Servdebt\SlimCore\Utils;

class Session
{

    public static function start(array $settings = [])
    {
        if (isset($settings['name'])) {
            session_name($settings['name']);
        }
        if (isset($settings['save_handler'])) {
            ini_set('session.save_handler', $settings['save_handler'] ?? 'files');
        }
        if (isset($settings['save_path']) || isset($settings['filesPath'])) {
            session_save_path($settings['save_path'] ?? $settings['filesPath']);

            if (ini_get('session.save_handler') == 'files' && !is_dir(ini_get('session.save_path'))) {
                mkdir(ini_get('session.save_path'), 0777, true);
            }
        }
        session_start();

        if (ini_get("session.use_cookies")) {
            setcookie(
                $settings['name'] ?? 'app',
                session_id(),
                time() + ($settings['lifetime'] ?: 3600),
                $settings['path'] ?? '/',
                $settings['domain'] ?? '',
                $settings['secure'] ?? false,
                $settings['httponly'] ?? false,
            );
        }
    }


    /**
     * Get one session var
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
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
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Delete one session var by key
     *
     * @param string $key
     * @return void
     */
    public static function delete(string $key): void
    {
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Clear all session vars
     *
     * @return void
     */
    public static function clearAll(): void
    {
        $_SESSION = [];
    }

    /**
     * Regenerate current session id
     *
     * @return void
     */
    public static function regenerate(): void
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destroy current session and delete session cookie
     *
     * @return bool
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