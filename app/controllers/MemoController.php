<?php
// MemoController.php の上部などで
require_once __DIR__ . '/../utils/GoogleCalendarSync.php';
use app\utils\GoogleCalendarSync;
/**
 * MemoController - 高機能メモ管理コントローラ
 * * 主な機能:
 * 1. AES-256-CBC によるコンテンツの暗号化・復号（平文互換）
 * 2. ユーザー別ディレクトリ管理と物理ファイル同期
 * 3. 画像アップロード・WebP変換・ストレージ使用量管理
 * 4. ランダム英数字IDによる競合防止
 * 5. 24時間限定共有URL発行
 * 6. tFPDFによる日本語PDFエクスポート
 * 7. ダッシュボード（カレンダー・ピン留め・グラフ）連携
 */
class MemoController
{
    private $baseDir;
    public $user;
    private $cipher_method = 'aes-256-cbc';
    private $cipher_key;
    private $max_storage = 536870912; // 512MB
    public $safeDirName;


    // ブラウザから画像にアクセスするためのベースURL（環境に合わせて調整してください）
    public $publicImageBaseUrl = "/sample/app/data/user_memos";

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->cipher_key = getenv('CIPHER_KEY') ?? 'default-key';

        // ログインユーザーの特定
        $this->user = $_SESSION['user'] ?? $_SESSION['username'] ?? 'guest';

        // 物理保存ディレクトリの決定と作成
        $this->baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/" . $this->user . "/";

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    /**
     * コンテンツの復号（暗号・平文を自動判別）
     * image_aa4e6b.png の復号エラー対策
     */
    public function decryptContent($data)
    {
        if (empty($data))
            return "";

        // 1. Base64デコードを試みる
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return $data; // Base64ですらなければ旧来の平文
        }

        // 2. IV（初期化ベクトル）の長さを確認
        $ivLength = openssl_cipher_iv_length($this->cipher_method);
        if (strlen($decoded) <= $ivLength) {
            return $data; // 長さが足りなければ平文（または破損）
        }

        // 3. 復号処理
        $iv = substr($decoded, 0, $ivLength);
        $encryptedRaw = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($encryptedRaw, $this->cipher_method, $this->cipher_key, 0, $iv);

