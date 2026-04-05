<?php

/**
 * MemoController
 * * 機能一覧:
 * 1. ユーザー別ディレクトリ管理 (C:/Apache24/htdocs/sample/app/data/user_memos/{user}/)
 * 2. メモ一覧取得 (通常一覧 / 日付フィルタ対応)
 * 3. メモ詳細・編集データ取得 (DB直接参照)
 * 4. メモ保存 (物理ファイル保存 + DB REPLACE INTO 同期)
 * 5. メモ削除 (DB削除成功時のみ物理ファイルを削除)
 * 6. ゲストメモ同期 (無記名 'guest' を 'guest_合言葉' へ一括更新)
 * 7. 自動採番 (欠番を優先埋めする 6桁 000001 形式)
 * 8. PDFエクスポート (tFPDF利用、日本語対応、署名追記)
 * 9. ダッシュボード連携 (FullCalendar用イベント / Chart.js用直近7日集計)
 */
class MemoController
{
    private $baseDir;
    private $user;

    public function __construct()
    {
        // セッションが開始されていなければ開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. ログインユーザー名を取得（未ログインなら guest）
        $this->user = $_SESSION['user'] ?? $_SESSION['username'] ?? 'guest';

        // 2. ユーザーごとにディレクトリを分ける
        $this->baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/" . $this->user . "/";

        // 3. フォルダがなければ自動作成
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    /**
     * メインのリクエスト処理（ルーティング相当）
     */
    public function handleRequest()
    {
        $action = $_GET['action'] ?? 'list';
        $id = $_GET['id'] ?? null;
        $target_date = $_GET['date'] ?? null; // カレンダー等からの日付指定を受け取る

        // 一覧表示の前に、無記名メモを現在の合言葉に紐付け直す
        if ($action === 'list') {
            $this->syncGuestMemos();
        }

        // --- PDFエクスポート処理 (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_export'])) {
            $content = $_POST['content'] ?? '';
            $guestName = $_POST['guest_name'] ?? '';
            $this->generatePdf($content, $guestName);
            return; // PDF出力後は終了
        }

        // --- 保存・削除処理 (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $memo_id = $_POST['id'] ?? null;

            // 削除ボタン押下時
            if (isset($_POST['delete'])) {
                $this->deleteMemo($memo_id);
                header("Location: /index.php?page=memo&action=list&message=deleted");
                exit;
            }

            // 保存（新規・更新共通）実行
            $this->saveMemo($memo_id, $content);
            header("Location: /index.php?page=memo&action=list&message=saved");
            exit;
        }

        // --- 表示用データの準備 (GET) ---
        // アクションが list の場合のみ一覧を取得、それ以外（編集等）は空配列
        $memos = ($action === 'list') ? $this->getMemoList($target_date) : [];

        // ID指定がある場合はDBから本文を取得 (以前の getMemoContent を統合)
        $content = ($id) ? $this->getMemo($id) : "";

