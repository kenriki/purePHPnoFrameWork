<?php
// app/templates/chat_view/search_users.php
require_once __DIR__ . '/../../dbconfig.php';
$pdo = getDB();

$keyword = $_GET['q'] ?? '';
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username LIKE ? AND id != ?");
$stmt->execute(['%' . $keyword . '%', $_SESSION['user_id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));