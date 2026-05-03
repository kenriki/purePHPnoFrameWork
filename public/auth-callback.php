<?php
/**
 * Google OAuth2 コールバック処理
 * 場所: C:\Apache24\htdocs\test\public\auth-callback.php
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
            $userInfoJson = curl_exec($uch);
            $userInfo = json_decode($userInfoJson, true);

            if (isset($userInfo['name'])) {
                $_SESSION['user_display_name'] = $userInfo['name'];
            }

            // --- 5. DB保存処理 (カラム名を user_name に修正) ---
            require_once __DIR__ . '/../app/dbconfig.php';
            $db = getDB();

            // ユーザー識別子をセット (既存の設計に合わせて user_name カラムを使用)
            $userName = $_SESSION['user_id'] ?? $_SESSION['user_name'] ?? 'guest';
            $refreshToken = $response['refresh_token'] ?? null;
            $expiresAt = time() + $response['expires_in'];

            // SQLのカラム名を user_id から user_name に修正しました
            $sql = "INSERT INTO google_tokens (user_name, access_token, refresh_token, expires_at) 
                    VALUES (:user, :access, :refresh, :expires)
                    ON DUPLICATE KEY UPDATE 
                        access_token = VALUES(access_token),
                        expires_at = VALUES(expires_at)";

            if ($refreshToken) {
                $sql .= ", refresh_token = VALUES(refresh_token)";
            }

            $stmt = $db->prepare($sql);
            $params = [
                ':user' => $userName,
                ':access' => $accessToken,
                ':refresh' => $refreshToken,
                ':expires' => $expiresAt
            ];
            $stmt->execute($params);

            // --- 6. カレンダー同期の疎通確認 ---
            $cch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary');
            curl_setopt($cch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            curl_setopt($cch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cch, CURLOPT_RETURNTRANSFER, true);
            $calendarInfo = json_decode(curl_exec($cch), true);

            echo "<h2>Google連携に成功しました！</h2>";
            echo "<p>ようこそ、" . htmlspecialchars($_SESSION['user_display_name'] ?? 'ユーザー') . " さん。</p>";
            echo "<p>同期先: " . htmlspecialchars($calendarInfo['summary'] ?? 'メインカレンダー') . "</p>";
            echo "<br><a href='index.php?page=home'>ダッシュボードへ戻る</a>";

        } catch (PDOException $e) {
            echo "データベース保存エラーが発生しました。：" . htmlspecialchars($e->getMessage());
        }
    } else {
        echo "トークンの取得に失敗しました。";
    }
} else {
    echo "認可コードが見つかりません。";
}