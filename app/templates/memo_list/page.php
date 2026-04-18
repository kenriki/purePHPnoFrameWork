<?php
/**
 * templates/memo_list/page.php
 */
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

    /* メモ内容のデザイン */
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

    /* 「もっと見る」リンクのスタイル */
    .memo-toggle {
        color: #007bff;
        cursor: pointer;
        font-weight: bold;
        display: inline-block;
        margin-top: 5px;
        text-decoration: underline;
    }

    .memo-toggle:hover {
        color: #0056b3;
    }

    .full-text {
        display: none;
    }

    /* 初期状態は非表示 */

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

    /* 上部・下部のレイアウト調整 */
    .dataTables_wrapper .dataTables_info {
        float: left !important;
        padding-top: 10px !important;
        margin-right: 20px;
    }

    .dataTables_wrapper .dataTables_paginate {
        float: left !important;
        margin-bottom: 10px;
        padding-top: 5px !important;
    }

    .dataTables_length {
        clear: both;
        display: block;
        padding-top: 10px;
        margin-bottom: 10px;
    }

    .dt-buttons {
        margin-bottom: 15px;
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
            dom: 'iplBfrtip',
            buttons: [
                { extend: 'excelHtml5', text: 'Excel出力', className: 'btn-success', exportOptions: { columns: [0, 1, 2] } },
                { extend: 'csvHtml5', text: 'CSV出力', className: 'btn-primary', exportOptions: { columns: [0, 1, 2] } }
            ],
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "全件"]],
            pageLength: 25,
            autoWidth: false,
            order: [[1, 'desc']],
            columnDefs: [
                {
                    "targets": 0,
                    "className": "dt-left",
                    // もっと見る機能のレンダリング
                    "render": function (data, type, row) {
                        if (type === 'display' && data.length > 150) {
                            var shortText = data.substr(0, 150) + '...';
                            return '<div class="memo-wrapper">' +
                                '<span class="short-text">' + shortText + '</span>' +
                                '<span class="full-text">' + data + '</span>' +
                                '<br><span class="memo-toggle">もっと見る</span>' +
                                '</div>';
                        }
                        return data;
                    }
                },
                { "targets": [1, 2], "className": "dt-center" }
            ],
            language: {
                "emptyTable": "データがありません",
                "info": " _TOTAL_ 件中 _START_ から _END_ まで表示",
                "infoEmpty": " 0 件中 0 から 0 まで表示",
                "lengthMenu": "表示件数: _MENU_",
                "search": "検索:",
                "paginate": { "first": "先頭", "last": "最終", "next": "次", "previous": "前" }
            }
        });

        // クリックイベント（開閉切り替え）
        $('#myMemosTable').on('click', '.memo-toggle', function () {
            var wrapper = $(this).closest('.memo-wrapper');
            var isShort = wrapper.find('.full-text').is(':hidden');

            if (isShort) {
                wrapper.find('.short-text').hide();
                wrapper.find('.full-text').fadeIn(100);
                $(this).text('閉じる');
            } else {
                wrapper.find('.full-text').hide();
                wrapper.find('.short-text').fadeIn(100);
                $(this).text('もっと見る');
            }
        });
    });
</script>