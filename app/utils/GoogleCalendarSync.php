<?php

namespace app\utils;

class GoogleCalendarSync
{
    private $clientId;
    private $clientSecret;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        // credentials.json の読み込み（パスは環境に合わせて調整してください）
        $configPath = __DIR__ . '/../../credentials.json';
        if (!file_exists($configPath)) {
            error_log("Google Config Error: credentials.json not found");
            return;
        }
        $config = json_decode(file_get_contents($configPath), true);
        $this->clientId = $config['web']['client_id'];
        $this->clientSecret = $config['web']['client_secret'];
    }

    /**
     * 有効なトークンを取得（切れていればリフレッシュ）
     */
    private function getAccessToken($userName)
    {
        // $stmt = $this->db->prepare("SELECT * FROM google_tokens WHERE user_name = ?");
        // login_id で検索するように変更
        $stmt = $this->db->prepare("SELECT * FROM google_tokens WHERE login_id = ?");
        $stmt->execute([$userName]);
        $token = $stmt->fetch();

        if (!$token)
            return false;

        // 有効期限切れチェック（現在時刻 + 余裕5秒）
        if ($token['expires_at'] <= (time() + 5)) {
            return $this->refresh($userName, $token['refresh_token']);
        }

        return $token['access_token'];
    }

    /**
     * 指定した日付のイベントを取得する（MemoControllerからの呼び出し用）
     */
    public function getEvents($username, $timeMin, $timeMax)
    {
        $accessToken = $this->getAccessToken($username);
        if (!$accessToken)
            return [];

        // 指定日の開始(00:00:00)と終了(23:59:59)をRFC3339形式で作成
        $timeMin = urlencode($timeMin . 'T00:00:00Z');
        $timeMax = urlencode($timeMax . 'T23:59:59Z');

        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events?timeMin={$timeMin}&timeMax={$timeMax}&singleEvents=true&orderBy=startTime";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            error_log("Google API Error: HTTP $httpCode");
            return [];
        }

        $data = json_decode($result, true);
        if (!isset($data['items']))
            return [];

        $events = [];
        foreach ($data['items'] as $item) {
            $events[] = [
                'id' => $item['id'],
                'summary' => $item['summary'] ?? '(無題)',
                'start' => $item['start']['dateTime'] ?? $item['start']['date'],
                'end' => $item['end']['dateTime'] ?? $item['end']['date']
            ];
        }
        return $events;
    }

    /**
     * トークンのリフレッシュ処理
     */
    private function refresh($userName, $refreshToken)
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ]));
        $response = json_decode(curl_exec($ch), true);

        if (!isset($response['access_token'])) {
            error_log("Google Token Refresh Failed for $userName");
            return false;
        }

        $newAccess = $response['access_token'];
        $expiresAt = time() + $response['expires_in'];

        $stmt = $this->db->prepare("UPDATE google_tokens SET access_token = ?, expires_at = ? WHERE user_name = ?");
        $stmt->execute([$newAccess, $expiresAt, $userName]);

        return $newAccess;
    }

    /**
     * カレンダーへイベント挿入（書き込み）
     */
    public function sync($userName, $summary, $description, $date)
    {
        $accessToken = $this->getAccessToken($userName);
        if (!$accessToken)
            return false;

        $event = [
            'summary' => $summary,
            'description' => $description,
            'start' => ['date' => $date],
            'end' => ['date' => date('Y-m-d', strtotime($date . ' +1 day'))],
        ];

        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            error_log('Google Sync cURL Error: ' . curl_error($ch));
        }

        return json_decode($result, true);
    }

    /**
     * Googleカレンダーの予定を取得してFullCalendar形式で返す（読み込み）
     */
    /**
     * Googleカレンダーの予定を取得してFullCalendar形式で返す
     * 
     * @param string $userName 取得対象のユーザー名
     * @param string $start 取得開始日 (YYYY-MM-DD)
     * @param string $end 取得終了日 (YYYY-MM-DD)
     * @return array FullCalendar用のイベント配列
     */
    public function getEventsForFullCalendar($userName, $start, $end)
    {
        // 1. 指定されたユーザーのアクセストークンを取得
        $accessToken = $this->getAccessToken($userName);
        if (!$accessToken) {
            error_log("GoogleCalendarSync: Access token not found for user: {$userName}");
            return [];
        }

        // 2. Google API用の日付形式 (RFC3339) に整形
        // FullCalendarから渡される $start, $end をタイムゾーン付きの形式に変換
        $timeMin = urlencode($start . 'T00:00:00Z');
        $timeMax = urlencode($end . 'T23:59:59Z');

        // 3. Google Calendar API エンドポイント構築
        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events?" . http_build_query([
            'timeMin' => $start . 'T00:00:00Z',
            'timeMax' => $end . 'T23:59:59Z',
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]);

        // 4. cURLによるリクエスト実行
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書エラー対策
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $result = curl_exec($ch);

        // 5. 通信エラーのチェック
        if ($result === false) {
            $error = curl_error($ch);
            error_log("Google API cURL Error: {$error}");
            return [];
        }

        // 6. レスポンスの解析
        $data = json_decode($result, true);

        // デバッグ用ログ：取得データを確認したい場合に有効化してください
        // error_log('Google API Response for ' . $userName . ': ' . print_r($data, true));

        if (!isset($data['items']) || !is_array($data['items'])) {
            error_log("Google API Error: 'items' not found in response. " . ($data['error']['message'] ?? 'Unknown error'));
            return [];
        }

        // 7. FullCalendar形式へのマッピング処理
        $events = [];
        foreach ($data['items'] as $item) {
            // 開始日時の取得（時間指定があれば dateTime、終日なら date）
            $eventStart = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
            $eventEnd = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;

            if (!$eventStart)
                continue;

            $events[] = [
                'id' => $item['id'],
                'title' => $item['summary'] ?? '(無題)', // Googleの 'summary' をマッピング
                'start' => $eventStart,
                'end' => $eventEnd,
                'url' => $item['htmlLink'] ?? '#',
                'description' => $item['description'] ?? '', // メモの内容など
                'color' => '#4285f4', // Googleカレンダー風のブルー
                'allDay' => isset($item['start']['date']) // 'date' のみの場合は終日予定として扱う
            ];
        }

        return $events;
    }
    /**
     * Googleカレンダーに予定を追加する
     */
    public function insertEvent($userName, $summary, $startDate, $endDate = null)
    {
        $accessToken = $this->getAccessToken($userName);
        if (!$accessToken)
            return false;

        // 終了時間が指定されていない場合は開始の1時間後に設定
        if (!$endDate) {
            $endDate = date('Y-m-d\TH:i:s\Z', strtotime($startDate . ' +1 hour'));
        }

        $url = "https://www.googleapis.com/calendar/v3/calendars/primary/events";

        $postData = [
            'summary' => $summary,
            'start' => ['dateTime' => $startDate], // 書式: 2026-05-21T10:00:00Z
            'end' => ['dateTime' => $endDate],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return ($httpCode === 200 || $httpCode === 201);
    }
}