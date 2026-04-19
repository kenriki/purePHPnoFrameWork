<div class="container mt-5">
    <div class="card shadow-sm border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-link"></i> 共有URLを発行しました</h5>
        </div>
        <div class="card-body">
            <p>以下のURLをコピーして、共有したい相手に送ってください。</p>
            <div class="input-group mb-3">
                <input type="text" id="shareUrl" class="form-control" value="<?= htmlspecialchars($shareUrl) ?>"
                    readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="copyUrl()">コピー</button>
            </div>
            <div class="alert alert-warning small">
                <i class="fas fa-exclamation-triangle"></i> このURLの有効期限は **24時間** です。
            </div>
            <div class="text-center mt-4">
                <a href="index.php?page=memo&action=list" class="btn btn-secondary">一覧に戻る</a>
            </div>
        </div>
    </div>
</div>

<script>
    function copyUrl() {
        const copyText = document.getElementById("shareUrl");
        copyText.select();
        document.execCommand("copy");
        alert("URLをコピーしました！");
    }
</script>