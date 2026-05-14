const CACHE_NAME = 'memo-app-v' + new Date().getTime(); // 毎回新しい名前を生成

self.addEventListener('install', (event) => {
    // 新しいSWがインストールされたら待機せずに有効化
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});

// Periodic Background Sync のイベントリスナー
// OSが「今なら通信していいよ」と許可した時に発火するイベント
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'update-location-bg') {
        event.waitUntil(
            // サーバーに「自分（ID:2）はここだよ」と通知
            // IDなどの必要な情報はIndexedDBに保存しておいたものを使う
            sendLocationToServer()
        );
    }
});

async function sendLocationToServer() {
    // 実際にはここに、サーバーへ座標を飛ばす fetch 処理を書きます
    console.log('[SW] バックグラウンドで位置更新を試行');

    // 注意: iOS等ではSW内でのgetCurrentPositionは動作しないため、
    // 画面が開いている間に「次に送信する予約」を登録するなどの工夫が必要です
}

self.addEventListener('fetch', (event) => {
    // 通常のフェッチ。キャッシュ戦略が必要な場合はここに追記
});