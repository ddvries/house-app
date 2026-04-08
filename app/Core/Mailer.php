<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    public static function sendVerification(string $to, string $token): bool
    {
        $appUrl   = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
        $from     = Env::get('MAIL_FROM', 'noreply@example.com');
        $fromName = Env::get('MAIL_FROM_NAME', 'Interieurstijl Dossier');
        $lang     = Language::current();

        $subject = Language::translate('auth.verification_email_subject', $lang);
        $intro   = Language::translate('auth.verification_email_intro', $lang);
        $btnText = Language::translate('auth.verify_button', $lang);
        $footer  = Language::translate('auth.verification_email_expires', $lang);

        $link = $appUrl . '/verify_email.php?token=' . urlencode($token);

        $safeLink    = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeIntro   = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeBtn     = htmlspecialchars($btnText, ENT_QUOTES, 'UTF-8');
        $safeFooter  = htmlspecialchars($footer, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><title>{$safeSubject}</title></head>
        <body style="font-family:sans-serif;background:#f5f5f5;margin:0;padding:2rem">
          <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
            <h2 style="margin-top:0">{$safeSubject}</h2>
            <p>{$safeIntro}</p>
            <p>
              <a href="{$safeLink}"
                 style="background:#2563eb;color:#fff;padding:.75rem 1.5rem;border-radius:6px;text-decoration:none;display:inline-block;font-weight:600">
                {$safeBtn}
              </a>
            </p>
            <p style="font-size:.85rem;color:#666;word-break:break-all">{$safeLink}</p>
            <hr style="border:none;border-top:1px solid #eee;margin:1.5rem 0">
            <p style="font-size:.8rem;color:#999">{$safeFooter}</p>
          </div>
        </body>
        </html>
        HTML;

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion(),
        ]);

        return mail($to, $encodedSubject, $html, $headers);
    }
}
