<?php
//  includes/auth.php — Gestion session et autorisation

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../config/db.php';

function getUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireAuth(array $roles = []): array {
    $user = getUser();
    if (!$user) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
    if (!empty($roles) && !in_array($user['role'], $roles)) {
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }
    return $user;
}

function loginUser(array $user): void {
    $_SESSION['user'] = [
        'id'          => $user['id'],
        'nom'         => $user['nom'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'association' => $user['association'],
        'avatar'      => $user['avatar'],
    ];
}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}

function hasRole(string ...$roles): bool {
    $user = getUser();
    return $user && in_array($user['role'], $roles);
}