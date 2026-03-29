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

        // PDF作成ボタンが押された場合
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_export'])) {
            $this->generatePdf($_POST['content']);
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

    // --- メソッド定義：ここが漏れているとエラーになります ---
    private function getMemoList()
    {
        $db = getDB();
        $stmt = $db->prepare("
        SELECT id, content, DATE_FORMAT(update_date, '%Y-%m-%d %H:%i') as time 
        FROM user_memos 
        WHERE username = :username 
        ORDER BY update_date DESC
    ");
        $stmt->execute([':username' => $this->user]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            // 改行で分割して1行目を取得
            $content = $row['content'] ?? "";
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
            $firstLine = trim($lines[0] ?? "");

            // ★もしDBのcontentが空なら、ファイルから直接読み取って補完する（同期ミス対策）
            if (empty($firstLine)) {
                $filePath = $this->baseDir . $row['id'] . ".txt";
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    $fLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));
                    $firstLine = trim($fLines[0] ?? "");
                }
            }

            $row['display_title'] = mb_strimwidth($firstLine, 0, 40, "...");

            // それでも空ならIDを出す
            if (empty($row['display_title'])) {
                $row['display_title'] = "メモ #" . $row['id'];
            }
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
        $filePath = $this->baseDir . $id . ".txt";

        // 1. 従来通りファイルに保存
        $fileResult = file_put_contents($filePath, $content);

        // 2. DBと同期（追加部分）
        if ($fileResult !== false) {
            $db = getDB(); // index.phpで読み込んでいるDB接続関数
            $stmt = $db->prepare("
            REPLACE INTO user_memos (id, username, content, update_date) 
            VALUES (:id, :username, :content, NOW())
        ");
            $stmt->execute([
                ':id' => $id,
                ':username' => $this->user,
                ':content' => $content
            ]);
        }

        return $fileResult;
    }

    private function deleteMemo($id)
    {
        if (empty($id))
            return;

        // 1. DBから削除
        $db = getDB();
        $stmt = $db->prepare("
        DELETE FROM user_memos 
        WHERE id = :id AND username = :username
        ");
        $stmt->execute([
            ':id' => $id,
            ':username' => $this->user
        ]);

        // 2. ファイル（物理データ）を削除
        $path = $this->baseDir . $id . ".txt";
        if (file_exists($path)) {
            unlink($path);
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
    private function generatePdf($content)
    {
        // 1. 出力バッファにあるゴミをすべて捨て、クリーンな状態にする
        while (ob_get_level()) {
            ob_end_clean();
        }

        // --- ライブラリ読み込み・生成（ここはこれまでのパスで） ---
        $baseDir = 'C:\\Apache24\\htdocs\\sample\\public\\';
        require_once $baseDir . 'tfpdf.php';
        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('NotoSansJP', '', 'NotoSansJP-VariableFont_wght.ttf', true);
        $pdf->SetFont('NotoSansJP', '', 11);

        // 行間を狭く (6)
        $pdf->MultiCell(0, 5, $content);

        // 2. ファイル名の生成
        $filename = "memo_" . date('Ymd_His') . ".pdf";

        // 3. ブラウザに「PDFファイル」であることを認識させるヘッダー
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');

        // 4. PDFを出力して即座に終了（これ以降のHTMLを混ぜない）
        $pdf->Output('D', $filename);
        exit;
    }
}