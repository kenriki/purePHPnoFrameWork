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
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;

            // 基本設定
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->Subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

            // 送信元も同様に
            $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
            $mail->setFrom(ADMIN_EMAIL, $encodedFromName);

            $mail->addAddress($to);
            $mail->Body = $body;

            $mail->send();
            return true;

        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }
}
?>