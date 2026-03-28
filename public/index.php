<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");

/**
 * 1. DB接続設定の読み込み
 * config.php を dbconfig.php にリネームしたものを読み込みます
 */
require_once __DIR__ . '/../app/dbconfig.php';

/**
 * 2. ルーターの読み込み
 * ここで session_start() や 認証チェック(403判定) が行われます
 */
require_once __DIR__ . '/../app/router.php';

// 3. ページパラメータの取得（デフォルトは home）
$page = $_GET['page'] ?? 'home';

/**
 * 4. ルーティングの実行
 * router.php 内で定義した checkAuthentication($page) が走り、
 * 未ログインかつ制限ページなら、ここで 403 を出して終了(exit)します。
 */
route($page);
?>