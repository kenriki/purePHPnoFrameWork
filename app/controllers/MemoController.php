<?php

class MemoController
{
    private $baseDir;
    private $user;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();

        // 1. ログインユーザー名を取得（未ログインなら guest）
        $this->user = $_SESSION['user'] ?? $_SESSION['username'] ?? 'guest';

        // 2. ユーザーごとにディレクトリを分ける
        // 固定の "kenmochi" ではなく、$this->user を使うように変更
        $this->baseDir = "C:/Apache24/htdocs/sample/app/data/user_memos/" . $this->user . "/";

        // 3. フォルダがなければ自動作成
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    public function handleRequest()
    {
        $action = $_GET['action'] ?? 'list';

        // 1. 一覧表示の前に、無記名メモを現在の合言葉に紐付け直す
        if ($action === 'list') {
            $this->syncGuestMemos();
        }

        // PDF作成ボタンが押された場合
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_export'])) {
            $content = $_POST['content'] ?? '';
            $guestName = $_POST['guest_name'] ?? '';
            $this->generatePdf($content, $guestName);
            return;
        }

        $id = $_GET['id'] ?? null;

        // POSTリクエスト（保存・削除）の処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $memo_id = $_POST['id'] ?? null;

            if (isset($_POST['delete'])) {
                $this->deleteMemo($memo_id);
                // 2. 削除後のリダイレクト先を list に固定
                header("Location: /index.php?page=memo&action=list&message=deleted");
                exit;
            }

