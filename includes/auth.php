<?php
function require_auth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

function require_role(string $role): void {
    require_auth();
    if ($_SESSION['role'] !== $role) {
        $url = $_SESSION['role'] === 'dosen'
            ? BASE_URL . '/dosen/dashboard.php'
            : BASE_URL . '/mahasiswa/dashboard.php';
        header("Location: $url");
        exit();
    }
}

function redirect_if_logged_in(): void {
    if (isset($_SESSION['user_id'])) {
        $url = $_SESSION['role'] === 'dosen'
            ? BASE_URL . '/dosen/dashboard.php'
            : BASE_URL . '/mahasiswa/dashboard.php';
        header("Location: $url");
        exit();
    }
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
