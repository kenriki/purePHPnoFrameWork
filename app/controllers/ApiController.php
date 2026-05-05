<?php
require_once __DIR__ . '/../dbconfig.php';

class ApiController
{
    public function handleRequest(string $endpoint): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $db = getDB(); // ← ここ重要。dbconfig.phpの関数を使う

        $stmt = $db->prepare("SELECT * FROM apis WHERE endpoint = ? AND method = ?");
        $stmt->execute([$endpoint, $_SERVER['REQUEST_METHOD']]);
        $api = $stmt->fetch();

        if ($api) {
            http_response_code(200);
            echo $api['response_json'];
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'API not found'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}