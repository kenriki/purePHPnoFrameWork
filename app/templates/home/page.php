<?php
/**
 * ======================================================================================
 * ダッシュボード テンプレート (page.php) - 全画像アーカイブ対応版
 * ======================================================================================
 * 【システム要件】
 * - PHP 8.0以上推奨
 * - FullCalendar 6.1.11 搭載
 * - Chart.js 搭載
 * 
 * 【修正・統合内容】
 * 1. サイドパネルの統合：1つのコンテナ内に全ての要素を指示通りの順序で配置。
 * 2. 画像取得の最大化：getRecentImages(9999) を使用し、事実上の全件表示を実現。
 * 3. スライダー操作の安定化：IDの衝突を避け、スムーズなスクロールを実現。
 * ======================================================================================
 */

// --- 1. コントローラーと基本変数の準備 ---
require_once dirname(__DIR__, 2) . '/controllers/MemoController.php';

// --- カレンダーデータの復号化処理 ---
$rawEvents = $page['dashboard']['events'] ?? [];
$decryptedEvents = [];

foreach ($rawEvents as $event) {
    // タイトル(summary)の復号
    $title = $event['summary'] ?? '';
    if (str_starts_with($title, 'base64:')) {
        $title = $controller->decryptContent($title);
    }

    // 詳細(description)の復号
    $desc = $event['description'] ?? '';
    if (str_starts_with($desc, 'base64:')) {
        $desc = $controller->decryptContent($desc);
    }

    // FullCalendarが解釈できる形式に整形
    // ここで ?? '' を使って未定義エラーを防ぎます
    $decryptedEvents[] = [
        'id' => $event['id'] ?? '',
        'title' => $title ?: '（予定なし）',
        'start' => $event['start_date'] ?? '', // ここを修正
        'end' => $event['end_date'] ?? '',   // ここを修正
        'extendedProps' => [
            'description' => $desc
        ],
        'backgroundColor' => $event['color'] ?? '#4f46e5',
        'borderColor' => $event['color'] ?? '#4f46e5'
    ];
}

// JavaScriptへ渡すためのJSONデータ
$jsonEvents = json_encode($decryptedEvents);
// 同期データが存在するかどうかの判定フラグ
$isSynced = !empty($decryptedEvents);
$syncTime = $page['dashboard']['sync_status']['last_sync'] ?? ''; // 同期日時があれば取得

$controller = new MemoController();
$initialDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$uDir = $controller->getSafeDirName($controller->user);

// ダッシュボード用データの安全な初期化
// $page が配列でない、あるいは文字列なら空配列で上書きする
if (!is_array($page)) {
    $page = [];
}

