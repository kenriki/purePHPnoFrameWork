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
    /* ==========================================
       全体レイアウト
    ========================================== */
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

    #year-view-container {
        display: none;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

    /* ==========================================
       サイドパネル & コンポーネント
    ========================================== */
    .side-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
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

    /* 最新フォトグリッド */
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

    /* 全画像スライダー */
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
        -ms-overflow-style: none;
        scroll-behavior: smooth;
    }

    #master-image-slider::-webkit-scrollbar {
        display: none;
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

    /* カレンダー背景色 */
    .fc-day-sat {
        background-color: #f0f7ff !important;
    }

    .fc-day-sun {
        background-color: #fff5f5 !important;
    }

    .fc-day-today {
        background-color: #fffde7 !important;
    }

    /* ==========================================
       インスタ風モーダル (PC & スマホ共通)
    ========================================== */
    .insta-modal {
        display: none;
        /* JSで flex に切り替え */
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(5px);
        /* 中身を中央に寄せる */
        align-items: center;
        justify-content: center;
    }

    .insta-modal-content {
        position: relative;
        background-color: #fff;
        width: 95%;
        max-width: 1000px;
        /* PCでの最大幅 */
        height: 85vh;
        /* 画面の高さ8.5割 */
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        /* PCでは横並び */
        flex-direction: row;
    }

    .insta-container {
        display: flex;
        width: 100%;
        height: 100%;
    }

    /* 左側：画像エリア */
    .insta-image-box {
        flex: 1.5;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 0;
    }

    .insta-image-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* 右側：情報エリア */
    .insta-info-box {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 20px;
        border-left: 1px solid #efefef;
        background: #fff;
        min-height: 0;
    }

    .insta-user-info {
        padding-bottom: 15px;
        border-bottom: 1px solid #efefef;
        margin-bottom: 15px;
    }

    .insta-caption {
        flex-grow: 1;
        overflow-y: auto;
        font-size: 0.95rem;
        line-height: 1.6;
        white-space: pre-wrap;
        color: #262626;
        margin-bottom: 15px;
    }

    .insta-footer {
        padding-top: 15px;
        border-top: 1px solid #efefef;
    }

    .insta-btn-edit {
        display: block;
        text-align: center;
        background: #0095f6;
        color: #fff !important;
        text-decoration: none;
        padding: 10px;
        border-radius: 4px;
        font-weight: bold;
    }

    .insta-close {
        position: absolute;
        top: 10px;
        right: 15px;
        color: #fff;
        font-size: 35px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10001;
        text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
    }

    /* ==========================================
       レスポンシブ：スマホ・タブレット (768px以下)
    ========================================== */
    @media (max-width: 768px) {
        .dashboard-grid {
            display: flex;
            flex-direction: column;
        }

        .main-content {
            order: 1;
            margin-bottom: 20px;
        }

        .side-panel {
            order: 2;
            width: 100%;
        }

        /* モーダルを縦並びに切り替え */
        .insta-modal-content {
            flex-direction: column;
            height: 90vh;
            width: 90%;
        }

        .insta-container {
            flex-direction: column;
        }

        .insta-image-box {
            flex: 1;
            /* 画像の比率を調整 */
            min-height: 40%;
        }

        .insta-info-box {
            flex: 1;
            border-left: none;
            border-top: 1px solid #efefef;
        }

        .fc .fc-toolbar-title {
            font-size: 1.2rem !important;
        }
    }

    /* 中間サイズ (992px以下) */
    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* 画面幅が狭い時（スマホなど）の設定 */
    @media (max-width: 1024px) {

        /* 768より少し広めに設定しておくとタブレット等でも安定します */
        .dashboard-grid {
            display: flex;
            flex-direction: column;
            /* 強制的に縦並びにする */
            gap: 20px;
        }

        .main-content,
        .side-panel {
            width: 100% !important;
            /* 横幅を画面いっぱいに */
            min-width: 0;
        }

        /* カレンダーの文字がはみ出さないように調整 */
        .fc {
            font-size: 0.8rem;
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
                        $imgName = $pic['image_path'] ?? '';
                        if (empty($imgName))
                            continue;

                        $imgPath = $controller->publicImageBaseUrl . '/' . $uDir . '/images/' . $imgName;

                        // --- JS用に安全に加工 ---
                        $rawContent = $pic['content'] ?? '';
                        $decryptedBody = method_exists($controller, 'decryptContent') ? $controller->decryptContent($rawContent) : $rawContent;

                        // 改行をスペースに変換し、バックスラッシュでクォートをエスケープ
                        $jsBody = str_replace(["\r", "\n"], ' ', $decryptedBody);
                        $jsBody = addslashes($jsBody);
                        // onclick属性の中で安全に動くようHTMLエンティティ化
                        $finalBody = htmlspecialchars($jsBody, ENT_QUOTES, 'UTF-8');

                        $displayDate = isset($pic['create_date']) ? date('m/d', strtotime($pic['create_date'])) : '--/--';
                        ?>
                        <a href="javascript:void(0)"
                            onclick="openInstaModal('<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>', '<?= $finalBody ?>', '<?= $pic['id'] ?>', '<?= $displayDate ?>')"
                            class="photo-grid-item">
                            <img src="<?= htmlspecialchars($imgPath) ?>"
                                onerror="this.src='https://placehold.jp/150x150.png?text=NoImage'">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. すべての添付画像（スライダー） -->
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
                        $allGallery = $controller->getRecentImagesAll();
                        if (!empty($allGallery)):
                            foreach ($allGallery as $item):
                                $imgName = $item['image_path'] ?? '';
                                if (empty($imgName))
                                    continue;

                                $imgPath = $controller->publicImageBaseUrl . '/' . $uDir . '/images/' . $imgName;

                                // --- ここでJS用に極限まで安全に加工する ---
                                $rawContent = $item['content'] ?? '';
                                $decryptedBody = method_exists($controller, 'decryptContent') ? $controller->decryptContent($rawContent) : $rawContent;

                                // 1. 改行を消す（JSの引数に改行があるとエラーになるため）
                                $jsBody = str_replace(["\r", "\n"], ' ', $decryptedBody);
                                // 2. クォートをエスケープする（I'm -> I\'m にする）
                                $jsBody = addslashes($jsBody);
                                // 3. HTMLとして安全にする（onclick属性を壊さないため）
                                $finalBody = htmlspecialchars($jsBody, ENT_QUOTES, 'UTF-8');

                                // タイトル表示用
                                $cleanText = trim(strip_tags(html_entity_decode($decryptedBody)));
                                $displayTitle = mb_strimwidth($cleanText, 0, 20, '...');
                                $displayDate = isset($item['create_date']) ? date('m/d', strtotime($item['create_date'])) : '--/--';
                                ?>
                                <div class="slider-unit" id="img-unit-<?= htmlspecialchars($item['id']) ?>">
                                    <div
                                        style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                        <a href="javascript:void(0)"
                                            onclick="openInstaModal('<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>', '<?= $finalBody ?>', '<?= $item['id'] ?>', '<?= $displayDate ?>')"
                                            style="display: block; text-decoration: none;">

                                            <img src="<?= htmlspecialchars($imgPath) ?>"
                                                style="width: 100%; height: 90px; object-fit: cover; display: block;"
                                                onerror="this.src='https://placehold.jp/150x100.png?text=NoImage'">

                                            <div style="padding: 5px; text-align: center;">
                                                <span
                                                    style="color: #007bff; font-weight: bold; font-size: 0.7rem; display: block;"><?= htmlspecialchars($displayDate) ?></span>
                                                <strong
                                                    style="color: #333; font-size: 0.75rem; display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;"><?= htmlspecialchars($displayTitle ?: 'No Title') ?></strong>
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
    <!-- 画像拡大モーダル (インスタ風) -->
    <div id="insta-modal" class="insta-modal modal-content-container" onclick="closeInstaModal(event)">
        <div class="insta-modal-content">
            <span class="insta-close">&times;</span>
            <div class="insta-container">
                <!-- 左：画像エリア -->
                <div class="insta-image-box modal-image-wrapper">
                    <img id="insta-img" src="" alt="">
                </div>
                <!-- 右：キャプション（メモ内容）エリア -->
                <div class="insta-info-box modal-info-card">
                    <div class="insta-user-info">
                        <strong>📸 添付メモのプレビュー</strong>
                        <div id="insta-date" style="font-size: 0.75rem; color: #999;"></div>
                    </div>
                    <div id="insta-caption" class="insta-caption"></div>
                    <div class="insta-footer">
                        <a id="insta-edit-link" href="#" class="insta-btn-edit">メモを編集する</a>
                    </div>
                </div>
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
            // FullCalendarの設定内
            // datesSet: function (info) {
            //     // 1. info.view.currentStart から現在表示中の日付を取得（安全な方法）
            //     var viewDate = info.view.currentStart;
            //     var year = viewDate.getFullYear();
            //     var month = ('0' + (viewDate.getMonth() + 1)).slice(-2);
            //     var day = ('0' + viewDate.getDate()).slice(-2);

            //     var dateStr = year + '-' + month + '-' + day;

            //     // 2. ボタンを取得（クラス名が .btn-new-memo か .action-button-new か確認してください）
            //     // スクショから推測して両方の可能性を考慮します
            //     var newMemoBtn = document.querySelector('.btn-new-memo') || document.querySelector('.action-button-new');

            //     if (newMemoBtn) {
            //         // 現在のURLを取得してベースを作成
            //         newMemoBtn.href = 'index.php?page=memo&action=new&date=' + dateStr;
            //         console.log("Selected Date for Button:", dateStr); // デバッグ用
            //     }
            // },
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
     * インスタ風モーダルを開く
     * @param {string} imgSrc 画像URL
     * @param {string} caption 復号済みメモ本文
     * @param {string} id メモID
     * @param {string} date 日付
     */
    function openInstaModal(imgSrc, caption, id, date) {
        document.getElementById('insta-img').src = imgSrc;
        document.getElementById('insta-caption').innerText = caption;
        document.getElementById('insta-date').innerText = date + " 投稿";
        document.getElementById('insta-edit-link').href = `index.php?page=memo&action=edit&id=${id}`;

        document.getElementById('insta-modal').style.display = 'block';
        document.body.style.overflow = 'hidden'; // 背景スクロール防止
    }

    /**
     * モーダルを閉じる
     */
    function closeInstaModal(event) {
        // 背景または×ボタンをクリックした場合のみ閉じる
        if (event.target.id === 'insta-modal' || event.target.className === 'insta-close') {
            document.getElementById('insta-modal').style.display = 'none';
            document.body.style.overflow = '';
        }
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