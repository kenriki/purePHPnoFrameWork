<?php
require_once __DIR__ . '/../dbconfig.php';

class AdminController
{
    public function handleRequest(): array
    {
        $this->checkBasicAuth();

        $db = getDB(); // dbconfig.phpの関数を使う
        $this->createTable($db); // テーブル無ければ作る

        // POST処理：追加/削除
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'add') {
                $stmt = $db->prepare("INSERT INTO apis (endpoint, method, response_json) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['endpoint'], $_POST['method'], $_POST['response_json']]);
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
        $db->exec("CREATE TABLE IF NOT EXISTS `apis` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `endpoint` VARCHAR(255) NOT NULL,
            `method` VARCHAR(10) NOT NULL,
            `response_json` TEXT NOT NULL,
            UNIQUE KEY `endpoint_method` (`endpoint`, `method`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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