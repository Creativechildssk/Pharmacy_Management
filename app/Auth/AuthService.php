<?php

declare(strict_types=1);

namespace Pharmacy\Auth;

use PDO;

final class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function attempt(string $username, string $password, ?string $ip = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, username, full_name, password_hash, active FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim($username)]);
        $user = $stmt->fetch();

        $success = is_array($user) && (int) $user['active'] === 1 && password_verify($password, $user['password_hash']);
        $attempt = $this->pdo->prepare('INSERT INTO login_attempts(username, ip_address, successful) VALUES(:username,:ip,:successful)');
        $attempt->execute(['username' => trim($username), 'ip' => $ip, 'successful' => $success ? 1 : 0]);

        if (!$success) {
            return false;
        }

        $roles = $this->pdo->prepare('SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:user_id');
        $roles->execute(['user_id' => $user['id']]);
        $roleCodes = array_column($roles->fetchAll(), 'code');

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'roles' => $roleCodes,
            'last_activity' => time(),
        ];

        $update = $this->pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=:id');
        $update->execute(['id' => $user['id']]);
        return true;
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
