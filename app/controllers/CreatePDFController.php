<?php

class CreatePDFController {

    // public function show() {
    //     require dirname(__DIR__) . '/../public/createPDF.php';
    //     exit;
    // }

    public function generate() {
        // JSON 読み込み
        $jsonPath = dirname(__DIR__) . '/data/pages.json';
        $json = json_decode(file_get_contents($jsonPath), true);

        // createPDF ページのデータを取得
        $data = $json['createPDF'] ?? [];

        // PDF 生成ファイルにデータを渡す
        $pdfData = $data; 
        require dirname(__DIR__) . '/../public/createPDF.php';
        exit;
    }

    public function show() {
        (new PageController())->render('createPDF');
    }


}


?>