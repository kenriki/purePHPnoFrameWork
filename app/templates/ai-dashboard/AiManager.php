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
     * 第1引数に provider、第2引数に prompt を確実に受け取る定義にします
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

        // レスポンスの解析
        $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            //  「解析に失敗しました」という固定文字を捨て、Googleが怒っている生のログを丸ごと画面に出す
            return ['error' => '数分、時間を置いてから再度話しかけてください: ' . json_encode($res, JSON_UNESCAPED_UNICODE)];
        }

        // レスポンスの解析
        $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            return ['error' => 'Gemini APIからのレスポンス解析に失敗しました。'];
        }

        return ['response' => $text];
    }

    /**
     * 共通リクエストメソッド (cURL)
     */
    private function postRequest(string $url, array $data): array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);

        return json_decode($response, true) ?? [];
    }
}