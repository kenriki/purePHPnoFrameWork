<?php
/**
 * templates/memo_list/page.php
 */
// Controllerから渡された $page 配列内のデータを使用するように調整
$displayMemos = $page['myMemos'] ?? [];
?>

<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
    .dataTables_wrapper {
        width: 100%;
        margin-top: 10px;
    }

    #myMemosTable {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    /* メモ内容の左寄せとデザイン */
    .memo-content-cell {
        text-align: left !important;
        white-space: pre-wrap !important;
        word-break: break-all !important;
        font-family: 'Consolas', 'Monaco', monospace !important;
        background-color: #fcfcfc !important;
        border-left: 5px solid #28a745 !important;
        min-width: 450px;
        padding: 12px !important;
        font-size: 0.95em !important;
        line-height: 1.6;
    }

    .btn-success {
        background-color: #28a745 !important;
        color: white !important;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-primary {
        background-color: #007bff !important;
        color: white !important;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
    }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

<div class="container-fluid mt-3">
    <h2 class="mb-4">マイメモ一覧</h2>

    <table id="myMemosTable" class="display cell-border stripe hover">
        <thead>
            <tr>
                <th>メモ内容</th>
                <th style="width: 160px;">作成日時</th>
                <th style="width: 160px;">最終更新</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayMemos as $memo): ?>
                <tr>
                    <td class="memo-content-cell"><?= htmlspecialchars($memo['content_plain'] ?? '') ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($memo['create_date'] ?? '') ?></td>
                    <td style="text-align: center;">
                        <?php if (($memo['update_date'] ?? '') === ($memo['create_date'] ?? '')): ?>
                            <span style="color:#bbb;">(未更新)</span>
                        <?php else: ?>
                            <strong><?= htmlspecialchars($memo['update_date'] ?? '') ?></strong>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        var table = $('#myMemosTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excel出力',
                    className: 'btn-success',
                    title: 'MyMemos_' + new Date().toISOString().slice(0, 10),
                    exportOptions: { columns: [0, 1, 2] }
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSV出力',
                    className: 'btn-primary',
                    title: 'MyMemos_' + new Date().toISOString().slice(0, 10),
                    exportOptions: { columns: [0, 1, 2] }
                }
            ],
            autoWidth: false,
            order: [[1, 'desc']],
            columnDefs: [
                { "targets": 0, "className": "dt-left" },
                { "targets": [1, 2], "className": "dt-center" }
            ],
            pageLength: 25,
            // 日本語化を確実にするため直接定義を記述
            language: {
                "emptyTable": "データがありません",
                "info": " _TOTAL_ 件中 _START_ から _END_ まで表示",
                "infoEmpty": " 0 件中 0 から 0 まで表示",
                "search": "検索:",
                "paginate": {
                    "first": "先頭",
                    "last": "最終",
                    "next": "次",
                    "previous": "前"
                }
            }
        });
    });
</script>