        // 復号に失敗した場合は平文として返す（移行期セーフティ）
        return ($decrypted === false) ? $data : $decrypted;
    }

    /**
     * メインのリクエストハンドラ
     */
    public function handleRequest()
    {
        $action = $_GET['action'] ?? 'list';
        $id = $_GET['id'] ?? null;
        $target_date = $_GET['date'] ?? null;

        // --- アクション分岐 ---

        // 24時間共有URL発行処理
        if ($action === 'generate_share_url') {
            $this->generate_share_url();
            return;
        }

        if ($action === 'view_share') {
            $this->view_share();
            return;
        }

        // ピン留め
        if ($action === 'toggle_pin') {
            $this->togglePin();
            return;
        }

        if ($action === 'toggle_pin_from_edit') {
            if (!empty($id)) {
                // DBの状態を反転させる
                $this->executeTogglePin($id);

                // 元の編集画面へリダイレクトして、URLを書き換える
                header("Location: index.php?page=memo&action=edit&id=" . urlencode($id));
                exit; // リダイレクト後は即座に終了
            }
        }

        // ゲストユーザーの同期
        if ($action === 'list') {
            $this->syncGuestMemos();
        }

        // 画像削除
        if ($action === 'delete_image') {
            $this->deleteImageOnly();
            return;
        }

        // PDF出力 (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_export'])) {
            // フォームから画像パスとユーザー名も受け取るようにします
            $this->generatePdf(
                $_POST['content'] ?? '',
                $_POST['guest_name'] ?? '',
                $_POST['image_path'] ?? '', // 追加
                $this->user                 // 追加（現在のログインユーザー）
            );
            return;
        }

        // 保存・削除 (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $memo_id = $_POST['id'] ?? null;

            if (isset($_POST['delete'])) {
                $this->deleteMemo($memo_id);
                $this->redirect("list", "deleted");
            }

            $event_date = $_POST['event_date'] ?? null;

            $this->saveMemo($memo_id, $content, $event_date);
            $this->redirect("list", "saved");
        }

        // 表示用データの構築 (GET)
        $memos = ($action === 'list') ? $this->getMemoList($target_date) : [];
        $content = "";
        $memo = null;

        if ($id) {
            $db = getDB();

            // 1. 閲覧を許可するユーザー名のリストを動的に作成
            // ログイン中の本人、または現在のセッションで名乗っているゲスト名、共通ゲストを許可
            $allowedUsers = [$this->user];
            if (!empty($_SESSION['guest_name'])) {
                $allowedUsers[] = 'guest_' . $_SESSION['guest_name'];
            }
            $allowedUsers[] = 'guest';

            // 重複を排除（念のため）
            $allowedUsers = array_unique($allowedUsers);

            // 2. IN句用のプレースホルダー (?) を作成
            $placeholders = implode(',', array_fill(0, count($allowedUsers), '?'));

            // 3. SQL実行：IDが一致し、かつ所有者が許可リストに含まれる場合のみ取得
            // これにより、他人のID（例：はやとさんの 000060）をURLに直打ちしてもヒットしなくなります
            $sql = "SELECT * FROM user_memos WHERE id = ? AND username IN ($placeholders)";

            $params = array_merge([$id], $allowedUsers);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $memo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($memo) {
                // 復号処理（暗号化データと旧来の平文を自動判別）
                $content = $this->decryptContent($memo['content']);
            } else {
                // IDが存在しても、自分に関連するデータでなければアクセス拒否
                // 管理者であっても、この個別画面では「自分のもの」以外は見せない設計に
                $this->redirect("list", "error_permission_denied");
                return;
            }
        }
        //var_dump($memos);

        return [
            'action' => $action,
            'id' => $id,
            'memoList' => $memos,
            'memos' => $memos,
            'memo' => $memo,
            'content' => $content,
            'user' => $this->user,
            'currentUserUsage' => $this->getUserStorageUsage($this->user),
            'target_date' => $target_date,
            'message' => $_GET['message'] ?? ""
        ];
    }

    /**
     * メモ一覧の取得（復号・タイトル抽出処理含む）
     */
    // private function getMemoList($target_date = null)
    // {
    //     $db = getDB();
    //     $params = [];
    //     $sql = "SELECT id, username, content, is_pinned, image_path,
    //                    DATE_FORMAT(create_date, '%Y-%m-%d %H:%i') as time 
    //             FROM user_memos WHERE ";

    //     // ユーザー条件
    //     if ($this->user !== 'guest' && !empty($this->user)) {
    //         $sql .= "username = :username";
    //         $params[':username'] = $this->user;
    //     } else {
    //         $guestSig = 'guest_' . ($_SESSION['guest_name'] ?? '');
    //         $sql .= "username = " . (($_SESSION['guest_name'] ?? '') ? ":guest_sig" : "'guest'");
    //         if ($_SESSION['guest_name'] ?? '')
    //             $params[':guest_sig'] = $guestSig;
    //     }

    //     // カレンダー日付条件
    //     if ($target_date) {
    //         $sql .= " AND DATE(create_date) = :target_date";
    //         $params[':target_date'] = $target_date;
    //     }

    //     $sql .= " ORDER BY is_pinned DESC, update_date DESC";
    //     $stmt = $db->prepare($sql);
    //     $stmt->execute($params);
    //     $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //     foreach ($rows as &$row) {
    //         // 1. コンテンツの復号
    //         $decrypted = $this->decryptContent($row['content'] ?? "");
    //         // 2. タイトル抽出ロジック
    //         $firstLine = trim(explode("\n", str_replace(["\r\n", "\r"], "\n", $decrypted))[0] ?? "");

    //         $displayTitle = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "無題のメモ #" . $row['id'];
    //         // 3. サフィックス（ゲスト表示用）の定義
    //         $suffix = "";
    //         if (strpos($row['username'], 'guest_') === 0) {
    //             $suffix = " <span class='guest-label'>(" . htmlspecialchars(substr($row['username'], 6)) . ")</span>";
    //         }
    //         $row['display_title_html'] = htmlspecialchars($displayTitle) . $suffix;
    //     }
    //     return $rows;
    // }
    private function getMemoList($target_date = null)
    {
        $db = getDB();
        $params = [];

        // 1. 自前DBからメモを取得
        // 表示用の時間は create_date (5/3など) を使い、
        // フィルタやソートは event_date (11/5など) を基準にする
        $sql = "SELECT id, username, content, is_pinned, image_path, event_date,
                   DATE_FORMAT(create_date, '%Y-%m-%d %H:%i') as time 
            FROM user_memos WHERE ";

        // ユーザー条件
        if ($this->user !== 'guest' && !empty($this->user)) {
            $sql .= "username = :username";
            $params[':username'] = $this->user;
        } else {
            $guestSig = 'guest_' . ($_SESSION['guest_name'] ?? '');
            $sql .= "username = " . (($_SESSION['guest_name'] ?? '') ? ":guest_sig" : "'guest'");
            if ($_SESSION['guest_name'] ?? '')
                $params[':guest_sig'] = $guestSig;
        }

        // カレンダー日付条件（event_dateと比較）
        if ($target_date) {
            $sql .= " AND DATE(event_date) = :target_date";
            $params[':target_date'] = $target_date;
        }

        $sql .= " ORDER BY is_pinned DESC, event_date DESC, create_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. 自前データの復号と整形
        foreach ($rows as &$row) {
            $decrypted = $this->decryptContent($row['content'] ?? "");
            $firstLine = trim(explode("\n", str_replace(["\r\n", "\r"], "\n", $decrypted))[0] ?? "");
            $displayTitle = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "無題のメモ #" . $row['id'];

            $suffix = "";
            if (strpos($row['username'], 'guest_') === 0) {
                $suffix = " <span class='guest-label'>(" . htmlspecialchars(substr($row['username'], 6)) . ")</span>";
            }
            $row['display_title_html'] = htmlspecialchars($displayTitle) . $suffix;
            $row['source'] = 'local'; // データ元を識別
        }

        // 3. Google カレンダーからのイベント取得（同期）
        // MemoController.php
        if ($target_date) {
            try {
                $sync = new GoogleCalendarSync($db); // コンストラクタに合わせて調整

                // 取得範囲を「指定された日の属する月」の全期間に設定
                $timeMin = date('Y-m-01T00:00:00Z', strtotime($target_date));
                $timeMax = date('Y-m-tT23:59:59Z', strtotime($target_date));

                // 引数を3つ渡す（$username, $timeMin, $timeMax）
                $googleEvents = $sync->getEvents($this->user, $timeMin, $timeMax);

                if ($googleEvents) {
                    foreach ($googleEvents as $gEvent) {
                        // イベントの開始日を取得（全日予定なら 'date'、時間指定なら 'dateTime'）
                        $eventDate = !empty($gEvent['start']['date'])
                            ? $gEvent['start']['date']
                            : date('Y-m-d', strtotime($gEvent['start']['dateTime']));

                        $rows[] = [
                            'id' => 'google_' . $gEvent['id'],
                            'username' => $this->user,
                            'display_title_html' => "🗓️ <span style='color:#4285f4;'>" . htmlspecialchars($gEvent['summary']) . "</span>",
                            'event_date' => $eventDate, // ループ内の各イベント本来の日付を入れる
                            'time' => 'Google同期済み',
                            'is_pinned' => 0,
                            'source' => 'google'
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Google Calendar Fetch Failed: " . $e->getMessage());
            }
        }

        return $rows;
    }

    /**
     * メモの新規保存・更新
     */
    private function saveMemo($id, $content, $event_date = null)
    {
        $db = getDB();
        $isNew = empty($id);
        if ($isNew) {
            $id = $this->generateNextId();
        }

        $saveUser = $this->user;
        if ($saveUser === 'guest' && !empty($_POST['guest_name'])) {
            $_SESSION['guest_name'] = $_POST['guest_name'];
            $saveUser = 'guest_' . $_POST['guest_name'];
        }

        // 未来日が指定されている場合はその日付を使用し、なければ現在時刻
        if (empty($event_date)) {
            $event_date = date('Y-m-d H:i:s');
        } else if (strlen($event_date) === 10) {
            $event_date .= ' ' . date('H:i:s');
        }

        // --- 画像アップロード処理 ---
        $imagePath = null;
        if (!empty($_FILES['memo_image']['tmp_name'])) {
            $imagePath = $this->uploadImage($_FILES['memo_image']);
        }

        // --- 暗号化 ---
        $ivLength = openssl_cipher_iv_length($this->cipher_method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($content, $this->cipher_method, $this->cipher_key, 0, $iv);
        $saveData = base64_encode($iv . $encrypted);

        // 物理ファイル同期（テキスト）
        file_put_contents($this->baseDir . $id . ".txt", $saveData);

        // --- DB更新処理 ---
        try {
            if ($isNew) {
                // 新規作成時
                $sql = "INSERT INTO user_memos (
                id, 
                username, 
                content, 
                image_path, 
                event_date, 
                create_date, 
                update_date
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

                $stmt = $db->prepare($sql);
                $success = $stmt->execute([$id, $saveUser, $saveData, $imagePath, $event_date]);
            } else {
                // 更新時
                $sql = "UPDATE user_memos SET 
                    content = ?, 
                    event_date = ?, 
                    update_date = NOW()";
                $params = [$saveData, $event_date];

                if ($imagePath) {
                    $sql .= ", image_path = ?";
                    $params[] = $imagePath;
                }

                $sql .= " WHERE id = ? AND username = ?";
                $params[] = $id;
                $params[] = $saveUser;

                $success = $db->prepare($sql)->execute($params);
            }

            // --- Googleカレンダー同期 (DB保存が成功した場合のみ実行) ---
            if ($success) {
                // 外部ファイルやクラスとして定義されている前提
                try {
                    // $this->db は getDB() で取得したインスタンスを使用
                    $sync = new GoogleCalendarSync($db);

                    // カレンダーに表示するタイトル（冒頭10文字）
                    $title = "★" . mb_substr($content, 0, 10);

                    // 同期実行 (Google側の形式に合わせて日付部分のみ抽出)
                    $targetDate = substr($event_date, 0, 10);
                    $sync->sync($saveUser, $title, $content, $targetDate);

                } catch (Exception $e) {
                    // カレンダー同期に失敗しても、メモ保存自体は完了しているため
                    // エラーをログに吐いて処理を続行（ユーザーにはメモ保存完了を返す）
                    error_log("Google Calendar Sync Failed: " . $e->getMessage());
                }
            }

            return $success;

        } catch (PDOException $e) {
            error_log("DB Save Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * メモの物理・論理削除
     */
    private function deleteMemo($id)
    {
        if (!$id)
            return;
        $db = getDB();

        // handleRequest と同じ「許可リスト」を作成
        $allowedUsers = [$this->user];
        if (!empty($_SESSION['guest_name'])) {
            $allowedUsers[] = 'guest_' . $_SESSION['guest_name'];
        }
        $allowedUsers[] = 'guest';

        $placeholders = implode(',', array_fill(0, count($allowedUsers), '?'));

        // 自分のメモ、または自分が名乗っているゲスト名のメモだけを消せるようにする
        $sql = "DELETE FROM user_memos WHERE id = ? AND username IN ($placeholders)";

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$id], $allowedUsers));

        if ($stmt->rowCount() > 0) {
            $path = $this->baseDir . $id . ".txt";
            if (file_exists($path))
                unlink($path);
        }
    }

    /**
     * ダッシュボード用データ取得
     * 活動ログを毎週月曜日0時にリセットする仕様に変更
     */
    public function getDashboardData($username)
    {
        $db = getDB();

        // 1. カレンダー (作成日基準)
        //$stmt = $db->prepare("SELECT id, content, DATE(create_date) as start FROM user_memos WHERE username = ?");
        $stmt = $db->prepare("SELECT id, content, DATE(event_date) as start FROM user_memos WHERE username = ?");
        $stmt->execute([$username]);
        $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $decrypted = $this->decryptContent($row['content']);
            $events[] = [
                'id' => $row['id'],
                'start' => $row['start'],
                'title' => mb_strimwidth(explode("\n", trim($decrypted))[0], 0, 30, "..."),
                'url' => "index.php?page=memo&action=edit&id=" . $row['id']
            ];
        }

        // Google カレンダーからのイベント取得とマージ 
        try {
            // 1. 表示範囲（今月いっぱい）を指定
            // $start = date('Y-m-01');
            // $end = date('Y-m-t');
            // 【修正ポイント】リクエストパラメータから期間を取得し、なければ今月分にする
            //$start = $_GET['start'] ?? date('Y-m-01');
            //$end = $_GET['end'] ?? date('Y-m-t');

            // もし「去年まで遡って一気に取得したい」場合は以下のように固定も可能
            $start = date('Y-01-01', strtotime('-1 year'));
            $end   = date('Y-12-31', strtotime('+1 year'));

            // 2. GoogleCalendarSyncのインスタンス化とデータ取得
            $sync = new GoogleCalendarSync($db);
            // $username は "kenmochi" であることを確認済み
            $googleEvents = $sync->getEventsForFullCalendar($username, $start, $end);

            // デバッグログ: 何件取得できたかを確認
            error_log("Google Events Found: " . (is_array($googleEvents) ? count($googleEvents) : 0));

            if (!empty($googleEvents) && is_array($googleEvents)) {
                foreach ($googleEvents as $gEvent) {
                    // FullCalendarが認識できる形式で $events 配列に追加
                    $events[] = [
                        'id' => $gEvent['id'] ?? uniqid('google_'),
                        'title' => $gEvent['title'] ?? '(予定あり)', // ここが空だと表示されません
                        'start' => $gEvent['start'],
                        'end' => $gEvent['end'],
                        'color' => $gEvent['color'] ?? '#4285f4', // デフォルトはGoogle Blue
                        'url' => $gEvent['url'] ?? '#',
                        'extendedProps' => [
                            'source' => 'google'
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("Dashboard Google Sync Error: " . $e->getMessage());
        }

        // 2. ピン留め
        $stmt = $db->prepare("SELECT id, content, update_date FROM user_memos WHERE username = ? AND is_pinned = 1 ORDER BY update_date DESC");
        $stmt->execute([$username]);
        $pinned = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $decrypted = $this->decryptContent($p['content']);
            $pinned[] = [
                'id' => $p['id'],
                'update_date' => $p['update_date'],
                'title' => mb_strimwidth(explode("\n", trim($decrypted))[0], 0, 50, "..."),
                'url' => "index.php?page=memo&action=edit&id=" . $p['id']
            ];
        }

        // 3. 活動ログ (月曜リセット仕様)
        // 「直近の月曜日 00:00:00」を取得（今日が月曜なら今日の0時、それ以外なら前の月曜）
        $startOfWeek = date('Y-m-d 00:00:00', strtotime('last monday', strtotime('tomorrow')));

        $stmt = $db->prepare("SELECT DATE(update_date) as date, COUNT(*) as count 
                               FROM user_memos 
                               WHERE username = ? 
                               AND update_date >= ? 
                               GROUP BY date 
                               ORDER BY date ASC");
        $stmt->execute([$username, $startOfWeek]);

        return [
            'events' => $events,
            'pinned' => $pinned,
            'chart' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * 新しいIDを生成（既存の数値IDと衝突しないランダム英数字）
     */
    private function generateNextId()
    {
        // 8文字のランダムな英数字を生成（例: 5f3a2b1c）
        // 32ビット分（約42億通り）のランダム性があるので、100人規模でも衝突はまず起きません
        return bin2hex(random_bytes(4));
    }

    /**
     * ピン留め状態の反転
     */
    public function togglePin()
    {
        $db = getDB();
        $id = $_GET['id'] ?? '';
        $stmt = $db->prepare("SELECT is_pinned FROM user_memos WHERE id = ?");
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            $newStatus = $row['is_pinned'] ? 0 : 1;
            // update_dateを更新せずにピン状態だけ変える
            $db->prepare("UPDATE user_memos SET is_pinned = ?, update_date = update_date WHERE id = ?")->execute([$newStatus, $id]);
        }
        $this->redirect("list", "pinned");
    }

    /**
     * ゲストメモの一括紐付け
     */
    private function syncGuestMemos()
    {
        $guestName = $_SESSION['guest_name'] ?? '';
        if ($this->user === 'guest' && !empty($guestName)) {
            $db = getDB();
            $target = 'guest_' . $guestName;
            $db->prepare("UPDATE user_memos SET username = ? WHERE username = 'guest'")->execute([$target]);
        }
    }

    /**
     * tFPDFによるPDF生成
     */
    private function generatePdf($content, $guestName = '', $imagePath = '', $username = '')
    {
        ini_set('memory_limit', '256M');
        // 追加：これまでのWarning出力をすべて消し去る
        if (ob_get_length())
            ob_clean();

        while (ob_get_level())
            ob_end_clean();

        require_once 'C:\\Apache24\\htdocs\\sample\\public\\tfpdf.php';

        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('NotoSansJP', '', 'NotoSansJP-VariableFont_wght.ttf', true);
        $pdf->SetFont('NotoSansJP', '', 10);

        // ヘッダー
        $header = "メモ エクスポート (" . date('Y-m-d H:i') . ")";
        //$pdf->Cell(0, 10, $header, 'B', 1);
        $pdf->Cell(0, 10, $header, 'B', 1, 'L'); // 'L'を明示し、1で改行
        $pdf->Ln(5);

        // 本文出力
        $pdf->MultiCell(0, 6, $content . ($guestName ? "\n\n---\n署名: $guestName" : ""));

        if (!empty($imagePath)) {
            $owner = $username ?: 'guest';
            $safeFolder = preg_match('/^[a-zA-Z0-9\._-]+$/', $owner) ? $owner : 'u_' . substr(md5($owner), 0, 12);

            // パスの組み立て（\ と / が混在しないよう調整）
            $baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/{$safeFolder}/images/";
            //$originalPath = $baseDir . $imagePath;
            $originalPath = $this->baseDir . "images/" . $imagePath;

            if (file_exists($originalPath)) {
                $tempPng = $baseDir . "temp_" . time() . ".png";
                $imgInfo = getimagesize($originalPath);
                $img = null;

                // GDライブラリによる変換
                switch ($imgInfo[2]) {
                    case IMAGETYPE_WEBP:
                        $img = @imagecreatefromwebp($originalPath);
                        break;
                    case IMAGETYPE_JPEG:
                        $img = @imagecreatefromjpeg($originalPath);
                        break;
                    case IMAGETYPE_PNG:
                        $img = @imagecreatefrompng($originalPath);
                        break;
                }

                if ($img) {
                    imagepng($img, $tempPng);
                    // imagedestroy($img); // PHP 8.5では非推奨のため削除またはコメントアウト

                    // 改ページ制御
                    $pdf->Ln(10); // 画像の上の余白
                    $imgWidth = 100; // 出力サイズ
                    $imgHeight = 0;   // 0にするとアスペクト比を維持して自動計算されますが、判定用に仮の値を想定

                    // 貼り付けたい画像の高さ（ここでは100mm程度と仮定）が、ページの残り（PageHeight - 下部余白 - 現在位置）より大きいか
                    $remainingHeight = $pdf->getPageHeight() - 20; // 20は下部マージン
                    if ($pdf->GetY() + 100 > $remainingHeight) {
                        $pdf->AddPage();
                    }

                    // 画像の埋め込み。第5引数に 'PNG' を明示
                    $pdf->Image($tempPng, $pdf->GetX() + 5, $pdf->GetY(), 100, 0, 'PNG');

                    // 削除フラグ（出力後に削除するため）
                    $tempFileToDelete = $tempPng;
                } else {
                    $pdf->Ln(5);
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 10, "Error: Failed to process image content.");
                    $pdf->SetTextColor(0, 0, 0);
                }
            } else {
                // デバッグ用：ファイルが見つからない場合にパスを表示
                $pdf->Ln(5);
                $pdf->SetTextColor(200, 0, 0);
                //$pdf->Cell(0, 10, "Debug: File not found at " . $originalPath);
                $pdf->Cell(100, 6, "（添付画像は表示できませんでした）", 0, 0, 'C');
                $pdf->SetTextColor(0, 0, 0);
            }
        }

        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=memo_" . date('YmdHis') . ".pdf");

        // PDFデータの生成
        $pdfData = $pdf->Output('S');

        // 出力前に、もし何か（警告など）が出てしまっていたらクリアする
        if (ob_get_length())
            ob_clean();

        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=memo_" . date('YmdHis') . ".pdf");
        header('Content-Length: ' . strlen($pdfData)); // ファイルサイズを指定するとより確実です

        echo $pdfData;

        // 出力後に一時ファイルを削除
        if (isset($tempFileToDelete) && file_exists($tempFileToDelete)) {
            unlink($tempFileToDelete);
        }
        exit;
    }

    /**
     * 画像のみを削除する処理（DB更新と物理削除を完結させる）
     */
    public function deleteImageOnly()
    {
        // JSONでレスポンスを返す準備
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            exit;
        }

        $memoId = $_POST['id'] ?? null;
        if (!$memoId) {
            echo json_encode(['status' => 'error', 'message' => 'ID is required']);
            exit;
        }

        try {
            $db = getDB();

            // 1. DBから現在の画像名とユーザー名を取得
            $stmt = $db->prepare("SELECT image_path, username FROM user_memos WHERE id = ?");
            $stmt->execute([$memoId]);
            $memo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($memo && !empty($memo['image_path'])) {
                // 2. パスの生成（View側とロジックを統一）
                $owner = $memo['username'] ?: 'guest';
                $safeFolder = preg_match('/^[a-zA-Z0-9\._-]+$/', $owner) ? $owner : 'u_' . substr(md5($owner), 0, 12);

                // 物理ファイルの絶対パス
                $baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/{$safeFolder}/images/";
                $filePath = $baseDir . $memo['image_path'];

                // 3. 物理ファイルの削除
                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        // 削除失敗時はログ等に残す（権限エラーなど）
                    }
                }

                // 4. DBの image_path カラムを NULL に更新
                // ここで確実に更新を行う
                $updateStmt = $db->prepare("UPDATE user_memos SET image_path = NULL WHERE id = ?");
                $success = $updateStmt->execute([$memoId]);

                if ($success) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update database']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No image found in database for this ID']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * ユーティリティ: リダイレクト
     */
    private function redirect($action, $msg)
    {
        header("Location: index.php?page=memo&action=$action&message=$msg");
        exit;
    }

    // app/controllers/MemoController.php 内に追加
    public function getAllUserMemos()
    {
        $db = getDB();
        // 全ユーザーのメモを結合して取得
        $sql = "SELECT m.id, m.username, m.content, m.create_date 
            FROM user_memos m 
            ORDER BY m.create_date DESC";
        $stmt = $db->prepare($sql);
        $memos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($memos as &$memo) {
            $memo['content_plain'] = $this->decryptContent($memo['content']);
        }
        return $memos;
    }
    public function getAllMemosForAdmin()
    {
        // 1. DB接続を関数から取得
        $db = getDB();

        // 2. 全ユーザーのメモを取得するSQL（テーブル名は既存のものに合わせる）
        $sql = "SELECT id, username, content, create_date,update_date FROM user_memos ORDER BY update_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        // 3. データを連想配列で全取得
        $memos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. データが存在する場合のみ復号ループを実行
        if ($memos) {
            foreach ($memos as &$memo) {
                // PHPの openssl_decrypt なら、364バイトのデータも確実に復号できます
                $memo['content_plain'] = $this->decryptContent($memo['content']);
            }
        }

        return $memos; // 配列を返す（空なら空配列）
    }

    /**
     * 24時間限定の共有URLを発行する
     */
    public function generate_share_url()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return;

        $memo_id = $_POST['memo_id'] ?? '';
        $db = getDB();

        // 1. 所有権チェック
        // guest_〇〇 形式と単体 guest の両方に対応
        $allowedUsers = [$this->user];
        if (!empty($_SESSION['guest_name'])) {
            $allowedUsers[] = 'guest_' . $_SESSION['guest_name'];
        }
        $allowedUsers[] = 'guest';
        $allowedUsers = array_unique($allowedUsers);

        $placeholders = implode(',', array_fill(0, count($allowedUsers), '?'));
        $sql = "SELECT id FROM user_memos WHERE id = ? AND username IN ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$memo_id], $allowedUsers));

        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => '所有権が確認できません。']);
            exit;
        }

        // 2. 共有トークンと期限（24時間後）を生成
        $shareToken = bin2hex(random_bytes(16));
        // MySQLのNOW()とズレないよう、DB形式の文字列で保持
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // 3. DB更新
        $stmt = $db->prepare("UPDATE user_memos SET share_token = ?, share_expires_at = ? WHERE id = ?");
        $stmt->execute([$shareToken, $expiresAt, $memo_id]);

        // 4. URLを生成してJSONで返す
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $currentScript = $_SERVER['SCRIPT_NAME']; // これで /sample/index.php などを自動取得

        $shareUrl = "{$protocol}://{$host}{$currentScript}?page=memo&action=view_share&token={$shareToken}";

        // 完了メッセージとURLを表示するテンプレートへ
        include TEMPLATE_PATH . 'memo/share_result.php';
        exit;
    }

    /**
     * 共有用URLからの閲覧処理
     * 修正ポイント: 
     * 1. 自クラスの decryptContent による確実な復号
     * 2. DBのusernameに基づく作成者表示
     * 3. ゲストユーザー名の整形
     */
    public function view_share()
    {
        $token = $_GET['token'] ?? '';
        // PDFダウンロードのフラグがある場合
        if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
            // ここでPDF生成ロジックを走らせて exit する
            $this->executePdfDownload($token);
            return;
        }
        if (empty($token)) {
            die("不正なアクセスです。");
        }

        $db = getDB();

        // 1. 検索（share_expires_at > 現在時刻）
        $sql = "SELECT * FROM user_memos WHERE share_token = ? AND share_expires_at > NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([$token]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$memo) {
            // ここで die してしまうと「リンク切れ」に見えます
            die("このリンクは無効か、有効期限が切れています。");
        }

        // 2. 復号化処理
        // saveMemoと同じ鍵・ロジックを使用するため、自クラスのメソッドを呼び出す
        $decryptedContent = $this->decryptContent($memo['content']);

        // PDFダウンロードがリクエストされた場合
        if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
            // PDF生成に必要なデータをセット
            $data = [
                'title' => $memo['title'],
                'content' => $this->decryptContent($memo['content']),
                'image_path' => $memo['image_path'],
                'username' => $memo['username']
            ];

            // 既存のPDF生成メソッドを呼び出し（出力先にブラウザを指定）
            $this->generatePdf($data, true);
            exit;
        }

        // 3. 表示用データの整理
        // タイトル：復号した内容の1行目から抽出（getMemoListと同様のロジック）
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $decryptedContent));
        $firstLine = trim($lines[0] ?? "");
        $title = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "共有されたメモ";

        // 作成者：管理ユーザー固定ではなく、DBのusernameを使用
        $creator = $memo['username'];
        if (strpos($creator, 'guest_') === 0) {
            // ゲストの場合はプレフィックスを除去して表示
            $creator = htmlspecialchars(substr($creator, 6)) . " (ゲスト)";
        } else {
            $creator = htmlspecialchars($creator);
        }

        // 有効期限
        $expires_at = $memo['share_expires_at'];

        // テンプレートへ渡す変数
        $content = $decryptedContent;

        // --- 【修正確定版：画像パスの動的生成】 ---
        $imagePath = null;
        if (!empty($memo['image_path'])) {
            // 1. ベースURL（例: /sample）を動的に取得
            $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

            // 2. DBのパスからファイル名のみを抽出
            $fileName = basename($memo['image_path']);

            // 3. 画像の保存ディレクトリを組み立て
            // 閲覧者が誰であれ、画像の場所は「作成者（$memo['username']）」のフォルダを指す必要があります。
            //$imagePath = $baseUrl . '/app/data/user_memos/' . $memo['username'] . '/images/' . $fileName;
            $imagePath = '/sample/app/data/user_memos/' . $memo['username'] . '/images/' . $fileName;
        }
        // ------------------------------------------

        // 4. 表示
        $templateFile = defined('TEMPLATE_PATH') ? TEMPLATE_PATH . 'memo/view_only.php' : null;
        if ($templateFile && file_exists($templateFile)) {
            // view_only.php 内では $title, $content, $creator, $expires_at を使用
            include $templateFile;
        } else {
            // テンプレートがない場合のフォールバック表示
            echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>共有メモ</title></head><body>";
            echo "<h1>" . htmlspecialchars($title) . "</h1>";
            if ($imagePath) {
                echo "<div style='margin-bottom:20px;'><img src='" . htmlspecialchars($imagePath) . "' style='max-width:100%;'></div>";
            }
            echo "<div>" . nl2br(htmlspecialchars($content)) . "</div>";
            echo "</body></html>";
        }
        exit;
    }

    /**
     * 共有トークンからPDFを生成して出力する
     */
    private function executePdfDownload($token)
    {
        // 重要：出力バッファをクリアして、WarningがPDFに混じらないようにする
        if (ob_get_length())
            ob_clean();

        $db = getDB();
        $sql = "SELECT * FROM user_memos WHERE share_token = ? AND share_expires_at > NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([$token]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$memo) {
            die("有効期限切れか、不正なトークンです。");
        }

        $decryptedContent = $this->decryptContent($memo['content']);

        // 画像パスの組み立て（メソッドを呼ばず直接書く）
        $fullImagePath = null;
        if (!empty($memo['image_path'])) {
            $fileName = basename($memo['image_path']);
            $fullImagePath = $_SERVER['DOCUMENT_ROOT'] . '/sample/app/data/user_memos/' . $memo['username'] . '/images/' . $fileName;
        }

        $pdfData = [
            'title' => !empty($memo['title']) ? $memo['title'] : '共有されたメモ',
            'content' => $decryptedContent,
            'image_path' => $fullImagePath,
            'created_at' => $memo['created_at'] ?? date('Y-m-d H:i:s')
        ];

        // PDF生成。ここで header() が送出されます。
        $this->generatePdf(
            $pdfData['content'],    // 第1引数: $content
            '共有ユーザー',          // 第2引数: $guestName (適宜変更可)
            basename($memo['image_path']), // 第3引数: $imagePath (ファイル名のみ)
            $memo['username']       // 第4引数: $username
        );
        exit;
    }

    /**
     * 画像アップロード・ストレージ管理
     */
    public function uploadImage($file)
    {
        if (empty($file['tmp_name']))
            return null;

        ini_set('memory_limit', '512M'); // 高精細スクショ展開用のメモリ確保
        set_time_limit(60);              // WebP変換にかかる時間を考慮

        // --- PDF出力時に残ったゴミ(temp_...)を自動削除 ---
        $imageDir = $this->baseDir . "images/";
        if (is_dir($imageDir)) {
            foreach (scandir($imageDir) as $f) {
                if (strpos($f, 'temp_') === 0 && (time() - filemtime($imageDir . $f) > 300)) {
                    @unlink($imageDir . $f);
                }
            }
        }

        // 1. 容量チェック
        $usage = $this->getUserStorageUsage($this->user);
        if ($usage + $file['size'] > $this->max_storage) {
            die("容量オーバーです。不要な画像を削除してください。");
        }

        // 2. 画像の生成 (JPG/PNG/WEBP/GIF対応)
        $src = $this->imagecreatefromany($file['tmp_name']);

        if (!$src) {
            // ここで return してしまうと DB に image_path が入らないため、
            // ログを確認するか、エラーを出して止める必要があります。
            error_log("画像生成に失敗しました: " . $file['name']);
            return null;
        }

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $src = imagerotate($src, 180, 0);
                        break;
                    case 6:
                        $src = imagerotate($src, -90, 0);
                        break;
                    case 8:
                        $src = imagerotate($src, 90, 0);
                        break;
                }
            }
        }

        // --- 【追加】Exif削除とリサイズ処理 ---
        $width = imagesx($src);
        $height = imagesy($src);
        $maxWidth = 1200; // 実用的なリサイズ幅

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) ($height * ($maxWidth / $width));
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // 新しいキャンバスを作成（ここでメタデータが引き継がれずクリーンになる）
        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // 透過設定の維持（WebP/PNG用）
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        // 再サンプリング実行
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        // ------------------------------------

        // 保存先ディレクトリの確保
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0777, true);
        }

        // ファイル名をランダム生成（常にWebPに変換して保存）
        $filename = bin2hex(random_bytes(8)) . ".webp";
        $fullPath = $imageDir . $filename;

        // WebPとして保存を実行（クリーンな $dst を保存）
        if (imagewebp($dst, $fullPath, 80)) {
            // 3. 使用量をDBに反映（実際のファイルサイズを取得）
            $this->updateUserUsage($this->user, filesize($fullPath));

            return $filename;
        }

        return null;
    }

    /**
     * DBから現在のユーザーの使用量を取得
     */
    private function getUserStorageUsage($username)
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT storage_usage FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * DBの使用量を更新
     */
    private function updateUserUsage($username, $filesize)
    {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET storage_usage = storage_usage + ? WHERE username = ?");
        $stmt->execute([(int) $filesize, $username]);
    }

    /**
     * 画像形式を自動判別してリソースを生成
     */
    private function imagecreatefromany($filepath)
    {
        if (!file_exists($filepath))
            return false;

        // getimagesize でファイルタイプを判定 (IMAGETYPE_常数を利用)
        $info = @getimagesize($filepath);
        if (!$info)
            return false;

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($filepath);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($filepath);
            case IMAGETYPE_WEBP:
                return @imagecreatefromwebp($filepath);
            default:
                return false;
        }
    }

    /**
     * ユーザー名から安全なフォルダ名を生成する（共通ロジック）
     */
    public function getSafeDirName($username)
    {
        if (empty($username) || $username === 'guest') {
            return 'guest';
        }
        // 英数字のみならそのまま、日本語等があればハッシュ化
        if (preg_match('/^[a-zA-Z0-9\._-]+$/', $username)) {
            return $username;
        }
        return 'u_' . substr(md5($username), 0, 12);
    }

    /**
     * 指定したIDのピン状態を反転させる
     */
    private function executeTogglePin($id)
    {
        $db = getDB();

        // 1. 現在のピン状態を取得
        $stmt = $db->prepare("SELECT is_pinned FROM user_memos WHERE id = :id AND username = :user");
        $stmt->execute([':id' => $id, ':user' => $this->user]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($memo) {
            // 2. 状態を反転（1なら0、0なら1）
            $newStatus = $memo['is_pinned'] ? 0 : 1;

            // 3. DBを更新
            $update = $db->prepare("UPDATE user_memos SET is_pinned = :status, update_date = NOW() WHERE id = :id AND username = :user");
            $update->execute([
                ':status' => $newStatus,
                ':id' => $id,
                ':user' => $this->user
            ]);
        }
    }

    /**
     * 最近添付された画像リストを取得
     */
    public function getRecentImages($limit = 10)
    {
        $db = getDB();

        // 安全のため、数値以外が入らないように強制キャスト
        $intLimit = (int) $limit;

        // LIMIT句に直接数値を埋め込む（バインドミスの回避）
        $sql = <<<SQL
        SELECT id, image_path, content, create_date
        FROM user_memos
        WHERE username = :user
          AND image_path IS NOT NULL
          AND image_path != ''
        ORDER BY create_date DESC
        LIMIT {$intLimit}
        SQL;

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user', $this->user, PDO::PARAM_STR);
        // :limit の bindValue は削除（直接埋め込んだため）

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * すべての画像リストを取得
     */
    public function getRecentImagesAll()
    {
        $db = getDB();

        // LIMIT句に直接数値を埋め込む（バインドミスの回避）
        $sql = "SELECT id, content, image_path, create_date 
        FROM user_memos 
        WHERE username = :username 
        AND image_path IS NOT NULL 
        AND image_path != '' 
        AND is_deleted = 0 
        ORDER BY create_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':username', trim($this->user), PDO::PARAM_STR);
        $stmt->execute();
        // $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // var_dump(count($result));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * ユーザーに紐づく直近のメモを取得する（Geminiコンテキスト用）
     */
    public function getRecentMemosAll($userName, $limit = 100)
    {
        try {
            $pdo = getDB();
            // WHERE user_id を WHERE username に変更
            $stmt = $pdo->prepare("SELECT content, create_date FROM user_memos WHERE username = ? AND is_deleted = 0 ORDER BY create_date DESC LIMIT ?");
            $stmt->bindValue(1, $userName, PDO::PARAM_STR); // 文字列なので STR
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            $memos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($memos as $m) {
                $decrypted = method_exists($this, 'decryptContent')
                    ? $this->decryptContent($m['content'])
                    : $m['content'];

                $result[] = "[{$m['create_date']}] " . $decrypted;
            }
            return $result;
        } catch (Exception $e) {
            // デバッグ時はここを error_log($e->getMessage()); にすると確実です
            return [];
        }
    }
}
?>