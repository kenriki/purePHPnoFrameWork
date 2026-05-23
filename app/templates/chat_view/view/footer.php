<?php if ($receiver_id && $receiver): ?>
    <div class="chat-footer">
        <form action="index.php?page=chat&receiver_id=<?php echo urlencode($receiver_id); ?>" method="POST"
            class="chat-form">
            <textarea name="message" placeholder="メッセージを入力..." required></textarea>
            <button type="submit">送信</button>
        </form>
    </div>
<?php endif; ?>

</div>
<script>
    const chatBox = document.getElementById('chatBox');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>