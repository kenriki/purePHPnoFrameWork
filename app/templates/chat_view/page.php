<?php
// 実際のフォルダ名「view」に合わせてインクルード
include __DIR__ . '/view/header.php';
?>

<div class="chat-box" id="chatBox">
    <?php if (!$receiver_id): ?>
        <p style="text-align: center; color: #999; margin-top: 20px;">検索窓からメッセージを送る相手を探してください。</p>
    <?php elseif (empty($messages)): ?>
        <p style="text-align: center; color: #999; margin-top: 20px;">メッセージはまだありません。<br>最初のメッセージを送ってみましょう！</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <?php $isSentByMe = ($msg['sender_id'] == $current_user_id); ?>
            <div class="message <?php echo $isSentByMe ? 'sent' : 'received'; ?>">
                <small>
                    <?php echo $isSentByMe ? '自分' : htmlspecialchars($receiver['name'], ENT_QUOTES, 'UTF-8'); ?>
                </small>
                <div class="message-content">
                    <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <small>
                    <?php echo date('m/d H:i', strtotime($msg['created_at'])); ?>
                    <?php if ($isSentByMe): ?>
                        <span style="color: #007bff;">
                            <?php echo (isset($msg['is_read']) && $msg['is_read']) ? '（既読）' : '（未読）'; ?>
                        </span>
                    <?php endif; ?>
                </small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// 実際のフォルダ名「view」に合わせてインクルード
include __DIR__ . '/view/footer.php';
?>