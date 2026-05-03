<?php
/**
 * templates/memo_list/page.php
 * MemoController を使用して復号を行う
 */

// --- 1. MemoController の準備 ---
// 読み込まれていない場合は require などの対応が必要ですが、
// 通常はオートローダーまたはコントローラー側から呼ばれている前提です。
$memoController = new MemoController();

// --- 2. データの取得 ---
$displayMemos = [];
$db_ptr = $pdo ?? $db ?? $conn ?? null;

if ($db_ptr) {
    try {
        // user_memos テーブルから取得
        $sql = "SELECT * FROM user_memos ORDER BY id DESC";
        $res = $db_ptr->query($sql);
        if ($res) {
            $displayMemos = $res->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // エラーハンドリング
    }
}

// --- 3. コントローラーの復号メソッドを適用 ---
foreach ($displayMemos as &$memo) {
    // テーブル内のデータが入っているカラムを確認
    $rawData = $memo['content'] ?? $memo['memo'] ?? '';

    if (!empty($rawData)) {
        // MemoController の decryptContent() を直接使用
        $memo['safe_content'] = $memoController->decryptContent($rawData);
    } else {
        $memo['safe_content'] = '---';
    }
}
unset($memo);
?>

<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
    .dataTables_wrapper {
        width: 100%;
        margin-top: 10px;
    }

    .table-responsive {
        width: 100%;
        margin-bottom: 1rem;
        overflow-x: auto;
    }

    #myMemosTable {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    .memo-content-cell {
        text-align: left !important;
        white-space: pre-wrap !important;
        word-break: break-all !important;
        font-family: 'Consolas', 'Monaco', monospace !important;
        background-color: #fcfcfc !important;
        border-left: 5px solid #007bff !important;
        min-width: 320px;
        padding: 12px !important;
        font-size: 0.95em !important;
    }

    .btn-excel {
        background-color: #28a745 !important;
        color: white !important;
        padding: 4px 12px;
        border: none;
        cursor: pointer;
        margin-right: 5px;
    }

    .btn-csv {
        background-color: #007bff !important;
        color: white !important;
        padding: 4px 12px;
        border: none;
        cursor: pointer;
    }

    .memo-toggle {
        color: #007bff;
        cursor: pointer;
        font-weight: bold;
        text-decoration: underline;
    }

    .full-text {
        display: none;
    }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

<div class="container-fluid mt-3">
    <h2 class="mb-4">マイメモ一覧</h2>

    <div style="background: #eef2f7; padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 0.9em;">
        表示件数: <strong><?= count($displayMemos) ?></strong> 件 |
    </div>

    <div class="table-responsive">
        <table id="myMemosTable" class="display cell-border stripe hover">
            <thead>
                <tr>
                    <th>メモ内容</th>
                    <th style="width: 150px;">作成日時</th>
                    <th style="width: 150px;">最終更新</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayMemos as $memo): ?>
                    <tr>
                        <td class="memo-content-cell">
                            <?= htmlspecialchars($memo['safe_content']) ?>
                        </td>
                        <td style="text-align: center;">
                            <?= htmlspecialchars($memo['created_at'] ?? $memo['create_date'] ?? '') ?>
                        </td>
                        <td style="text-align: center;">
                            <?php
                            $u = $memo['updated_at'] ?? $memo['update_date'] ?? '';
                            $c = $memo['created_at'] ?? $memo['create_date'] ?? '';
                            echo (empty($u) || $u === $c) ? '<span style="color:#bbb;">(未更新)</span>' : '<strong>' . htmlspecialchars($u) . '</strong>';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#myMemosTable').DataTable({
            dom: 'iplBfrtip',
            buttons: [
                { extend: 'excelHtml5', text: 'Excel出力', className: 'btn-excel' },
                { extend: 'csvHtml5', text: 'CSV出力', className: 'btn-csv' }
            ],
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [{
                "targets": 0,
                "render": function (data, type, row) {
                    if (type === 'display' && data && data.length > 200) {
                        return '<div class="memo-wrapper">' +
                            '<span class="short-text">' + data.substr(0, 200) + '...</span>' +
                            '<span class="full-text">' + data + '</span>' +
                            '<br><span class="memo-toggle">もっと見る</span>' +
                            '</div>';
                    }
                    return data;
                }
            }],
            language: { "search": "検索:", "info": "_TOTAL_ 件中 _START_ から _END_ まで表示" }
        });

        $('#myMemosTable').on('click', '.memo-toggle', function () {
            var wrapper = $(this).closest('.memo-wrapper');
            var isHidden = wrapper.find('.full-text').is(':hidden');
            wrapper.find('.full-text').toggle(isHidden);
            wrapper.find('.short-text').toggle(!isHidden);
            $(this).text(isHidden ? '閉じる' : 'もっと見る');
        });
    });
</script>