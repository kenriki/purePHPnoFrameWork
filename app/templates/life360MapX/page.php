<?php
/**
 * 簡易位置情報共有ツール (複数ユーザー表示・スマホ対応版)
 */

ob_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../dbconfig.php';
ob_end_clean();

$header_path = __DIR__ . '/../../header.php';
if (file_exists($header_path)) {
    include_once $header_path;
}

try {
    $pdo = getDB();
    // user_idを50文字まで対応させ、各ユーザーを識別
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
        user_id VARCHAR(50) PRIMARY KEY,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("接続失敗: " . $e->getMessage());
}

// 位置情報の更新（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat'])) {
    $stmt = $pdo->prepare("INSERT INTO user_locations (user_id, latitude, longitude) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude)");
    $stmt->execute([$_POST['uid'], $_POST['lat'], $_POST['lng']]);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'ok']));
}

// 全ユーザーの位置を取得して初期表示用にする
$stmt = $pdo->query("SELECT * FROM user_locations");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="draggable_panel" class="controls"
    style="position: absolute; top: 100px; left: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 280px; box-sizing: border-box; touch-action: none;">

    <div id="drag_handle"
        style="width: 100%; height: 34px; background: #f8f9fa; margin: -15px -15px 10px -15px; border-radius: 12px 12px 0 0; cursor: move; display: flex; align-items: center; justify-content: space-between; padding: 0 12px; font-size: 11px; color: #999; box-sizing: content-box; user-select: none; border-bottom: 1px solid #eee;">
        <span>⠿ ドラッグ移動</span>
        <span id="min_btn"
            style="cursor: pointer; font-weight: bold; padding: 8px 12px; color: #333; background: #eee; border-radius: 6px;">[
            ＿ ]</span>
    </div>

    <div id="panel_content">
        <div id="setup_section" style="margin-bottom: 15px;">
            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">自分の電話番号を設定:</label>
            <input type="tel" id="tel_input" placeholder="08012345678"
                style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 8px; box-sizing: border-box; font-size: 14px;">
            <button onclick="startSharing()"
                style="width: 100%; background: #28a745; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold;">共有を開始</button>
        </div>

        <div id="active_section" style="display: none;">
            <div style="margin-bottom: 8px;">
                <strong>共有中: <span id="display_uid" style="color: #007bff;">---</span></strong>
            </div>
            <div id="gps_status" style="font-size: 11px; color: #666; margin-bottom: 10px;">GPS準備中...</div>

            <div style="display: flex; flex-direction: column; gap: 6px;">
                <button onclick="shareLocation('line')"
                    style="background: #06C755; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;"><i
                        class="fab fa-line"></i> LINEで送る</button>
                <button onclick="shareLocation('teams')"
                    style="background: #4B53BC; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;"><i
                        class="fab fa-microsoft-teams"></i> Teamsで送る</button>
                <button onclick="shareLocation('x')"
                    style="background: #000; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;"><i
                        class="fa-brands fa-x-twitter"></i> Xで送る</button>
                <button onclick="shareLocation('fb')"
                    style="background: #1877F2; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;"><i
                        class="fab fa-facebook-f"></i> Facebook</button>
            </div>

            <hr style="margin: 12px 0; border: 0; border-top: 1px solid #eee;">
            <button onclick="resetId()"
                style="width: 100%; background: #f0f0f0; color: #666; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-size: 11px;">電話番号を変更</button>
        </div>
    </div>
</div>

