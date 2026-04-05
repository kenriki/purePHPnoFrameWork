<script src="/assets/js/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="/assets/js/chart.js"></script>

<style>
    .dashboard-container {
        padding: 20px;
        background: #fff;
        max-width: 1200px;
        margin: 0 auto;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 25px;
        margin-top: 20px;
    }

    .view-selector {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
    }

    .view-btn {
        padding: 8px 16px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.9rem;
    }

    .view-btn.active {
        background: #007bff;
        color: #fff;
        border-color: #007bff;
    }

    /* サイドパネル */
    .side-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .pinned-section {
        background: #fffdf0;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #f0e68c;
    }

    .pinned-item {
        display: block;
        text-decoration: none;
        color: #333;
        background: #fff;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #eee;
        margin-top: 8px;
        font-size: 0.85rem;
    }

    .btn-new-memo {
        display: block;
        text-align: center;
        background: #28a745;
        color: white !important;
        padding: 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
    }

    /* --- 土日の背景色 (CSS) --- */
    .fc-day-sat {
        background-color: #f0f7ff !important;
    }

    /* 土曜：薄い青 */
    .fc-day-sun {
        background-color: #fff5f5 !important;
    }

    /* 日曜：薄い赤 */
    .fc-day-today {
        background-color: #fffde7 !important;
    }

    /* 今日：薄い黄 */

    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-container">
    <div
        style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #007bff; padding-bottom:10px;">
        <h2 style="margin:0;">📅 ダッシュボード</h2>
    </div>

    <div class="dashboard-grid">
        <div class="main-content">
            <div class="view-selector">
                <button class="view-btn active" onclick="switchView('month')">月</button>
                <button class="view-btn" onclick="switchView('week')">週</button>
                <button class="view-btn" onclick="switchView('day')">日</button>
                <button class="view-btn" onclick="switchView('year')">年 (12ヶ月)</button>
            </div>

            <div id="main-calendar-container">
                <div id="calendar-main"></div>
            </div>

            <div id="year-view-container" style="display: none;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <div class="month-card"
                        style="border:1px solid #eee; border-radius:10px; padding:10px; margin-bottom:15px; background:#fafafa;">
                        <h3 style="text-align:center; color:#007bff; border-bottom:1px solid #eee;"><?= $m ?>月</h3>
                        <div id="calendar-year-<?= $m ?>"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="side-panel">
            <a href="index.php?page=memo&action=new" class="btn-new-memo">＋ 新規メモを作成</a>

            <?php if (!empty($page['dashboard']['pinned'])): ?>
                <div class="pinned-section">
                    <h4 style="margin:0; font-size:0.9rem; color:#856404;">📌 ピン留め</h4>
                    <?php foreach ($page['dashboard']['pinned'] as $p): ?>
                        <a href="<?= htmlspecialchars($p['url']) ?>" class="pinned-item">
                            <strong><?= htmlspecialchars($p['title']) ?></strong>
                            <div style="font-size:0.7rem; color:#999;"><?= $p['update_date'] ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="chart-container"
                style="background:#fff; padding:15px; border:1px solid #eee; border-radius:10px;">
                <h4 style="margin:0 0 10px 0; font-size:0.9rem;">📈 活動ログ</h4>
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    let mainCalendar;
    const yearCalendars = [];
    const dbData = <?= json_encode($page['dashboard'] ?? ['events' => [], 'chart' => []]) ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const mainEl = document.getElementById('calendar-main');

        mainCalendar = new FullCalendar.Calendar(mainEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

            // --- 重要：メモのだぶりを防ぐためここには events を書かない ---
            dayMaxEvents: 3,

            // --- moreクリックでその日の新規作成画面へ ---
            moreLinkClick: function (info) {
                const d = info.date;
                const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

                // action=new ではなく 一覧表示(index.php?page=memo) に日付を渡す
                window.location.href = `index.php?page=memo&date=${dateStr}`;
                return false;
            },

            // --- 日付クリックや詳細リンク設定 ---
            navLinks: true,
            navLinkDayClick: function (date, jsEvent) {
                switchView('day');
                mainCalendar.gotoDate(date);
            },

            views: {
                dayGridMonth: { dayMaxEvents: 3 },
                dayGridWeek: { dayMaxEvents: false },
                dayGridDay: { dayMaxEvents: false }
            },

            // --- データの読み込み（ここに集約） ---
            eventSources: [
                {
                    id: 'memo-data',
                    events: dbData.events || []
                },
                {
                    id: 'holidays',
                    url: 'https://calendar.google.com/calendar/ical/ja.japanese%23holiday%40group.v.calendar.google.com/public/basic.ics',
                    format: 'ics',
                    display: 'background',
                    color: '#ffebee'
                }
            ],

            eventClick: function (info) {
                if (info.event.id) {
                    window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                    info.jsEvent.preventDefault();
                }
            },
            dateClick: (info) => {
                window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
            }
        });
        mainCalendar.render();

        // 年間カレンダー (12ヶ月分) の初期化
        const currentYear = new Date().getFullYear();
        for (let m = 1; m <= 12; m++) {
            const el = document.getElementById('calendar-year-' + m);
            const cal = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                initialDate: `${currentYear}-${String(m).padStart(2, '0')}-01`,
                headerToolbar: false,
                height: 'auto',
                events: dbData.events || [], // 年間側もだぶりがないか確認
                eventClick: (info) => {
                    if (info.event.id) {
                        window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                    }
                }
            });
            yearCalendars.push(cal);
        }

        // 活動グラフ (Chart.js)
        const ctx = document.getElementById('activityChart');
        if (ctx && dbData.chart && dbData.chart.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dbData.chart.map(d => d.date.slice(5)),
                    datasets: [{ label: '投稿', data: dbData.chart.map(d => d.count), backgroundColor: '#007bff' }]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    });

    function switchView(type) {
        const mainCont = document.getElementById('main-calendar-container');
        const yearCont = document.getElementById('year-view-container');
        const btns = document.querySelectorAll('.view-btn');

        btns.forEach(b => {
            b.classList.remove('active');
            if (b.getAttribute('onclick').includes(`'${type}'`)) {
                b.classList.add('active');
            }
        });

        if (type === 'year') {
            mainCont.style.display = 'none';
            yearCont.style.display = 'grid';
            yearCalendars.forEach(c => c.render());
        } else {
            yearCont.style.display = 'none';
            mainCont.style.display = 'block';

            // 時間軸を表示させない設定
            const views = {
                month: 'dayGridMonth',
                week: 'dayGridWeek',
                day: 'dayGridDay'
            };
            mainCalendar.changeView(views[type]);
            mainCalendar.render();
        }
    }
</script>