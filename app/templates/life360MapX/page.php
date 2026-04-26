<?php
/**
 * 簡易位置情報共有ツール (自分中心表示・ドラッグ移動窓・SNS共有対応版)
 */

// パス設定
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../dbconfig.php';

// --- 【修正】ヘッダーを強制的に読み込む (ログインなし対策) ---
$header_path = __DIR__ . '/../../header.php'; 
if (file_exists($header_path)) {
    include_once $header_path;
}

try {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
        user_id VARCHAR(50) PRIMARY KEY,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("接続失敗: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat'])) {
    $stmt = $pdo->prepare("INSERT INTO user_locations (user_id, latitude, longitude) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude)");
    $stmt->execute([$_POST['uid'], $_POST['lat'], $_POST['lng']]);
    exit(json_encode(['status' => 'ok']));
}

$stmt = $pdo->query("SELECT * FROM user_locations");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="draggable_panel" class="controls"
    style="position: absolute; top: 100px; left: 20px; z-index: 1000; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); min-width: 220px; max-width: 280px; box-sizing: border-box; cursor: default;">

    <div id="drag_handle"
        style="width: 100%; height: 20px; background: #f0f0f0; margin: -15px -15px 10px -15px; border-radius: 8px 8px 0 0; cursor: move; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">
        ⋮⋮ ドラッグして移動 ⋮⋮
    </div>

    <div style="margin-bottom: 8px;">
        <strong>自分: <span id="display_uid">設定中...</span></strong>
    </div>
    <div id="gps_status" style="font-size: 11px; color: #666; margin-bottom: 10px;">GPS取得中...</div>

    <div style="display: flex; flex-direction: column; gap: 8px;">
        <button onclick="shareLocation('line')"
            style="background: #06C755; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-weight: bold;">LINEで送る</button>
        <button onclick="shareLocation('teams')"
            style="background: #4B53BC; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-weight: bold;">Teamsで送る</button>
        <button onclick="shareLocation('x')"
            style="background: #000000; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-weight: bold;">Xで送る</button>
        <button onclick="shareLocation('fb')"
            style="background: #1877F2; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-weight: bold;">Facebookで送る</button>
    </div>

    <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
    <button onclick="resetName()"
        style="width: 100%; background: #f0f0f0; color: #666; border: none; padding: 4px; border-radius: 4px; cursor: pointer; font-size: 10px;">表示名を変更</button>
</div>

<div id="map" style="height: calc(100vh - 60px); width: 100%;"></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // 1. 名前（UID）の決定
    const urlParams = new URLSearchParams(window.location.search);
    let myUid = urlParams.get('uid') || localStorage.getItem('my_map_name');

    if (!myUid) {
        myUid = prompt("地図に表示する名前を入力してください", "");
        if (!myUid || myUid.trim() === "") {
            myUid = 'Guest_' + Math.floor(Math.random() * 1000);
        }
        localStorage.setItem('my_map_name', myUid);
    }
    document.getElementById('display_uid').innerText = myUid;

    function resetName() {
        const newName = prompt("新しい名前を入力してください", myUid);
        if (newName && newName.trim() !== "") {
            localStorage.setItem('my_map_name', newName);
            window.location.href = `?page=life360MapX&uid=${encodeURIComponent(newName)}`;
        }
    }

    // 2. 地図初期化
    const map = L.map('map').setView([35.6812, 139.7671], 13);
    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        attribution: '© Google Maps'
    }).addTo(map);

    const markers = {};
    const initialUsers = <?php echo json_encode($users); ?>;
    const redIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    let hasMovedToMe = false;
    initialUsers.forEach(u => {
        addOrUpdateMarker(u.user_id, u.latitude, u.longitude);
        if (u.user_id === myUid && !hasMovedToMe) {
            map.setView([u.latitude, u.longitude], 15);
            hasMovedToMe = true;
        }
    });

    function addOrUpdateMarker(uid, lat, lng) {
        if (markers[uid]) {
            markers[uid].setLatLng([lat, lng]);
        } else {
            const isMe = (uid === myUid);
            markers[uid] = L.marker([lat, lng], { icon: redIcon })
                .addTo(map)
                .bindPopup(isMe ? "<b>自分</b><br>" + uid : "ユーザー: " + uid);
            if (isMe) markers[uid].openPopup();
        }
    }

    // 3. 位置更新 & 住所取得ロジック
    async function getCurrentLocationName(lat, lng) {
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lng=${lng}&zoom=18&addressdetails=1`, {
                headers: { 'Accept-Language': 'ja' }
            });
            const data = await response.json();
            const addr = data.address;
            const pref = addr.province || addr.prefecture || "";
            const city = addr.city || addr.town || addr.village || "";
            const sub = addr.suburb || addr.neighbourhood || "";
            return `${pref}${city}${sub}`;
        } catch (e) {
            return "現在の場所";
        }
    }

    let isFirstUpdate = true;
    function updateMyLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            addOrUpdateMarker(myUid, lat, lng);
            document.getElementById('gps_status').innerText = "最終更新: " + new Date().toLocaleTimeString();

            if (isFirstUpdate && !hasMovedToMe) {
                map.setView([lat, lng], 15);
                isFirstUpdate = false;
                hasMovedToMe = true;
            }

            const fd = new FormData();
            fd.append('uid', myUid);
            fd.append('lat', lat);
            fd.append('lng', lng);
            fetch(window.location.href, { method: 'POST', body: fd });
        }, err => {
            document.getElementById('gps_status').innerText = "GPSエラー: " + err.message;
        }, { enableHighAccuracy: true });
    }

    // 4. ドラッグ機能
    const panel = document.getElementById("draggable_panel");
    const handle = document.getElementById("drag_handle");
    let isDragging = false;
    let offset = { x: 0, y: 0 };

    const startDrag = (x, y) => { isDragging = true; offset.x = x - panel.offsetLeft; offset.y = y - panel.offsetTop; };
    const doDrag = (x, y) => { if (isDragging) { panel.style.left = (x - offset.x) + "px"; panel.style.top = (y - offset.y) + "px"; } };
    const stopDrag = () => { isDragging = false; };

    handle.onmousedown = (e) => startDrag(e.clientX, e.clientY);
    document.onmousemove = (e) => doDrag(e.clientX, e.clientY);
    document.onmouseup = stopDrag;
    handle.ontouchstart = (e) => startDrag(e.touches[0].clientX, e.touches[0].clientY);
    document.ontouchmove = (e) => doDrag(e.touches[0].clientX, e.touches[0].clientY);
    document.ontouchend = stopDrag;

    // 5. 住所テキスト付き共有機能
    async function shareLocation(type) {
        const myMarker = markers[myUid];
        let text = "現在の位置情報を共有します";
        
        if (myMarker) {
            const pos = myMarker.getLatLng();
            const placeName = await getCurrentLocationName(pos.lat, pos.lng);
            text = `【${myUid}】は今、${placeName}付近にいます。`;
        }

        const url = encodeURIComponent(`${window.location.origin}${window.location.pathname}?page=life360MapX&uid=${encodeURIComponent(myUid)}`);
        const encodedText = encodeURIComponent(text);

        const shareUrls = {
            line: `https://social-plugins.line.me/lineit/share?url=${url}&text=${encodedText}`,
            teams: `https://teams.microsoft.com/share?href=${url}&msgText=${encodedText}`,
            x: `https://x.com/intent/tweet?text=${encodedText}&url=${url}`,
            fb: `https://www.facebook.com/sharer/sharer.php?u=${url}`
        };
        window.open(shareUrls[type], '_blank', type === 'fb' || type === 'teams' ? 'width=600,height=500' : '');
    }

    updateMyLocation();
    setInterval(updateMyLocation, 30000);
</script>