<div id="map" style="height: calc(100vh - 60px); width: 100%;"></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // 1. ID管理
    let myId = localStorage.getItem('my_map_id');
    const setupDiv = document.getElementById('setup_section');
    const activeDiv = document.getElementById('active_section');

    if (myId) {
        showActiveMode(myId);
    }

    function startSharing() {
        const input = document.getElementById('tel_input').value.trim();
        if (input.length < 8) {
            alert("正しい電話番号を入力してください");
            return;
        }
        localStorage.setItem('my_map_id', input);
        location.reload();
    }

    function showActiveMode(id) {
        setupDiv.style.display = 'none';
        activeDiv.style.display = 'block';
        document.getElementById('display_uid').innerText = id;
    }

    function resetId() {
        if (confirm("番号を変更して共有を停止しますか？")) {
            localStorage.removeItem('my_map_id');
            location.reload();
        }
    }

    // 2. 最小化機能
    function togglePanel() {
        const content = document.getElementById("panel_content");
        content.style.display = (content.style.display === "none") ? "block" : "none";
    }

    // 3. 地図初期化
    const map = L.map('map', { zoomControl: false }).setView([35.6812, 139.7671], 13);
    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { attribution: '© Google Maps' }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    const markers = {};
    const redIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });
    const blueIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });

    function addOrUpdateMarker(uid, lat, lng) {
        const isMe = (uid === myId);
        if (markers[uid]) {
            markers[uid].setLatLng([lat, lng]);
        } else {
            markers[uid] = L.marker([lat, lng], { icon: isMe ? blueIcon : redIcon }).addTo(map).bindPopup("<b>" + uid + (isMe ? " (自分)" : "") + "</b>");
            if (isMe) markers[uid].openPopup();
        }
    }

    // 4. ドラッグ＆クリック両立
    const panel = document.getElementById("draggable_panel");
    const handle = document.getElementById("drag_handle");
    const minBtn = document.getElementById("min_btn");
    let isDragging = false, offset = { x: 0, y: 0 };

    const start = (e) => {
        if (e.target === minBtn) return;
        const touch = e.type === 'touchstart' ? e.touches[0] : e;
        if (e.target.closest('#drag_handle')) {
            isDragging = true;
            offset.x = touch.clientX - panel.offsetLeft;
            offset.y = touch.clientY - panel.offsetTop;
            if (e.type === 'touchstart') e.stopPropagation();
        }
    };

    const move = (e) => {
        if (!isDragging) return;
        const touch = e.type === 'touchmove' ? e.touches[0] : e;
        panel.style.left = (touch.clientX - offset.x) + "px";
        panel.style.top = (touch.clientY - offset.y) + "px";
        e.preventDefault();
    };

    const end = () => { isDragging = false; };

    handle.addEventListener('mousedown', start);
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', end);
    handle.addEventListener('touchstart', start, { passive: false });
    document.addEventListener('touchmove', move, { passive: false });
    document.addEventListener('touchend', end);

    minBtn.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(); });
    minBtn.addEventListener('touchstart', (e) => { e.stopPropagation(); togglePanel(); }, { passive: false });

    // 5. 位置更新ロジック
    function updateMyLocation() {
        if (!navigator.geolocation || !myId) return;
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            addOrUpdateMarker(myId, lat, lng);
            document.getElementById('gps_status').innerText = "最終更新: " + new Date().toLocaleTimeString();

            const fd = new FormData();
            fd.append('uid', myId); // 保存された電話番号を送信
            fd.append('lat', lat);
            fd.append('lng', lng);
            fetch(window.location.href, { method: 'POST', body: fd });
        }, null, { enableHighAccuracy: true });
    }

    // 初期ロード時にDBから全員分を表示
    const initialUsers = <?php echo json_encode($users); ?>;
    initialUsers.forEach(u => addOrUpdateMarker(u.user_id, u.latitude, u.longitude));

    // 定期更新
    if (myId) {
        updateMyLocation();
        setInterval(updateMyLocation, 30000);
    }

    // 他人の更新を反映させるためにページを定期的にリロードするか、APIで取得する処理を別途追加可能
    setInterval(() => {
        fetch(window.location.href.split('?')[0] + '?action=get_all_users') // 簡易的に位置取得のみ行う場合は別途API化推奨
            .then(() => { /* ここで最新の $users データを取得して markers を更新するロジックを組むとリアルタイム化します */ });
    }, 60000);

    // 6. 共有機能
    async function shareLocation(type) {
        const cleanUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname}?page=life360MapX`);
        const text = encodeURIComponent(`【${myId}】の位置情報を地図で確認！`);
        const urls = {
            line: `https://social-plugins.line.me/lineit/share?url=${cleanUrl}&text=${text}`,
            teams: `https://teams.microsoft.com/share?href=${cleanUrl}&msgText=${text}`,
            x: `https://x.com/intent/tweet?text=${text}&url=${cleanUrl}`,
            fb: `https://www.facebook.com/sharer/sharer.php?u=${cleanUrl}`
        };
        window.open(urls[type], '_blank');
    }
</script>