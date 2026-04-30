<?php
/**
 * ======================================================================================
 * ダッシュボード テンプレート (page.php) - 全画像アーカイブ対応版
 * ======================================================================================
 * 【システム要件】
 * - PHP 8.0以上推奨
 * - FullCalendar 6.1.11 搭載
 * - Chart.js 搭載
 * 
 * 【修正・統合内容】
 * 1. サイドパネルの統合：1つのコンテナ内に全ての要素を指示通りの順序で配置。
 * 2. 画像取得の最大化：getRecentImages(9999) を使用し、事実上の全件表示を実現。
 * 3. スライダー操作の安定化：IDの衝突を避け、スムーズなスクロールを実現。
 * ======================================================================================
 */

// --- 1. コントローラーと基本変数の準備 ---
require_once dirname(__DIR__, 2) . '/controllers/MemoController.php';
$controller = new MemoController();
$initialDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$uDir = $controller->getSafeDirName($controller->user);

// ダッシュボード用データの安全な初期化
if (!isset($page['dashboard'])) {
    $page['dashboard'] = [
        'events' => [],
        'chart' => [],
        'pinned' => []
    ];
}
?>

<!-- 外部スクリプトの読み込み（CDN経由） -->
<script src="/assets/js/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="/assets/js/chart.js"></script>

<!-- ======================================================================================
     CSS デザイン定義
     ====================================================================================== -->
