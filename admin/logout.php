<?php
require_once __DIR__ . '/includes/auth.php';

auth_logout();
auth_set_flash('Sesion finalizada correctamente.', 'info');

header('Location: login.php');
exit;
