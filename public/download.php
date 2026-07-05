<?php
header('Referrer-Policy: no-referrer');
session_start();
require_once __DIR__ . '/../app/dbconfig.php';
$pdo = getDB();

// URLからトークンを取得（&t=... が付いていても良いように処理）
if (isset($_GET['token'])) {
    // もしtokenに '&' が含まれていたら、最初の部分だけを切り出す
    $raw_token = $_GET['token'];
    $token = explode('&', $raw_token)[0]; 

    // SQL検索（前回修正した、ゲスト公開と通常ファイルの判定ロジックを維持）
    $stmt = $pdo->prepare("
        SELECT * FROM file_uploads 
        WHERE file_path LIKE ? 
        AND (is_public = 0 OR (is_public = 1 AND expires_at > NOW()))
    ");
    // $token を部分一致で検索
    $stmt->execute(['%' . $token . '%']);
    $file = $stmt->fetch();

    if (!$file) {
        die("ファイルが見つからないか、期限が切れています。");
    }

    $full_path = __DIR__ . '/../app/' . $file['file_path'];

    if (file_exists($full_path)) {
        // キャッシュ対策と強制ダウンロードヘッダー
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
        header('Content-Length: ' . filesize($full_path));
        header('Pragma: no-cache'); // スマホ対策としてキャッシュさせない
        
        readfile($full_path);
        exit;
    } else {
        die("ファイルの実体が見つかりません。");
    }
}
?>