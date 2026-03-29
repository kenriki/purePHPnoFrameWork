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

        // 一覧表示の前に、無記名メモを現在の合言葉に紐付け直す
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
                header("Location: /index.php?page=memo&message=deleted");
                exit;
            }

            // 保存実行
            $this->saveMemo($memo_id, $content);
            header("Location: /index.php?page=memo&action=list&message=saved");
            exit;
        }

        // 表示用データの取得
        $memos = ($action === 'list') ? $this->getMemoList() : [];
        $content = ($id) ? $this->getMemoContent($id) : "";

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

            // 💡 署名の表示判定
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

    private function getMemoContent($id)
    {
        $db = getDB();
        $stmt = $db->prepare("
        SELECT content FROM user_memos 
        WHERE id = :id AND username = :username
    ");
        $stmt->execute([':id' => $id, ':username' => $this->user]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $memo['content'] ?? "";
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
            return;

        // 現在のユーザー（guest含む）が所有しているメモかを確認して削除
        // $this->user が 'guest' の場合は、そのセッションで作成されたものだけが対象
        $db = getDB();

        // 1. まずDBから削除
        $stmt = $db->prepare("
        DELETE FROM user_memos 
        WHERE id = :id AND username = :username
    ");
        $stmt->execute([
            ':id' => $id,
            ':username' => $this->user
        ]);

        // 2. DBで削除された（＝権限があった）場合のみ、物理ファイルも消す
        if ($stmt->rowCount() > 0) {
            $path = $this->baseDir . $id . ".txt";
            if (file_exists($path)) {
                unlink($path);
            }

            // ★ ID管理ファイル (last_id.txt) は消さない（欠番は許容するのが一般的）
        }
    }

    private function generateNextId()
    {
        $idFile = $this->baseDir . "last_id.txt";
        $lastId = file_exists($idFile) ? (int) file_get_contents($idFile) : 0;
        $newId = $lastId + 1;
        file_put_contents($idFile, $newId);
        return sprintf('%06d', $newId); // 000001 形式
    }

    // MemoController.php 内の generatePdf メソッド
    /**
     * PDF生成メソッド
     * $content に加えて $guestName も受け取れるように調整
     */
    private function generatePdf($content, $guestName = '')
    {
        // 1. 出力バッファをクリア（PDF破損防止）
        if (ob_get_length())
            ob_clean();

        // 2. Guest（未ログイン）かつ名前がある場合、本文末尾に追記
        // セッションが切れていても「誰のメモか」を物理的に残す
        if (!$this->user && !empty($guestName)) {
            $content .= "\n\n---\n作成者: " . $guestName . " (Guest投稿)";
        }

        // --- 以下、以前設定したtFPDFのロジック ---
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

        $filename = "memo_" . date('Ymd_His') . ".pdf";
        header('Content-Type: application/pdf');
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($filename));

        $pdf->Output('I', $filename); // 'D'にすれば直接ダウンロード
        exit;
    }
}