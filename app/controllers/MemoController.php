<?php

/**
 * MemoController - 高機能メモ管理コントローラ
 * * 主な機能:
 * 1. AES-256-CBC によるコンテンツの暗号化・復号
 * 2. 既存の平文データとの互換性維持（自動判別）
 * 3. 作成日(create_date)と更新日(update_date)の完全分離
 * 4. ユーザー別ディレクトリ管理と物理ファイル同期
 * 5. 6桁IDの自動採番（欠番利用型）
 * 6. tFPDFによる日本語PDFエクスポート
 * 7. ダッシュボード（カレンダー・ピン留め・グラフ）連携
 */
class MemoController
{
    private $baseDir;
    private $user;
    private $cipher_method = 'aes-256-cbc';
    private $cipher_key = 'your-secret-key-here'; // 運用時は .env 等へ

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ログインユーザーの特定
        $this->user = $_SESSION['user'] ?? $_SESSION['username'] ?? 'guest';

        // 物理保存ディレクトリの決定と作成
        $this->baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/" . $this->user . "/";
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
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

        // ゲストユーザーの同期
        if ($action === 'list') {
            $this->syncGuestMemos();
        }

        // PDF出力 (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_export'])) {
            $this->generatePdf($_POST['content'] ?? '', $_POST['guest_name'] ?? '');
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

