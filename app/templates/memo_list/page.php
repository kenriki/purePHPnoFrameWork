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

    /* テーブル全体のレスポンシブ対応 */
    .table-responsive {
        width: 100%;
        margin-bottom: 1rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
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
        min-width: 320px;
        padding: 10px !important;
        font-size: 0.9em !important;
    }

    /* ボタンのデザインを強制適用 */
    .btn-excel {
        background-color: #28a745 !important;
        color: white !important;
        border-radius: 4px;
        padding: 4px 12px;
        margin-right: 5px;
        border: none;
        cursor: pointer;
    }

    .btn-csv {
        background-color: #007bff !important;
        color: white !important;
        border-radius: 4px;
        padding: 4px 12px;
        border: none;
        cursor: pointer;
    }

    /* UI要素の配置調整（スマホで重ならないように） */
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        display: inline-block !important;
        margin-top: 10px !important;
        padding: 5px !important;
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

    <div class="table-responsive">
        <table id="myMemosTable" class="display cell-border stripe hover">
            <thead>
                <tr>
                    <th>メモ内容</th>
                    <th style="width: 120px;">作成日時</th>
                    <th style="width: 120px;">最終更新</th>
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
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        var table = $('#myMemosTable').DataTable({
            // 'B' を含めることでボタンを表示。上下に情報(i)とページング(p)を配置
            dom: 'iplBfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excel出力',
                    className: 'btn-excel',
                    title: 'MyMemos_' + new Date().toISOString().slice(0, 10)
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSV出力',
                    className: 'btn-csv',
                    title: 'MyMemos_' + new Date().toISOString().slice(0, 10)
                }
            ],
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "全件"]],
            pageLength: 25,
            autoWidth: false,
            order: [[1, 'desc']],
            columnDefs: [
                {
                    "targets": 0,
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
                }
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

        // もっと見る/閉じる の切り替え
        $('#myMemosTable').on('click', '.memo-toggle', function () {
            var wrapper = $(this).closest('.memo-wrapper');
            var isShort = wrapper.find('.full-text').is(':hidden');
            wrapper.find('.short-text').toggle(!isShort);
            wrapper.find('.full-text').toggle(isShort);
            $(this).text(isShort ? '閉じる' : 'もっと見る');
        });
    });
</script>