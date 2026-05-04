<?php
/**
 * Google OAuth2 コールバック処理
 * 場所: C:\Apache24\htdocs\sample\public\auth-callback.php
 */

// 1. セッションの二重起動防止
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. パス設定
$credentialsPath = __DIR__ . '/credentials.json';
if (!file_exists($credentialsPath)) {
    die("エラー：設定ファイル（credentials.json）が見つかりません。");
}

$config = json_decode(file_get_contents($credentialsPath), true);
$clientId = $config['web']['client_id'];
$clientSecret = $config['web']['client_secret'];

/**
 * 3. リダイレクトURI
 */
$redirectUri = 'https://desktop-mnoqic1.tail7aa158.ts.net/index.php?page=google_callback';

// エラーハンドリング
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    echo "<h2>認証エラー: {$error}</h2>";
    echo "<br><a href='index.php?page=home'>ホームへ戻る</a>";
    exit;
}

if (isset($_GET['code'])) {
    // 4. 認可コードをトークンに交換
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]));

    $responseJson = curl_exec($ch);
    $response = json_decode($responseJson, true);

    if (isset($response['access_token'])) {
        try {
            $accessToken = $response['access_token'];

            // --- Googleからユーザー情報を取得 ---
            $uch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $accessToken);
            curl_setopt($uch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($uch, CURLOPT_RETURNTRANSFER, true);
            $userInfo = json_decode(curl_exec($uch), true);

            $googleRealName = $userInfo['name'] ?? 'unknown';
            $_SESSION['user_display_name'] = $googleRealName;

            // --- 5. DB保存処理 ---
            require_once __DIR__ . '/../app/dbconfig.php';
            $db = getDB();

            /**
             * 【修正ポイント】ログインユーザーをDBから取得
             * セッションにあるID（例: user_id）を元に、最新のログインIDをDBから引きます
             */
            $systemLoginId = null;
            if (isset($_SESSION['user_id'])) {
                $uStmt = $db->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
                $uStmt->execute([':id' => $_SESSION['user_id']]);
                $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow) {
                    $systemLoginId = $userRow['username']; // DB上の正しいID
                }
            }

            // DBから取れなかった場合のバックアップ
            if (!$systemLoginId) {
                $systemLoginId = $_SESSION['user_name'] ?? 'guest';
            }

            $refreshToken = $response['refresh_token'] ?? null;
            $expiresAt = time() + $response['expires_in'];

            // SQLの準備
            $sql = "INSERT INTO google_tokens (login_id, user_name, access_token, refresh_token, expires_at) 
                    VALUES (:login_id, :user_name, :access_token, :refresh_token, :expires_at)
                    ON DUPLICATE KEY UPDATE 
                    user_name = :user_name,
                    access_token = :access_token, 
                    refresh_token = IFNULL(:refresh_token, refresh_token), 
                    expires_at = :expires_at";

            $stmt = $db->prepare($sql);

            // 92行目〜のexecute
            $stmt->execute([
                ':login_id' => $systemLoginId,    // ここにDBから取得したIDが入ります
                ':user_name' => $googleRealName,
                ':access_token' => $accessToken,
                ':refresh_token' => $refreshToken,
                ':expires_at' => $expiresAt
            ]);

            // --- 6. カレンダー同期の疎通確認 (フル) ---
            // トークンが有効か、カレンダーAPIを叩いてテストします
            $cch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary');
            curl_setopt($cch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($cch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cch, CURLOPT_RETURNTRANSFER, true);

            $calendarResponse = curl_exec($cch);
            $calendarData = json_decode($calendarResponse, true);

            // デバッグが必要な場合は、ここで $calendarData をログ出力できます
            // error_log(print_r($calendarData, true));

            // 108行目: リダイレクト
            header("Location: ./index.php?page=home");
            exit;

        } catch (PDOException $e) {
            echo "データベースエラー：" . htmlspecialchars($e->getMessage());
        }
    } else {
        echo "トークンの取得に失敗しました。";
    }
} else {
    echo "認可コードが見つかりません。";
}