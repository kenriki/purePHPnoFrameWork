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

            /**
             * ユーザー名表示のロジック
             * 1. Google認証済みの名前 ($_SESSION['user_display_name']) があれば最優先
             * 2. 次に通常のログインユーザー名 ($_SESSION['username'])
             * 3. いずれも無ければ 'guest' (これまでのデフォルト)
             */
            $displayName = $_SESSION['user_display_name'] ?? $_SESSION['username'] ?? 'guest';
            $internalUser = $_SESSION['username'] ?? 'guest'; // DBクエリ用などの内部識別名

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

                // 17時以降は「お疲れ様です」を優先
                if ($hour >= 17) {
                    $greeting = "お疲れ様です。";
                }

                // JSONのタイトルを「挨拶＋表示名」で上書き
                // Google認証していればGoogleの名前、していなければ guest 等が表示されます
                $page['title'] = "{$greeting} {$displayName} さん";

                // $page 配列に 'dashboard' キーとしてデータを追加
                $page['dashboard'] = $memoCtrl->getDashboardData($internalUser);
            }

            if ($pageId === 'sample6') {
                require_once __DIR__ . '/MemoController.php';
                $memoCtrl = new MemoController();
                $page['allMemos'] = $memoCtrl->getAllMemosForAdmin();
            }

            // if ($pageId === 'memo_list') {
            //     // 自身の showMemoList メソッドを呼び出してデータを取得
            //     $memoData = $this->showMemoList();
            //     // 取得した myMemos を $page 配列に注入
            //     $myMemos = $memoData['myMemos'];
            //     $page['title'] = $memoData['title'];
            //     $page['myMemos'] = $myMemos; // テンプレート側で使用できるように注入
            // }
            // index.php の 404判定部分
            if ($pageId === 'memo_list') { // 無効にしたいページ
                header("HTTP/1.0 404 Not Found");
                $page = [
                    'title' => '404 Not Found',
                    'content' => '指定されたページ（memo_list）は現在ご利用いただけません。'
                ];
                // この後、include 'app/templates/page.php'; が走るようにする
            }

            if ($pageId === 'diga_list') {
                $targetUrl = "http://192.168.3.36/cgi-bin/dispframe.cgi?DISP_PAGE=213";

                // ブラウザのデベロッパーツールで確認した最新のCookieをセット
                $myCookie = "6hYNnPplBt47PvS5DyFHHKWSg9QBk+DbkFWt7S2uWbF0QHhquZsMCVGXZzCsFxnDnJQwBieNVi9FF/zag5VnMcYU+plgMzjo/1G5c3Q5NITm2PnooDlOXlKgeJYo";

                $options = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: Mozilla/5.0\r\n" .
                            "Referer: http://192.168.3.36/cgi-bin/topMenu.cgi\r\n" .
                            "Cookie: $myCookie\r\n",
                        'timeout' => 30
                    ]
                ];

                $context = stream_context_create($options);

                // DIGAからデータを取得
                $handle = @fopen($targetUrl, 'rb', false, $context);
                $rawHtml = '';

                if ($handle) {
                    while (!feof($handle)) {
                        $buffer = fread($handle, 8192);
                        if ($buffer === false)
                            break;
                        $rawHtml .= $buffer;
                    }
                    fclose($handle);

                    // 文字コード変換
                    $htmlUtf8 = mb_convert_encoding($rawHtml, "UTF-8", "SJIS-win");
                    $page['diga_raw_html'] = $htmlUtf8;

                    // --- GASへの送信処理 ---
                    $gasUrl = "https://script.google.com/macros/s/AKfycbzRL37DFj5-8wKeODRG9BoLvhcphKnHAq5Dvj6CJhY/dev";

                    $postData = [
                        'body' => $htmlUtf8
                    ];

                    $gasOptions = [
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/json\r\n",
                            'content' => json_encode($postData),
                            'timeout' => 10,
                            'follow_location' => 1
                        ]
                    ];

                    @file_get_contents($gasUrl, false, stream_context_create($gasOptions));
                } else {
                    $page['diga_raw_html'] = "接続エラー：ストリームを開けませんでした。";
                }

                $page['title'] = "DIGA 録画番組リスト";
            }

            // --- ここまで ---

            // ページ固有テンプレートのパス設定
            $templateDir = TEMPLATE_PATH . $pageId . '/';
            if (!is_dir($templateDir)) {
                $templateDir = TEMPLATE_PATH;
            }
        }

        // 3. 共通レイアウトのインクルード
        // header.php 内で $page['title'] を出力していれば、挨拶メッセージが反映されます
        include TEMPLATE_PATH . 'layout/header.php';
        include $templateDir . 'page.php';
        include TEMPLATE_PATH . 'layout/footer.php';
    }

    public function showMemoList()
    {
        $db = getDB();

        // セッションキーを確認
        $username = $_SESSION['username'] ?? $_SESSION['user'] ?? 'guest';

        // SQL実行
        $stmt = $db->prepare("SELECT content, create_date, update_date FROM user_memos WHERE username = ? ORDER BY create_date DESC");
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 復号化と配列整形
        require_once __DIR__ . '/MemoController.php';
        $memoCtrl = new MemoController();
        $myMemos = [];
        foreach ($rows as $row) {
            $myMemos[] = [
                'content_plain' => $memoCtrl->decryptContent($row['content']),
                'create_date' => $row['create_date'],
                'update_date' => $row['update_date']
            ];
        }

        return [
            'title' => 'マイメモ一覧',
            'pageId' => 'memo_list',
            'myMemos' => $myMemos,
        ];
    }
}