<?php
// app/utils/MailUtil.php

require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailUtil
{
    public static function sendMail($to, $subject, $body, $fromName = 'システム管理')
    {
        $mail = new PHPMailer(true);

        try {
            // SMTP設定
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;

            // PHPMailerの自動変換を極力バイパスする
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // ★ 対策：Subject/FromName を手動でRFC2047形式に固定
            // PHPの内部エンコード(internal_encoding)が何であれ、バイナリとしてUTF-8ヘッダーを作る
            $utf8_subject = mb_convert_encoding($subject, "UTF-8", "UTF-8,SJIS,EUC-JP,auto");
            $mail->Subject = "=?UTF-8?B?" . base64_encode($utf8_subject) . "?=";

            $utf8_fromName = mb_convert_encoding($fromName, "UTF-8", "UTF-8,SJIS,EUC-JP,auto");
            $mail->setFrom(ADMIN_EMAIL, "=?UTF-8?B?" . base64_encode($utf8_fromName) . "?=");

            $mail->addAddress($to);

            // ★ 対策：本文の「不正バイト（ヌル文字等）」を物理的に除去してからセット
            // これにより $new_password 以降が消える現象を防ぎます
            $clean_body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $body);
            $mail->Body = mb_convert_encoding($clean_body, "UTF-8", "UTF-8,SJIS,EUC-JP,auto");

            $mail->send();
            return true;

        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }
}
?>