<?php

declare(strict_types=1);

/**
 * Gemini API 専用管理クラス
 */
readonly class AiManager
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private string $geminiKey = ''
    ) {
    }

    /**
     * プロンプトをGemini APIに送信
     * @param string $provider 互換性維持のための引数
     * @param string $prompt 組み立てられたプロンプト文字列
     * @return array<string, mixed> 応答テキスト、またはエラー配列
     */
    public function ask(string $provider, string $prompt): array
    {
        if (empty($this->geminiKey)) {
            return ['error' => 'Gemini APIキーが設定されていません。'];
        }

        // 安定性とレスポンス性能に優れた最新の 2.5-flash を利用
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}";

        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,    // 応答のブレを最小限に抑える
                'topP' => 0.95,
                'maxOutputTokens' => 4096 // 十分なアウトプット出力を確保
            ]
        ];

        // APIリクエストの実行
        $res = $this->postRequest($url, $data);

        // 通信レベル、またはAPI側からエラーが明示的に戻されている場合のハンドリング
        if (isset($res['error'])) {
            $errMessage = $res['error']['message'] ?? json_encode($res['error'], JSON_UNESCAPED_UNICODE);
            return ['error' => 'APIエラーが発生しました。数分時間を置いてから再度話しかけてください: ' . $errMessage];
        }

        // レスポンスからテキストコンテンツの抽出を厳格に行う
        $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null || trim($text) === '') {
            // 解析失敗時にGoogleから返ってきたJSONの生データを丸ごと開発画面に出却してデバッグを容易に
            return ['error' => 'Gemini APIからのレスポンス解析に失敗しました。応答データ: ' . json_encode($res, JSON_UNESCAPED_UNICODE)];
        }

        return ['response' => trim($text)];
    }

    /**
     * 共通リクエストメソッド (cURLによる堅牢なエラーキャッチ構造)
     * @param string $url エンドポイントURL
     * @param array<string, mixed> $data 送信データ
     * @return array<string, mixed> デコードされたレスポンス、または内部エラー配列
     */
    private function postRequest(string $url, array $data): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => ['message' => 'cURLの初期化に失敗しました。']];
        }

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // タイムアウトは余裕を持って60秒

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // 1. 通信そのものが遮断された・失敗した場合（タイムアウト、DNS解決失敗など）
        if ($response === false) {
            return ['error' => ['message' => "ネットワーク通信エラーが発生しました(cURL_ERR): {$curlError}"]];
        }

        $decoded = json_decode($response, true);

        // 2. HTTPステータスコードが200（正常）以外の場合のエラーラップ
        if ($httpCode !== 200) {
            return [
                'error' => [
                    'message' => "HTTPステータス異常 (Code: {$httpCode})",
                    'raw' => $decoded ?? $response
                ]
            ];
        }

        // 3. レスポンスが正常なJSONでなかった場合の防御
        if (!is_array($decoded)) {
            return ['error' => ['message' => 'APIから不正なフォーマットのデータが返却されました。']];
        }

        return $decoded;
    }
}