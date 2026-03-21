<?php

// PageControllerをインスタンス化して呼び出す既存のスタイルに合わせる
class Sample4Controller {
    public function show() {
        $pageId = 'sample4'; // templates/sample4/page.php を読み込む想定
        (new PageController())->render($pageId);
    }
}
?>