if (!isset($page['dashboard'])) {
    $page['dashboard'] = [
        'events' => [],
        'chart' => [],
        'pinned' => [],
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3-1. 未完了のTODOデータを今週（月〜日）の範囲で取得
    $todoIncompleteStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 0
        GROUP BY DATE(due_date)
    ");
    $todoIncompleteStmt->execute([$controller->user, $monday, $sunday]);
    $todoIncompleteRaw = $todoIncompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoIncompleteMap = [];
    foreach ($todoIncompleteRaw as $row) {
        $todoIncompleteMap[$row['date']] = (int) $row['count'];
    }

    // 3-2. 完了したTODOデータを今週（月〜日）の範囲で取得
    $todoCompletedStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 1
        GROUP BY DATE(due_date)
    ");
    $todoCompletedStmt->execute([$controller->user, $monday, $sunday]);
    $todoCompletedRaw = $todoCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoCompletedMap = [];
    foreach ($todoCompletedRaw as $row) {
        $todoCompletedMap[$row['date']] = (int) $row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int) $row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形 ＋ 詳細データの紐付け[cite: 3]
    $memoCounts = [];
    $todoIncompleteCounts = [];
    $todoCompletedCounts = [];
    $formattedLabels = [];
    $dailyDetailsMap = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5);          // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;           // データがなければ 0
        $todoIncompleteCounts[] = $todoIncompleteMap[$date] ?? 0; // 未完了TODO
        $todoCompletedCounts[] = $todoCompletedMap[$date] ?? 0;   // 完了TODO

        // その日のイベントや予定の抽出[cite: 3]
        $dayEvents = [];
        foreach (($dbData['events'] ?? []) as $ev) {
            if (isset($ev['start']) && str_starts_with($ev['start'], $date)) {
                $dayEvents[] = [
                    'title' => $ev['title'] ?? '予定',
                    'type' => 'event'
                ];
            }
        }

        // 日付ごとの詳細マップ[cite: 3]
        $dailyDetailsMap[$date] = [
            'date' => $date,
            'memo_count' => $memoMap[$date] ?? 0,
            'todo_incomplete_count' => $todoIncompleteMap[$date] ?? 0,
            'todo_completed_count' => $todoCompletedMap[$date] ?? 0,
            'events' => $dayEvents
        ];
    }

    // まとめてJSに渡すための配列に格納[cite: 3]
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'all_dates' => $allDates,
        'memo' => $memoCounts,
        'todo_incomplete' => $todoIncompleteCounts,
        'todo_completed' => $todoCompletedCounts,
        'details' => $dailyDetailsMap
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = [
        'labels' => [],
        'all_dates' => [],
        'memo' => [],
        'todo_incomplete' => [],
        'todo_completed' => [],
        'details' => []
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3-1. 未完了のTODOデータを今週（月〜日）の範囲で取得
    $todoIncompleteStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 0
        GROUP BY DATE(due_date)
    ");
    $todoIncompleteStmt->execute([$controller->user, $monday, $sunday]);
    $todoIncompleteRaw = $todoIncompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoIncompleteMap = [];
    foreach ($todoIncompleteRaw as $row) {
        $todoIncompleteMap[$row['date']] = (int) $row['count'];
    }

    // 3-2. 完了したTODOデータを今週（月〜日）の範囲で取得
    $todoCompletedStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 1
        GROUP BY DATE(due_date)
    ");
    $todoCompletedStmt->execute([$controller->user, $monday, $sunday]);
    $todoCompletedRaw = $todoCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoCompletedMap = [];
    foreach ($todoCompletedRaw as $row) {
        $todoCompletedMap[$row['date']] = (int) $row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int) $row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形 ＋ 詳細データの紐付け[cite: 3]
    $memoCounts = [];
    $todoIncompleteCounts = [];
    $todoCompletedCounts = [];
    $formattedLabels = [];
    $dailyDetailsMap = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5);          // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;           // データがなければ 0
        $todoIncompleteCounts[] = $todoIncompleteMap[$date] ?? 0; // 未完了TODO
        $todoCompletedCounts[] = $todoCompletedMap[$date] ?? 0;   // 完了TODO

        // その日のイベントや予定の抽出[cite: 3]
        $dayEvents = [];
        foreach (($dbData['events'] ?? []) as $ev) {
            if (isset($ev['start']) && str_starts_with($ev['start'], $date)) {
                $dayEvents[] = [
                    'title' => $ev['title'] ?? '予定',
                    'type' => 'event'
                ];
            }
        }

        // 日付ごとの詳細マップ[cite: 3]
        $dailyDetailsMap[$date] = [
            'date' => $date,
            'memo_count' => $memoMap[$date] ?? 0,
            'todo_incomplete_count' => $todoIncompleteMap[$date] ?? 0,
            'todo_completed_count' => $todoCompletedMap[$date] ?? 0,
            'events' => $dayEvents
        ];
    }

    // まとめてJSに渡すための配列に格納[cite: 3]
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'all_dates' => $allDates,
        'memo' => $memoCounts,
        'todo_incomplete' => $todoIncompleteCounts,
        'todo_completed' => $todoCompletedCounts,
        'details' => $dailyDetailsMap
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = [
        'labels' => [],
        'all_dates' => [],
        'memo' => [],
        'todo_incomplete' => [],
        'todo_completed' => [],
        'details' => []
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3-1. 未完了のTODOデータを今週（月〜日）の範囲で取得
    $todoIncompleteStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 0
        GROUP BY DATE(due_date)
    ");
    $todoIncompleteStmt->execute([$controller->user, $monday, $sunday]);
    $todoIncompleteRaw = $todoIncompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoIncompleteMap = [];
    foreach ($todoIncompleteRaw as $row) {
        $todoIncompleteMap[$row['date']] = (int) $row['count'];
    }

    // 3-2. 完了したTODOデータを今週（月〜日）の範囲で取得
    $todoCompletedStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 1
        GROUP BY DATE(due_date)
    ");
    $todoCompletedStmt->execute([$controller->user, $monday, $sunday]);
    $todoCompletedRaw = $todoCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoCompletedMap = [];
    foreach ($todoCompletedRaw as $row) {
        $todoCompletedMap[$row['date']] = (int) $row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int) $row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形 ＋ 詳細データの紐付け[cite: 3]
    $memoCounts = [];
    $todoIncompleteCounts = [];
    $todoCompletedCounts = [];
    $formattedLabels = [];
    $dailyDetailsMap = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5);          // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;           // データがなければ 0
        $todoIncompleteCounts[] = $todoIncompleteMap[$date] ?? 0; // 未完了TODO
        $todoCompletedCounts[] = $todoCompletedMap[$date] ?? 0;   // 完了TODO

        // その日のイベントや予定の抽出[cite: 3]
        $dayEvents = [];
        foreach (($dbData['events'] ?? []) as $ev) {
            if (isset($ev['start']) && str_starts_with($ev['start'], $date)) {
                $dayEvents[] = [
                    'title' => $ev['title'] ?? '予定',
                    'type' => 'event'
                ];
            }
        }

        // 日付ごとの詳細マップ[cite: 3]
        $dailyDetailsMap[$date] = [
            'date' => $date,
            'memo_count' => $memoMap[$date] ?? 0,
            'todo_incomplete_count' => $todoIncompleteMap[$date] ?? 0,
            'todo_completed_count' => $todoCompletedMap[$date] ?? 0,
            'events' => $dayEvents
        ];
    }

    // まとめてJSに渡すための配列に格納[cite: 3]
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'all_dates' => $allDates,
        'memo' => $memoCounts,
        'todo_incomplete' => $todoIncompleteCounts,
        'todo_completed' => $todoCompletedCounts,
        'details' => $dailyDetailsMap
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = [
        'labels' => [],
        'all_dates' => [],
        'memo' => [],
        'todo_incomplete' => [],
        'todo_completed' => [],
        'details' => []
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3-1. 未完了のTODOデータを今週（月〜日）の範囲で取得
    $todoIncompleteStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 0
        GROUP BY DATE(due_date)
    ");
    $todoIncompleteStmt->execute([$controller->user, $monday, $sunday]);
    $todoIncompleteRaw = $todoIncompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoIncompleteMap = [];
    foreach ($todoIncompleteRaw as $row) {
        $todoIncompleteMap[$row['date']] = (int) $row['count'];
    }

    // 3-2. 完了したTODOデータを今週（月〜日）の範囲で取得
    $todoCompletedStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 1
        GROUP BY DATE(due_date)
    ");
    $todoCompletedStmt->execute([$controller->user, $monday, $sunday]);
    $todoCompletedRaw = $todoCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoCompletedMap = [];
    foreach ($todoCompletedRaw as $row) {
        $todoCompletedMap[$row['date']] = (int) $row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int) $row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形 ＋ 詳細データの紐付け[cite: 3]
    $memoCounts = [];
    $todoIncompleteCounts = [];
    $todoCompletedCounts = [];
    $formattedLabels = [];
    $dailyDetailsMap = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5);          // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;           // データがなければ 0
        $todoIncompleteCounts[] = $todoIncompleteMap[$date] ?? 0; // 未完了TODO
        $todoCompletedCounts[] = $todoCompletedMap[$date] ?? 0;   // 完了TODO

        // その日のイベントや予定の抽出[cite: 3]
        $dayEvents = [];
        foreach (($dbData['events'] ?? []) as $ev) {
            if (isset($ev['start']) && str_starts_with($ev['start'], $date)) {
                $dayEvents[] = [
                    'title' => $ev['title'] ?? '予定',
                    'type' => 'event'
                ];
            }
        }

        // 日付ごとの詳細マップ[cite: 3]
        $dailyDetailsMap[$date] = [
            'date' => $date,
            'memo_count' => $memoMap[$date] ?? 0,
            'todo_incomplete_count' => $todoIncompleteMap[$date] ?? 0,
            'todo_completed_count' => $todoCompletedMap[$date] ?? 0,
            'events' => $dayEvents
        ];
    }

    // まとめてJSに渡すための配列に格納[cite: 3]
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'all_dates' => $allDates,
        'memo' => $memoCounts,
        'todo_incomplete' => $todoIncompleteCounts,
        'todo_completed' => $todoCompletedCounts,
        'details' => $dailyDetailsMap
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = [
        'labels' => [],
        'all_dates' => [],
        'memo' => [],
        'todo_incomplete' => [],
        'todo_completed' => [],
        'details' => []
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3-1. 未完了のTODOデータを今週（月〜日）の範囲で取得
    $todoIncompleteStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 0
        GROUP BY DATE(due_date)
    ");
    $todoIncompleteStmt->execute([$controller->user, $monday, $sunday]);
    $todoIncompleteRaw = $todoIncompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoIncompleteMap = [];
    foreach ($todoIncompleteRaw as $row) {
        $todoIncompleteMap[$row['date']] = (int) $row['count'];
    }

    // 3-2. 完了したTODOデータを今週（月〜日）の範囲で取得
    $todoCompletedStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ? AND is_completed = 1
        GROUP BY DATE(due_date)
    ");
    $todoCompletedStmt->execute([$controller->user, $monday, $sunday]);
    $todoCompletedRaw = $todoCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

    $todoCompletedMap = [];
    foreach ($todoCompletedRaw as $row) {
        $todoCompletedMap[$row['date']] = (int) $row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int) $row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形 ＋ 詳細データの紐付け[cite: 3]
    $memoCounts = [];
    $todoIncompleteCounts = [];
    $todoCompletedCounts = [];
    $formattedLabels = [];
    $dailyDetailsMap = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5);          // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;           // データがなければ 0
        $todoIncompleteCounts[] = $todoIncompleteMap[$date] ?? 0; // 未完了TODO
        $todoCompletedCounts[] = $todoCompletedMap[$date] ?? 0;   // 完了TODO

        // その日のイベントや予定の抽出[cite: 3]
        $dayEvents = [];
        foreach (($dbData['events'] ?? []) as $ev) {
            if (isset($ev['start']) && str_starts_with($ev['start'], $date)) {
                $dayEvents[] = [
                    'title' => $ev['title'] ?? '予定',
                    'type' => 'event'
                ];
            }
        }

        // 日付ごとの詳細マップ[cite: 3]
        $dailyDetailsMap[$date] = [
            'date' => $date,
            'memo_count' => $memoMap[$date] ?? 0,
            'todo_incomplete_count' => $todoIncompleteMap[$date] ?? 0,
            'todo_completed_count' => $todoCompletedMap[$date] ?? 0,
            'events' => $dayEvents
        ];
    }

    // まとめてJSに渡すための配列に格納[cite: 3]
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'all_dates' => $allDates,
        'memo' => $memoCounts,
        'todo_incomplete' => $todoIncompleteCounts,
        'todo_completed' => $todoCompletedCounts,
        'details' => $dailyDetailsMap
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = [
        'labels' => [],
        'all_dates' => [],
        'memo' => [],
        'todo_incomplete' => [],
        'todo_completed' => [],
        'details' => []
    ];
}

