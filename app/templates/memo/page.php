<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php
// URLから状態を直接取得
$current_action = $_GET['action'] ?? 'list';
$current_id = $_GET['id'] ?? null;

// 右上の表示用ユーザー判定
$display_user = $user ?? $_SESSION['user'] ?? 'guest';

// 判定ロジック：$display_user が 'guest' か空ならゲストモード
$isGuestMode = ($display_user === 'guest' || empty($display_user));

// --- ストレージ計算用の補助 ---
$max_mb = 512;
$memos = $result['memos'] ?? [];
$current_usage_bytes = $currentUserUsage ?? 0;
$current_mb = round($current_usage_bytes / 1024 / 1024, 1);
$percent = ($max_mb > 0) ? min(100, round(($current_mb / $max_mb) * 100)) : 0;
?>

<style>
    /* 全体コンテナのベース調整 */
    .memo-container {
        box-sizing: border-box;
        padding: 20px;
        width: 100%;
        max-width: 900px;
        margin: 20px auto;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    /* テーブルのレスポンシブ・フォント調整 */
    .memo-container table {
        font-size: 16px !important;
        width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .memo-container td,
    .memo-container th {
        padding: 15px 10px !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    /* --- ピン留めボタンのスタイル --- */
    .pin-link {
        text-decoration: none;
        font-size: 1.4rem;
        margin-right: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        transition: all 0.2s ease;
        background: #f8f9fa;
        border: 1px solid #ddd;
    }

    .pin-active {
        background: #fff3cd !important;
        border: 2px solid #ffc107 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .row-pinned {
        background-color: #fffef0 !important;
    }

    /* 入力エリアのズーム防止 */
    textarea {
        font-size: 16px !important;
        width: 100%;
        box-sizing: border-box;
        font-family: 'Consolas', 'Monaco', monospace;
    }

    /* --- 画像表示・プレビュー関連 --- */
    .image-preview-container {
        position: relative;
        display: inline-block;
        margin: 15px auto;
        text-align: center;
    }

    .memo-image {
        width: 150px !important;      /* 閉じている時はサイズ固定 */
        height: 150px !important;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 1px solid #eee;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
        display: block;
    }

    .memo-image:hover {
        transform: scale(1.05);
    }

    /* 削除ボタン（×） */
    .delete-image-btn {
        position: absolute;
        top: -10px;
        right: -10px;
        width: 28px;
        height: 28px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        border: 2px solid white;
        font-size: 18px;
        font-weight: bold;
        line-height: 24px;
        cursor: pointer;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        z-index: 10;
        padding: 0;
    }

    /* モーダル（拡大表示） */
    #imageModal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.9);
        justify-content: center;
        align-items: center;
        cursor: zoom-out;
    }

    .modal-content {
        width: auto !important;
        height: auto !important;
        max-width: 95% !important;
        max-height: 95% !important;
        object-fit: contain !important;
        border: 2px solid #fff;
        border-radius: 4px;
        animation: zoom 0.2s ease-out;
    }

    @keyframes zoom {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    /* ローディング等 */
    #upload-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: #fff;
    }

    .spinner {
        width: 40px; height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    @media (max-width: 600px) {
        .memo-container { padding: 10px !important; border: none; box-shadow: none; margin: 0; }
    }
</style>

<div id="upload-overlay">
    <div class="spinner"></div>
    <div style="margin-top:10px;">データを保存中...</div>
    <div class="progress-bar-wrap" style="width: 80%; max-width: 300px; background: #444; height: 12px; border-radius: 6px; overflow: hidden; margin: 15px 0;">
        <div id="overlay-progress-bar" style="width: 0%; height: 100%; background: #28a745; transition: width 0.3s;"></div>
    </div>
</div>

<div class="memo-container">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007bff; margin-bottom: 20px; padding-bottom: 10px;">
        <h2 style="margin: 0; color: #333;">📝 メモ管理</h2>
        <span style="background: #f0f0f0; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
            User: <?= htmlspecialchars($display_user) ?>
        </span>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <a href="index.php?page=memo&action=list" style="text-decoration: none; padding: 10px 18px; border: 1px solid #ddd; border-radius: 5px; color: #555; background: #fff; font-weight: bold; font-size: 0.9rem;">📋 一覧表示</a>
        <a href="index.php?page=memo&action=new" style="text-decoration: none; padding: 10px 18px; border: none; border-radius: 5px; color: #fff; background: #28a745; font-weight: bold; font-size: 0.9rem;">＋ 新規作成</a>
        <?php if ($display_user === 'guest'): ?>
            <form action="index.php?page=memo&action=set_guest_name" method="POST"
                style="margin-left: auto; display: flex; gap: 5px;">
                <input type="text" name="guest_name" value="<?= htmlspecialchars($_SESSION['guest_name'] ?? '') ?>"
                    placeholder="名前を入力" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 120px;">
                <button type="submit"
                    style="padding: 6px 10px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;">適用</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($current_action === 'new' || $current_action === 'edit'): ?>
        <form id="memo-form" method="post" action="index.php?page=memo" enctype="multipart/form-data">
            <input type="hidden" name="id" id="memo-id-input" value="<?= htmlspecialchars($current_id ?? '') ?>">
            <input type="hidden" name="image_path" value="<?= htmlspecialchars($memo['image_path'] ?? ''); ?>">

            <div class="pin-status-area" style="margin-bottom: 15px;">
                <?php 
                // 新規作成時は $memo 自体が null なので isset で判定
                $isPinned = (isset($memo['is_pinned']) && $memo['is_pinned'] == 1);
                $m_id = $memo['id'] ?? null;
                ?>
                <?php if ($m_id): ?>
                    <a href="index.php?page=memo&action=toggle_pin_from_edit&id=<?= htmlspecialchars($m_id) ?>" 
                    class="pin-toggle-btn" 
                    style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 4px; background-color: <?= $isPinned ? '#ffeeba' : '#f8f9fa' ?>; border: 1px solid <?= $isPinned ? '#ffe8a1' : '#ddd' ?>; color: #212529;"
                    title="<?= $isPinned ? 'ピン留めを外す' : 'ピン留めする' ?>">
                        <span style="font-size: 1.2rem;"><?= $isPinned ? '📌' : '📍' ?></span>
                        <span style="font-size: 0.9rem; font-weight: bold;"><?= $isPinned ? 'ピン留め中' : 'ピン留めする' ?></span>
                    </a>
                <?php else: ?>
                    <small style="color: #888;">※ピン留めは保存後に設定できます</small>
                <?php endif; ?>
            </div>

            <?php if ($isGuestMode): ?>
                <div style="background: #fff5f5; padding: 12px; border: 1px solid #d9534f; border-radius: 5px; margin-bottom: 15px;">
                    <label style="color: #d9534f; font-weight: bold; font-size: 0.9rem;">⚠️ ゲストモード：保存時に署名が必要です</label>
                    <input type="text" name="guest_name" placeholder="お名前"
                        style="width: 100%; margin-top: 5px; padding: 8px; border: 1px solid #ccc;">
                </div>
            <?php endif; ?>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #eee;">
                <textarea name="content" id="memo-content" style="height: 400px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; line-height: 1.6; resize: vertical;" placeholder="内容を入力してください..."><?= htmlspecialchars($content ?? $memo['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="margin-bottom: 15px; padding: 10px; border: 1px dashed #ccc; border-radius: 5px; background: #fff;">
                <!-- <p style="color: #d9534f; font-size: 0.9em; font-weight: bold;">
                    ※現在はスクリーンショット（PNG形式）のみ対応しています。JPGやGIFを選択するとエラーになるため、ご注意ください。
                </p> -->
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">📸 写真を添付 (PDFにも反映されます)</label>
                <input type="file" name="memo_image" id="file-input" accept="image/*" style="font-size: 0.9rem;">
                
                <div style="width: 100%; background: #eee; height: 8px; border-radius: 4px; margin-top: 10px;">
                    <?php $p_val = $percent ?? 0; ?>
                    <div id="storage-progress-bar" style="width: <?= (int)$p_val ?>%; background: #28a745; height: 100%; border-radius: 4px;"></div>
                </div>
                <small style="color: #666;">使用量: <?= $current_mb ?? 0 ?>MB / <?= $max_mb ?? 512 ?>MB</small>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" id="save-btn" style="padding: 12px 25px; background: #007bff; color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">💾 保存して更新</button>
                    <button type="submit" name="pdf_export" formtarget="_blank" style="padding: 12px 15px; background: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer;">PDF出力</button>
                </div>
                <?php if (!empty($current_id)): ?>
                    <button type="submit" name="delete" onclick="return confirm('削除してもよろしいですか？')" style="color: #dc3545; background: none; border: none; text-decoration: underline; cursor: pointer;">🗑️ 削除</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($memo['image_path'])): ?>
                <div class="image-preview-container" id="image-wrapper">
                    <?php 
                        $owner = $memo['username'] ?? $display_user;
                        $safeFolder = (!preg_match('/^[a-zA-Z0-9\._-]+$/', $owner)) ? 'u_' . substr(md5($owner), 0, 12) : $owner;
                        
                        // パス混入を防ぐため、現在のディレクトリ構造に合わせて調整してください
                        $projectRoot = "/sample"; // 本番環境に合わせて空文字 "" または "/test" に変更
                        $imgUrl = $projectRoot . "/app/data/user_memos/" . htmlspecialchars($safeFolder) . "/images/" . htmlspecialchars($memo['image_path']);
                    ?>
                    <button type="button" class="delete-image-btn" title="サーバーから物理削除" onclick="deleteImageFromServer('<?= htmlspecialchars($current_id) ?>')">×</button>
                    <img src="<?= $imgUrl ?>" class="memo-image" title="クリックで拡大" style="max-width: 200px; border-radius: 8px; cursor: pointer;">
                </div>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <table class="memo-table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="width: 70%; border-bottom: 2px solid #dee2e6; padding: 10px; text-align: left;">メモ内容</th>
                        <th style="width: 30%; border-bottom: 2px solid #dee2e6; padding: 10px; text-align: right;">作成日</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // デバッグ出力は確認できたら消してOKです
                    // var_dump(count($memoList)); 

                    if (!empty($memoList) && is_array($memoList)): 
                        foreach ($memoList as $m): 
                            $isPinned = !empty($m['is_pinned']);
                            $memoId = htmlspecialchars($m['id']);
                            $memoTime = htmlspecialchars($m['time'] ?? '');
                            // display_title_html はコントローラーで生成済みのHTMLを使用
                            $titleHtml = $m['display_title_html'] ?? '無題のメモ';
                    ?>
                        <tr class="<?= $isPinned ? 'row-pinned' : '' ?>" style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <a href="index.php?page=memo&action=toggle_pin&id=<?= $memoId ?>" class="pin-link <?= $isPinned ? 'pin-active' : '' ?>" style="text-decoration: none;">
                                        <?= $isPinned ? '📌' : '📍' ?>
                                    </a>
                                    <a href="index.php?page=memo&action=edit&id=<?= $memoId ?>" style="color: #007bff; text-decoration: none; font-weight: bold; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= $titleHtml ?>
                                    </a>
                                </div>
                            </td>
                            <td style="padding: 10px; text-align: right; color: #888; font-size: 0.85rem;">
                                <?= $memoTime ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="2" style="text-align:center; color:#999; padding:40px;">
                                メモが見つかりません。
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($memo['id'])): ?>
        <input type="hidden" name="memo_id" value="<?= htmlspecialchars((string) $memo['id']) ?>">

        <div class="alert alert-info">
            <p>このメモを誰かに共有しますか？</p>
            <form action="index.php?page=generate_share_url" method="POST">
                <input type="hidden" name="memo_id" value="<?= htmlspecialchars((string) $memo['id']) ?>">
                <button type="submit" class="btn btn-sm btn-info">24H限定の共有URLを発行</button>
            </form>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee;">
        <a href="index.php?page=home" style="text-decoration: none; color: #007bff; font-weight: bold;">🏠 ホーム画面へ戻る</a>
    </div>
</div>

<div id="imageModal" onclick="this.style.display='none'">
    <img class="modal-content" id="imgFull">
</div>

<script>
/**
 * サーバー側の物理ファイルとDBレコード(image_path)を即座に削除する
 */
function deleteImageFromServer(memoId) {
    if (!memoId) return;
    if (!confirm('画像をサーバーから完全に削除しますか？\n（この操作は取り消せません）')) return;

    const formData = new FormData();
    formData.append('id', memoId);

    // サーバーに削除リクエストを送信
    fetch('index.php?page=memo&action=delete_image', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // 成功したら画面上の画像プレビューを非表示にする
            const wrapper = document.getElementById('image-wrapper');
            if (wrapper) {
                wrapper.style.transition = 'opacity 0.3s';
                wrapper.style.opacity = '0';
                setTimeout(() => wrapper.remove(), 300);
            }
            alert('画像を削除しました。');
        } else {
            alert('削除に失敗しました: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('通信エラーが発生しました。');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log("JS Loaded"); // デバッグ用：コンソールにこれが出るか確認

    const form = document.getElementById('memo-form');
    const saveBtn = document.getElementById('save-btn');
    const overlay = document.getElementById('upload-overlay');
    const overlayBar = document.getElementById('overlay-progress-bar');
    const fileInput = document.getElementById('file-input');

    // 1. ファイルバリデーション (PNGのみ)
    // if (fileInput) {
    //     fileInput.addEventListener('change', function(e) {
    //         const file = e.target.files[0];
    //         if (!file) return;

    //         const isPng = file.type === 'image/png';
    //         const isPngExt = file.name.toLowerCase().endsWith('.png');

    //         if (!isPng || !isPngExt) {
    //             alert("申し訳ありませんが、現在はJPGやGIF形式には対応しておりません。\nスクリーンショット（PNG形式）の画像を選択してください。");
    //             this.value = ""; 
    //         }
    //     });
    // }

    // 2. フォーム送信時のプログレスバー
    if (form && saveBtn) {
        form.addEventListener('submit', function(e) {
            // PDF出力・削除時は無視
            if (e.submitter && (e.submitter.name === 'pdf_export' || e.submitter.name === 'delete')) {
                return;
            }
            
            console.log("Submit start"); // デバッグ用
            overlay.style.display = 'flex';
            saveBtn.disabled = true;
            
            let p = 0;
            const interval = setInterval(() => {
                if (p < 90) p += 5;
                if (overlayBar) overlayBar.style.width = p + '%';
                if (p >= 100) clearInterval(interval);
            }, 100);
        });
    }

    // 3. モーダル拡大処理 (ここから file 関連の記述を完全に排除)
    const modal = document.getElementById('imageModal');
    const fullImg = document.getElementById('imgFull');

    document.querySelectorAll('.memo-image').forEach(img => {
        img.addEventListener('click', function() {
            if (fullImg && modal) {
                fullImg.src = this.src;
                modal.style.display = 'flex';
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal) modal.style.display = 'none';
    });
});
</script>