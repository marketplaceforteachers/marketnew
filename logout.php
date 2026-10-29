<?php
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $toAdminLogin = post('next') === 'admin';
    logout_user();
    if ($toAdminLogin) {
        redirect('/admin/login.php');
    }
}
redirect('/index.php');
