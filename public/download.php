<?php
session_start();
require_once __DIR__ . '/../app/dbconfig.php';
$pdo = getDB();

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // 1. まずDBにトークンが含まれるデータがあるか検索
    $stmt = $pdo->prepare("SELECT * FROM file_uploads WHERE file_path LIKE ?");
    $stmt->execute(['%' . $token . '%']);
    $file = $stmt->fetch();

    if (!$file) {
        die("デバッグ: DBに該当トークンを含むファイルが見つかりませんでした。トークン: " . htmlspecialchars($token));
    }

    // 2. パスの構築
    $full_path = __DIR__ . '/../app/' . $file['file_path'];

    // 3. 存在確認とデバッグ情報の出力
    if (!file_exists($full_path)) {
        die("デバッグ: ファイルが存在しません。<br>探したパス: " . htmlspecialchars($full_path) . "<br>DBのパス: " . htmlspecialchars($file['file_path']));
    }

    // 存在した場合はダウンロード
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
    readfile($full_path);
    exit;
}
?>