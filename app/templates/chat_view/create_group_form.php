<?php
// セッションチェック
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}
?>

<div class="group-create-container">
    <h2>グループ作成</h2>
    <form action="index.php?page=do_create_group" method="POST">
        
        <div class="form-group">
            <label for="room_name">グループ名:</label>
            <input type="text" name="room_name" id="room_name" required placeholder="例: プロジェクトチーム">
        </div>

        <!-- <div class="form-group">
            <label>メンバーを招待:</label>
            <div class="search-area">
                <input type="text" id="userSearchInput" placeholder="ユーザー名で検索...">
                <button type="button" onclick="searchUsers()">検索</button>
            </div>
            <div id="userResults" style="margin-top: 10px; border: 1px solid #ccc; padding: 10px;">
                <p style="color: #666;">検索ボタンを押してメンバーを選択してください。</p>
            </div>
        </div> -->

        <button type="submit" style="margin-top: 20px;">グループを作成する</button>
    </form>
</div>

<script>
// ユーザー検索機能（以前作成したものをそのまま使えます）
function searchUsers() {
    const keyword = document.getElementById('userSearchInput').value;
    fetch('app/templates/chat_view/search_users.php?q=' + encodeURIComponent(keyword))
        .then(response => response.json())
        .then(users => {
            const container = document.getElementById('userResults');
            container.innerHTML = ''; // クリア
            
            if (users.length === 0) {
                container.innerHTML = 'ユーザーが見つかりませんでした。';
                return;
            }

            users.forEach(user => {
                const label = document.createElement('label');
                label.style.display = 'block';
                label.innerHTML = `
                    <input type="checkbox" name="user_ids[]" value="${user.id}"> 
                    ${user.username}
                `;
                container.appendChild(label);
            });
        })
        .catch(err => {
            console.error(err);
            alert('検索中にエラーが発生しました');
        });
}
</script>

<style>
.group-create-container { max-width: 400px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
</style>