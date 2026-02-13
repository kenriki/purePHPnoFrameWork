<?php

class SampleController {

    public function show() {
        $pageId = 'sample';
        (new PageController())->render($pageId);
    }
}

?>