            $this->saveMemo($memo_id, $content);
            $this->redirect("list", "saved");
        }

        // 表示用データの構築 (GET)
        $memos = ($action === 'list') ? $this->getMemoList($target_date) : [];
        $content = "";
        $memo = null;

        // if ($id) {
        //     $db = getDB();
        //     $stmt = $db->prepare("SELECT * FROM user_memos WHERE id = ? AND (username = ? OR username LIKE 'guest%')");
        //     $stmt->execute([$id, $this->user]);
        //     $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        //     if ($memo) {
        //         $content = $this->decryptContent($memo['content']);
        //     }
        // }

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

        return [
            'action' => $action,
            'id' => $id,
            'memos' => $memos,
            'memo' => $memo,
            'content' => $content,
            'user' => $this->user,
            'target_date' => $target_date,
            'message' => $_GET['message'] ?? ""
        ];
    }

    /**
     * メモ一覧の取得（復号・タイトル抽出処理含む）
     */
    private function getMemoList($target_date = null)
    {
        $db = getDB();
        $params = [];
        $sql = "SELECT id, username, content, is_pinned, 
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

        // カレンダー日付条件
        if ($target_date) {
            $sql .= " AND DATE(create_date) = :target_date";
            $params[':target_date'] = $target_date;
        }

        $sql .= " ORDER BY is_pinned DESC, update_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $decrypted = $this->decryptContent($row['content'] ?? "");
            $firstLine = trim(explode("\n", str_replace(["\r\n", "\r"], "\n", $decrypted))[0] ?? "");

            $displayTitle = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "無題のメモ #" . $row['id'];

            $suffix = "";
            if (strpos($row['username'], 'guest_') === 0) {
                $suffix = " <span class='guest-label'>(" . htmlspecialchars(substr($row['username'], 6)) . ")</span>";
            }
            $row['display_title_html'] = htmlspecialchars($displayTitle) . $suffix;
        }
        return $rows;
    }

    /**
     * メモの新規保存・更新
     */
    // private function saveMemo($id, $content)
    // {
    //     $db = getDB();
    //     $isNew = empty($id);
    //     if ($isNew)
    //         $id = $this->generateNextId();

    //     $saveUser = $this->user;
    //     if ($saveUser === 'guest' && !empty($_POST['guest_name'])) {
    //         $_SESSION['guest_name'] = $_POST['guest_name'];
    //         $saveUser = 'guest_' . $_POST['guest_name'];
    //     }

    //     // --- 暗号化 ---
    //     $ivLength = openssl_cipher_iv_length($this->cipher_method);
    //     $iv = openssl_random_pseudo_bytes($ivLength);
    //     $encrypted = openssl_encrypt($content, $this->cipher_method, $this->cipher_key, 0, $iv);
    //     $saveData = base64_encode($iv . $encrypted);

    //     // 物理ファイル同期
    //     file_put_contents($this->baseDir . $id . ".txt", $saveData);

    //     // DB更新
    //     if ($isNew) {
    //         $stmt = $db->prepare("INSERT INTO user_memos (id, username, content, create_date, update_date) VALUES (?, ?, ?, NOW(), NOW())");
    //         $stmt->execute([$id, $saveUser, $saveData]);
    //     } else {
    //         $stmt = $db->prepare("UPDATE user_memos SET content = ?, update_date = NOW() WHERE id = ?");
    //         $stmt->execute([$saveData, $id]);
    //     }
    // }

    private function saveMemo($id, $content)
    {
        $db = getDB();
        $isNew = empty($id);
        if ($isNew)
            $id = $this->generateNextId();

        $saveUser = $this->user;
        if ($saveUser === 'guest' && !empty($_POST['guest_name'])) {
            $_SESSION['guest_name'] = $_POST['guest_name'];
            $saveUser = 'guest_' . $_POST['guest_name'];
        }

        // --- 画像アップロード処理 ---
        $imagePath = null;
        if (!empty($_FILES['memo_image']['tmp_name'])) {
            // 前回の回答で作成した uploadImage メソッドを呼び出す
            $imagePath = $this->uploadImage($_FILES['memo_image']);
        }

        // --- 暗号化 ---
        $ivLength = openssl_cipher_iv_length($this->cipher_method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($content, $this->cipher_method, $this->cipher_key, 0, $iv);
        $saveData = base64_encode($iv . $encrypted);

        // 物理ファイル同期（テキスト）
        file_put_contents($this->baseDir . $id . ".txt", $saveData);

        // DB更新
        if ($isNew) {
            // image_path カラムを追加したSQL
            $stmt = $db->prepare("INSERT INTO user_memos (id, username, content, create_date, update_date) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$id, $saveUser, $saveData]);
        } else {
            // 更新時は画像がある場合のみ image_path を更新する
            if ($imagePath) {
                $stmt = $db->prepare("UPDATE user_memos SET content = ?, update_date = NOW() WHERE id = ?");
                $stmt->execute([$saveData, $id]);
            } else {
                $stmt = $db->prepare("UPDATE user_memos SET content = ?, update_date = NOW() WHERE id = ?");
                $stmt->execute([$saveData, $id]);
            }
        }
    }

    /**
     * メモの物理・論理削除
     */
    // private function deleteMemo($id)
    // {
    //     if (!$id)
    //         return;
    //     $db = getDB();
    //     $stmt = $db->prepare("DELETE FROM user_memos WHERE id = ? AND (username = ? OR username LIKE 'guest%')");
    //     $stmt->execute([$id, $this->user]);

    //     if ($stmt->rowCount() > 0) {
    //         $path = $this->baseDir . $id . ".txt";
    //         if (file_exists($path))
    //             unlink($path);
    //     }
    // }
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
     */
    /**
     * ダッシュボード用データ取得
     * 活動ログを毎週月曜日0時にリセットする仕様に変更
     */
    public function getDashboardData($username)
    {
        $db = getDB();

        // 1. カレンダー (作成日基準)
        $stmt = $db->prepare("SELECT id, content, DATE(create_date) as start FROM user_memos WHERE username = ?");
        $stmt->execute([$username]);
        $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $decrypted = $this->decryptContent($row['content']);
            $events[] = [
                'id' => $row['id'],
                'start' => $row['start'],
                'title' => mb_strimwidth(explode("\n", trim($decrypted))[0], 0, 30, "..."),
                'url' => "index.php?page=memo&action=show&id=" . $row['id']
            ];
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
     * 6桁IDの採番（隙間を埋めるロジック）
     */
    // private function generateNextId()
    // {
    //     $db = getDB();
    //     $sql = "SELECT min_id + 1 AS next_id FROM (SELECT 0 AS min_id UNION ALL SELECT CAST(id AS UNSIGNED) FROM user_memos) AS t WHERE NOT EXISTS (SELECT 1 FROM user_memos WHERE CAST(id AS UNSIGNED) = t.min_id + 1) ORDER BY next_id ASC LIMIT 1";
    //     $res = $db->query($sql)->fetch();
    //     return sprintf('%06d', $res['next_id'] ?? 1);
    // }

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
    private function generatePdf($content, $guestName = '')
    {
        while (ob_get_level())
            ob_end_clean();
        require_once 'C:\\Apache24\\htdocs\\sample\\public\\tfpdf.php';

        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('NotoSansJP', '', 'NotoSansJP-VariableFont_wght.ttf', true);
        $pdf->SetFont('NotoSansJP', '', 10);

        $header = "メモ エクスポート (" . date('Y-m-d H:i') . ")";
        $pdf->Cell(0, 10, $header, 'B', 1);
        $pdf->Ln(5);
        $pdf->MultiCell(0, 6, $content . ($guestName ? "\n\n---\n署名: $guestName" : ""));

        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=memo_" . date('YmdHis') . ".pdf");
        echo $pdf->Output('S');
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

        // セキュリティチェック：所有者を確認（user_id ではなく username を使用）
        $stmt = $db->prepare("SELECT id FROM user_memos WHERE id = ? AND username = ?");
        $stmt->execute([$memo_id, $this->user]); // $this->user はコンストラクタで設定済み

        if (!$stmt->fetch()) {
            die("不正なアクセスです。所有権が確認できません。 ID:" . htmlspecialchars($memo_id));
        }

        // 2. 24時間限定の共有トークンを生成
        $shareToken = bin2hex(random_bytes(16)); // 32文字のランダム文字列
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

        // 3. DBを更新
        $stmt = $db->prepare("UPDATE user_memos SET share_token = ?, share_expires_at = ? WHERE id = ?");
        $stmt->execute([$shareToken, $expiresAt, $memo_id]);

        // 4. 共有URLを組み立てて画面に表示（またはリダイレクト）
        $shareUrl = "http://{$_SERVER['HTTP_HOST']}/index.php?page=view_share&token={$shareToken}";

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
        if (empty($token)) {
            die("不正なアクセスです。");
        }

        $db = getDB();

        // 1. トークン照合（期限内かつ削除されていないもの）
        // ※ is_deleted カラムがない場合は、この行を削除してください
        $stmt = $db->prepare("
            SELECT * FROM user_memos 
            WHERE share_token = ? 
              AND share_expires_at > NOW()
              AND is_deleted = 0
        ");
        $stmt->execute([$token]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$memo) {
            // ここで die してしまうと「リンク切れ」に見えます
            die("このリンクは無効か、有効期限が切れています。");
        }

        // 2. 復号化処理
        // saveMemoと同じ鍵・ロジックを使用するため、自クラスのメソッドを呼び出す
        $decryptedContent = $this->decryptContent($memo['content']);

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

        // 4. 表示
        $templateFile = defined('TEMPLATE_PATH') ? TEMPLATE_PATH . 'memo/view_only.php' : null;
        if ($templateFile && file_exists($templateFile)) {
            // view_only.php 内では $title, $content, $creator, $expires_at を使用
            include $templateFile;
        } else {
            // テンプレートがない場合のフォールバック表示
            echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>共有メモ</title></head><body>";
            echo "<h1>" . htmlspecialchars($title) . "</h1>";
            echo "<p style='color:#666;'>作成者: {$creator} / 有効期限: {$expires_at}</p><hr>";
            echo "<div>" . nl2br(htmlspecialchars($content)) . "</div>";
            echo "</body></html>";
        }
        exit;
    }
    // プロパティ定義（クラスの先頭付近に配置）
    private $max_storage = 536870912; // 512 * 1024 * 1024

    /**
     * 画像アップロードと最適化
     */
    public function uploadImage($file)
    {
        if (empty($file['tmp_name']))
            return;

        // 1. 容量チェック
        $currentUserUsage = $this->getUserStorageUsage($this->user);
        if ($currentUserUsage + $file['size'] > $this->max_storage) {
            die("容量オーバーです。不要な画像を削除してください。");
        }

        // 2. 画像の最適化（リサイズ & WebP変換）
        $image = $this->imagecreatefromany($file['tmp_name']);
        if (!$image)
            return;

        // 保存先ディレクトリの確保
        $imageDir = $this->baseDir . "images/";
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0777, true);
        }

        $filename = bin2hex(random_bytes(8)) . ".webp";
        $path = $imageDir . $filename;

        // WebP形式、クオリティ80で保存
        imagewebp($image, $path, 80);

        // PHP 8.5以降、imagedestroy() は不要（自動解放されるため削除）

        // 3. 使用量をDBに反映
        $this->updateUserUsage($this->user, filesize($path));

        return $filename; // 保存したファイル名を返す
    }

    /**
     * DBから現在のユーザーの使用量を取得
     */
    private function getUserStorageUsage($username)
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT storage_usage FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['storage_usage'] : 0;
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

        $type = @exif_imagetype($filepath);
        switch ($type) {
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filepath);
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filepath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filepath);
            default:
                return false;
        }
    }


}
?>