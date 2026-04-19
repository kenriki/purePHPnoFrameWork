<?php

class PageController
{
    public function render($pageId)
    {
        // 1. JSONからページ設定を読み込む
        $json = json_decode(file_get_contents(DATA_PATH), true);

        // 2. ページが存在しない場合の 404 処理
        if (!isset($json[$pageId])) {
            http_response_code(404);
            $page = [
                'title' => 'ページが見つかりません',
                'sections' => [
                    [
                        'type' => 'notFound',
                        'headline' => '404 Not Found',
                        'subtext' => '指定されたページは存在しません。'
                    ]
                ]
            ];
            $templateDir = TEMPLATE_PATH;
        } else {
            // 通常ページ
            $page = $json[$pageId];

            // --- ここからデータ注入の追加 ---

            // 全ページ共通、または 'home' 専用でユーザー名を取得
            $username = $_SESSION['username'] ?? 'kenmochi';

            if ($pageId === 'home') {
                // MemoControllerを読み込んでインスタンス化
                require_once __DIR__ . '/MemoController.php';
                $memoCtrl = new MemoController();

                // --- 挨拶メッセージ生成ロジック ---
                $hour = (int) date("H");

                // 時間帯による出し分け
                if ($hour >= 5 && $hour < 11) {
                    $greeting = "おはようございます！";
                } elseif ($hour >= 11 && $hour < 18) {
                    $greeting = "こんにちは！";
                } else {
                    $greeting = "こんばんは。";
                }

                // 17時以降は「お疲れ様です」を優先（必要に応じて調整してください）
                if ($hour >= 17) {
                    $greeting = "お疲れ様です。";
                }

                // JSONのタイトルを「挨拶＋ユーザー名」で上書き
                $page['title'] = "{$greeting} {$username} さん";

                // $page 配列に 'dashboard' キーとしてデータを追加
                $page['dashboard'] = $memoCtrl->getDashboardData($username);
            }
            if ($pageId === 'sample6') {
                $memoCtrl = new MemoController();
                $page['allMemos'] = $memoCtrl->getAllMemosForAdmin();
            }
            if ($pageId === 'memo_list') {
                // 自身の showMemoList メソッドを呼び出してデータを取得
                $memoData = $this->showMemoList();
                // 取得した myMemos を $page 配列に注入
                $myMemos = $memoData['myMemos'];
                $page['title'] = $memoData['title'];
            }

            // --- ここまで ---

            // ページ固有テンプレートのパス設定
            $templateDir = TEMPLATE_PATH . $pageId . '/';
            if (!is_dir($templateDir)) {
                $templateDir = TEMPLATE_PATH;
            }
        }

        // 3. 共通レイアウトのインクルード
        include TEMPLATE_PATH . 'layout/header.php';
        include $templateDir . 'page.php';
        include TEMPLATE_PATH . 'layout/footer.php';
    }

    public function showMemoList()
    {
        $db = getDB();

        // セッションキーを確認（お使いの環境に合わせて username か user を指定）
        $username = $_SESSION['username'] ?? $_SESSION['user'] ?? null;

        if (!$username) {
            $myMemos = [];
        } else {
            // SQL実行
            $stmt = $db->prepare("SELECT content, create_date, update_date FROM user_memos WHERE username = ? ORDER BY create_date DESC");
            $stmt->execute([$username]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 復号化と配列整形
            $memoCtrl = new MemoController();
            $myMemos = [];
            foreach ($rows as $row) {
                $myMemos[] = [
                    // decryptContent が public であることを前提としています
                    'content_plain' => $memoCtrl->decryptContent($row['content']),
                    'create_date' => $row['create_date'],
                    'update_date' => $row['update_date']
                ];
            }
        }

        // テンプレートに $myMemos として渡す
        return [
            'title' => 'マイメモ一覧',
            'pageId' => 'memo_list',
            'myMemos' => $myMemos,
        ];
    }
}
?>