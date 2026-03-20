<?php
use PHPUnit\Framework\TestCase;

// --- 1. 定数の定義（以前と同様に必要です） ---
if (!defined('TEMPLATE_PATH')) define('TEMPLATE_PATH', __DIR__ . '/../app/templates/');
if (!defined('DATA_PATH')) {
    // フォルダではなく「ファイルそのもの」を指すようにする
    define('DATA_PATH', realpath(__DIR__ . '/../app/data/pages.json'));
}
// --- 2. 必要なファイルを読み込む ---
require_once __DIR__ . '/../app/controllers/PageController.php';
require_once __DIR__ . '/../app/controllers/HomeController.php';

class HomeControllerTest extends TestCase {

    // public function testShow() {
    //     $controller = new HomeController();

    //     // --- ここを追加！ ---
    //     // コントローラーが $_GET を見ている場合、値をセットしてあげる
    //     $_GET['page'] = 'home'; 

    //     // 出力をキャッチ
    //     ob_start();
    //     $controller->show();
    //     $output = ob_get_clean();

    //     // 検証1：何かしらの HTML が出力されているか
    //     //$this->assertNotEmpty($output, "Homeの出力が空です。テンプレートのパスが正しいか確認してください。");

    //     // 検証2：特定のキーワード（例えば 'home' やタイトルなど）が含まれているか
    //     // ※ 実際の templates/home/page.php の内容に合わせて調整してください
    //     $this->assertStringContainsString('ホーム', $output);

    //     // 例：わざと失敗させる
    //     //$this->assertStringContainsString('存在しないはずの文字', $output);

    // }
    public function testShow() {
        // --- ここを追加して、パスがどう認識されているか確認 ---
        //echo "\n[DEBUG] DATA_PATH: " . DATA_PATH;
        //echo "\n[DEBUG] Full Path: " . realpath(DATA_PATH . 'pages.json');
        //echo "\n[DEBUG] Exists?: " . (file_exists(DATA_PATH . 'pages.json') ? 'YES' : 'NO') . "\n";
        $controller = new HomeController();

        // --- ここを追加！ ---
        // コントローラーが $_GET['page'] を見て判断している場合、これを書かないと 404 になります
        $_GET['page'] = 'home'; 

        ob_start();
        $controller->show();
        $output = ob_get_clean();

        // 404 画面になっていないか確認
        $this->assertStringNotContainsString('ページが見つかりません', $output, "404エラーが発生しています。パスやGETパラメータを確認してください。");
        
        // 本来チェックしたかった文字
        $this->assertStringContainsString('ホーム', $output);
    }

}
?>