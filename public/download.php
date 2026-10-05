<?php
header('Referrer-Policy: no-referrer');
session_start();
require_once __DIR__ . '/../app/dbconfig.php';
$pdo = getDB();

if (isset($_GET['token'])) {
    $raw_token = $_GET['token'];
    $token = explode('&', $raw_token)[0]; 

    $current_user = $_SESSION['user_id'] ?? null;

    // ファイルを検索（トークン部分一致）
    $stmt = $pdo->prepare("SELECT * FROM file_uploads WHERE file_path LIKE ?");
    $stmt->execute(['%' . $token . '%']);
    $file = $stmt->fetch();

    if (!$file) {
        die("ファイルが見つからないか、期限が切れています。");
    }

    $is_allowed = false;

    // 1. 24H期限付き(is_public = 1)かつ期限内の場合、誰でもダウンロード可能
    if ($file['is_public'] == 1) {
        if (!empty($file['expires_at']) && strtotime($file['expires_at']) > time()) {
            $is_allowed = true;
        }
    } 
    // 2. ログインしている場合
    else if ($current_user !== null) {
        // 自分がアップロードしたファイル、または allowed_user_ids に含まれている場合
        if ($file['user_id'] == $current_user) {
            $is_allowed = true;
        } else if (!empty($file['allowed_user_ids'])) {
            // カンマ区切りのIDリストを配列に分解してチェック
            $allowed_ids = array_map('trim', explode(',', $file['allowed_user_ids']));
            if (in_array((string)$current_user, $allowed_ids, true)) {
                $is_allowed = true;
            }
        }
    }

    if (!$is_allowed) {
        die("ファイルが見つからないか、アクセス権限がない、または期限が切れています。");
    }

    // 物理パスの結合（アップロード側のパス定義に合わせる）
    $base_dir = __DIR__ . '/../app/';
    $full_path = $base_dir . $file['file_path'];

    if (file_exists($full_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($full_path));
        
        readfile($full_path);
        exit;
    } else {
        die("ファイルの実体が見つかりません。");
    }
} else {
    die("不正なリクエストです。");
}
?>