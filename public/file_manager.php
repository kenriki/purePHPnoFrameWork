<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../app/dbconfig.php';
$pdo = getDB();
$current_user_id = $_SESSION['user_id'] ?? null;

// ログインユーザーのユーザー名を取得（初期表示用）
$current_username = '';
if ($current_user_id) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_username = $stmt->fetchColumn() ?: '';
}

// ファイル保存先の物理パス定義
$base_dir = __DIR__ . '/../app/';
$upload_dir = $base_dir . 'data/uploads/';

// --- 1. API処理（ユーザー検索） ---
if (isset($_GET['search_exact'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$_GET['search_exact']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

// --- 2. ファイルダウンロード処理 ---
if (isset($_GET['token'])) {
    // トークン（ファイル名そのもの）で直接検索
    $stmt = $pdo->prepare("SELECT * FROM file_uploads WHERE file_path LIKE ? AND is_public = 1 AND expires_at > NOW()");
    $stmt->execute(['%/' . $_GET['token']]);
    $file = $stmt->fetch();

    if ($file) {
        $full_path = $base_dir . $file['file_path'];
        if (file_exists($full_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($full_path));
            readfile($full_path);
            exit;
        }
    }
    die("ファイルが見つからないか、期限切れです。");
}

// --- 3. POST処理（削除・アップロード） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user_id) {
    // 削除処理
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("SELECT file_path FROM file_uploads WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $current_user_id]);
        $file = $stmt->fetch();
        if ($file) {
            $pdo->prepare("DELETE FROM file_uploads WHERE id = ?")->execute([$_POST['delete_id']]);
            $full_path = $base_dir . $file['file_path'];
            if (file_exists($full_path))
                unlink($full_path);
        }
        header("Location: index.php?page=file_upload");
        exit;
    }

    // アップロード処理
    if (isset($_FILES['file'])) {
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $expires = $is_public ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null;

        $allowed_id_list = [];

        // 入力欄に指定がある場合、ユーザーIDへ変換
        if (!empty($_POST['target_users'])) {
            $names = array_map('trim', explode(',', $_POST['target_users']));
            $names = array_filter($names); // 空文字を除去

            if (!empty($names)) {
                $placeholders = implode(',', array_fill(0, count($names), '?'));
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username IN ($placeholders)");
                $stmt->execute($names);
                $allowed_id_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // 限定公開でID群が抽出できている場合、保存用の文字列（カンマ区切り）を生成
        $allowed_ids = !empty($allowed_id_list) ? implode(',', array_unique($allowed_id_list)) : null;

        $new_name = uniqid() . '_' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $new_name)) {
            $stmt = $pdo->prepare("INSERT INTO file_uploads (file_name, file_path, user_id, is_public, expires_at, allowed_user_ids) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_FILES['file']['name'], 'data/uploads/' . $new_name, $current_user_id, $is_public, $expires, $allowed_ids]);
        }
        header("Location: index.php?page=file_upload");
        exit;
    }
}

// --- 4. 一覧取得用SQL ---
$files = [];
if ($current_user_id) {
    $sql = "SELECT f.*, u.username FROM file_uploads f JOIN users u ON f.user_id = u.id 
            WHERE f.user_id = :uid OR f.allowed_user_ids IS NULL OR f.allowed_user_ids = '' OR FIND_IN_SET(:uid, f.allowed_user_ids) 
            ORDER BY uploaded_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $current_user_id]);
    $files = $stmt->fetchAll();
}

// ファイルサイズを読みやすい形式に変換する関数
function formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824)
        return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)
        return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)
        return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>添付ファイル管理サイト</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f7f9;
            padding: 20px;
        }

        section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        h1 {
            color: #005bac;
            border-bottom: 2px solid #005bac;
            padding-bottom: 10px;
        }

        h2 {
            font-size: 1.2em;
            color: #005bac;
            margin-top: 0;
        }

        .file-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* アップロードフォームのユーザー指定欄 */
        #userIn {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .sugg-box {
            border: 1px solid #ddd;
            position: absolute;
            background: #fff;
            width: 250px;
            z-index: 100;
        }

        .sugg-item {
            padding: 5px;
            cursor: pointer;
        }

        .sugg-item:hover {
            background: #eef;
        }

        .guest-link {
            font-size: 0.8em;
            color: #005bac;
            display: block;
            margin-top: 5px;
        }

        /* DataTables レイアウト調整 */
        .dataTables_wrapper .top {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .dataTables_length {
            margin: 0 !important;
        }

        .dataTables_info {
            margin: 0 !important;
            padding: 0 !important;
            float: none !important;
        }

        .dataTables_filter {
            display: none !important;
        }

        .dataTables_wrapper .bottom {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .dataTables_paginate {
            float: right;
        }

        #filterRow th {
            padding: 8px !important;
        }

        #filterRow th input {
            width: 95% !important;
            padding: 5px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        table.dataTable thead .sorting:after,
        table.dataTable thead .sorting_asc:after,
        table.dataTable thead .sorting_desc:after {
            bottom: 25px !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>

<body>
    <h1>添付ファイル管理サイト</h1>
    <?php if (isset($error_message)): ?>
        <section><?= $error_message ?></section>
    <?php else: ?>
        <section>
            <h2>ファイルアップロード</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="file" required><br>
                <label><input type="checkbox" name="is_public"> 24H期限付き</label><br>
                <div style="position:relative; width: 100%; max-width: 500px;">
                    <!-- 初期値(value)に自分のユーザー名を設定 -->
                    <input type="text" name="target_users" id="userIn" 
                           value="<?= htmlspecialchars($current_username ? $current_username . ', ' : '', ENT_QUOTES, 'UTF-8') ?>" 
                           placeholder="ユーザー名(カンマ区切りで完全一致)" autocomplete="off">
                    <div id="sugg" class="sugg-box"></div>
                </div>
                <button type="submit" style="background:#005bac;">アップロード</button>
            </form>
        </section>

        <section>
            <h2>ファイル一覧</h2>
            <table id="fileTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ファイル名</th>
                        <th>サイズ</th>
                        <th>投稿者</th>
                        <th>日時</th>
                        <th>操作</th>
                    </tr>
                    <tr id="filterRow">
                        <th><input type="text" placeholder="検索" /></th>
                        <th></th>
                        <th><input type="text" placeholder="検索" /></th>
                        <th><input type="text" placeholder="検索" /></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $f):
                        $safe_token = htmlspecialchars(basename($f['file_path']));
                        $safe_id = htmlspecialchars($f['id']);
                        $full_path = $base_dir . $f['file_path'];
                        $file_size = file_exists($full_path) ? formatSizeUnits(filesize($full_path)) : '不明';
                        ?>
                        <tr>
                            <td>
                                <a href="download.php?token=<?= $safe_token ?>" target="_blank"
                                    style="color:#005bac; font-weight:bold; text-decoration:none;">
                                    <?= htmlspecialchars($f['file_name']) ?>
                                </a>

                                <?php if ($f['is_public'] && !empty($f['expires_at'])): ?>
                                    <div style="font-size: 0.75em; color: #dc3545; margin-top: 2px;">
                                        期限: <?= htmlspecialchars($f['expires_at']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                                $download_link = $base_url . '/download.php?token=' . $safe_token;
                                ?>
                                <div class="guest-link" style="font-size:0.8em; color:#005bac; margin-top:5px;">
                                    URL:
                                    <input type="text" id="url_<?= $safe_id ?>" value="<?= htmlspecialchars($download_link) ?>"
                                        style="width:200px; font-size:0.9em; padding:2px; border:1px solid #ccc; border-radius:3px;"
                                        readonly>
                                    <button type="button" onclick="copyToClipboard('url_<?= $safe_id ?>')"
                                        style="background:#28a745; color:white; border:none; padding:2px 8px; font-size:0.9em; cursor:pointer; border-radius:3px;">
                                        コピー
                                    </button>
                                </div>
                            </td>
                            <td><?= $file_size ?></td>
                            <td><?= htmlspecialchars($f['username']) ?></td>
                            <td><?= htmlspecialchars($f['uploaded_at']) ?></td>
                            <td>
                                <?php if ($f['user_id'] == $current_user_id): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="delete_id" value="<?= $safe_id ?>">
                                        <button type="submit" onclick="return confirm('削除しますか？')">削除</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
    <script>
        $(document).ready(function () {
            if ($.fn.DataTable.isDataTable('#fileTable')) {
                $('#fileTable').DataTable().destroy();
            }

            $('#fileTable').DataTable({
                initComplete: function () {
                    this.api().columns().every(function (i) {
                        var column = this;
                        var input = $('#filterRow th').eq(i).find('input');

                        input.on('keyup change clear', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    });
                },
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ja.json"
                },
                "pageLength": 5,
                "lengthMenu": [5, 10, 25, 50, 100, 500],
                "columnDefs": [
                    { "orderable": false, "targets": 4 }
                ],
                "dom": '<"top"lip>rt<"bottom">'
            });

            const input = document.getElementById('userIn');
            const sugg = document.getElementById('sugg');

            if (input && sugg) {
                input.addEventListener('input', async (e) => {
                    const val = e.target.value.split(',').pop().trim();
                    if (!val) { sugg.innerHTML = ''; return; }

                    try {
                        const res = await fetch('index.php?page=file_upload&search_exact=' + encodeURIComponent(val));
                        const users = await res.json();
                        sugg.innerHTML = users.map(u => `<div class="sugg-item" onclick="add('${u}')">${u}</div>`).join('');
                    } catch (err) {
                        console.error('Search error:', err);
                    }
                });
            }
        });

        function add(name) {
            const input = document.getElementById('userIn');
            let parts = input.value.split(',');
            parts.pop();
            parts.push(name);
            input.value = parts.join(', ') + ', ';
            document.getElementById('sugg').innerHTML = '';
        }

        function copyToClipboard(elementId) {
            const copyText = document.getElementById(elementId);
            if (!copyText) return;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(copyText.value).then(() => {
                    alert("コピー完了！");
                }).catch(() => {
                    manualCopy(copyText);
                });
            } else {
                manualCopy(copyText);
            }
        }

        function manualCopy(copyText) {
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                alert("コピー完了");
            } catch (err) {
                alert("コピーに失敗しました。");
            }
        }
    </script>
</body>

</html>