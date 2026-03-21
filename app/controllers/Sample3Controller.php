<?php

// PageControllerをインスタンス化して呼び出す既存のスタイルに合わせる
class Sample3Controller {
    public function show() {
        $pageId = 'sample3'; // templates/sample3/page.php を読み込む想定
        (new PageController())->render($pageId);
    }
}
?>