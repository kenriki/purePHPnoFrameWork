<?php
/**
 * ImageProcessorController
 * 画像のメタデータ（Exif）削除およびリサイズを担当
 */
class ImageProcessorController
{

    /**
     * アップロードされた画像をクリーンにして保存する
     * 
     * @param string $tmpPath   一時ファイルのパス ($_FILES['...']['tmp_name'])
     * @param string $savePath  保存先の物理パス
     * @param int    $maxWidth  最大横幅 (デフォルト1200px)
     * @return bool  成功時にtrue
     */
    public static function processAndSave($tmpPath, $savePath, $maxWidth = 1200)
    {
        // 1. 画像情報の取得
        $info = getimagesize($tmpPath);
        if (!$info) {
            return false;
        }

        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];

        // 2. MIMEタイプに応じた画像リソースの作成
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($tmpPath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($tmpPath);
                break;
            default:
                return false; // 対応外の形式
        }

        // 3. リサイズ計算
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) ($height * ($maxWidth / $width));
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // 4. 新しいキャンバス（True Color）の作成
        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // 5. 透明度の処理 (PNG対策)
        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        // 6. 再描画 (この工程でExifデータが物理的に破棄される)
        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // 7. 保存 (JPEG形式で保存し、画質を85%に調整して軽量化)
        $result = imagejpeg($dst, $savePath, 85);

        // 8. メモリ解放
        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }
}