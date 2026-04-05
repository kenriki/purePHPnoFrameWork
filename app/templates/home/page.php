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
        margin-top: 20px;
    }

    /* ピン留めセクションのスタイル */
    .pinned-section {
        background: #fffdf0;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #f0e68c;
        margin-bottom: 0px;
    }

    .pinned-item {
        display: block;
        text-decoration: none;
        color: #333;
        background: #fff;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        font-size: 0.85rem;
        margin-top: 10px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .pinned-item:hover {
        transform: translateX(5px);
        border-color: #ffc107;
        background: #fffef5;
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
            📄 <?= isset($page['target_date']) ? htmlspecialchars($page['target_date']) . " のメモ" : "ダッシュボード" ?>
        </h2>
        <div class="header-actions">
            <?php if (isset($page['target_date'])): ?>
                <a href="index.php?page=memo&action=list" style="font-size: 0.85rem; color: #007bff; text-decoration: none;">全件表示に戻る</a>
            <?php else: ?>
                <span class="user-badge">User: <?= htmlspecialchars($page['login_user'] ?? 'Guest') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($page['memo']) && $page['memo']): ?>
        <div class="memo-detail-view">
            <div class="memo-header" style="border-bottom: none; margin-bottom: 10px;">
                <div class="memo-info" style="color: #666; font-size: 0.85em;">
                    最終更新：<?= htmlspecialchars($page['memo']['update_date'] ?? '不明') ?>
                </div>

                <div class="pin-action">
                    <?php $isPinned = $page['memo']['is_pinned'] ?? 0; ?>
                    <a href="index.php?page=memo&action=toggle_pin&id=<?= $page['memo']['id'] ?>&from=detail"
                        style="text-decoration: none; padding: 6px 12px; border: 1px solid <?= $isPinned ? '#ffc107' : '#ccc' ?>; border-radius: 20px; font-size: 0.9em; background: <?= $isPinned ? '#fffdf5' : '#fff' ?>; color: <?= $isPinned ? '#856404' : '#666' ?>;">
                        <?= $isPinned ? '📌 ピン留め中' : '📍 ピン留めする' ?>
                    </a>
                </div>
            </div>

            <div class="memo-body">
                <?= htmlspecialchars($page['memo']['content']) ?>
            </div>

            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <a href="index.php?page=home"
                    style="background:#6c757d; color:white; padding:10px 20px; border-radius:5px; text-decoration:none; font-size: 0.9em;">ホームへ戻る</a>
                <a href="index.php?page=memo&action=edit&id=<?= $page['memo']['id'] ?>"
                    style="background:#007bff; color:white; padding:10px 20px; border-radius:5px; text-decoration:none; font-size: 0.9em;">このメモを編集する</a>
            </div>
        </div>
        <hr style="margin: 40px 0; border: 0; border-top: 1px dashed #ccc;">
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="calendar-wrapper">
            <div id="calendar"></div>
        </div>

        <div class="analysis-side">
            <?php if (!empty($page['dashboard']['pinned'])): ?>
                <div class="pinned-section">
                    <h4 style="margin:0 0 10px 0; font-size:0.95rem; color:#856404; display: flex; align-items: center;">
                        <span style="margin-right: 5px;">📌</span> ピン留めされたメモ
                    </h4>
                    <?php foreach ($page['dashboard']['pinned'] as $pinnedMemo): ?>
                        <a href="<?= htmlspecialchars($pinnedMemo['url']) ?>" class="pinned-item">
                            <div style="font-weight:bold; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom: 3px;">
                                <?= htmlspecialchars($pinnedMemo['title']) ?>
                            </div>
                            <div style="font-size:0.75rem; color:#888;">
                                <?= date('Y/m/d H:i', strtotime($pinnedMemo['update_date'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a href="index.php?page=memo&action=new" class="btn-new-memo">＋ 新規メモを作成</a>

            <div class="chart-container">
                <h4 style="margin:0 0 15px 0; font-size: 1rem;">📈 直近7日の活動</h4>
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. データの安全な取得 ---
        const dbData = <?= json_encode($page['dashboard'] ?? ['events' => [], 'chart' => []]) ?>;
        const loginUser = <?= json_encode($_SESSION['username'] ?? 'guest') ?>;

        // --- 2. カレンダーの描画 (FullCalendar v6) ---
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                },
                events: dbData.events || [],
                dayMaxEvents: 3,
                moreLinkText: '件',
                
                // イベントクリック：編集画面へ
                eventClick: function (info) {
                    if (info.event.id) {
                        window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}&username=${loginUser}`;
                        info.jsEvent.preventDefault();
                    }
                },

                // 「他 +N 件」クリック：その日のリストへ
                moreLinkClick: function (info) {
                    const d = info.date;
                    const targetDate = `${d.getFullYear()}-${('0' + (d.getMonth() + 1)).slice(-2)}-${('0' + d.getDate()).slice(-2)}`;
                    window.location.href = `index.php?page=memo&action=list&date=${targetDate}`;
                    return false;
                },

                // 空白日付クリック：その日の新規作成へ
                dateClick: function (info) {
                    window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
                }
            });
            calendar.render();
        }

        // --- 3. グラフの描画 (Chart.js) ---
        const canvasEl = document.getElementById('activityChart');
        if (canvasEl && dbData.chart && dbData.chart.length > 0) {
            const ctx = canvasEl.getContext('2d');

            // 既存インスタンス破棄（メモリリーク防止）
            if (window.myChart instanceof Chart) {
                window.myChart.destroy();
            }

            window.myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dbData.chart.map(d => (d.date ? d.date.slice(5) : '')), // MM-DD形式
                    datasets: [{
                        label: '投稿数',
                        data: dbData.chart.map(d => d.count),
                        backgroundColor: 'rgba(0, 123, 255, 0.7)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, precision: 0 }
                        }
                    }
                }
            });
        }
    });
</script>