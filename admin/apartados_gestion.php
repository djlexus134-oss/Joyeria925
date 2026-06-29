<?php

require_once __DIR__ . '/includes/auth.php';

if (!auth_is_logged_in()) {
    header('Location: login.php');
    exit;
}

header('Location: apartados_consulta.php?accion=leer', true, 302);
exit;
