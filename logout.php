<?php
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user();
}
redirect('/index.php');