// TODO の集計処理 ＆ メモとの日付軸統合処理（月曜日起点・今週分に限定）
try {
    // 1. 今週の月曜日と日曜日の日付を計算 (本日は2026年8月14日金曜日です)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    // 2. 過去7日間（月〜日）の日付配列（軸）をあらかじめ固定で作成
    $chartLabels = [];
    $allDates = [];
    for ($i = 0; $i < 7; $i++) {
        $targetDate = date('Y-m-d', strtotime("$monday +{$i} days"));
        $allDates[] = $targetDate;
    }

    // 3. TODOのデータを今週（月〜日）の範囲で取得
    $todoStmt = $pdo->prepare("
        SELECT DATE(due_date) as date, COUNT(*) as count 
        FROM todo_items 
        WHERE category = ? AND due_date BETWEEN ? AND ?
        GROUP BY DATE(due_date)
    ");
    $todoStmt->execute([$controller->user, $monday, $sunday]);
    $todoRaw = $todoStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $todoMap = [];
    foreach ($todoRaw as $row) {
        $todoMap[$row['date']] = (int)$row['count'];
    }

    // 4. メモ側のデータを今週（月〜日）の範囲に絞ってマップ化
    $memoMap = [];
    foreach (($page['dashboard']['chart'] ?? []) as $row) {
        $date = $row['date'];
        // 今週の範囲内のみを対象にする
        if ($date >= $monday && $date <= $sunday) {
            $memoMap[$date] = (int)$row['count'];
        }
    }

    // 5. 月曜から日曜までの軸に合わせてデータを整形
    $memoCounts = [];
    $todoCounts = [];
    $formattedLabels = [];

    foreach ($allDates as $date) {
        $formattedLabels[] = substr($date, 5); // '08-10' 形式に整形
        $memoCounts[] = $memoMap[$date] ?? 0;   // データがなければ 0
        $todoCounts[] = $todoMap[$date] ?? 0;   // データがなければ 0
    }

    // まとめてJSに渡すための配列に格納
    $page['dashboard']['unified_chart'] = [
        'labels' => $formattedLabels,
        'memo' => $memoCounts,
        'todo' => $todoCounts
    ];

} catch (Exception $e) {
    $page['dashboard']['unified_chart'] = ['labels' => [], 'memo' => [], 'todo' => []];
}

// コントローラー側から引き渡された未読件数（デフォルトは0）
$unread_count = $unread_count ?? 0;

// .env からクライアントIDを取得（環境に合わせてどちらかを使用）
$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
$redirectUri = 'https://desktop-mnoqic1.tail7aa158.ts.net/index.php?page=google_callback';
// プロトコルとホスト名を取得
$protocol = "https";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = $_SERVER['SCRIPT_NAME'];

$redirectUri = $protocol . "://" . $host . $scriptPath . "?page=google_callback";

// 名前（profile）とメールアドレス（email）のスコープを追加
$scopes = [
    'https://www.googleapis.com/auth/calendar.events',
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];

// $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
//     'client_id' => $clientId,
//     'redirect_uri' => $redirectUri,
//     'response_type' => 'code',
//     'scope' => implode(' ', $scopes),
//     'access_type' => 'offline',
//     'prompt' => 'consent' // 毎回同意画面を出して確実にスコープを承認させる
// ]);
$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => implode(' ', $scopes),
    'access_type' => 'offline',   // オフラインアクセスを有効にしてリフレッシュトークンを得る
    'prompt' => 'select_account' // consentを外すと、ログイン済みなら同意画面をスキップできる
]);
?>

<?php
// index.phpで取得した未読数
$unread_count = $GLOBALS['unread_count'] ?? 0;
$latest_sender_id = null;
if (!isset($current_user_id) && isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
}

// 未読がある場合、自動的にその相手のIDを1件取得する
if ($unread_count > 0 && isset($current_user_id)) {
    try {
        $db = getDB();
        //$stmtSender = $db->prepare("SELECT sender_id FROM messages WHERE receiver_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 1");
        $stmtSender = $db->prepare("SELECT sender_id, message FROM messages WHERE receiver_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 1");
        $stmtSender->execute([$current_user_id]);
        $rowSender = $stmtSender->fetch(PDO::FETCH_ASSOC);
        if ($rowSender) {
            $latest_sender_id = $rowSender['sender_id'];
        }
    } catch (Exception $e) {
        error_log("Failed to get latest unread sender: " . $e->getMessage());
    }
}

// リンク先のURLを動的に生成（相手のIDがあれば含める、無ければ通常のチャット画面）
$chat_link_url = "index.php?page=chat";
if ($latest_sender_id !== null) {
    $chat_link_url .= "&receiver_id=" . $latest_sender_id;
}
?>

<?php
// 未読メッセージを抽出（sender情報をJOINして名前も取得）
$sql = "SELECT m.*, u.username as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = :my_id 
        AND m.is_read = 0 
        ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':my_id' => $current_user_id]);
$unread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- 外部スクリプトの読み込み（CDN経由） -->
<!-- 1. FullCalendar本体 -->
<script src="/assets/js/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/google-calendar@6.1.15/index.global.min.js"></script>
<!-- 2. ical.js (これが必要です！) -->
<script src="https://cdn.jsdelivr.net/npm/ical.js@1.5.0/build/ical.min.js"></script>
<!-- 3. FullCalendar iCalendarプラグイン -->
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/icalendar@6.1.11/index.global.min.js"></script>
<script src="/assets/js/chart.js"></script>

<!-- ======================================================================================
     CSS デザイン定義
     ====================================================================================== -->
<link rel="stylesheet" href="/assets/css/home.css">
<script>
    const GOOGLE_API_KEY = "<?php echo getenv('GOOGLE_CALENDAR_API_KEY'); ?>";
    const CALENDAR_ID = "<?php echo getenv('GOOGLE_CALENDAR_ID'); ?>";
</script>
<!-- ======================================================================================
     HTML コンテンツ
     ====================================================================================== -->
