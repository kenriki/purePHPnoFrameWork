<?php
require_once __DIR__ . '/../dbconfig.php';

class AdminController
{
    public function handleRequest(): array
    {
        $this->checkBasicAuth();

        $db = getDB();
        $this->createTable($db);

        // POST処理：追加/削除
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'add') {
                // 新しいカラムを含めて保存
                $stmt = $db->prepare("INSERT INTO apis (endpoint, method, request_params, response_json, is_dynamic) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['endpoint'],
                    $_POST['method'],
                    $_POST['request_params'] ?? '', // SwaggerのParameter定義のように使用
                    $_POST['response_json'],
                    isset($_POST['is_dynamic']) ? 1 : 0 // チェックされていれば1
                ]);
            } elseif (($_POST['action'] ?? '') === 'delete') {
                $stmt = $db->prepare("DELETE FROM apis WHERE id = ?");
                $stmt->execute([$_POST['id']]);
            }
            header("Location: ?page=admin");
            exit;
        }

        $apis = $db->query("SELECT * FROM apis ORDER BY id DESC")->fetchAll();

        return [
            'title' => 'API管理画面',
            'apis' => $apis
        ];
    }

    private function createTable(PDO $db): void
    {
        // 基本テーブル作成
        $db->exec("CREATE TABLE IF NOT EXISTS `apis` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `endpoint` VARCHAR(255) NOT NULL,
            `method` VARCHAR(10) NOT NULL,
            `request_params` TEXT NULL,
            `response_json` TEXT NOT NULL,
            `is_dynamic` TINYINT(1) DEFAULT 0,
            UNIQUE KEY `endpoint_method` (`endpoint`, `method`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 既存テーブルがある場合のカラム追加（エラーは無視）
        try {
            $db->exec("ALTER TABLE `apis` ADD `request_params` TEXT NULL AFTER `method` covers");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE `apis` ADD `is_dynamic` TINYINT(1) DEFAULT 0 AFTER `response_json` covers");
        } catch (Exception $e) {
        }
    }

    private function checkBasicAuth(): void
    {
        if (
            !isset($_SERVER['PHP_AUTH_USER']) ||
            $_SERVER['PHP_AUTH_USER'] !== 'admin' ||
            $_SERVER['PHP_AUTH_PW'] !== 'admin@1234'
        ) {
            header('WWW-Authenticate: Basic realm="Admin Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo '認証が必要です';
            exit;
        }
    }
}
