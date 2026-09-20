<?php
declare(strict_types=1);
namespace GymPro\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class Mailer
{
    public static function send(string $to, string $subject, string $html, string $text=''): bool
    {
        if (!\env('MAIL_ENABLED', false)) {
            $line=sprintf("[%s] TO %s | %s | %s\n",date('c'),$to,$subject,strip_tags($text ?: $html));
            file_put_contents(ROOT_PATH.'/storage/logs/mail.log',$line,FILE_APPEND|LOCK_EX);
            return true;
        }
        if (!class_exists(PHPMailer::class)) {
            file_put_contents(ROOT_PATH.'/storage/logs/mail.log',"PHPMailer is not installed.\n",FILE_APPEND|LOCK_EX);
            return false;
        }
        try {
            $mail=new PHPMailer(true);
            $mail->isSMTP(); $mail->Host=(string)\env('MAIL_HOST','smtp.gmail.com'); $mail->SMTPAuth=true;
            $mail->Username=(string)\env('MAIL_USERNAME'); $mail->Password=(string)\env('MAIL_PASSWORD');
            $mail->SMTPSecure=(string)\env('MAIL_ENCRYPTION','tls'); $mail->Port=(int)\env('MAIL_PORT',587);
            $mail->setFrom((string)\env('MAIL_FROM_ADDRESS'),(string)\env('MAIL_FROM_NAME','GymPro'));
            $mail->addAddress($to); $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$html; $mail->AltBody=$text ?: strip_tags($html); $mail->send(); return true;
        } catch (Throwable $e) {
            file_put_contents(ROOT_PATH.'/storage/logs/mail.log',sprintf("[%s] ERROR %s\n",date('c'),$e->getMessage()),FILE_APPEND|LOCK_EX); return false;
        }
    }
}
