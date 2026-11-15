<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ir('index.php');
verificar_post();
if (!empty($_SESSION['uid'])) bitacora('Cerró sesión');
$_SESSION = [];
session_regenerate_id(true);
session_destroy();
ir('login.php');
