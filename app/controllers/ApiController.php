<?php
require_once __DIR__ . '/../dbconfig.php';

class ApiController
{
    public function handleRequest(string $endpoint): void
    {
        // ヘッダーの設定
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // OPTIONSリクエスト（プリフライト）への対応
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $db = getDB();
        $method = $_SERVER['REQUEST_METHOD'];

        // DBからエンドポイントとメソッドが一致するものを取得
        $stmt = $db->prepare("SELECT * FROM apis WHERE endpoint = ? AND method = ?");
        $stmt->execute([$endpoint, $method]);
        $api = $stmt->fetch();

        if ($api) {
            http_response_code(200);

            $responseBody = $api['response_json'];

            // 動的モード（is_dynamic = 1）の場合、プレースホルダーを置換
            if ((int) $api['is_dynamic'] === 1) {
                // POSTパラメータ（JSONまたは通常のPOST）とGETパラメータをマージ
                $inputData = $this->getMergedInput();

                // {{key}} を入力値で置換
                foreach ($inputData as $key => $value) {
                    if (is_scalar($value)) { // 文字列や数値のみ置換対象にする
                        $responseBody = str_replace("{{" . $key . "}}", (string) $value, $responseBody);
                    }
                }
            }

            echo $responseBody;
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'API not found',
                'requested_endpoint' => $endpoint,
                'requested_method' => $method
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * GET/POST/JSONリクエストからパラメータを取得してマージする
     */
    private function getMergedInput(): array
    {
        $input = $_GET;

        // POST/PUT/PATCHの場合
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
            // application/json で送られてきた場合
            $json = json_decode(file_get_contents('php://input'), true);
            if (is_array($json)) {
                $input = array_merge($input, $json);
            }
            // 通常のフォーム形式 (application/x-www-form-urlencoded)
            $input = array_merge($input, $_POST);
        }

        return $input;
    }
}
