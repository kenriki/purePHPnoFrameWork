<script src="/assets/js/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="/assets/js/chart.js"></script>

<style>
    /* 共通レイアウト */
    .memo-container {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-top: 20px;
    }

    .memo-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }

    .user-badge {
        background: #f8f9fa;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 0.9em;
        border: 1px solid #ddd;
    }

    /* 詳細表示用のスタイル */
    .memo-body {
        white-space: pre-wrap;
        line-height: 1.8;
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ececec;
        min-height: 200px;
        margin-bottom: 20px;
    }

    /* ダッシュボード（カレンダー・グラフ）用のスタイル */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-top: 25px;
    }

    #calendar {
        background: #fff;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #eee;
        min-height: 550px;
    }

    .chart-container {
        background: #fff;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #eee;
    }

    .btn-new-memo {
        display: block;
        text-align: center;
        background: #28a745;
        color: white !important;
        padding: 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="memo-container">
    <div class="memo-header">
        <h2 style="margin:0;">
            📄 <?= isset($page['target_date']) ? htmlspecialchars($page['target_date']) . " のメモ" : "メモ一覧" ?>
        </h2>
        <?php if (isset($page['target_date'])): ?>
            <a href="index.php?page=memo&action=list" style="font-size: 0.8rem; color: #007bff;">全件表示に戻る</a>
        <?php endif; ?>
    </div>

    <?php if (isset($page['memo']) && $page['memo']): ?>
        <div class="memo-detail-view">
            <div class="memo-info" style="color: #666; font-size: 0.85em; margin-bottom: 10px;">
                最終更新：<?= htmlspecialchars($page['memo']['update_date'] ?? '不明') ?>
            </div>
            <div class="memo-body"><?= htmlspecialchars($page['memo']['content']) ?></div>
            <div style="display: flex; gap: 10px;">
                <a href="index.php?page=home"
                    style="background:#6c757d; color:white; padding:10px 20px; border-radius:5px; text-decoration:none;">ホームへ戻る</a>
                <a href="index.php?page=memo&action=edit&id=<?= $page['memo']['id'] ?>"
                    style="background:#007bff; color:white; padding:10px 20px; border-radius:5px; text-decoration:none;">編集する</a>
            </div>
        </div>
        <hr style="margin: 40px 0; border: 0; border-top: 1px dashed #ccc;">
    <?php endif; ?>

    <div class="dashboard-grid">
        <div id="calendar"></div>
        <div class="analysis-side">
            <div class="chart-container">
                <h4 style="margin:0 0 15px 0;">📈 直近7日の活動</h4>
                <canvas id="activityChart"></canvas>
            </div>
            <a href="index.php?page=memo&action=new" class="btn-new-memo">＋ 新規メモ作成</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. データの安全な取得 ---
        const dbData = <?= json_encode($page['dashboard'] ?? ['events' => [], 'chart' => []]) ?>;
        // ログインユーザー名をPHPセッションから直接取得（確実性を上げるため）
        const loginUser = <?= json_encode($_SESSION['username'] ?? 'guest') ?>;

        // --- 2. カレンダーの描画 ---
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                height: 'auto',
                events: dbData.events || [], // データが空でもエラーにしない
                dayMaxEvents: true, // セルの高さに合わせて制限
                locale: 'ja',
                moreLinkText: '件', // 「+3 more」を「+3 件」にするなら
                dayMaxEvents: 3,
                eventClick: function (info) {
                    if (info.event.id) {
                        // 【修正】指定された編集URLへ遷移
                        window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}&username=${loginUser}`;
                        info.jsEvent.preventDefault();
                    }
                },
                // FullCalendar オプション内
                moreLinkClick: function (info) {
                    // クリックした日付（YYYY-MM-DD）を取得
                    const year = info.date.getFullYear();
                    const month = ('0' + (info.date.getMonth() + 1)).slice(-2);
                    const day = ('0' + info.date.getDate()).slice(-2);
                    const targetDate = `${year}-${month}-${day}`;

                    // 一覧画面（action=list）へ日付パラメータ付きで遷移
                    window.location.href = `index.php?page=memo&action=list&date=${targetDate}`;
                    return false; // デフォルトのポップアップ表示を防止
                },

                // 日付セルそのものをクリックした時は「その日の新規作成」へ
                dateClick: function (info) {
                    window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
                }
            });
            calendar.render();
        }

        // --- 3. グラフの描画（ループ・エラー対策版） ---
        const canvasEl = document.getElementById('activityChart');
        // canvasが存在し、かつデータが1件以上ある場合のみ実行
        if (canvasEl && dbData.chart && dbData.chart.length > 0) {
            const ctx = canvasEl.getContext('2d');

            // 既存のチャートがある場合は破棄（ループ防止策）
            if (window.myChart instanceof Chart) {
                window.myChart.destroy();
            }

            window.myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dbData.chart.map(d => (d.date ? d.date.slice(5) : '')),
                    datasets: [{
                        label: '件数',
                        data: dbData.chart.map(d => d.count),
                        backgroundColor: '#007bff'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    });
</script>