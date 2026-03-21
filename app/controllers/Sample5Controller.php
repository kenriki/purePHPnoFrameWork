<?php

// PageControllerをインスタンス化して呼び出す既存のスタイルに合わせる
class Sample5Controller {
    public function show() {
        $pageId = 'sample5'; // templates/sample5/page.php を読み込む想定
        (new PageController())->render($pageId);
    }
}
?>