<?php
class SurveyController
{
    // アンケート入力画面の表示
    public function show()
    {
        (new PageController())->render('survey');
    }

    // DBへの保存処理
    // DBへの保存処理
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                die("エラー：ログインしてから回答してください。");
            }

            $rating  = $_POST['rating'] ?? 0;
            $comment = $_POST['comment'] ?? '';

            if ($rating === 0) {
                die("エラー：評価を選択してください。");
            }

            try {
                // 1. dbconfig.php は既に読み込まれている可能性が高いため require_once を使用
                // もしエラーが出る場合はここをコメントアウトしてもOKです
                require_once dirname(__DIR__) . '/dbconfig.php'; 

                // 2. dbconfig.php で定義されている getDB() 関数をそのまま使う
                // これだけで接続（PDOの生成）が完了します
                $pdo = getDB(); 

                // 3. プリペアドステートメントで安全にINSERT
                $stmt = $pdo->prepare("INSERT INTO survey_responses (user_id, rating, comment) VALUES (?, ?, ?)");
                
                // 実行
                $stmt->execute([$userId, $rating, $comment]);

                // 4. 完了画面へリダイレクト
                header("Location: index.php?page=survey_thanks");
                exit;

            } catch (PDOException $e) {
                die("DBエラーが発生しました: " . $e->getMessage());
            }
        }
    }
}
?>