<?php

// 1. 基準となる PageController を最初に読み込む
require_once __DIR__ . '/controllers/PageController.php';

// 2. controllers フォルダ内のファイルを一括で読み込む (手書き不要)
foreach (glob(__DIR__ . '/controllers/*Controller.php') as $filename) {
    require_once $filename;
}

function route($page) {
    // クラス名を推測（例: 'home' -> 'HomeController'）
    $controllerName = ucfirst($page) . 'Controller';

    // 1. クラスが存在する場合（HomeController, SampleControllerなど）
    if (class_exists($controllerName)) {
        $controller = new $controllerName();
        // PDFなどの特殊なアクション分岐がある場合
        if ($page === 'createPDF' && isset($_GET['action']) && $_GET['action'] === 'generate') {
            return $controller->generate();
        }
        return $controller->show();
    }

    // 2. クラスがない場合は、PageController で JSON ページとして処理
    // (PageController 内で 404 判定も行う)
    return (new PageController())->render($page);
}
?>