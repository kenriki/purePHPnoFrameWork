<?php
// バッファリングで余計な出力を防ぐ
ob_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../dbconfig.php';
ob_end_clean();

// ヘッダーをJSONに設定（JS側で受け取りやすくするため）
header('Content-Type: application/json');

// パラメータ取得
$uid = isset($_GET['uid']) ? $_GET['uid'] : null;

if (!$uid) {
    echo json_encode(['error' => 'UID is missing']);
    exit;
}

try {
    // 最新の1件を取得（バッテリー情報も含む）
    $sql = "SELECT lat, lng, u_name, battery_level as battery, 
                   DATE_FORMAT(created_at, '%H:%i') as time 
            FROM Log_table 
            WHERE uid = :uid 
            ORDER BY created_at DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'No data found']);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}