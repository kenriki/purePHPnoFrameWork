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
    // $stmt = $pdo->prepare("
    //     SELECT * FROM file_uploads 
    //     WHERE file_path LIKE ? 
    //     AND (is_public = 0 OR (is_public = 1 AND expires_at > NOW()))
    // ");
    // // $token を部分一致で検索
    // $stmt->execute(['%' . $token . '%']);
    // $file = $stmt->fetch();

    $current_user = $_SESSION['user_id'] ?? null;

    // SQL検索
    // 1. 全体公開(is_public=1)かつ有効期限内（未ログインでも可能）
    // 2. 限定公開(is_public=0)で「ログイン済み」かつ「自分が投稿者(user_id)」または「allowed_usersに含まれる」
    $stmt = $pdo->prepare("
        SELECT * FROM file_uploads 
        WHERE file_path LIKE ? 
        AND (
            (is_public = 1 AND expires_at > NOW())
            OR 
            (
                is_public = 0 
                AND ? IS NOT NULL 
                AND (
                    user_id = ? 
                    OR FIND_IN_SET(?, allowed_users) 
                    OR allowed_users LIKE ?
                )
            )
        )
    ");
    
    // パラメータ割り当て (トークン, ログイン判定用, 投稿者判定用, 共有先判定用×2)
    $stmt->execute([
        '%' . $token . '%', 
        $current_user,
        $current_user, 
        $current_user, 
        '%' . $current_user . '%'
    ]);
    $file = $stmt->fetch();

    if (!$file) {
        die("ファイルが見つからないか、アクセス権限がない、または期限が切れています。");
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