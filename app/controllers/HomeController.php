<?php

class HomeController {

    public function show() {
        $pageId = 'home';
        (new PageController())->render($pageId);
    }
}
?>