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

    /* --- ピン留めボタン（アイコン）のスタイル --- */
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

    /* ピン留め済み：目立つ背景色と枠線 */
    .pin-active {
        background: #fff3cd !important;
        /* 薄いオレンジ */
        border: 2px solid #ffc107 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* ピン留めされている行全体の背景色 */
    .row-pinned {
        background-color: #fffef0 !important;
    }

    .pin-link:active {
        transform: scale(1.3);
    }

    /* 入力エリアのズーム防止 */
    textarea {
        font-size: 16px !important;
        width: 100%;
        box-sizing: border-box;
        font-family: 'Consolas', 'Monaco', monospace;
    }

    /* モバイル表示の最適化 */
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

        .pin-link {
            width: 36px;
            height: 36px;
            font-size: 1.2rem;
        }
    }
</style>

<div class="memo-container">

    <div
        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007bff; margin-bottom: 20px; padding-bottom: 10px;">
        <h2 style="margin: 0; color: #333;">📝 メモ管理</h2>
        <span style="background: #f0f0f0; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">
            User: <?= htmlspecialchars($display_user) ?>
        </span>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <a href="index.php?page=memo&action=list"
            style="text-decoration: none; padding: 10px 18px; border: 1px solid #ddd; border-radius: 5px; color: #555; background: #fff; font-weight: bold; font-size: 0.9rem;">📋
            一覧表示</a>
        <a href="index.php?page=memo&action=new"
            style="text-decoration: none; padding: 10px 18px; border: none; border-radius: 5px; color: #fff; background: #28a745; font-weight: bold; font-size: 0.9rem;">＋
            新規作成</a>

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
        <form method="post" action="index.php?page=memo">
            <input type="hidden" name="id" value="<?= htmlspecialchars($current_id ?? '') ?>">

            <?php if ($isGuestMode): ?>
                <div
                    style="background: #fff5f5; padding: 12px; border: 1px solid #d9534f; border-radius: 5px; margin-bottom: 15px;">
                    <label style="color: #d9534f; font-weight: bold; font-size: 0.9rem;">⚠️ ゲストモード：保存時に署名が必要です</label>
                    <input type="text" name="guest_name" placeholder="お名前"
                        style="width: 100%; margin-top: 5px; padding: 8px; border: 1px solid #ccc;">
                </div>
            <?php endif; ?>

            <div
                style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #eee;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-weight: bold; color: #444;">
                        <?= ($current_action === 'new') ? '✨ 新規メモ' : '✍️ 編集モード' ?>
                    </label>

                    <?php if ($current_action === 'edit' && isset($page['memo'])): ?>
                        <?php $isPinned = ($page['memo']['is_pinned'] ?? 0); ?>
                        <a href="index.php?page=memo&action=toggle_pin&id=<?= $current_id ?>&from=detail"
                            style="text-decoration: none; font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; border: 2px solid <?= $isPinned ? '#ffc107' : '#ccc' ?>; background: <?= $isPinned ? '#fff9e6' : '#fff' ?>; color: <?= $isPinned ? '#856404' : '#666' ?>; font-weight: bold;">
                            <?= $isPinned ? '📌 ピン留めを外す' : '📍 ピン留めする' ?>
                        </a>
                    <?php endif; ?>
                </div>

                <textarea name="content" id="memo-content"
                    style="height: 400px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; line-height: 1.6; resize: vertical;"
                    placeholder="内容を入力してください..."><?php echo htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit"
                        style="padding: 12px 25px; background: #007bff; color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">💾
                        保存して更新</button>
                    <button type="submit" name="pdf_export" formtarget="_blank"
                        style="padding: 12px 15px; background: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer;">PDF出力</button>
                </div>
                <?php if (!empty($current_id)): ?>
                    <button type="submit" name="delete" onclick="return confirm('削除してもよろしいですか？')"
                        style="color: #dc3545; background: none; border: none; text-decoration: underline; cursor: pointer;">🗑️
                        削除</button>
                <?php endif; ?>
            </div>
        </form>
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

    <?php else: ?>
        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <table>
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="width: 70%; border-bottom: 2px solid #dee2e6;">メモ内容</th>
                        <th style="width: 30%; border-bottom: 2px solid #dee2e6; text-align: right;">作成日</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memos)): ?>
                        <tr>
                            <td colspan="2" style="text-align:center; color:#999; padding:40px;">メモが見つかりません。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($memos as $m): ?>
                            <?php $isPinned = ($m['is_pinned'] ?? 0); ?>
                            <tr class="<?= $isPinned ? 'row-pinned' : '' ?>" style="border-bottom: 1px solid #eee;">
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <a href="index.php?page=memo&action=toggle_pin&id=<?= $m['id'] ?><?= !empty($target_date) ? '&date=' . $target_date : '' ?>"
                                            class="pin-link <?= $isPinned ? 'pin-active' : '' ?>"
                                            title="<?= $isPinned ? 'ピンを外す（更新日は維持されます）' : 'ピン留めする（更新日は維持されます）' ?>">
                                            <?= $isPinned ? '📌' : '📍' ?>
                                        </a>

                                        <a href="index.php?page=memo&action=edit&id=<?= htmlspecialchars($m['id']) ?>"
                                            style="color: #007bff; text-decoration: none; font-weight: <?= $isPinned ? '900' : 'bold' ?>; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= $m['display_title_html'] ?>
                                        </a>
                                    </div>
                                </td>
                                <td style="text-align: right; color: #888; font-size: 0.85rem; white-space: nowrap;">
                                    <?= htmlspecialchars($m['time']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee;">
        <a href="index.php?page=home"
            style="text-decoration: none; color: #007bff; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
            🏠 ホーム画面へ戻る
        </a>
    </div>
</div>