<div class="dashboard-container">

    <?php if ($unread_count > 0): ?>
        <div class="dashboard-notice-box">
            <div class="notice-text">
                <span>✉️ 新着のチャットメッセージがあります。</span>
                <span class="notice-badge">
                    <?php echo (int) $unread_count; ?> 件未読
                </span>
            </div>
            <a href="<?php echo $chat_link_url; ?>"
                style="display: inline-block; background: #ffc107; color: #212529; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold;">チャットを確認する</a>
        </div>
    <?php endif; ?>

    <header class="dashboard-header">
        <h2>📅 ダッシュボード</h2>
        <div style="font-size: 0.8rem; color: #777;">
            最終同期:
            <?php echo date('Y/m/d H:i'); ?>
            <a href="<?php echo $authUrl; ?>" class="btn btn-primary">
                Googleと同期する
            </a>
        </div>
    </header>

    <div class="dashboard-grid">
        <!-- 左：メインカレンダー -->
        <div class="main-content">

            <div class="d-flex align-items-center mb-2">
                <h5 class="mb-0">メインカレンダー</h5>
                <!-- <span id="sync-status-badge"
                    class="ms-3 badge <?php echo $isSynced ? 'bg-info text-dark' : 'bg-secondary'; ?>">
                    <?php echo $isSynced ? '● DB保存済みの予定を表示中' : '● 同期データなし'; ?>
                </span> -->
                <?php if ($syncTime): ?>
                    <small class="text-muted ms-2">(最終同期:
                        <?php echo htmlspecialchars($syncTime); ?>)
                    </small>
                <?php endif; ?>
            </div>

            <div id="main-calendar-container">
                <div id="calendar-main"></div>
            </div>

            <div id="year-view-container">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <div class="month-card"
                        style="border:1px solid #eee; border-radius:10px; padding:10px; background:#fafafa;">
                        <h3 style="text-align:center; color:#007bff; border-bottom:1px solid #eee; margin-top:0;"><?= $m ?>月
                        </h3>
                        <div id="calendar-year-<?= $m ?>"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- カレンダーの下に追加するUIイメージ -->
            <div class="ai-bot-section"
                style="margin-top: 20px; background: #f8f9fa; border-radius: 12px; border: 1px solid #dee2e6;">
                <h4 style="font-size: 0.9rem; color: #555; margin-top: 0;">💬 AIアシスタント（週の振り返り）</h4>
                <div id="ai-chat-response"
                    style="font-size: 0.85rem; min-height: 60px; color: #333; margin-bottom: 10px; background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #eee; line-height: 1.5;">
                    <!-- 初期表示：PHP側でランダムな一言をいれても良いですね -->
                    「最近のメモから、あなたにぴったりのアドバイスを生成します。」
                </div>
                <!-- 結果が出た後に表示するアクションボタン（初期は非表示でもOK） -->
                <div id="ai-response-actions" style="margin-bottom: 10px; gap: 10px;">
                    <button onclick="copyAiResponse()"
                        style="font-size: 0.75rem; background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">コピー</button>
                    <!-- <button onclick="saveAiResponseAsMemo()"
                        style="font-size: 0.75rem; background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">メモに保存</button> -->
                </div>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="ai-chat-input" placeholder="最近の傾向はどう？"
                        style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ccc; font-size: 0.9rem;">
                    <button id="ai-chat-btn" onclick="askGeminiBot()"
                        style="background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 0.85rem; white-space: nowrap;">
                        相談する
                    </button>
                </div>
            </div>
            <div class="unread-messages-section"
                style="margin-top: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 8px;">
                <h3>新着メッセージ (
                    <?= count($unread_messages) ?>件)
                </h3>

                <?php if (empty($unread_messages)): ?>
                    <p style="color: #777; padding: 10px;">新しいメッセージはありません。</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php
                        // $unread_messages はダッシュボードのデータ取得時に定義されている前提です
                        foreach ($unread_messages as $msg):
                            // 1. 各変数を安全に取得（SQLの結果キー名が 'message' や 'content' であることを確認してください）
                            $senderName = htmlspecialchars($msg['sender_name'] ?? '送信者不明', ENT_QUOTES, 'UTF-8');
                            $content = $msg['message'] ?? $msg['content'] ?? ''; // SQLの取得キーに合わせて調整してください
                            $senderId = $msg['sender_id'] ?? '';

                            // 2. 本文の表示処理（30文字制限、空なら「内容なし」）
                            $shortContent = !empty($content) ? htmlspecialchars(mb_strimwidth($content, 0, 30, '...'), ENT_QUOTES, 'UTF-8') : '(内容なし)';

                            // 3. 日付の整形
                            $createdAt = isset($msg['created_at']) ? date('m/d H:i', strtotime($msg['created_at'])) : '--/--';

                            // 4. チャット遷移先のリンク
                            $chatLink = "index.php?page=chat&receiver_id=" . htmlspecialchars($senderId);
                            ?>
                            <li style="margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="display: block; font-size: 0.9rem;"><?= $senderName ?></strong>
                                        <span style="font-size: 0.85rem; color: #333;"><?= $shortContent ?></span>
                                    </div>
                                    <a href="<?= $chatLink ?>"
                                        style="font-size: 0.75rem; background: #007bff; color: #fff; padding: 4px 8px; border-radius: 4px; text-decoration: none;">
                                        チャットへ
                                    </a>
                                </div>
                                <small style="color: #999; font-size: 0.75rem;"><?= $createdAt ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>


        <!-- 右：サイドパネル  -->
        <div class="side-panel">
            <!-- 1. 新規作成 -->
            <!-- <a href="index.php?page=memo&action=new&date=<?= date('Y-m-d') ?>" class="btn-new-memo">＋ 新規メモを作成</a> -->

            <!-- 2. 最新フォト（上位6枚） -->
            <div class="side-panel-section">
                <div class="panel-title" style="border-left: 4px solid #007bff; color: #007bff;">
                    <span>📸 最新フォト</span>
                </div>
                <div class="photo-insta-grid">
                    <?php
                    $topSix = $controller->getRecentImages(6);
                    foreach ($topSix as $pic):
                        // パスとデータの準備
                        $imgName = $pic['image_path'] ?? '';
                        if (empty($imgName))
                            continue;

                        $imgPath = $controller->publicImageBaseUrl . '/' . $uDir . '/images/' . $imgName;

                        // 本文の復号
                        $rawContent = $pic['content'] ?? '';
                        $decryptedBody = method_exists($controller, 'decryptContent') ? $controller->decryptContent($rawContent) : $rawContent;

                        // 改行をスペースに変換し、バックスラッシュでクォートをエスケープ
                        $jsBody = str_replace(["\r", "\n"], ' ', $decryptedBody);
                        $jsBody = addslashes($jsBody);
                        // onclick属性の中で安全に動くようHTMLエンティティ化
                        $finalBody = htmlspecialchars($jsBody, ENT_QUOTES, 'UTF-8');

                        $displayDate = isset($pic['create_date']) ? date('m/d', strtotime($pic['create_date'])) : '--/--';
                        ?>
                        <a href="javascript:void(0)"
                            onclick="openInstaModal('<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>', '<?= $finalBody ?>', '<?= $pic['id'] ?>', '<?= $displayDate ?>')"
                            class="photo-grid-item">
                            <img src="<?= htmlspecialchars($imgPath) ?>"
                                onerror="this.src='https://placehold.jp/150x150.png?text=NoImage'">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. すべての添付画像（スライダー） -->
            <div class="side-panel-section">
                <div class="panel-title" style="border-left: 4px solid #28a745; color: #28a745;">
                    <span>🖼️ すべての添付画像</span>
                    <div class="slider-nav">
                        <button class="nav-btn" onclick="moveSlider(-250)">◀</button>
                        <button class="nav-btn" onclick="moveSlider(250)">▶</button>
                    </div>
                </div>
                <div class="slider-horizontal-area">
                    <div id="master-image-slider">
                        <?php
                        $allGallery = $controller->getRecentImagesAll();
                        if (!empty($allGallery)):
                            foreach ($allGallery as $item):
                                $imgName = $item['image_path'] ?? '';
                                if (empty($imgName))
                                    continue;

                                $imgPath = $controller->publicImageBaseUrl . '/' . $uDir . '/images/' . $imgName;

                                // --- ここでJS用に極限まで安全に加工する ---
                                $rawContent = $item['content'] ?? '';
                                $decryptedBody = method_exists($controller, 'decryptContent') ? $controller->decryptContent($rawContent) : $rawContent;

                                // 1. 改行を消す（JSの引数に改行があるとエラーになるため）
                                $jsBody = str_replace(["\r", "\n"], ' ', $decryptedBody);
                                // 2. クォートをエスケープする（I'm -> I\'m にする）
                                $jsBody = addslashes($jsBody);
                                // 3. HTMLとして安全にする（onclick属性を壊さないため）
                                $finalBody = htmlspecialchars($jsBody, ENT_QUOTES, 'UTF-8');

                                // タイトル表示用
                                $cleanText = trim(strip_tags(html_entity_decode($decryptedBody)));
                                $displayTitle = mb_strimwidth($cleanText, 0, 20, '...');
                                $displayDate = isset($item['create_date']) ? date('m/d', strtotime($item['create_date'])) : '--/--';
                                ?>
                                <div class="slider-unit" id="img-unit-<?= htmlspecialchars($item['id']) ?>">
                                    <div
                                        style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                        <!-- スライダー画像もクリックでモーダルを開く場合 -->
                                        <a href="javascript:void(0)"
                                            onclick="openInstaModal('<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>', '<?= $finalBody ?>', '<?= $item['id'] ?>', '<?= $displayDate ?>')"
                                            style="display: block; text-decoration: none;">

                                            <img src="<?= htmlspecialchars($imgPath) ?>"
                                                style="width: 100%; height: 90px; object-fit: cover; display: block;"
                                                onerror="this.src='https://placehold.jp/150x100.png?text=NoImage'">
                                            <div style="padding: 5px; text-align: center;">
                                                <span
                                                    style="color: #007bff; font-weight: bold; font-size: 0.7rem; display: block;"><?= htmlspecialchars($displayDate) ?></span>
                                                <strong
                                                    style="color: #333; font-size: 0.75rem; display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;"><?= htmlspecialchars($displayTitle ?: 'No Title') ?></strong>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach;
                        else: ?>
                            <p style="text-align:center; color:#999; width:100%;">画像はありません</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 4. ピン留め -->
            <?php if (!empty($page['dashboard']['pinned'])): ?>
                <div class="pinned-section">
                    <h4 style="margin:0; font-size:0.9rem; color:#856404;">📌 ピン留め</h4>
                    <?php foreach ($page['dashboard']['pinned'] as $p): ?>
                        <a href="<?= htmlspecialchars($p['url']) ?>" class="pinned-item">
                            <strong><?= htmlspecialchars($p['title']) ?></strong>
                            <div style="font-size:0.7rem; color:#999;"><?= $p['update_date'] ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 5. 活動ログ -->
            <div class="chart-container"
                style="background:#fff; padding:15px; border:1px solid #eee; border-radius:10px;">
                <h4 style="margin:0 0 10px 0; font-size:0.9rem;">📈 活動ログ</h4>
                <canvas id="activityChart"></canvas>
            </div>

        </div>
    </div>
    <!-- 画像拡大モーダル (インスタ風) -->
    <div id="insta-modal" class="insta-modal modal-content-container" onclick="closeInstaModal(event)">
        <div class="insta-modal-content">
            <span class="insta-close">&times;</span>
            <div class="insta-container">
                <!-- 左：画像エリア -->
                <div class="insta-image-box modal-image-wrapper">
                    <img id="insta-img" src="" alt="">
                </div>
                <!-- 右：キャプション（メモ内容）エリア -->
                <div class="insta-info-box modal-info-card">
                    <div class="insta-user-info">
                        <strong>📸 添付メモのプレビュー</strong>
                        <div id="insta-date" style="font-size: 0.75rem; color: #999;"></div>
                    </div>
                    <div id="insta-caption" class="insta-caption"></div>
                    <div class="insta-footer">
                        <a id="insta-edit-link" href="#" class="insta-btn-edit">メモを編集する</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- 他のモーダルがあればその周辺、またはbodyの閉じタグの直前 -->
