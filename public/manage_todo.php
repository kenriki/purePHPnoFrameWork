<?php
// セッションを開始
session_start();

// アクセス元のオリジンを動的に取得して許可する
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . $origin);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// プリフライトリクエスト（OPTIONS）の場合はここで終了
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$path = dirname(__DIR__) . '/app/dbconfig.php';
if (!file_exists($path)) {
    die(json_encode(['success' => false, 'error' => "エラー：ファイルが見つかりません。探している場所: " . $path]));
}
require_once $path;

$pdo = getDB();

// ログインユーザー名を取得（セッションから）
$currentLoginUser = $_SESSION['username'] ?? $_SESSION['user'] ?? $_SESSION['name'] ?? $_SESSION['login_user'] ?? 'guest_user';

// --- GETパラメータでの簡易登録機能 ---
if (isset($_GET['action']) && $_GET['action'] === 'insert') {
    $raw_id = $_GET['id'] ?? ('url-' . time());
    $id = explode('&', $raw_id)[0];

    $raw_content = $_GET['content'] ?? 'テストデータ';
    $content = explode('&', $raw_content)[0];

    // カテゴリが未指定または 'guest' の場合はログインユーザー名を使用
    $raw_category = $_GET['category'] ?? $currentLoginUser;
    $category = (empty($raw_category) || $raw_category === 'guest') ? $currentLoginUser : explode('&', $raw_category)[0];

    $raw_due_date = $_GET['due_date'] ?? null;
    $due_date = $raw_due_date ? explode('&', $raw_due_date)[0] : null;

    $stmt = $pdo->prepare("
        INSERT INTO todo_items (id, content, category, is_completed, due_date) 
        VALUES (?, ?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE 
            content = VALUES(content),
            category = VALUES(category),
            due_date = VALUES(due_date),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $id,
        $content,
        $category,
        $due_date
    ]);
    echo json_encode(['success' => true, 'message' => 'Inserted via GET successfully!']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// JSONデータ、または通常のPOST（$_POST）の両方を受け取れるようにする
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

// データ取得 (GET) - 閲覧制限を追加
if ($method === 'GET') {
    // URLクエリなどで ?user=kenriki のように指定されている場合はそのデータを、
    // 指定がない場合は現在ログインしているユーザーのデータのみに絞り込む
    $targetUser = $_GET['user'] ?? $currentLoginUser;

    $stmt = $pdo->prepare("SELECT * FROM todo_items WHERE category = ? ORDER BY created_at DESC");
    $stmt->execute([$targetUser]);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // フロントエンドに current_user, todos, およびデバッグ用の session_debug を返す
    echo json_encode([
        'current_user' => $currentLoginUser,
        'target_user' => $targetUser,
        'todos' => $todos,
        'session_debug' => $_SESSION
    ]);
} 
// データ更新・削除・追加 (POST)
else if ($method === 'POST') {
    $action = $input['action'] ?? '';

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM todo_items WHERE id = ?");
        $stmt->execute([$input['id']]);
    } else {
        // IDが空の場合は自動採番（UUID風またはプレフィックス付きタイムスタンプ）
        $id = $input['id'] ?? '';
        if (empty($id)) {
            $id = 'todo_' . uniqid() . '_' . mt_rand(100, 999);
        }

        $content = $input['content'] ?? '';
        
        // カテゴリが空または 'guest' の場合はログインユーザー名を強制適用
        $raw_category = $input['category'] ?? $currentLoginUser;
        $category = (empty($raw_category) || $raw_category === 'guest') ? $currentLoginUser : $raw_category;
        
        $is_completed = !empty($input['is_completed']) ? 1 : 0;
        $due_date = !empty($input['due_date']) ? $input['due_date'] : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO todo_items (id, content, category, is_completed, due_date) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                content = VALUES(content), 
                category = VALUES(category), 
                is_completed = VALUES(is_completed),
                due_date = VALUES(due_date),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $id, 
            $content, 
            $category, 
            $is_completed,
            $due_date
        ]);
    }
    
    // リクエスト元に応じてレスポンスを切り替え（通常のHTMLフォーム送信ならダッシュボードへリダイレクト、JSONならJSONを返す）
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!empty($_POST) && !$isAjax) {
        // 通常のフォームからのPOST送信の場合はダッシュボード（home）に戻す
        header("Location: index.php?page=home");
        exit;
    }

    echo json_encode(['success' => true]);
}
?>