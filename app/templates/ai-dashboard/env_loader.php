<?php
/**
 * .envファイルを読み込み $_ENV に格納するシンプルな関数
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // コメントアウトや空行を無視
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // 最初の = で分割
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // 前後のクォーテーションを削除
            $value = trim($value, '"\'');

            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}