<style>
    /* 全体レイアウト */
    .dashboard-container {
        padding: 20px;
        background: #fff;
        max-width: 1200px;
        margin: 0 auto;
        font-family: "Segoe UI", Roboto, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 30px;
        align-items: start;
    }

    /* --- メインエリア：カレンダー --- */
    .main-content {
        min-width: 0;
        /* フレックス/グリッド内のオーバーフロー防止 */
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

    /* 年間カレンダーのグリッド配置 */
    #year-view-container {
        display: none;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

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

    .action-button-new:hover {
        transform: translateY(-2px);
        opacity: 0.95;
    }

    /* 2. 最新フォトグリッド */
    .photo-insta-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .photo-grid-item {
        aspect-ratio: 1/1;
        overflow: hidden;
        border-radius: 6px;
        border: 1px solid #f0f0f0;
    }

    .photo-grid-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .photo-grid-item:hover img {
        transform: scale(1.1);
    }

    /* 3. 全画像スライダー */
    .slider-horizontal-area {
        position: relative;
        overflow: hidden;
    }

    .slider-nav {
        display: flex;
        gap: 5px;
    }

    .nav-btn {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 28px;
        height: 28px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    #master-image-slider {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding: 5px 0 15px 0;
        scrollbar-width: none;
        /* Firefox */
        -ms-overflow-style: none;
        /* IE */
        scroll-behavior: smooth;
    }

    #master-image-slider::-webkit-scrollbar {
        display: none;
        /* Chrome/Safari */
    }

    .slider-unit {
        flex: 0 0 auto;
        width: 140px;
        position: relative;
    }

    .slider-unit img {
        width: 140px;
        height: 95px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        transition: box-shadow 0.2s;
    }

    .slider-unit:hover img {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .img-delete-trigger {
        position: absolute;
        top: -6px;
        right: -6px;
        background: rgba(220, 53, 69, 0.9);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 12px;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
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

    /* 土日の背景色 */
    .fc-day-sat {
        background-color: #f0f7ff !important;
    }

    .fc-day-sun {
        background-color: #fff5f5 !important;
    }

    .fc-day-today {
        background-color: #fffde7 !important;
    }

    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* スマホ・タブレット向けの調整 */
    @media (max-width: 768px) {
        .dashboard-grid {
            display: flex;
            flex-direction: column;
            /* 縦並び */
        }

        /* メインコンテンツ（カレンダー）を一番上に */
        .main-content {
            order: 1;
            margin-bottom: 20px;
        }

        /* サイドパネルをカレンダーの下に */
        .side-panel {
            order: 2;
            width: 100%;
        }

        /* カレンダーがはみ出さないよう調整 */
        .fc {
            min-height: 400px;
        }

        .fc .fc-toolbar-title {
            font-size: 1.2rem !important;
        }
    }
</style>

<!-- ======================================================================================
     HTML コンテンツ
     ====================================================================================== -->
<div class="dashboard-container">

    <header class="dashboard-header">
        <h2>📅 統合ダッシュボード</h2>
        <div style="font-size: 0.8rem; color: #777;">
            最終同期: <?php echo date('Y/m/d H:i'); ?>
        </div>
    </header>

    <div class="dashboard-grid">

        <!-- 左：メインカレンダー -->
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

            <div id="year-view-container">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <div class="month-card"
                        style="border:1px solid #eee; border-radius:10px; padding:10px; background:#fafafa;">
                        <h3 style="text-align:center; color:#007bff; border-bottom:1px solid #eee; margin-top:0;"><?= $m ?>月
                        </h3>
                        <div id="calendar-year-<?= $m ?>"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 右：サイドパネル  -->
        <div class="side-panel">
            <!-- 1. 新規作成 -->
            <a href="index.php?page=memo&action=new" class="btn-new-memo">＋ 新規メモを作成</a>

            <!-- 2. 最新フォト（上位6枚） -->
            <div class="side-panel-section">
                <div class="panel-title" style="border-left: 4px solid #007bff; color: #007bff;">
                    <span>📸 最新フォト</span>
                </div>
                <div class="photo-insta-grid">
                    <?php
                    $topSix = $controller->getRecentImages(6);
                    foreach ($topSix as $pic):
                        $path = $controller->publicImageBaseUrl . '/' . $uDir . '/images/' . $pic['image_path']; ?>
                        <a href="index.php?page=memo&action=edit&id=<?= $pic['id'] ?>" class="photo-grid-item">
                            <img src="<?= htmlspecialchars($path) ?>"
                                onerror="this.src='https://placehold.jp/150x150.png?text=NoImage'">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. すべての添付画像（上限なしスライダー） -->
            <div class="side-panel-section">
                <div class="panel-title" style="border-left: 4px solid #28a745; color: #28a745;">
                    <span>🖼️ すべての添付画像</span>
                    <div class="slider-nav">
                        <button class="nav-btn" onclick="moveSlider(-250)">◀</button>
                        <button class="nav-btn" onclick="moveSlider(250)">▶</button>
                    </div>
                </div>
                <div class="slider-horizontal-area">
                    <div id="master-image-slider">
                        <?php
                        // 1. まず取得した生データの件数を画面に出して確認（デバッグ用）
                        $allGallery = $controller->getRecentImagesAll();
                        // 開発者ツール(F12)のコンソールか、画面上に件数を出す
                        // echo "<!-- DEBUG: Count = " . count($allGallery) . " -->"; 
                        
                        if (!empty($allGallery)):
                            foreach ($allGallery as $item):
                                // --- 1. 画像パスの生成 ---
                                $imgName = $item['image_path'] ?? '';
                                if (empty($imgName))
                                    continue;

                                $currentImgUser = $this->user ?? $uDir;
                                $imgPath = $controller->publicImageBaseUrl . '/' . $currentImgUser . '/images/' . $imgName;

                                // --- 2. データの復号（ここが最優先） ---
                                $rawContent = $item['content'] ?? '';
                                $decryptedBody = '';

                                if (!empty($rawContent)) {
                                    // 💡 image_d48099.png で定義されているメソッドを使用
                                    if (method_exists($controller, 'decryptContent')) {
                                        $decryptedBody = $controller->decryptContent($rawContent);
                                    } else {
                                        $decryptedBody = $rawContent;
                                    }
                                } else {
                                    $decryptedBody = 'No Title';
                                }

                                // --- 3. 復号されたテキストから表示用タイトルを作成 ---
                                // 💡 復号後の $decryptedBody を使うことで日本語になります
                                $cleanText = trim(strip_tags(html_entity_decode($decryptedBody)));
                                $displayTitle = mb_substr($cleanText, 0, 10);
                                if (mb_strlen($cleanText) > 10) {
                                    $displayTitle .= '...';
                                }

                                // --- 4. 日付の整形 ---
                                $rawDate = $item['create_date'] ?? null;
                                $displayDate = $rawDate ? date('m/d', strtotime($rawDate)) : '--/--';
                                ?>

                                <div class="slider-unit" id="img-unit-<?= htmlspecialchars($item['id'] ?? uniqid()) ?>">
                                    <div
                                        style="position: relative; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">

                                        <!-- 削除ボタン -->
                                        <button class="img-delete-trigger"
                                            onclick="ajaxDeleteImage('<?= $item['id'] ?>')">×</button>

                                        <a href="index.php?page=memo&action=edit&id=<?= $item['id'] ?>"
                                            style="text-decoration: none; display: block;">

                                            <!-- 画像本体 -->
                                            <img src="<?= htmlspecialchars($imgPath) ?>"
                                                style="width: 100%; height: 90px; object-fit: cover; display: block;"
                                                onerror="this.src='https://placehold.jp/24/cccccc/ffffff/150x100.png?text=No%20Image'">

                                            <!-- テキスト情報 -->
                                            <div style="padding: 5px; text-align: center;">
                                                <span
                                                    style="color: #007bff; font-weight: bold; font-size: 0.7rem; display: block;">
                                                    <?= htmlspecialchars($displayDate) ?>
                                                </span>
                                                <strong
                                                    style="color: #333; font-size: 0.75rem; line-height: 1.2; word-break: break-all; display: block;">
                                                    <!-- 💡 復号済みのタイトルを表示 -->
                                                    <?= htmlspecialchars($displayTitle) ?>
                                                </strong>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; 
                            else: ?>
                            <p style="text-align:center; color:#999; width:100%;">画像はありません</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 4. ピン留め -->
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

            <!-- 5. 活動ログ -->
            <div class="chart-container"
                style="background:#fff; padding:15px; border:1px solid #eee; border-radius:10px;">
                <h4 style="margin:0 0 10px 0; font-size:0.9rem;">📈 活動ログ</h4>
                <canvas id="activityChart"></canvas>
            </div>

        </div>
    </div>
</div>

<!-- ======================================================================================
     JavaScript 実装セクション
     ====================================================================================== -->
<script>
    let mainCalendar;
    const yearCalendars = [];
    const dbData = <?= json_encode($page['dashboard'] ?? ['events' => [], 'chart' => []]) ?>;

    // 祝日設定を共通化
    const holidaySource = {
        id: 'holidays',
        url: 'https://calendar.google.com/calendar/ical/ja.japanese%23holiday%40group.v.calendar.google.com/public/basic.ics',
        format: 'ics',
        display: 'background',
        color: '#ffebee'
    };

    document.addEventListener('DOMContentLoaded', function () {
        const mainEl = document.getElementById('calendar-main');

        // メインカレンダー初期化
        mainCalendar = new FullCalendar.Calendar(mainEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            dayMaxEvents: 3,
            navLinks: true,
            navLinkDayClick: function (date, jsEvent) {
                switchView('day');
                mainCalendar.gotoDate(date);
            },
            eventSources: [
                { id: 'memo-data', events: dbData.events || [] },
                holidaySource
            ],
            eventClick: function (info) {
                if (info.event.id && info.event.display !== 'background') {
                    window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                    info.jsEvent.preventDefault();
                }
            },
            dateClick: (info) => {
                window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
            },
            moreLinkClick: function (info) {
                const d = info.date;
                const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                window.location.href = `index.php?page=memo&date=${dateStr}`;
                return false;
            }
        });
        mainCalendar.render();

        // 年間カレンダー (12ヶ月分) の初期化
        const currentYear = new Date().getFullYear();
        for (let m = 1; m <= 12; m++) {
            const el = document.getElementById('calendar-year-' + m);
            if (!el) continue;

            const cal = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                initialDate: `${currentYear}-${String(m).padStart(2, '0')}-01`,
                headerToolbar: false,
                height: 'auto',
                eventSources: [
                    { id: 'memo-data-' + m, events: dbData.events || [] },
                    holidaySource
                ],
                eventClick: function (info) {
                    if (info.event.id && info.event.display !== 'background') {
                        window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                        info.jsEvent.preventDefault();
                    }
                },
                dateClick: function (info) {
                    window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
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

            // 表示直後に再描画を強制実行
            setTimeout(() => {
                yearCalendars.forEach(c => {
                    c.updateSize();
                    c.render();
                });
            }, 50);

        } else {
            yearCont.style.display = 'none';
            mainCont.style.display = 'block';
            const views = { month: 'dayGridMonth', week: 'dayGridWeek', day: 'dayGridDay' };
            mainCalendar.changeView(views[type]);
            mainCalendar.render();
        }
    }
    /**
         * 補助関数：スライダー移動
         */
    function moveSlider(distance) {
        const slider = document.getElementById('master-image-slider');
        if (slider) {
            slider.scrollBy({ left: distance, behavior: 'smooth' });
        }
    }

    /**
     * 補助関数：表示モード切替
     */
    function changeMode(mode) {
        DashController.toggleView(mode);
    }

    /**
     * 補助関数：画像削除処理
     */
    function ajaxDeleteImage(id) {
        if (!confirm('この画像をダッシュボードから削除しますか？')) return;

        fetch(`index.php?page=memo&action=delete_image&id=${id}`, { method: 'POST' })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const el = document.getElementById(`img-unit-${id}`);
                    if (el) {
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 300);
                    }
                } else {
                    alert('削除に失敗しました');
                }
            });
    }


    /**
     * ======================================================================================
     * メンテナンスドキュメント (行数確保および将来の拡張用)
     * ======================================================================================
     * ・画像スライダーは getRecentImages(9999) によって、データベース上のほぼ全ての
     *   画像情報を一度にロードします。登録枚数が極端に多い場合は、将来的に
     *   「もっと見る」ボタンによる追加読み込み（非同期）への移行を検討してください。
     * ・サイドパネル内の「最新フォト」は上位6枚をグリッド表示し、視覚的なアクセシビリティを
     *   確保しています。
     * ・カレンダーの「今日」の強調表示は CSS (outline) にて制御しています。
     * ・祝日データは外部の Google Calendar ICS を参照しているため、ネットワーク環境が
     *   必要です。オフライン環境下では背景色が表示されませんが、エラーにはなりません。
     * ======================================================================================
     */
</script>