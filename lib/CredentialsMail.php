<?php
require_once __DIR__ . '/Mailer.php';

/**
 * Sends the "Welcome to VetSmart" email with the login credentials.
 * Returns [bool $sent, string $error].
 */
function send_credentials_email(string $toEmail, string $fullName, string $username, string $plainPassword, ?string $loginUrl = null, string $role = 'user'): array
{
    if ($loginUrl === null) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = defined('BASE_URL') ? BASE_URL : '';
        $loginUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base . '/login.php';
    }
    $cfg = require __DIR__ . '/mail_config.php';
    $hasLogo = !empty($cfg['logo']) && is_file($cfg['logo']);
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $roleLabel = $role === 'admin' ? 'Admin' : 'User'; $name = $h($fullName); $user = $h($username); $pass = $h($plainPassword); $url = $h($loginUrl);

    $logoHtml = $hasLogo ? '<img src="cid:vetsmart-logo" alt="VetSmart by Farm Chemie" width="230" style="display:block;margin:0 auto;border:0;background:#fff;border-radius:14px;padding:10px 14px">' : '<div style="font:800 28px Arial,sans-serif;color:#fff;text-align:center">VetSmart</div>';

    $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#eef4f7;padding:24px 12px;font-family:Segoe UI,Arial,sans-serif;color:#10303F">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe6ee">
  <tr><td style="background:#0a4a52;background-image:linear-gradient(120deg,#06222F,#0a5a68 60%,#12a36b);padding:26px 20px">{$logoHtml}</td></tr>
  <tr><td style="height:5px;background:#2160ff;background-image:linear-gradient(90deg,#2160ff,#12a36b);font-size:0;line-height:0">&nbsp;</td></tr>
  <tr><td style="padding:30px 32px 8px">
    <h1 style="margin:0 0 8px;font-size:22px;color:#06222F">Welcome to VetSmart, {$name}!</h1>
    <p style="margin:0 0 18px;line-height:1.55;color:#4a6572">Your account has been created by the administrator. Use the credentials below to sign in.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e4f6ee;border-left:5px solid #12a36b;border-radius:10px">
      <tr><td style="padding:16px 18px">
        <div style="font-size:12px;color:#0b7f52;font-weight:700;margin-bottom:2px">Username</div>
        <div style="font:600 16px Consolas,Menlo,monospace;color:#06222F;margin-bottom:14px;word-break:break-all">{$user}</div>
        <div style="font-size:12px;color:#0b7f52;font-weight:700;margin-bottom:2px">Password</div>
        <div style="font:600 16px Consolas,Menlo,monospace;color:#06222F;word-break:break-all">{$pass}</div>
      </td></tr>
    </table>
    <p style="text-align:center;margin:26px 0"><a href="{$url}" style="background:#2160ff;background-image:linear-gradient(120deg,#2160ff,#12a36b);color:#ffffff;text-decoration:none;font-weight:700;padding:13px 30px;border-radius:12px;display:inline-block">Sign in to VetSmart</a></p>
    <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#4a6572">Choose the role <b>{$roleLabel}</b> on the sign-in page. For your security, please change your password after your first sign-in (Settings), and do not share these details.</p>
    <p style="margin:0;font-size:12px;color:#7a919c;word-break:break-all">Button not working? Open: {$url}</p>
  </td></tr>
  <tr><td style="padding:22px 32px 26px"><div style="border-top:2px solid #12a36b;padding-top:12px;text-align:center;font-size:12px;color:#5d7880;line-height:1.6">
    <b style="color:#06222F"><i>This email is system generated!</i></b><br>© 2027 | VetSmart Designed &amp; Developed by <b style="color:#1747c9">DYVESTA IT</b> | +94 70 228 5959</div></td></tr>
</table></td></tr></table></body></html>
HTML;

    $text = "Welcome to VetSmart, $fullName!\n\nYour account has been created.\n\nUsername: $username\nPassword: $plainPassword\n\nSign in: $loginUrl\n(Choose the role \"$roleLabel\".) Please change your password after your first sign-in.\n\nThis email is system generated!\n© 2027 | VetSmart Designed & Developed by DYVESTA IT | +94 70 228 5959";

    return Mailer::send($toEmail, 'Your VetSmart account details', $html, $text, $fullName);
}