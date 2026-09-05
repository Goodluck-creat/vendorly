<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();
logout();
header('Location: /login.php');
exit;