        return [
            'action' => $action,
            'id' => $id,
            'memos' => $memos,
            'content' => $content,
            'user' => $this->user,
            'target_date' => $target_date,
            'message' => $_GET['message'] ?? ""
        ];
    }

    /**
     * ホーム画面（ダッシュボード）用データ提供
     */
    public function home()
    {
        // カレンダーとグラフ用のデータを一括取得
        $dashboardData = $this->getDashboardData($this->user);

        return [
            'title' => 'ホームページ',
            'dashboard' => $dashboardData,
            'login_user' => $this->user, // 右上表示用
        ];
    }

    /**
     * DBからメモ一覧を取得する
     * @param string|null $target_date 'YYYY-MM-DD' 形式でフィルタリング
     */
    private function getMemoList($target_date = null)
    {
        $db = getDB();
        $currentUser = $this->user ?? 'guest';
        $params = [];

        // 1. ユーザー状態に応じた基本SQLの構築
        if ($currentUser !== 'guest' && !empty($currentUser)) {
            // ログインユーザー用
            $sql = "SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
                    FROM user_memos WHERE username = :username";
            $params[':username'] = $currentUser;
        } else {
            // ゲストユーザー用（合言葉の有無で分岐）
            $guestName = $_SESSION['guest_name'] ?? null;
            if (!empty($guestName)) {
                $sql = "SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
                        FROM user_memos WHERE username = :guest_sig";
                $params[':guest_sig'] = 'guest_' . $guestName;
            } else {
                $sql = "SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
                        FROM user_memos WHERE username = 'guest'";
            }
        }

        // 2. 日付フィルタリング（カレンダーの「+more」等からの遷移時）
        if ($target_date) {
            $sql .= " AND DATE(update_date) = :target_date";
            $params[':target_date'] = $target_date;
        }

        $sql .= " ORDER BY update_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. 表示用データの整形（1行目をタイトル化、署名付与）
        foreach ($rows as &$row) {
            $content = $row['content'] ?? "";
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
            $firstLine = trim($lines[0] ?? "");

            // タイトルを60文字でカット
            $displayTitle = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "メモ #" . $row['id'];

            $u = $row['username'];
            $suffix = "";
            // 合言葉（guest_りき 等）がある場合は (りき) を後ろに付ける
            if ($u !== 'guest' && strpos($u, 'guest_') === 0) {
                $nameOnly = str_replace('guest_', '', $u);
                $suffix = " <span style='color:#888; font-size:0.8rem;'>(" . htmlspecialchars($nameOnly) . ")</span>";
            }

            // view側でそのまま出力するためのHTML
            $row['display_title_html'] = htmlspecialchars($displayTitle) . $suffix;
        }
        return $rows;
    }

    /**
     * IDをキーにしてDBからメモ本文を取得
     */
    private function getMemo($id)
    {
        if (empty($id))
            return "";

        $db = getDB();
        $stmt = $db->prepare("SELECT content FROM user_memos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['content'] : "";
    }

    /**
     * メモの保存（ファイル & DB）
     */
    private function saveMemo($id, $content)
    {
        // IDがなければ採番
        if (empty($id)) {
            $id = $this->generateNextId();
        }

        $currentUser = $this->user ?? 'guest';
        $saveUser = $currentUser;
        $formGuestName = $_POST['guest_name'] ?? '';

        // ゲスト保存時に合言葉があれば反映
        if ($currentUser === 'guest' && !empty($formGuestName)) {
            $_SESSION['guest_name'] = $formGuestName;
            $saveUser = 'guest_' . $formGuestName;
        }

        // 物理ファイル保存
        $filePath = $this->baseDir . $id . ".txt";
        $fileResult = file_put_contents($filePath, $content);

        // DB同期
        if ($fileResult !== false) {
            $db = getDB();
            $stmt = $db->prepare("REPLACE INTO user_memos (id, username, content, update_date) VALUES (:id, :username, :content, NOW())");
            $stmt->execute([
                ':id' => $id,
                ':username' => $saveUser,
                ':content' => $content
            ]);
        }
        return $fileResult;
    }

    /**
     * メモの削除（DB & ファイル）
     */
    private function deleteMemo($id)
    {
        if (empty($id))
            return false;

        $db = getDB();
        $currentUser = $this->user ?? 'guest';
        $targetUser = $currentUser;

        if ($currentUser === 'guest') {
            $guestName = $_SESSION['guest_name'] ?? '';
            $targetUser = !empty($guestName) ? 'guest_' . $guestName : 'guest';
        }

        // DBから削除（権限チェックを兼ねてユーザー名も条件に含める）
        $stmt = $db->prepare("DELETE FROM user_memos WHERE id = :id AND username = :username");
        $stmt->execute([
            ':id' => $id,
            ':username' => $targetUser
        ]);

        // DB削除成功時のみファイルを消す
        if ($stmt->rowCount() > 0) {
            $path = $this->baseDir . $id . ".txt";
            if (file_exists($path)) {
                unlink($path);
            }
            return true;
        }
        return false;
    }

    /**
     * ダッシュボード用データ取得（カレンダー・グラフ）
     */
    public function getDashboardData($username)
    {
        $db = getDB();

        // 1. カレンダー用イベント取得
        $stmt = $db->prepare("SELECT id, content, DATE(update_date) as start FROM user_memos WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $firstLine = explode("\n", $row['content'])[0];
            $events[] = [
                'id' => $row['id'],
                'title' => mb_strimwidth($firstLine, 0, 30, "..."),
                'start' => $row['start'],
                // カレンダー内クリックで直接編集画面へ
                'url' => "index.php?page=memo&action=edit&id=" . $row['id'] . "&username=" . $username
            ];
        }

        // 2. 活動グラフ用（直近7日間の件数推移）
        $stmt = $db->prepare("
            SELECT DATE(update_date) as date, COUNT(*) as count 
            FROM user_memos 
            WHERE username = :username 
              AND update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY date
            ORDER BY date ASC
        ");
        $stmt->execute([':username' => $username]);
        $chart = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['events' => $events, 'chart' => $chart];
    }

    /**
     * メモ詳細表示用
     */
    public function showDetail()
    {
        $username = $_GET['username'] ?? $_SESSION['username'] ?? 'guest';
        $id = $_GET['id'] ?? null;

        if (!$id) {
            return ['memo' => null, 'login_user' => $username];
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_memos WHERE id = :id AND username = :username");
        $stmt->execute(['id' => $id, 'username' => $username]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'memo' => $memo,
            'login_user' => $username,
        ];
    }

    /**
     * 単一IDでのメモ取得（予備）
     */
    public function getMemoById($id)
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_memos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 無記名 'guest' 投稿を現在の 'guest_合言葉' へ一括同期
     */
    private function syncGuestMemos()
    {
        $guestName = $_SESSION['guest_name'] ?? '';
        $currentUser = $this->user ?? 'guest';

        if ($currentUser === 'guest' && !empty($guestName)) {
            $db = getDB();
            $targetName = 'guest_' . $guestName;

            // 1. 重複回避：既に自分の合言葉で存在するIDと被る 'guest' データを消す
            $stmtDel = $db->prepare("
                DELETE FROM user_memos 
                WHERE username = 'guest' 
                AND id IN (SELECT id FROM (SELECT id FROM user_memos WHERE username = :new_name) as tmp)
            ");
            $stmtDel->execute([':new_name' => $targetName]);

            // 2. 一括更新：残った 'guest' 名義をすべて自分の名前に書き換える
            $stmtUpd = $db->prepare("UPDATE user_memos SET username = :new_name WHERE username = 'guest'");
            $stmtUpd->execute([':new_name' => $targetName]);
        }
    }

    /**
     * 欠番を考慮した 6桁 ID の生成
     */
    private function generateNextId()
    {
        $db = getDB();
        $sql = "
            SELECT min_id + 1 AS next_id
            FROM (
                SELECT 0 AS min_id
                UNION ALL
                SELECT CAST(id AS UNSIGNED) FROM user_memos
            ) AS existing_ids
            WHERE NOT EXISTS (
                SELECT 1 FROM user_memos 
                WHERE CAST(id AS UNSIGNED) = existing_ids.min_id + 1
            )
            ORDER BY next_id ASC
            LIMIT 1
        ";
        $stmt = $db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextIdNum = $result['next_id'] ?? 1;

        return sprintf('%06d', $nextIdNum);
    }

    /**
     * PDFエクスポート機能 (tFPDF)
     */
    private function generatePdf($content, $guestName = '')
    {
        // 出力バッファをクリアしてPDFバイナリの破損を防ぐ
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 署名追記
        if ($this->user === 'guest' && !empty($guestName)) {
            $content .= "\n\n---\n作成者: " . $guestName . " (Guest投稿)";
        }

        require_once 'C:\\Apache24\\htdocs\\sample\\public\\tfpdf.php';

        $pdf = new tFPDF();
        $pdf->AddPage();

        // フォント設定（NotoSansJPを使用）
        $pdf->AddFont('NotoSansJP', '', 'NotoSansJP-VariableFont_wght.ttf', true);
        $pdf->SetFont('NotoSansJP', '', 9);

        $pdf->Cell(0, 10, 'メモ エクスポート', 0, 1);
        $pdf->Ln(5);

        // 本文出力
        $pdf->MultiCell(0, 5, $content);

        // PDFデータの生成
        $pdf_data = $pdf->Output('S');
        $filename = "memo_" . date('Ymd_His') . ".pdf";

        // ダウンロード用ヘッダー
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
        header("Content-Length: " . strlen($pdf_data));
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');

        echo $pdf_data;
        exit;
    }
}