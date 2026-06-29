<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/tienda_auth.php';

tienda_logout();

header('Location: ../index.php');
exit;
