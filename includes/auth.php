<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentTitle() {
    return $_SESSION['title'] ?? null;
}

function isTraveller() {
    return currentTitle() === 'Traveller';
}

function isAgency() {
    return currentTitle() === 'Travel Agency';
}

function requireRole($role) {
    if (!isLoggedIn() || currentTitle() !== $role) {
        header('Location: ' . baseUrl() . 'login.php');
        exit;
    }
}

function baseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $base = (preg_match('#/(traveller|agency)$#', $scriptDir))
        ? rtrim(dirname($scriptDir), '/')
        : rtrim($scriptDir, '/');
    return $base === '' ? '/' : $base . '/';
}

function attemptLogin($username, $password) {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM USER WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user_id']  = (int)$user['userID'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['title']    = $user['title'];
    return true;
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
