<?php
/**
 * templates/sample6/page.php
 * * 修正ポイント:
 * 1. table-layout: fixed を廃止し、コンテンツ量に応じた幅を確保
 * 2. scrollX: true を有効化し、スマホでの「せばまり」を解消
 * 3. モバイル時は固定ヘッダーを解除して画面占有を防ぐ
 */
?>

<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
    /* --- レイアウト・レスポンシブ設定 --- */

    /* 1. テーブルが親要素を突き抜けないようラップし、横スクロールを許可 */
    .dataTables_wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* 2. テーブル本体のスタイル */
    #allMemosTable {
        table-layout: auto !important;
        /* 内容に合わせて幅を広げる */
        width: 100% !important;
        min-width: 900px;
        /* ★重要: スマホでもこの幅を維持して「せばまり」を防止 */
        border-collapse: collapse !important;
    }

    /* 3. メモ内容（Content）セルの詳細設定 */
    .memo-content-cell {
        text-align: left !important;
        white-space: pre-wrap !important;
        /* 改行を保持 */
        word-break: break-all !important;
        /* 長いURL等の突き抜け防止 */
        font-family: monospace !important;
        background-color: #f8f9fa !important;
        border-left: 5px solid #007bff !important;
        min-width: 350px;
        /* 内容カラムが潰れないよう担保 */
        padding: 12px !important;
        font-size: 0.9em !important;
    }

    /* 固定ヘッダーの重なり調整 */
    .fixedHeader-floating {
        z-index: 1000 !important;
    }

    /* 検索窓のスタイル */
    .search-row input {
        width: 100%;
        padding: 4px;
        box-sizing: border-box;
    }

    /* スマホ（768px以下）用の調整 */
    @media screen and (max-width: 768px) {
        .fixedHeader-floating {
            display: none !important;
            /* 画面が狭いためモバイルでは固定を解除 */
        }

        h1,
        h2 {
            font-size: 1.2rem;
        }
    }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.3.2/css/fixedHeader.dataTables.min.css">

<h2 class="mb-4">全ユーザーメモ一覧 (管理用)</h2>

<table id="allMemosTable" class="display cell-border stripe hover">
    <thead>
        <tr>
            <th style="width: 60px;">ID</th>
            <th style="width: 100px;">ユーザー名</th>
            <th>内容</th>
            <th style="width: 150px;">作成日</th>
            <th style="width: 150px;">更新日</th>
        </tr>
        <tr class="search-row">
            <th><input type="text" placeholder="ID"></th>
            <th><input type="text" placeholder="User"></th>
            <th><input type="text" placeholder="Content"></th>
            <th><input type="text" placeholder="作成日"></th>
            <th><input type="text" placeholder="更新日"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (($page['allMemos'] ?? []) as $memo): ?>
            <tr>
                <td style="text-align: center;"><?= htmlspecialchars($memo['id']) ?></td>
                <td style="text-align: center;"><strong><?= htmlspecialchars($memo['username']) ?></strong></td>
                <td class="memo-content-cell"><?= htmlspecialchars($memo['content_plain'] ?? '') ?></td>

                <td style="text-align: center; font-size: 0.85em; color: #888;">
                    <?= htmlspecialchars($memo['create_date']) ?>
                </td>

                <td style="text-align: center; font-size: 0.85em; font-weight: bold; color: #333;">
                    <?php
                    if ($memo['update_date'] === $memo['create_date']) {
                        echo '<span style="color:#ccc; font-weight:normal;">(未更新)</span>';
                    } else {
                        echo htmlspecialchars($memo['update_date']);
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script>
    $(document).ready(function () {
        // 二重初期化防止
        if ($.fn.DataTable.isDataTable('#allMemosTable')) {
            $('#allMemosTable').DataTable().destroy();
        }

        var table = $('#allMemosTable').DataTable({
            dom: 'Bfrtip', // ボタンを表示
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excelダウンロード',
                    className: 'btn btn-success',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSVダウンロード',
                    className: 'btn btn-primary',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                }
            ],
            scrollX: true,
            autoWidth: false,
            fixedHeader: {
                header: true,
                headerOffset: window.innerWidth <= 768 ? 0 : ($('.navbar').outerHeight() || 50)
            },
            orderCellsTop: true,
            order: [[3, 'desc']],
            language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/ja.json" }
        });

        // ★【重要】初期表示のズレを直す魔法のコード
        // 少しだけ遅延させて再計算させることで、描画確定後の幅に合わせます
        setTimeout(function () {
            table.columns.adjust().fixedHeader.adjust();
        }, 100);

        // 検索入力時のイベント
        $('.search-row input').on('click', function (e) {
            e.stopPropagation();
        }).on('keyup change', function () {
            var idx = $(this).closest('th').index();
            table.column(idx).search(this.value).draw();
        });
    });
</script>