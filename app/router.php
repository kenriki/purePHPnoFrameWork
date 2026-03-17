<?php

require_once __DIR__ . '/controllers/PageController.php';
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/SampleController.php';
require_once __DIR__ . '/controllers/Sample1Controller.php';
require_once __DIR__ . '/controllers/Sample2Controller.php';
require_once __DIR__ . '/controllers/CreatePDFController.php';

function route($page) {

    switch ($page) {

        case 'home':
            return (new HomeController())->show();

        case 'sample':
            return (new SampleController())->show();

        case 'sample1':
            return (new Sample1Controller())->show();

        case 'sample2':
            return (new Sample2Controller())->show();

        case 'createPDF':
            // PDF生成アクション
            if (isset($_GET['action']) && $_GET['action'] === 'generate') {
                return (new CreatePDFController())->generate();
            }

            // 通常のHTMLページ
            return (new CreatePDFController())->show();

        default:
            // JSONページ（固定ページ）
            return (new PageController())->render($page);
    }
}

?>