<?php

class Sample2Controller {

    public function show() {
        $pageId = 'sample2';
        (new PageController())->render($pageId);
    }
}

?>