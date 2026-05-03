<?php
// get_holidays.php
header('Content-Type: text/calendar; charset=utf-8');
$url = 'https://calendar.google.com/calendar/ical/ja.japanese%23holiday%40group.v.calendar.google.com/public/basic.ics';

// Googleからicsデータを取得してそのまま出力
echo file_get_contents($url);