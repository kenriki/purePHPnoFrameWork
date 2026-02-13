<?php

class PageController {

    public function render($pageId) {

        $json = json_decode(file_get_contents(DATA_PATH), true);

        // ページが存在しない場合は 404 用のデータを作る
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

            // 404 は共通テンプレートを使う
            $templateDir = TEMPLATE_PATH;
        } else {
            // 通常ページ
            $page = $json[$pageId];

            // ページ固有テンプレート
            $templateDir = TEMPLATE_PATH . $pageId . '/';
            if (!is_dir($templateDir)) {
                $templateDir = TEMPLATE_PATH;
            }
        }
        // 共通レイアウト
        include TEMPLATE_PATH . 'layout/header.php';
        include $templateDir . 'page.php';
        include TEMPLATE_PATH . 'layout/footer.php';
    }
}

?>