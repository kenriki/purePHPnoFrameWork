<?php
/**
 * templates/memo_list/page.php
 */

// --- 1. DB接続の確保 ---
try {
    $db_ptr = getDB();
} catch (Error $e) {
    require_once __DIR__ . '/../../dbconfig.php';
    $db_ptr = getDB();
}

$memoController = new MemoController();
$displayMemos = [];
$debug_log = [];

// --- 2. ユーザー特定 (セッションから) ---
$session_user = $_SESSION['user'] ?? null;
$db_user_id = $session_user['username'] ?? $_SESSION['username'] ?? null;
$user_email = $session_user['email'] ?? $_SESSION['email'] ?? '未取得';

// --- 3. メモ取得ロジック ---
if ($db_ptr instanceof PDO && $db_user_id) {
    try {
        // sample_db.user_memos から取得
        $sql = "SELECT id, username, content, create_date, update_date 
                FROM sample_db.user_memos 
                WHERE username = :username 
                ORDER BY id DESC";

        $stmt = $db_ptr->prepare($sql);
        $stmt->execute([':username' => $db_user_id]);
        $displayMemos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_log[] = "Connected via getDB(). Found " . count($displayMemos) . " memos.";
    } catch (PDOException $e) {
        $debug_log[] = "SQL Error: " . $e->getMessage();
    }
} else {
    $debug_log[] = "Connection or UserID missing.";
}

// --- 4. 復号処理 ---
foreach ($displayMemos as &$memo) {
    $rawData = $memo['content'] ?? '';
    $memo['safe_content'] = !empty($rawData) ? $memoController->decryptContent($rawData) : '---';
}
unset($memo);
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- DataTables & Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

<style>
    /* テーブル全体の基本設定 */
    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        /* これで幅を固定してはみ出しを防ぐ */
    }

    td {
        word-wrap: break-word;
        /* 長い文章を強制改行 */
    }

    /* スマホ用の設定（画面幅が768px以下の時） */
    @media screen and (max-width: 768px) {

        /* テーブルを普通のブロック要素に変える */
        table,
        thead,
        tbody,
        th,
        td,
        tr {
            display: block;
        }

        /* ヘッダー（メモ内容、作成日などの見出し）を隠す */
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tr {
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            background: #fff;
        }

        td {
            border: none;
            position: relative;
            padding-left: 40% !important;
            /* 左側にラベル用のスペースを作る */
            text-align: left;
        }

        /* 疑似要素で「ラベル」を表示する */
        td::before {
            content: attr(data-label);
            /* HTMLのdata-label属性を読み取る */
            position: absolute;
            left: 10px;
            width: 35%;
            font-weight: bold;
            color: #666;
        }
    }

    .memo-content-cell {
        text-align: left !important;
        white-space: pre-wrap !important;
        word-break: break-all !important;
        border-left: 5px solid #198754 !important;
        padding: 12px !important;
    }

    .memo-toggle {
        color: #007bff;
        cursor: pointer;
        font-weight: bold;
        text-decoration: underline;
        font-size: 0.85em;
    }

    .full-text {
        display: none;
    }

    .btn-excel {
        background-color: #28a745 !important;
        color: white !important;
        border: none !important;
    }

    .btn-csv {
        background-color: #007bff !important;
        color: white !important;
        border: none !important;
    }

    .dataTables_wrapper {
        margin-top: 20px;
    }
</style>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>マイメモ一覧</h2>
        <span class="text-muted small">System Date: 2026-05-04 08:43</span>
    </div>

    <!-- ステータスパネル -->
    <div class="card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1">
                        <i class="fas fa-user-circle text-primary"></i>
                        <strong><?= htmlspecialchars($session_user['user_display_name'] ?? '剣持力') ?></strong>
                    </h5>
                    <div class="text-muted small">
                        Email: <code><?= htmlspecialchars($user_email) ?></code> |
                        ID: <strong><?= htmlspecialchars($db_user_id ?? '---') ?></strong>
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-success">
                    <i class="fas fa-database"></i> データ取得完了 (<?= count($displayMemos) ?> 件)
                </div>
            </div>
            <?php if (!empty($debug_log)): ?>
                <div class="mt-2 text-muted" style="font-size: 0.7rem; font-family: monospace;">
                    [Log] <?= htmlspecialchars(implode(' | ', $debug_log)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table id="memoTable" class="display cell-border stripe hover" style="width:100%">
            <thead>
                <tr class="table-dark">
                    <th>メモ内容</th>
                    <th style="width:160px">作成日</th>
                    <th style="width:160px">最終更新</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayMemos as $memo): ?>
                    <tr>
                        <td class="memo-content-cell">
                            <?= htmlspecialchars($memo['safe_content']) ?>
                        </td>
                        <td class="text-center small"><?= htmlspecialchars($memo['create_date']) ?></td>
                        <td class="text-center small">
                            <?= htmlspecialchars($memo['update_date'] ?? $memo['create_date']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!-- Excel出力に必須のライブラリ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        // ID を "memoTable" に統一して初期化
        var table = $('#memoTable').DataTable({
            dom: 'Blfrtip', // B:Buttons, l:Length, f:Filter, r:Processing, t:Table, i:Info, p:Pagination
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel出力',
                    className: 'btn-excel btn-sm'
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV出力',
                    className: 'btn-csv btn-sm'
                }
            ],
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [{
                "targets": 0,
                "render": function (data, type, row) {
                    // 表示用(display)かつ長い文字列の場合のみ「もっと見る」を適用
                    if (type === 'display' && data && data.length > 150) {
                        return '<div class="memo-wrapper">' +
                            '<span class="short-text">' + data.substr(0, 150) + '...</span>' +
                            '<span class="full-text">' + data + '</span>' +
                            '<br><span class="memo-toggle">もっと見る</span>' +
                            '</div>';
                    }
                    return data;
                }
            }],
            language: {
                "search": "絞り込み検索:",
                "info": "_TOTAL_ 件中 _START_ から _END_ まで表示",
                "lengthMenu": "_MENU_ 件表示",
                "paginate": { "next": "次へ", "previous": "前へ" }
            }
        });

        // 「もっと見る / 閉じる」のクリックイベント
        $('#memoTable').on('click', '.memo-toggle', function () {
            var wrapper = $(this).closest('.memo-wrapper');
            var isHidden = wrapper.find('.full-text').is(':hidden');
            wrapper.find('.full-text').toggle(isHidden);
            wrapper.find('.short-text').toggle(!isHidden);
            $(this).text(isHidden ? '閉じる' : 'もっと見る');
        });
    });
</script>