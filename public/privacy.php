<?php
/**
 * プライバシーポリシーページ
 * 場所: C:\Apache24\htdocs\sample\public\privacy.php
 */

// サイト名や連絡先を必要に応じて書き換えてください
$siteName = "リケモる（メモアプリ）";
$adminContact = "riki.kenmochi@gmail.com";
$backupUrl = "https://kenriki.static.jp/";

?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プライバシーポリシー |
        <?php echo $siteName; ?>
    </title>
    <style>
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            background-color: #f9f9f9;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        h1 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            color: #007bff;
            font-size: 24px;
        }

        h2 {
            font-size: 18px;
            margin-top: 30px;
            border-left: 4px solid #007bff;
            padding-left: 10px;
        }

        p,
        li {
            font-size: 15px;
        }

        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .footer-link {
            margin-top: 40px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>プライバシーポリシー</h1>
        <p>
            <?php echo $siteName; ?>（以下「当アプリ」）は、ユーザーの皆様の個人情報の重要性を認識し、その保護に努めます。
        </p>

        <h2>1. 取得する情報</h2>
        <p>当アプリでは、サービスの提供にあたり以下の情報を取得します。</p>
        <ul>
            <li><strong>アカウント情報：</strong> ユーザーID、暗号化されたパスワード、表示名。</li>
            <li><strong>ユーザー生成コンテンツ：</strong> 作成されたメモ内容、画像データ、およびその更新日時。</li>
            <li><strong>Google連携データ：</strong> Googleカレンダー同期機能を利用する場合、Googleから提供されるアクセストークンおよびユーザー識別子。</li>
        </ul>

        <h2>2. 利用目的</h2>
        <p>取得した情報は、以下の目的でのみ利用します。</p>
        <ul>
            <li>ユーザー認証および個別のメモ管理。</li>
            <li>Googleカレンダーとのスケジュール同期機能の提供。</li>
            <li>重要なお知らせやシステム更新の通知。</li>
        </ul>

        <h2>3. データの管理とセキュリティ</h2>
        <p>ユーザーのデータは厳重に管理されています。</p>
        <ul>
            <li>メモ内容、添付画像やパスワードはハッシュ化（復元不可能な状態）して保存されています。</li>
            <li>※ Googleカレンダーから持ってきた情報は、トークン切れの際に削除されます。</li>
            <li>設定ファイルや認証情報への直接アクセスは、サーバー設定により遮断されています。</li>
        </ul>

        <h2>4. サーバー障害時の対応（バックアップ手段）</h2>
        <div class="alert-box">
            <p><strong>【重要】サービスが利用できない場合：</strong></p>
            <p>当アプリのサーバーがダウンしている、またはアクセスできない場合は、以下の外部バックアップサイトをご利用ください。あらかじめエクスポートしたCSVやJSONファイルを読み込み、データの閲覧や編集が可能です。
            </p>
            <p><a href="<?php echo $backupUrl; ?>" target="_blank" rel="noopener noreferrer"><strong>バックアップ・ツールサイト：
                        <?php echo $backupUrl; ?>
                    </strong></a></p>
        </div>

        <h2>5. データの削除（退会）</h2>
        <p>ユーザーはいつでも登録情報の削除を申請できます。退会手続きが完了次第、サーバー上のメモデータおよび連携トークンは速やかに破棄されます。</p>

        <h2>6. お問い合わせ</h2>
        <p>本ポリシーに関するお問い合わせは、管理者（
            <?php echo $adminContact; ?>）までご連絡ください。
        </p>

        <div class="footer-link">
            <hr>
            <p><a href="index.php" class="btn">アプリに戻る</a></p>
        </div>
    </div>

</body>

</html>