<?php

class Sample1Controller {

    public function show() {
        $pageId = 'sample1';
        (new PageController())->render($pageId);
    }
}

?>