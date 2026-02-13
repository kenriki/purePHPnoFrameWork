<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/router.php';

$page = $_GET['page'] ?? 'home';
route($page);
?>