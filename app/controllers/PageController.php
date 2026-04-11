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
}
?>