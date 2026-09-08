<?php
/**
 * Logout
 * Sistema Estacionamento v3.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth/AuthDomain.php';

$auth = new AuthDomain();
$auth->logout();

header('Location: login.php');
exit;