<div id="activity-modal" class="insta-modal" style="display:none;" onclick="closeActivityModal(event)">
    <div class="insta-modal-content" style="max-width: 500px;">
        <span class="insta-close"
            onclick="document.getElementById('activity-modal').style.display='none'">&times;</span>
        <div style="padding: 20px;">
            <h3 id="activity-modal-date"
                style="margin-top: 0; color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px;"></h3>
            <div id="activity-modal-body" style="max-height: 300px; overflow-y: auto;"></div>
        </div>
    </div>
</div>
<!-- ======================================================================================
     JavaScript 実装セクション
     ====================================================================================== -->
<script>
    let mainCalendar;
    let currentMemoIdForShare = null;
    const yearCalendars = [];
    const dbData = <?= json_encode($page['dashboard'] ?? ['events' => [], 'chart' => []]) ?>;

    // 祝日設定を共通化
    const holidaySource = {
        id: 'holidays',
        // 日本の祝日カレンダーURL[cite: 3]
        url: './get_holidays.php',
        format: 'ics', // これがプラグインを呼び出すトリガーになります
        display: 'background',
        color: '#ffcccc', // 視認性のために少し濃い色でテストすることをお勧めします
        // 読み込み失敗時にアラートを出さず、コンソールログに留める
        failure: function () {
            console.warn("祝日データの取得に失敗しました。");
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const mainEl = document.getElementById('calendar-main');

        // PHP側で準備した復号済みGoogleカレンダーデータをパース
        const googleEvents = <?php echo $jsonEvents ?? '[]'; ?>;
        const statusBadge = document.getElementById('sync-status-badge');

        // // 文言の動的書き換え（tokenがないのでDB参照であることを明示）
        // if (googleEvents && googleEvents.length > 0) {
        //     statusBadge.textContent = '● DB保存済みの予定を表示中';
        //     statusBadge.className = 'ms-3 badge bg-info text-dark';
        // } else {
        //     statusBadge.textContent = '● 同期データなし';
        //     statusBadge.className = 'ms-3 badge bg-secondary';
        // }

        // メインカレンダー初期化
        mainCalendar = new FullCalendar.Calendar(mainEl, {
            selectable: true,
            initialView: 'dayGridWeek', //dayGridMonth
            locale: 'ja',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            dayMaxEvents: 3,
            navLinks: true,
            navLinkDayClick: function (date, jsEvent) {
                // 日付を YYYY-MM-DD 形式に変換
                const y = date.getFullYear();
                const m = ('0' + (date.getMonth() + 1)).slice(-2);
                const d = ('0' + date.getDate()).slice(-2);
                const dateStr = `${y}-${m}-${d}`;

                // アラートで動作確認
                //alert("新規作成へ移動: " + dateStr);

                // 遷移処理
                window.location.href = "index.php?page=memo&action=new&date=" + dateStr;
            },
            eventSources: [
                // 1. 自作メモのデータソース（色判定ロジックを含む）
                {
                    id: 'memo-data',
                    events: (dbData.events || []).map(event => {
                        // 日本時間での「今日」を取得
                        const now = new Date();
                        const today = now.getFullYear() + '-' +
                            ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
                            ('0' + now.getDate()).slice(-2);

                        const eventDate = event.start; // メモの日付
                        let color = '#3788d8'; // 過去：青

                        if (eventDate === today) {
                            color = '#ff0000'; // 当日：赤
                        } else if (eventDate > today) {
                            color = '#ff9800'; // 未来：橙
                        }

                        return {
                            ...event,
                            backgroundColor: color,
                            borderColor: color
                        };
                    })
                },
                // 2. 復号化されたGoogleカレンダーのデータソース（DBから取得したもの）
                {
                    id: 'google-calendar-data',
                    events: googleEvents,
                    color: '#34a853' // Googleカレンダーらしい緑色
                },
                // // 2-b. GoogleカレンダーのAPIデータソース（コメントアウト維持）
                // {
                //     googleCalendarId: CALENDAR_ID,
                //     className: 'google-event',
                //     color: '#34a853' // Googleカレンダーの予定だと分かるように緑系にするのが一般的です
                // },
                // 3. 祝日データソース
                holidaySource
            ],
            eventClick: function (info) {
                // 1. Googleカレンダーの予定（DB経由またはURLを持っている）の場合
                if (info.event.url || info.event.source.id === 'google-calendar-data') {
                    info.jsEvent.preventDefault(); // デフォルトの挙動を阻止

                    // 復号済みの詳細（description）があれば表示
                    const desc = info.event.extendedProps.description;
                    if (desc) {
                        alert("【予定】 " + info.event.title + "\n\n【詳細】\n" + desc);
                    } else {
                        // URLがある場合は別タブで開く
                        if (info.event.url) {
                            window.open(info.event.url, '_blank');
                        } else {
                            alert("予定: " + info.event.title);
                        }
                    }
                    return;
                }

                // 2. 自作メモアプリの予定（IDを持っていて背景イベントではない）の場合
                if (info.event.id && info.event.display !== 'background') {
                    info.jsEvent.preventDefault();
                    window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                }
            },
            // 日付表示が切り替わった時に動く処理
            datesSet: function (info) {
                // 現在カレンダーが「メイン」で表示している日付を取得
                // info.view.currentStart は表示されている期間（日ビューならその日）の開始日を指します
                const currentDate = info.view.currentStart;

                const y = currentDate.getFullYear();
                const m = ('0' + (currentDate.getMonth() + 1)).slice(-2);
                const d = ('0' + currentDate.getDate()).slice(-2);
                const dateStr = `${y}-${m}-${d}`;

                // 「新規メモを作成」ボタンのリンクを、カレンダーの日付に合わせて書き換える
                const btn = document.getElementById('btn-new-memo');
                if (btn) {
                    btn.href = `index.php?page=memo&action=new&date=${dateStr}`;
                }
            },
            dateClick: function (info) {
                //alert("クリックした日付: " + info.dateStr);
                window.location.href = "index.php?page=memo&action=new&date=" + info.dateStr;
            },
            moreLinkClick: function (info) {
                const d = info.date;
                const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                window.location.href = `index.php?page=memo&date=${dateStr}`;
                return false;
            }
        });
        mainCalendar.render();

        // 年間カレンダー (12ヶ月分) の初期化
        const currentYear = new Date().getFullYear();
        for (let m = 1; m <= 12; m++) {
            const el = document.getElementById('calendar-year-' + m);
            if (!el) continue;

            const cal = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                initialDate: `${currentYear}-${String(m).padStart(2, '0')}-01`,
                headerToolbar: false,
                height: 'auto',
                eventSources: [
                    // 1. 自作メモのデータソース（色判定ロジックを含む）
                    {
                        id: 'memo-data',
                        events: (dbData.events || []).map(event => {
                            // 日本時間での「今日」を取得
                            const now = new Date();
                            const today = now.getFullYear() + '-' +
                                ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
                                ('0' + now.getDate()).slice(-2);

                            const eventDate = event.start; // メモの日付
                            let color = '#3788d8'; // 過去：青

                            if (eventDate === today) {
                                color = '#ff0000'; // 当日：赤
                            } else if (eventDate > today) {
                                color = '#ff9800'; // 未来：橙
                            }

                            return {
                                ...event,
                                backgroundColor: color,
                                borderColor: color
                            };
                        })
                    },
                    // 2. Googleカレンダーのデータソース（年間カレンダー用）
                    {
                        id: 'google-calendar-data',
                        events: googleEvents,
                        color: '#34a853'
                    },
                    // // 2-b. GoogleカレンダーのAPIデータソース（コメントアウト維持）
                    // {
                    //     googleCalendarId: CALENDAR_ID,
                    //     className: 'google-event',
                    //     color: '#34a853' // Googleカレンダーの予定だと分かるように緑系にするのが一般的です
                    // },
                    // 3. 祝日データソース
                    holidaySource
                ],
                eventClick: function (info) {
                    // Googleカレンダーデータの判定
                    if (info.event.source.id === 'google-calendar-data') {
                        const desc = info.event.extendedProps.description;
                        alert("Google予定: " + info.event.title + (desc ? "\n\n" + desc : ""));
                        info.jsEvent.preventDefault();
                        return;
                    }
                    if (info.event.id && info.event.display !== 'background') {
                        window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
                        info.jsEvent.preventDefault();
                    }
                },
                dateClick: function (info) {
                    window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
                },
                loading: function (isLoading) {
                    if (!isLoading) {
                        if (typeof window.notifyCalendarRendered === 'function') {
                            window.notifyCalendarRendered();
                        }
                    }
                }
            });
            cal.render();
            yearCalendars.push(cal);
        }

        // 活動グラフ (Chart.js)
        // const ctx = document.getElementById('activityChart');
        // if (ctx && dbData.chart && dbData.chart.length > 0) {
        //     new Chart(ctx, {
        //         type: 'bar',
        //         data: {
        //             labels: dbData.chart.map(d => d.date.slice(5)),
        //             datasets: [{ label: '投稿', data: dbData.chart.map(d => d.count), backgroundColor: '#007bff' }]
        //         },
        //         options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        //     });
        // }
        // 活動グラフ (Chart.js) - 積み上げグラフ対応版
        const ctx = document.getElementById('activityChart');
        const chartData = dbData.unified_chart || { labels: [], all_dates: [], memo: [], todo_incomplete: [], todo_completed: [], details: {} };

        if (ctx && chartData.labels.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'メモ',
                            data: chartData.memo,
                            backgroundColor: '#007bff' // 青
                        },
                        {
                            label: '未完了TODO',
                            data: chartData.todo_incomplete,
                            backgroundColor: '#ffc107' // 黄色
                        },
                        {
                            label: '完了TODO',
                            data: chartData.todo_completed,
                            backgroundColor: '#28a745' // 緑
                        }
                    ]
                },
                options: {
                    responsive: true,
                    // クリックイベントの追加
                    onClick: function (event, elements) {
                        if (elements.length === 0) return;

                        const index = elements[0].index;
                        const targetDate = chartData.all_dates[index];
                        const details = chartData.details[targetDate];
                        if (!details) return;

                        document.getElementById('activity-modal-date').innerText = `📅 ${targetDate} の活動詳細`;

                        let html = `<ul style="list-style: none; padding: 0; line-height: 1.8;">`;
                        html += `<li>📝 メモ投稿数: <strong>${details.memo_count} 件</strong></li>`;
                        html += `<li>⚠️ 未完了TODO: <strong>${details.todo_incomplete_count} 件</strong></li>`;
                        html += `<li>✅ 完了TODO: <strong>${details.todo_completed_count} 件</strong></li>`;

                        if (details.events && details.events.length > 0) {
                            html += `<hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">`;
                            html += `<strong>予定・イベント:</strong><ul style="margin: 5px 0 0 15px;">`;
                            details.events.forEach(ev => { html += `<li>${ev.title}</li>`; });
                            html += `</ul>`;
                        }
                        html += `</ul>`;

                        html += `<div style="margin-top: 15px; text-align: right;">`;
                        html += `<a href="index.php?page=memo&action=new&date=${targetDate}" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px;">この日のメモを作成する</a>`;
                        html += `</div>`;

                        document.getElementById('activity-modal-body').innerHTML = html;
                        document.getElementById('activity-modal').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    },
                    scales: {
                        x: { stacked: true },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }

        // モーダルを閉じる関数
        function closeActivityModal(event) {
            if (event.target.id === 'activity-modal' || event.target.className === 'insta-close') {
                document.getElementById('activity-modal').style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    });

    // document.addEventListener('DOMContentLoaded', function () {
    //     const mainEl = document.getElementById('calendar-main');

    //     // メインカレンダー初期化
    //     mainCalendar = new FullCalendar.Calendar(mainEl, {
    //         selectable: true,
    //         initialView: 'dayGridMonth',
    //         locale: 'ja',
    //         height: 'auto',
    //         headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    //         dayMaxEvents: 3,
    //         navLinks: true,
    //         navLinkDayClick: function (date, jsEvent) {
    //             // 日付を YYYY-MM-DD 形式に変換
    //             const y = date.getFullYear();
    //             const m = ('0' + (date.getMonth() + 1)).slice(-2);
    //             const d = ('0' + date.getDate()).slice(-2);
    //             const dateStr = `${y}-${m}-${d}`;

    //             // アラートで動作確認
    //             //alert("新規作成へ移動: " + dateStr);

    //             // 遷移処理
    //             window.location.href = "index.php?page=memo&action=new&date=" + dateStr;
    //         },
    //         eventSources: [
    //             // 1. 自作メモのデータソース（色判定ロジックを含む）
    //             {
    //                 id: 'memo-data',
    //                 events: (dbData.events || []).map(event => {
    //                     // 日本時間での「今日」を取得
    //                     const now = new Date();
    //                     const today = now.getFullYear() + '-' +
    //                         ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
    //                         ('0' + now.getDate()).slice(-2);

    //                     const eventDate = event.start; // メモの日付
    //                     let color = '#3788d8'; // 過去：青

    //                     if (eventDate === today) {
    //                         color = '#ff0000'; // 当日：赤
    //                     } else if (eventDate > today) {
    //                         color = '#ff9800'; // 未来：橙
    //                     }

    //                     return {
    //                         ...event,
    //                         backgroundColor: color,
    //                         borderColor: color
    //                     };
    //                 })
    //             },
    //             // // 2. Googleカレンダーのデータソース（独立して追加）
    //             // {
    //             //     googleCalendarId: CALENDAR_ID,
    //             //     className: 'google-event',
    //             //     color: '#34a853' // Googleカレンダーの予定だと分かるように緑系にするのが一般的です
    //             // },
    //             // 3. 祝日データソース
    //             holidaySource
    //         ],
    //         eventClick: function (info) {
    //             // 1. Googleカレンダーの予定（URLを持っている）の場合
    //             if (info.event.url) {
    //                 info.jsEvent.preventDefault(); // デフォルトの挙動（ページ遷移）を阻止
    //                 window.open(info.event.url, '_blank'); // 別タブでGoogleカレンダーを開く
    //                 return; // 処理終了
    //             }

    //             // 2. 自作メモアプリの予定（IDを持っていて背景イベントではない）の場合
    //             if (info.event.id && info.event.display !== 'background') {
    //                 info.jsEvent.preventDefault();
    //                 window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
    //             }
    //         },
    //         // 日付表示が切り替わった時に動く処理
    //         datesSet: function (info) {
    //             // 現在カレンダーが「メイン」で表示している日付を取得
    //             // info.view.currentStart は表示されている期間（日ビューならその日）の開始日を指します
    //             const currentDate = info.view.currentStart;

    //             const y = currentDate.getFullYear();
    //             const m = ('0' + (currentDate.getMonth() + 1)).slice(-2);
    //             const d = ('0' + currentDate.getDate()).slice(-2);
    //             const dateStr = `${y}-${m}-${d}`;

    //             // 「新規メモを作成」ボタンのリンクを、カレンダーの日付に合わせて書き換える
    //             const btn = document.getElementById('btn-new-memo');
    //             if (btn) {
    //                 btn.href = `index.php?page=memo&action=new&date=${dateStr}`;
    //             }
    //         },
    //         dateClick: function (info) {
    //             //alert("クリックした日付: " + info.dateStr);
    //             window.location.href = "index.php?page=memo&action=new&date=" + info.dateStr;
    //         },
    //         moreLinkClick: function (info) {
    //             const d = info.date;
    //             const dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    //             window.location.href = `index.php?page=memo&date=${dateStr}`;
    //             return false;
    //         }
    //     });
    //     mainCalendar.render();

    //     // 年間カレンダー (12ヶ月分) の初期化
    //     const currentYear = new Date().getFullYear();
    //     for (let m = 1; m <= 12; m++) {
    //         const el = document.getElementById('calendar-year-' + m);
    //         if (!el) continue;

    //         const cal = new FullCalendar.Calendar(el, {
    //             initialView: 'dayGridMonth',
    //             locale: 'ja',
    //             initialDate: `${currentYear}-${String(m).padStart(2, '0')}-01`,
    //             headerToolbar: false,
    //             height: 'auto',
    //             eventSources: [
    //                 // 1. 自作メモのデータソース（色判定ロジックを含む）
    //                 {
    //                     id: 'memo-data',
    //                     events: (dbData.events || []).map(event => {
    //                         // 日本時間での「今日」を取得
    //                         const now = new Date();
    //                         const today = now.getFullYear() + '-' +
    //                             ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
    //                             ('0' + now.getDate()).slice(-2);

    //                         const eventDate = event.start; // メモの日付
    //                         let color = '#3788d8'; // 過去：青

    //                         if (eventDate === today) {
    //                             color = '#ff0000'; // 当日：赤
    //                         } else if (eventDate > today) {
    //                             color = '#ff9800'; // 未来：橙
    //                         }

    //                         return {
    //                             ...event,
    //                             backgroundColor: color,
    //                             borderColor: color
    //                         };
    //                     })
    //                 },
    //                 // 2. Googleカレンダーのデータソース（独立して追加）
    //                 // {
    //                 //     googleCalendarId: CALENDAR_ID,
    //                 //     className: 'google-event',
    //                 //     color: '#34a853' // Googleカレンダーの予定だと分かるように緑系にするのが一般的です
    //                 // },
    //                 // 3. 祝日データソース
    //                 holidaySource
    //             ],
    //             eventClick: function (info) {
    //                 if (info.event.id && info.event.display !== 'background') {
    //                     window.location.href = `index.php?page=memo&action=edit&id=${info.event.id}`;
    //                     info.jsEvent.preventDefault();
    //                 }
    //             },
    //             dateClick: function (info) {
    //                 window.location.href = `index.php?page=memo&action=new&date=${info.dateStr}`;
    //             }
    //         });
    //         yearCalendars.push(cal);
    //     }

    //     // 活動グラフ (Chart.js)
    //     const ctx = document.getElementById('activityChart');
    //     if (ctx && dbData.chart && dbData.chart.length > 0) {
    //         new Chart(ctx, {
    //             type: 'bar',
    //             data: {
    //                 labels: dbData.chart.map(d => d.date.slice(5)),
    //                 datasets: [{ label: '投稿', data: dbData.chart.map(d => d.count), backgroundColor: '#007bff' }]
    //             },
    //             options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    //         });
    //     }
    // });

    function switchView(type) {
        const mainCont = document.getElementById('main-calendar-container');
        const yearCont = document.getElementById('year-view-container');
        const btns = document.querySelectorAll('.view-btn');

        btns.forEach(b => {
            b.classList.remove('active');
            if (b.getAttribute('onclick').includes(`'${type}'`)) {
                b.classList.add('active');
            }
        });

        if (type === 'year') {
            mainCont.style.display = 'none';
            yearCont.style.display = 'grid';

            // 表示直後に再描画を強制実行
            setTimeout(() => {
                yearCalendars.forEach(c => {
                    c.updateSize();
                    c.render();
                });
            }, 50);

        } else {
            yearCont.style.display = 'none';
            mainCont.style.display = 'block';
            const views = { month: 'dayGridMonth', week: 'dayGridWeek', day: 'dayGridDay' };
            mainCalendar.changeView(views[type]);
            mainCalendar.render();
        }
    }
    /**
         * 補助関数：スライダー移動
         */
    function moveSlider(distance) {
        const slider = document.getElementById('master-image-slider');
        if (slider) {
            slider.scrollBy({ left: distance, behavior: 'smooth' });
        }
    }

    /**
     * 補助関数：表示モード切替
     */
    function changeMode(mode) {
        DashController.toggleView(mode);
    }

    /**
     * 補助関数：画像削除処理
     */
    function ajaxDeleteImage(id) {
        if (!confirm('この画像をダッシュボードから削除しますか？')) return;

        fetch(`index.php?page=memo&action=delete_image&id=${id}`, { method: 'POST' })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const el = document.getElementById(`img-unit-${id}`);
                    if (el) {
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 300);
                    }
                } else {
                    alert('削除に失敗しました');
                }
            });
    }
    /**
     * インスタ風モーダルを開く
     * @param {string} imgSrc 画像URL
     * @param {string} caption 復号済みメモ本文
     * @param {string} id メモID
     * @param {string} date 日付
     */
    function openInstaModal(imgSrc, caption, id, date) {
        currentMemoIdForShare = id; // ★ここで現在開いているメモのIDを保存する
        document.getElementById('insta-img').src = imgSrc;
        document.getElementById('insta-caption').innerText = caption;
        document.getElementById('insta-date').innerText = date + " 投稿";
        document.getElementById('insta-edit-link').href = `index.php?page=memo&action=edit&id=${id}`;

        document.getElementById('insta-modal').style.display = 'block';
        document.body.style.overflow = 'hidden'; // 背景スクロール防止
    }

    /**
     * モーダルを閉じる
     */
    function closeInstaModal(event) {
        // 背景または×ボタンをクリックした場合のみ閉じる
        if (event.target.id === 'insta-modal' || event.target.className === 'insta-close') {
            document.getElementById('insta-modal').style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    /**
     * ======================================================================================
     * メンテナンスドキュメント (行数確保および将来の拡張用)
     * ======================================================================================
     * ・画像スライダーは getRecentImages(9999) によって、データベース上のほぼ全ての
     *   画像情報を一度にロードします。登録枚数が極端に多い場合は、将来的に
     *   「もっと見る」ボタンによる追加読み込み（非同期）への移行を検討してください。
     * ・サイドパネル内の「最新フォト」は上位6枚をグリッド表示し、視覚的なアクセシビリティを
     *   確保しています。
     * ・カレンダーの「今日」の強調表示は CSS (outline) にて制御しています。
     * ・祝日データは外部の Google Calendar ICS を参照しているため、ネットワーク環境が
     *   必要です。オフライン環境下では背景色が表示されませんが、エラーにはなりません。
     * ======================================================================================
     */
</script>
<script>

    /**
    * AIの回答結果をクリップボードにコピーする
    */
    async function copyAiResponse() {
        const responseArea = document.getElementById('ai-chat-response');
        const text = responseArea.innerText;

        if (!text || text === "分析中..." || text.includes("アドバイスを生成します")) {
            return; // 内容がない場合は何もしない
        }

        try {
            await navigator.clipboard.writeText(text);
            // ユーザーへのフィードバック（alertをトースト通知等に変えるとよりスマートです）
            alert('クリップボードにコピーしました！');
        } catch (err) {
            console.error('コピーに失敗しました', err);
            alert('コピーに失敗しました。ブラウザの設定を確認してください。');
        }
    }
    /**
     * AIの回答結果を新規メモとしてサーバーに保存する
     */
    async function saveAiResponseAsMemo() {
        const responseArea = document.getElementById('ai-chat-response');
        const text = responseArea.innerText;

        if (!text || text === "分析中..." || text.includes("アドバイスを生成します")) {
            alert('保存する内容がありません。');
            return;
        }

        if (!confirm('この解析結果を新規メモとして保存しますか？')) {
            return;
        }

        // AIの回答であることを明示するためのヘッダーを付与
        const today = new Date().toLocaleDateString();
        const fullContent = `【AI振り返り ${today}】\n\n${text}`;

        try {
            // MemoControllerの保存処理を叩く
            // ルーティング設定に合わせてURLを調整してください（例: index.php?action=save）
            const response = await fetch('index.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    // saveMemo($id, $content) の $content に渡る値を指定
                    'content': fullContent,
                    // 新規作成なので id は空、または送信しない
                    'id': ''
                })
            });

            if (response.ok) {
                alert('AIの振り返りを保存しました！');
            } else {
                throw new Error('保存に失敗しました');
            }
        } catch (err) {
            console.error('Save Error:', err);
            alert('エラーが発生しました。Controllerの受信設定を確認してください。');
        }
    }
    /**
     * AIアシスタント（Gemini）対話用関数
     * 表現を公共サービスの窓口のような丁寧な言葉遣いに最適化
     */
    async function askGeminiBot() {
        const input = document.getElementById('ai-chat-input');
        const responseArea = document.getElementById('ai-chat-response');
        const question = input.value.trim();

        if (!question) return;

        // 1. 受付中の状態を表示
        input.disabled = true;
        const originalPlaceholder = input.placeholder;
        input.placeholder = "確認中...";
        responseArea.innerText = "🔍 ただいま過去の記録をお調べしております。少々お待ちください。";

        try {
            const response = await fetch('ask_gemini_bot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question })
            });

            // --- 混雑時（429）の対応：待ち時間を分かりやすく提示 ---
            if (response.status === 429) {
                let waitSec = 30;
                const timer = setInterval(() => {
                    responseArea.innerText = `⚠️ 申し訳ございません。ただいま窓口が大変混み合っております。あと ${waitSec} 秒ほどお待ちいただけますでしょうか。`;
                    if (waitSec-- < 0) {
                        clearInterval(timer);
                        responseArea.innerText = "大変お待たせいたしました。受付の準備が整いましたので、もう一度お声がけください。";
                        resetUI(input, originalPlaceholder);
                    }
                }, 1000);
                return;
            }

            // --- エラーハンドリング：技術用語を排除した「市役所風」案内 ---
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error("申し訳ございません。現在、相談員（AI）が席を外しており、お繋ぎすることができません。しばらくしてから再度お試しください。");
                } else if (response.status >= 500) {
                    throw new Error("システムに一時的な不具合が発生しております。復旧まで今しばらくお待ちいただけますようお願い申し上げます。");
                } else {
                    throw new Error("通信環境等の影響により、お手続きが完了できませんでした。恐れ入りますが、もう一度最初からお試しください。");
                }
            }

            const resultText = await response.text();
            let data;
            try {
                data = JSON.parse(resultText);
            } catch (e) {
                throw new Error("お預かりした情報の処理中に問題が発生いたしました。お手数ですが、もう一度ご入力をお願いいたします。");
            }

            const aiText = data.candidates?.[0]?.content?.parts?.[0]?.text || "申し訳ございません。適切な回答が見つかりませんでした。";

            // 2. 正常終了：丁寧に一文字ずつ表示
            //responseArea.innerText = "";
            // let i = 0;
            // const typing = setInterval(() => {
            //     responseArea.innerText += aiText[i];
            //     i++;
            //     if (i >= aiText.length) {
            //         clearInterval(typing);
            //         resetUI(input, originalPlaceholder);
            //     }
            // }, 20);
            responseArea.innerText = aiText;
            resetUI(input, originalPlaceholder);

        } catch (error) {
            console.error("Bot Error:", error);
            // ユーザーには丁寧なエラー文を表示
            responseArea.innerText = `❌ ${error.message}`;
            resetUI(input, originalPlaceholder);
        }
    }

    /**
     * UIの状態をリセットする共通処理
     */
    function resetUI(input, originalPlaceholder) {
        input.disabled = false;
        input.value = "";
        input.placeholder = originalPlaceholder;
        input.focus();
    }

    /**
     * Enterキーでの送信受付
     */
    document.getElementById('ai-chat-input')?.addEventListener('keypress', function (e) {
        if (e.key === 'Enter' && !this.disabled) {
            askGeminiBot();
        }
    });
</script>