            // 保存実行
            $this->saveMemo($memo_id, $content);
            // 3. 保存後のリダイレクト先
            header("Location: /index.php?page=memo&action=list&message=saved");
            exit;
        }

        // 4. 表示用データの取得（ここが最重要修正）
        $memos = ($action === 'list') ? $this->getMemoList() : [];

        // 修正前: getMemoContent($id) 
        // 修正後: getMemo($id) に変更して定義済みのメソッドを呼ぶようにする
        $content = ($id) ? $this->getMemo($id) : "";

        return [
            'action' => $action,
            'id' => $id,
            'memos' => $memos,
            'content' => $content,
            'user' => $this->user,
            'message' => $_GET['message'] ?? ""
        ];
    }

    /**
     * ホームページ（カレンダー・グラフ）表示用
     */
    public function home()
    {
        // ダッシュボード用データを取得
        $dashboardData = $this->getDashboardData($this->user);

        return [
            'title' => 'ホームページ',
            'dashboard' => $dashboardData,
            'login_user' => $this->user, // これが右上の表示に使われる
        ];
    }

    /**
     * IDをキーにして、DBからメモの内容（本文）を取得する
     */
    private function getMemo($id)
    {
        if (empty($id))
            return "";

        $db = getDB();

        // ファイル (.txt) を見に行くのではなく、DBの content 列を直接引く
        // これにより、ファイルとの不整合（幽霊データ）を防ぎます
        $stmt = $db->prepare("
        SELECT content 
        FROM user_memos 
        WHERE id = :id
    ");

        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // データがあれば中身を、なければ空文字を返す
        return $result ? $result['content'] : "";
    }

    /**
     * 無記名メモの同期ロジック
     * @return void
     */
    private function syncGuestMemos()
    {
        $guestName = $_SESSION['guest_name'] ?? '';
        $currentUser = $this->user ?? 'guest';

        if ($currentUser === 'guest' && !empty($guestName)) {
            $db = getDB();
            $targetName = 'guest_' . $guestName;

            // 1. 重複回避：既に自分の名前(guest_りき)で存在するIDと被る「guest」データを消す
            $stmtDel = $db->prepare("
            DELETE FROM user_memos 
            WHERE username = 'guest' 
            AND id IN (SELECT id FROM (SELECT id FROM user_memos WHERE username = :new_name) as tmp)
        ");
            $stmtDel->execute([':new_name' => $targetName]);

            // 2. 一括更新：残った「guest」名義をすべて自分の名前に書き換える
            $stmtUpd = $db->prepare("
            UPDATE user_memos 
            SET username = :new_name 
            WHERE username = 'guest'
        ");
            // $stmtUpd.execute ではなく $stmtUpd->execute に修正
            $stmtUpd->execute([':new_name' => $targetName]);
        }
    }

    // --- メソッド定義：ここが漏れているとエラーになります ---
    private function getMemoList()
    {
        $db = getDB();

        //  1. ユーザー状態の判定（ログイン or ゲスト）
        $currentUser = $this->user ?? 'guest';

        if ($currentUser !== 'guest' && !empty($currentUser)) {
            // 【ログイン中】本人のメモのみを取得
            $stmt = $db->prepare("
            SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
            FROM user_memos 
            WHERE username = :username 
            ORDER BY update_date DESC
        ");
            $stmt->execute([':username' => $currentUser]);
        } else {
            // 【ゲスト時】
            $guestName = $_SESSION['guest_name'] ?? null;

            if (!empty($guestName)) {
                // 合言葉がある：'guest_りき' 等に一致するメモのみを出す
                $stmt = $db->prepare("
                SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
                FROM user_memos 
                WHERE username = :guest_sig 
                ORDER BY update_date DESC
            ");
                $stmt->execute([':guest_sig' => 'guest_' . $guestName]);
            } else {
                // 合言葉が空：純粋な 'guest'（無記名）の共有メモだけを出す
                $stmt = $db->prepare("
                SELECT id, username, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
                FROM user_memos 
                WHERE username = 'guest' 
                ORDER BY update_date DESC
            ");
                $stmt->execute();
            }
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. 表示用データの整形（文字数 60 調整済み）
        foreach ($rows as &$row) {
            $content = $row['content'] ?? "";
            // 改行コードを統一して1行目を取得
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
            $firstLine = trim($lines[0] ?? "");

            // タイトルを 60 文字でカットし、空なら ID を表示
            $displayTitle = !empty($firstLine) ? mb_strimwidth($firstLine, 0, 60, "...") : "メモ #" . $row['id'];

            $u = $row['username'];
            $suffix = "";

            // 署名の表示判定
            // 'guest' ぴったりの時は署名なし、'guest_りき' 等のときは (りき) を付与
            if ($u !== 'guest' && strpos($u, 'guest_') === 0) {
                $nameOnly = str_replace('guest_', '', $u);
                $suffix = " <span style='color:#888; font-size:0.8rem;'>(" . htmlspecialchars($nameOnly) . ")</span>";
            }

            // page.php でそのまま出力するための HTML 組み立て
            $row['display_title_html'] = htmlspecialchars($displayTitle) . $suffix;
        }

        return $rows;
    }

    private function saveMemo($id, $content)
    {
        if (empty($id)) {
            $id = $this->generateNextId();
        }

        // 1. 誰として保存するかを判定
        // $this->user が未設定（null）の場合は 'guest' をデフォルトにする
        $currentUser = $this->user ?? 'guest';
        $saveUser = $currentUser;

        // 2. フォームから送られてきた合言葉を取得
        $formGuestName = $_POST['guest_name'] ?? '';

        if ($currentUser === 'guest' && !empty($formGuestName)) {
            // 保存時に合言葉が入力されていれば、セッションも更新しておく（利便性のため）
            $_SESSION['guest_name'] = $formGuestName;

            // DB上は「guest_名前」形式で保存
            $saveUser = 'guest_' . $formGuestName;
        }

        $filePath = $this->baseDir . $id . ".txt";

        // 1. ファイル保存
        $fileResult = file_put_contents($filePath, $content);

        // 2. DBと同期
        if ($fileResult !== false) {
            $db = getDB();

            // 3. REPLACE INTO で ID が重複した場合は「上書き（更新）」する
            // これにより、無記名(guest)だったデータが(guest_りき)に書き換わります
            $stmt = $db->prepare("
            REPLACE INTO user_memos (id, username, content, update_date) 
            VALUES (:id, :username, :content, NOW())
        ");

            $stmt->execute([
                ':id' => $id,
                ':username' => $saveUser,
                ':content' => $content
            ]);
        }

        return $fileResult;
    }

    private function deleteMemo($id)
    {
        if (empty($id))
            return false;

        $db = getDB();

        // 1. 削除権限を持つユーザー名を判定
        $currentUser = $this->user ?? 'guest';
        $targetUser = $currentUser;

        if ($currentUser === 'guest') {
            $guestName = $_SESSION['guest_name'] ?? '';
            // 合言葉があれば 'guest_りき'、なければ 'guest'
            $targetUser = !empty($guestName) ? 'guest_' . $guestName : 'guest';
        }

        // 2. DBから削除（IDとユーザー名が一致する場合のみ）
        $stmt = $db->prepare("
        DELETE FROM user_memos 
        WHERE id = :id AND username = :username
    ");

        $stmt->execute([
            ':id' => $id,
            ':username' => $targetUser
        ]);

        // 3. DBで削除に成功した場合のみ、物理ファイルも消す
        if ($stmt->rowCount() > 0) {
            $path = $this->baseDir . $id . ".txt";
            if (file_exists($path)) {
                unlink($path);
            }
            return true;
        }

        return false;
    }

    private function generateNextId()
    {
        $db = getDB();

        /**
         * 削除されたID（欠番）を優先的に見つけるSQL
         * 1から順に「存在しない番号」の最小値を探します。
         */
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

        // 取得失敗（初期状態など）なら 1 を使う
        $nextIdNum = $result['next_id'] ?? 1;

        /**
         * 000001 形式（6桁）のゼロ埋めで返す
         * ※以前は 5桁でしたが、元のコードに合わせて 6桁に調整しました。
         */
        return sprintf('%06d', $nextIdNum);
    }

    // MemoController.php 内の generatePdf メソッド
    /**
     * PDF生成メソッド
     * $content に加えて $guestName も受け取れるように調整
     */
    private function generatePdf($content, $guestName = '')
    {
        // 1. 強力に出力バッファを掃除（PDFバイナリの破損を徹底防止）
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 2. 署名の追記ロジック
        if ($this->user === 'guest' && !empty($guestName)) {
            $content .= "\n\n---\n作成者: " . $guestName . " (Guest投稿)";
        }

        // 3. tFPDFのロードと描画
        $baseDir = 'C:\\Apache24\\htdocs\\sample\\public\\';
        require_once $baseDir . 'tfpdf.php';

        $pdf = new tFPDF();
        $pdf->AddPage();

        // 行間を狭く(5)、フォントを9に設定
        $fontFileName = 'NotoSansJP-VariableFont_wght.ttf';
        $pdf->AddFont('NotoSansJP', '', $fontFileName, true);
        $pdf->SetFont('NotoSansJP', '', 9);

        $pdf->Cell(0, 10, 'メモ エクスポート', 0, 1);
        $pdf->Ln(5);

        // 第2引数を 5 にして行間を詰める
        $pdf->MultiCell(0, 5, $content);

        // ポイント1：PDFデータを一旦「文字列」として取得
        $pdf_data = $pdf->Output('S');
        $pdf_size = strlen($pdf_data);

        // ポイント2：ファイル名の生成
        $filename = "memo_" . date('Ymd_His') . ".pdf";
        $encoded_filename = rawurlencode($filename);

        // ポイント3：スマホが「ダウンロード」と認識するヘッダー群
        header('Content-Type: application/pdf');
        // inline ではなく attachment を指定
        header("Content-Disposition: attachment; filename*=UTF-8''" . $encoded_filename);
        header("Content-Length: " . $pdf_size);
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');

        // 最終出力
        echo $pdf_data;
        exit;
    }
    /**
     * ダッシュボード（page=home）用のデータを取得
     * カレンダー用イベントと、直近7日の活動グラフ用データを返す
     */
    public function getDashboardData($username)
    {
        $db = getDB();

        // 1. カレンダー用
        $stmt = $db->prepare("
            SELECT id, content, DATE(update_date) as start 
            FROM user_memos 
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        // MemoController.php の getDashboardData メソッド内

        foreach ($rows as $row) {
            $firstLine = explode("\n", $row['content'])[0];
            $events[] = [
                'id' => $row['id'],
                'title' => mb_strimwidth($firstLine, 0, 30, "..."),
                'start' => $row['start'],
                // 詳細ページへのURLを追加
                'url' => '?page=memo_detail&id=' . $row['id']
            ];
        }

        // 2. グラフ用（エラー修正済みSQL）
        // DATE(update_date) に 'date' という名前を付けて、ORDER BY でもそれを使います
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
    // app/controllers/MemoController.php 内に追加
    public function getMemoById($id)
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_memos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function showDetail()
    {
        // 1. ユーザー名の取得（優先順位：GETパラメータ > セッション > guest）
        $username = $_GET['username'] ?? $_SESSION['username'] ?? 'guest';
        $id = $_GET['id'] ?? null;

        if (!$id) {
            return ['memo' => null, 'login_user' => $username];
        }

        // 2. DB接続を取得
        $db = getDB();

        // 3. 直接クエリを実行してメモを取得
        $stmt = $db->prepare("SELECT * FROM user_memos WHERE id = :id AND username = :username");
        $stmt->execute(['id' => $id, 'username' => $username]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. テンプレートへ渡すデータを返す
        return [
            'memo' => $memo,
            'login_user' => $username, // 画面右上の表示用に使用
        ];
    }
}