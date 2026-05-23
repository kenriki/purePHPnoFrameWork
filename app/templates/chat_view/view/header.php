<style>
    .chat-container {
        max-width: 800px;
        margin: 20px auto;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .chat-header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }

    .chat-header-main {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .chat-title-name {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }

    .chat-search-form {
        display: flex;
        gap: 8px;
    }

    .chat-search-form input[type="text"] {
        padding: 6px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        width: 200px;
    }

    .chat-search-form button {
        padding: 0 12px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .chat-search-results {
        margin-top: 8px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 0;
        list-style: none;
    }

    .chat-search-results li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
    }

    .chat-search-results li:last-child {
        border-bottom: none;
    }

    .open-chat-btn {
        font-size: 12px;
        padding: 4px 8px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }

    .chat-box {
        height: 400px;
        overflow-y: auto;
        padding: 20px;
        background: #fafafa;
    }

    .message {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
    }

    .message.sent {
        align-items: flex-end;
    }

    .message.received {
        align-items: flex-start;
    }

    .message-content {
        max-width: 70%;
        padding: 10px 15px;
        border-radius: 15px;
        margin: 2px 0;
        word-break: break-all;
        font-size: 14px;
    }

    .message.sent .message-content {
        background: #007bff;
        color: #fff;
        border-bottom-right-radius: 2px;
    }

    .message.received .message-content {
        background: #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .message small {
        color: #888;
        font-size: 11px;
    }

    .chat-footer {
        background: #f8f9fa;
        padding: 15px 20px;
        border-top: 1px solid #eee;
    }

    .chat-form {
        display: flex;
        gap: 10px;
    }

    .chat-form textarea {
        flex: 1;
        height: 40px;
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: none;
        font-family: sans-serif;
        font-size: 14px;
    }

    .chat-form button {
        padding: 0 20px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
    }

    .chat-form button:hover {
        background: #218838;
    }
</style>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-main">
            <div class="chat-title-name">
                <?php echo htmlspecialchars($receiver['name'] ?? 'メッセージ送信先が未選択', ENT_QUOTES, 'UTF-8'); ?> 
            </div>

            <form action="index.php" method="GET" class="chat-search-form">
                <input type="hidden" name="page" value="chat">
                <input type="hidden" name="receiver_id"
                    value="<?php echo htmlspecialchars($receiver_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="search_name"
                    value="<?php echo htmlspecialchars($search_keyword ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="ユーザーを検索...">
                <button type="submit">検索</button>
            </form>
        </div>

        <?php if (isset($search_results)): ?>
            <ul class="chat-search-results">
                <?php if (empty($search_results)): ?>
                    <li style="color: #888; font-size: 13px;">ユーザーが見つかりませんでした。</li>
                <?php else: ?>
                    <?php foreach ($search_results as $u): ?>
                        <li>
                            <span
                                style="font-size: 14px;"><strong><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                さん</span>
                            <a href="index.php?page=chat&receiver_id=<?php echo urlencode($u['id']); ?>"
                                class="open-chat-btn">チャットを開く</a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>