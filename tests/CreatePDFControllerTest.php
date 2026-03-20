<?php
use PHPUnit\Framework\TestCase;

// --- ここで定数を定義する ---
if (!defined('TEMPLATE_PATH')) {
    define('TEMPLATE_PATH', __DIR__ . '/../app/templates/'); // 実際のフォルダ名に合わせてください
}
if (!defined('DATA_PATH')) {
    define('DATA_PATH', __DIR__ . '/../app/data/');
}

// --- 手動で依存ファイルを読み込む ---
require_once __DIR__ . '/../app/controllers/PageController.php';
require_once __DIR__ . '/../app/controllers/CreatePDFController.php';

class CreatePDFControllerTest extends TestCase {

    private $controller;

    // 各テストの前に実行される準備処理
    protected function setUp(): void {
        $this->controller = new CreatePDFController();
    }

    /**
     * show() メソッドのテスト
     */
    public function testShow() {
        // 出力をキャッチ開始
        ob_start();
        $this->controller->show();
        $output = ob_get_clean(); // 出力内容を変数に入れてバッファを終了

        // 検証：出力が空でないこと
        $this->assertNotEmpty($output, "show() メソッドが何も出力していません。");
        
        // 検証：特定の文字列（例：HTMLのタグなど）が含まれているか
        // $this->assertStringContainsString('<html', $output);
    }

    /**
     * generate() メソッドのテスト
     */
    // public function testGenerate() {
    //     $controller = new CreatePDFController();

    //     // --- ここから出力を横取り ---
    //     ob_start(); 
        
    //     $controller->generate();
        
    //     // 出力されたデータ（PDFの中身）を変数に取得
    //     $output = ob_get_contents(); 
        
    //     // 画面に出さずにバッファを破棄して終了
    //     ob_end_clean(); 
    //     // --- ここまで ---

    //     // 検証：PDFのヘッダー文字「%PDF」が含まれているか確認
    //     $this->assertStringContainsString('%PDF', $output, "PDFデータが正しく生成されていません。");
    // }

}
