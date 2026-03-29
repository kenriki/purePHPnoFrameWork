<?php
// URLから状態を直接取得（コントローラーとの連携ミスを防止）
$current_action = $_GET['action'] ?? 'list';
$current_id = $_GET['id'] ?? null;
$display_content = isset($content) && is_string($content) ? $content : "";
$display_user = $user ?? $_SESSION['user'] ?? 'guest';
?>

<div class="memo-container"
    style="padding: 20px; max-width: 900px; margin: auto; background: #fff; border: 1px solid #eee; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
    <div
        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007bff; margin-bottom: 20px; padding-bottom: 10px;">
        <h2 style="margin: 0; color: #333;">📝 仕事メモ</h2>
        <span
            style="background: #f0f0f0; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem;">ログイン：<?= htmlspecialchars($display_user) ?></span>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <a href="/index.php?page=memo&action=list"
            style="text-decoration: none; padding: 10px 20px; border: 1px solid #ddd; border-radius: 5px; color: #555; background: #fff; font-weight: bold;">📋
            一覧を見る</a>
        <a href="/index.php?page=memo&action=new"
            style="text-decoration: none; padding: 10px 20px; border: none; border-radius: 5px; color: #fff; background: #28a745; font-weight: bold;">＋
            新規作成</a>
    </div>

    <?php if ($current_action === 'new' || $current_action === 'edit'): ?>
        <form method="post" action="/index.php?page=memo">
            <input type="hidden" name="id" value="<?= htmlspecialchars($current_id ?? '') ?>">

            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 10px; color: #444;">
                    <?= ($current_action === 'new') ? '✨ 新規メモ作成' : '✍️ メモ編集' ?>
                </label>
                <textarea name="content"
                    style="width: 100%; height: 450px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; box-sizing: border-box; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.6; resize: vertical;"
                    placeholder="ここに入力してください..."><?= htmlspecialchars($display_content) ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <button type="submit"
                    style="padding: 12px 40px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-weight: bold;">保存する</button>

                <?php if ($current_id): ?>
                    <button type="submit" name="delete" onclick="return confirm('本当にこのメモを削除しますか？')"
                        style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.9rem;">🗑️
                        このメモを削除</button>
                <?php endif; ?>
            </div>
        </form>

    <?php else: ?>
        <div style="border: 1px solid #eee; border-radius: 5px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; background: #fff;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; color: #666;">内容</th>
                        <th style="padding: 15px; text-align: right; border-bottom: 2px solid #eee; color: #666;">最終更新</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($memos ?? []) as $m): ?>
                        <tr style="border-bottom: 1px solid #f1f1f1;">
                            <td style="padding: 15px;">
                                <a href="/index.php?page=memo&action=edit&id=<?= htmlspecialchars($m['id']) ?>"
                                    style="color: #007bff; text-decoration: none; font-weight: bold; font-size: 1.1rem;">
                                    📄 <?= htmlspecialchars($m['display_title'] ?? $m['id']) ?>
                                </a>
                            </td>
                            <td style="padding: 15px; text-align: right; color: #888; font-size: 0.9rem;">
                                <?= htmlspecialchars($m['time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($memos)): ?>
                        <tr>
                            <td colspan="2" style="padding: 40px; text-align: center; color: #aaa;">保存されたメモはまだありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="/index.php?page=home" style="text-decoration: none; color: #666; font-size: 0.9rem;">← ホームへ戻る</a>
    </div>
</div>