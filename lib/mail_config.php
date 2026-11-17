<?php
/** Email settings. Fill these in once. Works with Gmail, Google Workspace, Outlook, Zoho, cPanel mail, SendGrid SMTP, etc. */
return [
    'driver'    => 'smtp',                 // 'smtp' (recommended) or 'mail' (PHP mail(), works only if the server is set up for it)
    'host'      => 'smtp.gmail.com',       // e.g. smtp.gmail.com | smtp.office365.com | mail.yourdomain.lk
    'port'      => 587,                    // 587 with 'tls'  |  465 with 'ssl'
    'secure'    => 'tls',                  // 'tls' (STARTTLS) | 'ssl' | '' (none)
    'username'  => 'your-sender@gmail.com',
    'password'  => 'your-app-password',    // Gmail: create an "App password" (needs 2-step verification)
    'from'      => 'your-sender@gmail.com',
    'from_name' => 'VetSmart by Farm Chemie',
    'timeout'   => 15,
    'logo'      => __DIR__ . '/../assets/img/docx_logo.png',   // shown at the top of the email
];
