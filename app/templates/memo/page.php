<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php
// URLから状態を直接取得
$current_action = $_GET['action'] ?? 'list';
$current_id = $_GET['id'] ?? null;

// 右上の表示用ユーザー判定
$display_user = $user ?? $_SESSION['user'] ?? 'guest';

// 💡 判定ロジック：$display_user が 'guest' か空ならゲストモード
$isGuestMode = ($display_user === 'guest' || empty($display_user));
?>

<style>
    /* モバイル・デスクトップ共通のベース調整 */
    .memo-container {
        box-sizing: border-box;
    }

    /* 一覧テーブルのスマホ最適化 */
    .memo-container table {
        font-size: 16px !important;
        width: 100% !important;
        table-layout: fixed;
    }

    .memo-container td,
    .memo-container th {
        padding: 12px 8px !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    /* --- ピン留めボタンのスタイル（強化版） --- */
    .pin-link {
        text-decoration: none;
        font-size: 1.4rem;
        margin-right: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        transition: all 0.2s ease;
        background: #f0f0f0;
        /* デフォルト背景 */
    }

    /* ピン留め済み：オレンジ背景に赤いピン */
    .pin-active {
        background: #fff3cd !important;
        border: 1px solid #ffeeba;
        transform: scale(1.1);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* ピン留め中行のハイライト */
    .row-pinned {
        background-color: #fffdf5 !important;
    }

    .pin-link:active {
        transform: scale(1.4);
    }

    /* 入力エリアのフォントサイズ（iPhoneの自動ズーム防止） */
    textarea {
        font-size: 16px !important;
        width: 100%;
        box-sizing: border-box;
    }

    @media (max-width: 600px) {
        .memo-container {
            padding: 10px !important;
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
        }

        .memo-container h2 {
            font-size: 1.2rem;
        }
    }
</style>

<div class="memo-container"
    style="padding: 20px; width: 100%; max-width: 900px; margin: 20px auto; background: #fff; border: 1px solid #eee; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">

    <div
        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007bff; margin-bottom: 20px; padding-bottom: 10px;">
        <h2 style="margin: 0; color: #333;">📝 メモ</h2>
        <span style="background: #f0f0f0; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
            ログイン：<?= htmlspecialchars($display_user) ?>
        </span>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <a href="index.php?page=memo&action=list"
            style="text-decoration: none; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; color: #555; background: #fff; font-weight: bold; font-size: 0.9rem;">📋
            一覧</a>
        <a href="index.php?page=memo&action=new"
            style="text-decoration: none; padding: 10px 15px; border: none; border-radius: 5px; color: #fff; background: #28a745; font-weight: bold; font-size: 0.9rem;">＋
            新規作成</a>
    </div>

    <?php if ($display_user === 'guest'): ?>
        <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <form action="index.php?page=memo&action=set_guest_name" method="POST"
                style="display: flex; gap: 5px; margin-left: auto;">
                <input type="text" name="guest_name" value="<?= htmlspecialchars($_SESSION['guest_name'] ?? '') ?>"
                    placeholder="合言葉(例:なまえ)"
                    style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 140px; font-size: 0.9rem;">
                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.9rem;">適用</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($current_action === 'new' || $current_action === 'edit'): ?>
        <form method="post" action="index.php?page=memo">
            <input type="hidden" name="id" value="<?= htmlspecialchars($current_id ?? '') ?>">

            <?php if ($isGuestMode): ?>
                <div
                    style="background: #fff5f5; padding: 12px; border: 1px solid #d9534f; border-radius: 5px; margin-bottom: 15px;">
                    <label
                        style="color: #d9534f; font-weight: bold; font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                        <span>⚠️</span> セッションが切れています（Guestモード）
                    </label>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px;">
                        <span style="font-size: 0.8rem; color: #444; font-weight: bold;">署名:</span>
                        <input type="text" name="guest_name" placeholder="例：田中"
                            style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem;">
                    </div>
                </div>
            <?php endif; ?>

            <div
                style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #eee;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-weight: bold; color: #444;">
                        <?= ($current_action === 'new') ? '✨ 新規メモ作成' : '✍️ メモ編集' ?>
                    </label>

                    <?php if ($current_action === 'edit' && isset($page['memo'])): ?>
                        <?php $isPinned = ($page['memo']['is_pinned'] ?? 0); ?>
                        <a href="index.php?page=memo&action=toggle_pin&id=<?= $current_id ?>&from=detail"
                            style="text-decoration: none; font-size: 0.9rem; padding: 6px 15px; border-radius: 20px; border: 2px solid <?= $isPinned ? '#ffc107' : '#ccc' ?>; background: <?= $isPinned ? '#fff9e6' : '#fff' ?>; color: <?= $isPinned ? '#856404' : '#666' ?>; font-weight: bold;">
                            <?= $isPinned ? '📌 ピン留め解除' : '📍 ピン留めする' ?>
                        </a>
                    <?php endif; ?>
                </div>

                <textarea name="content" id="memo-content"
                    style="width: 100%; height: 450px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.6; resize: vertical; box-sizing: border-box; font-size: 1rem;"
                    placeholder="ここに入力してください..."><?php echo htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit"
                        style="padding: 12px 30px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: bold;">保存する</button>
                    <button type="submit" name="pdf_export" formtarget="_blank"
                        style="padding: 12px 20px; background: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: bold;">PDF</button>
                </div>

                <?php if (!empty($current_id)): ?>
                    <button type="submit" name="delete" onclick="return confirm('本当にこのメモを削除しますか？')"
                        style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.85rem; text-decoration: underline;">🗑️
                        このメモを削除</button>
                <?php endif; ?>
            </div>
        </form>

    <?php else: ?>
        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th
                            style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; color: #666; width: 75%;">
                            内容</th>
                        <th
                            style="padding: 15px; text-align: right; border-bottom: 2px solid #eee; color: #666; width: 25%;">
                            最終更新</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memos)): ?>
                        <tr>
                            <td colspan="2" style="padding: 30px; text-align: center; color: #999;">メモはまだありません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($memos as $m): ?>
                            <?php $isPinned = ($m['is_pinned'] ?? 0); ?>
                            <tr style="border-bottom: 1px solid #f1f1f1;" class="<?= $isPinned ? 'row-pinned' : '' ?>">
                                <td style="padding: 15px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                    <div style="display: flex; align-items: center;">
                                        <a href="index.php?page=memo&action=toggle_pin&id=<?= $m['id'] ?><?= !empty($target_date) ? '&date=' . $target_date : '' ?>"
                                            class="pin-link <?= $isPinned ? 'pin-active' : '' ?>" title="ピン留め切替">
                                            <?= $isPinned ? '📌' : '📍' ?>
                                        </a>

                                        <a href="index.php?page=memo&action=edit&id=<?= htmlspecialchars($m['id']) ?>"
                                            style="color: #007bff; text-overflow: ellipsis; overflow: hidden; text-decoration: none; font-weight: <?= $isPinned ? '900' : 'bold' ?>; font-size: 1rem; flex: 1;">
                                            <?= $m['display_title_html'] ?>
                                        </a>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: right; color: #888; font-size: 0.85rem; white-space: nowrap;">
                                    <?= htmlspecialchars($m['time']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
        <a href="index.php?page=home"
            style="text-decoration: none; color: #007bff; font-size: 0.9rem; font-weight: bold;">← ホームへ戻る</a>
    </div>
</div>