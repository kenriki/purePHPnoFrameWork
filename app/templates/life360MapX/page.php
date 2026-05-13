<?php
/**
 * 簡易位置情報共有ツール (完全版：個別マスタ登録・複数表示・スマホ対応)
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
    // user_idを主キーにして、番号ごとに独立したデータを持つようにする
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
        phone_number VARCHAR(50) PRIMARY KEY,
        user_name VARCHAR(50),
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("接続失敗: " . $e->getMessage());
}

// 位置情報の受信とマスタ更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat'])) {
    // user_name も一緒に保存・更新するように修正
    $stmt = $pdo->prepare("INSERT INTO user_locations (phone_number, user_name, latitude, longitude) VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), latitude=VALUES(latitude), longitude=VALUES(longitude)");
    $stmt->execute([$_POST['uid'], $_POST['u_name'], $_POST['lat'], $_POST['lng']]);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'ok']));
}

// 地図表示時にDB内の全ユーザーを取得する
// 取得時は user_name をそのまま取得
$stmt = $pdo->query("SELECT phone_number, user_name, latitude, longitude, updated_at FROM user_locations");
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
            <label style="font-size: 11px; color: #666;">電話番号（ログイン用）:</label>
            <input type="tel" id="tel_input" placeholder="数字入力のみ"
                style="width: 100%; padding: 10px; margin-bottom: 8px; font-size: 16px;">

            <label style="font-size: 11px; color: #666;">表示名（ニックネーム）:</label>
            <input type="text" id="name_input" placeholder="例：英語のみ対応"
                style="width: 100%; padding: 10px; margin-bottom: 8px; font-size: 16px;">

            <button onclick="startSharing()"
                style="width: 100%; background: #28a745; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold;">位置共有を開始</button>
        </div>

        <div id="active_section" style="display: none;">
            <div style="margin-bottom: 8px;">
                <strong>共有中: <span id="display_uid" style="color: #007bff;">---</span></strong>
                <span id="geo_badge"
                    style="padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; background: #ccc; color: white;">状態確認中</span>
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

            <!-- setup_section の後、または active_section 内に追加 -->
            <div id="search_section" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 5px;">相手を探す:</label>
                <div style="display: flex; gap: 5px;">
                    <input type="tel" id="search_tel" placeholder="相手の番号"
                        style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                    <button onclick="searchUser()"
                        style="background: #007bff; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;">検索</button>
                </div>
            </div>

            <hr style="margin: 12px 0; border: 0; border-top: 1px solid #eee;">
            <button onclick="resetId()"
                style="width: 100%; background: #f0f0f0; color: #666; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-size: 11px;">登録番号を変更</button>
        </div>
    </div>
</div>

<div id="map" style="height: calc(100vh - 60px); width: 100%;"></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // 1. 登録・識別管理
    let myId = localStorage.getItem('my_map_id');
    const setupDiv = document.getElementById('setup_section');
    const activeDiv = document.getElementById('active_section');

    if (myId) {
        showActiveMode(myId);
    }

    // function searchUser() {
    //     const targetId = document.getElementById('search_tel').value.trim();

    //     // 1. すでに地図に表示されているか確認
    //     if (markers[targetId]) {
    //         focusMarker(targetId);
    //     }
    //     // 2. 地図にない場合、DBから取得したリスト(initialUsers)から探す
    //     else {
    //         const foundUser = initialUsers.find(u => u.phone_number === targetId);
    //         if (foundUser) {
    //             // 見つかったらマーカーを作成して表示
    //             addOrUpdateMarker(foundUser.phone_number, foundUser.latitude, foundUser.longitude, foundUser.user_name, foundUser.updated_at);
    //             focusMarker(targetId);
    //         } else {
    //             alert("その番号のユーザーは見つかりません。");
    //         }
    //     }
    // }
    // --- 検索関数を「追跡開始」付きにアップデート ---
    function searchUser() {
        const targetId = document.getElementById('search_tel').value.trim();
        if (!targetId) return;

        // 1. すでに地図に表示されているか確認
        if (markers[targetId]) {
            focusMarker(targetId);
            startFriendTracking(targetId); // ★ここで追跡を開始
        }
        // 2. 地図にない場合、DBから取得したリスト(initialUsers)から探す
        else {
            const foundUser = initialUsers.find(u => u.phone_number === targetId);
            if (foundUser) {
                // 見つかったらマーカーを作成して表示
                addOrUpdateMarker(foundUser.phone_number, foundUser.latitude, foundUser.longitude, foundUser.user_name, foundUser.updated_at);
                focusMarker(targetId);
                startFriendTracking(targetId); // ★ここでも追跡を開始
            } else {
                alert("その番号のユーザーは見つかりません。");
            }
        }
    }

    // ズームしてポップアップを開く共通処理
    function focusMarker(uid) {
        const marker = markers[uid];
        map.setView(marker.getLatLng(), 17);
        marker.openPopup();
    }

    /**
     * 時間の差分を「〜分前」のような形式に変換する関数
     */
    function timeAgo(dateString) {
        if (!dateString) return "不明";
        const now = new Date();
        const past = new Date(dateString);
        const diffInSec = Math.floor((now - past) / 1000);

        if (isNaN(diffInSec)) return "不明";
        if (diffInSec < 60) return "今すぐ";
        if (diffInSec < 3600) return Math.floor(diffInSec / 60) + "分前";
        if (diffInSec < 86400) return Math.floor(diffInSec / 3600) + "時間前";
        return Math.floor(diffInSec / 86400) + "日前";
    }

    // 1. 登録・識別管理
    function startSharing() {
        const tel = document.getElementById('tel_input').value.trim();
        const name = document.getElementById('name_input').value.trim();
        if (tel.length < 8 || name === "") {
            alert("番号と名前を正しく入力してください");
            return;
        }
        localStorage.setItem('my_map_id', tel);
        localStorage.setItem('my_map_name', name); // 名前も保存
        myId = tel;
        showActiveMode(name); // 画面表示を名前に
        updateMyLocation(true);
    }

    // 5. 位置更新ロジック (引数 moveMap を追加)
    // function updateMyLocation(moveMap = false) {
    //     if (!navigator.geolocation || !myId) return;

    //     navigator.geolocation.getCurrentPosition(pos => {
    //         const lat = pos.coords.latitude;
    //         const lng = pos.coords.longitude;

    //         // マーカーを更新
    //         addOrUpdateMarker(myId, lat, lng);
    //         document.getElementById('gps_status').innerText = "最終更新: " + new Date().toLocaleTimeString();

    //         // 地図を自分の位置に移動（開始時や初回のみ有効）
    //         if (moveMap) {
    //             map.setView([lat, lng], 16);
    //         }

    //         // サーバー（DB）へ送信
    //         const fd = new FormData();
    //         fd.append('uid', myId);
    //         fd.append('lat', lat);
    //         fd.append('lng', lng);
    //         fetch(window.location.href, { method: 'POST', body: fd });
    //     }, (err) => {
    //         console.error("位置情報の取得に失敗:", err);
    //         alert("位置情報の取得を許可してください。");
    //     }, { enableHighAccuracy: true });
    // }

    // 初期ロード時の処理
    if (myId) {
        showActiveMode(myId);
        // ページを開いた時も、自分の位置がわかったらそこに飛ばす
        updateMyLocation(true);
        setInterval(() => updateMyLocation(false), 30000); // 30秒ごとの更新時は地図を動かさない
    }

    function showActiveMode(id) {
        setupDiv.style.display = 'none';
        activeDiv.style.display = 'block';
        document.getElementById('display_uid').innerText = id;
    }

    function resetId() {
        if (confirm("登録を解除しますか？")) {
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
    const map = L.map('map', { zoomControl: false }).setView([36.2, 138.2], 5);
    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { attribution: '© Google Maps' }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // --- 追加：ページ読み込み時に現在地へジャンプする機能 ---
    function jumpToCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                // ズームレベル15で自分の現在地に移動
                map.setView([lat, lng], 15);
            }, (err) => {
                console.warn("現在地の取得に失敗しました:", err);
                // 失敗時はデフォルトに飛ばす
                map.setView([0.522, 0.473], 14);
            }, { enableHighAccuracy: true });
        }
    }

    // 実行
    jumpToCurrentLocation();

    // --- 5. 位置更新ロジック (既存のものを微調整) ---
    // function updateMyLocation() {
    //     if (!navigator.geolocation || !myId) return;
    //     navigator.geolocation.getCurrentPosition(pos => {
    //         const lat = pos.coords.latitude;
    //         const lng = pos.coords.longitude;

    //         addOrUpdateMarker(myId, lat, lng);
    //         document.getElementById('gps_status').innerText = "最終更新: " + new Date().toLocaleTimeString();

    //         const fd = new FormData();
    //         fd.append('uid', myId);
    //         fd.append('lat', lat);
    //         fd.append('lng', lng);
    //         fetch(window.location.href, { method: 'POST', body: fd });

    //         // 初回更新時のみ地図の中心を自分にする場合はここに追加ロジックを書けます
    //     }, null, { enableHighAccuracy: true });
    // }

    const markers = {};
    const redIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });
    const blueIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });

    // 3. マーカー表示関数の引数に名前を追加
    function addOrUpdateMarker(uid, lat, lng, u_name, time) {
        const displayTitle = u_name || uid; // 名前があれば名前、なければ番号
        const isMe = (uid === myId);
        const ago = `⏳ ${timeAgo(time)}`;

        if (markers[uid]) {
            markers[uid].setLatLng([lat, lng]);
            markers[uid].setPopupContent(`<b>${displayTitle}${isMe ? " (自分)" : ""}</b><br><small>${ago}</small>`);
        } else {
            markers[uid] = L.marker([lat, lng], { icon: isMe ? blueIcon : redIcon }).addTo(map)
                .bindPopup(`<b>${displayTitle}</b><br><small>${ago}</small>`);
        }
    }

    // 4. ドラッグ＆クリック両立 (スマホ対応)
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

    // 5. 位置更新ロジック (マスタ登録・送信)
    function updateMyLocation(moveMap = false) {
        if (!navigator.geolocation || !myId) return;
        const myName = localStorage.getItem('my_map_name');

        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            // マーカー表示に名前を使用
            addOrUpdateMarker(myId, lat, lng, myName, new Date().toISOString());

            const fd = new FormData();
            fd.append('uid', myId);
            fd.append('u_name', myName); // 名前を送信！
            fd.append('lat', lat);
            fd.append('lng', lng);
            fetch(window.location.href, { method: 'POST', body: fd });

            if (moveMap) map.setView([lat, lng], 16);
        }, null, { enableHighAccuracy: true });
    }

    /// 初期データは持っておくだけにする（表示はしない）
    const initialUsers = <?php echo json_encode($users); ?>;
    //initialUsers.forEach(u => addOrUpdateMarker(u.phone_number, u.latitude, u.longitude, u.user_name, u.updated_at));

    // 30秒ごとに自分の位置をマスタに反映
    if (myId) {
        updateMyLocation();
        setInterval(updateMyLocation, 30000);
    }

    // 6. 共有リンク機能
    async function shareLocation(type) {
        const cleanUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname}?page=life360MapX`);
        const text = encodeURIComponent(`【${myId}】の位置を確認してね！`);
        const urls = {
            line: `https://social-plugins.line.me/lineit/share?url=${cleanUrl}&text=${text}`,
            teams: `https://teams.microsoft.com/share?href=${cleanUrl}&msgText=${text}`,
            x: `https://x.com/intent/tweet?text=${text}&url=${cleanUrl}`,
            fb: `https://www.facebook.com/sharer/sharer.php?u=${cleanUrl}`
        };
        window.open(urls[type], '_blank');
    }
    // --- 【追加】相手の位置を定期的に取得して更新する処理 ---
    // 検索した相手がいる場合、その位置を30秒ごとに更新します
    let searchInterval = null;

    function startFriendTracking(targetId) {
        // すでにタイマーが動いていたら一度クリア（二重起動防止）
        if (searchInterval) clearInterval(searchInterval);

        searchInterval = setInterval(() => {
            // 前に作った get_friend_location.php を叩く
            // パラメータ名は uid に合わせています
            fetch(`get_friend_location.php?uid=${targetId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.lat && data.lng) {
                        // 既存のマーカー表示関数を再利用して位置を更新
                        // 相手の battery 情報があればそれも反映されるように後で関数を拡張可能です
                        addOrUpdateMarker(targetId, data.lat, data.lng, data.u_name, data.created_at);
                        console.log(`相手(${targetId})の位置を自動更新しました。`);
                    }
                })
                .catch(error => console.error('自動更新失敗:', error));
        }, 30000); // 30秒間隔
    }

    // 既存の searchUser 関数を拡張：検索成功時に追跡を開始させる
    const originalSearchUser = searchUser; // 元の関数を退避
    searchUser = function () {
        originalSearchUser(); // 元の検索処理を実行
        const targetId = document.getElementById('search_tel').value.trim();
        if (targetId) {
            startFriendTracking(targetId); // 追跡タイマー起動
        }
    };
    // --- 【追加】位置情報の権限状態をチェックして表示を更新する関数 ---
    async function checkGeoPermission() {
        const badge = document.getElementById('geo_badge');
        if (!navigator.permissions) {
            badge.innerText = "不明";
            return;
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            const updateBadge = (status) => {
                if (status === 'granted') {
                    badge.innerText = "位置情報：ON";
                    badge.style.background = "#28a745"; // 緑
                } else if (status === 'prompt') {
                    badge.innerText = "許可待ち";
                    badge.style.background = "#ffc107"; // 黄色
                } else {
                    badge.innerText = "位置情報：OFF";
                    badge.style.background = "#dc3545"; // 赤
                }
            };

            updateBadge(result.state);

            // 設定が変更されたら自動でバッジも更新されるようにする
            result.onchange = () => updateBadge(result.state);

        } catch (error) {
            console.error("権限チェック失敗:", error);
        }
    }

    // 初期ロード時と、共有開始時に実行
    if (myId) {
        checkGeoPermission();
    }
</script>