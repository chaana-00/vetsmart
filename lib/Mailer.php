<?php
/**
 * Mailer - tiny dependency-free SMTP sender (TLS/SSL + AUTH LOGIN) with PHP mail() fallback.
 * Sends HTML + plain-text emails with an optional inline logo.
 *
 *   [$ok, $error] = Mailer::send('user@example.com', 'Subject', '<p>Hi</p>', 'Hi', 'User Name');
 */
class Mailer
{
    private static function clean(string $s): string { return trim(preg_replace('/[\r\n]+/', ' ', $s)); }
    private static function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
    private static function b64(string $s): string { return chunk_split(base64_encode($s), 76, "\r\n"); }

    public static function send(string $to, string $subject, string $html, string $text = '', ?string $toName = null): array
    {
        $cfg = require __DIR__ . '/mail_config.php';
        $to = self::clean($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Invalid recipient email address.'];
        if ($text === '') $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

        [$headers, $body] = self::compose($cfg, $to, $toName, $subject, $html, $text);
        try {
            return ($cfg['driver'] ?? 'smtp') === 'mail' ? self::viaMail($to, $subject, $headers, $body) : self::viaSmtp($cfg, $to, $headers, $body);
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /** Build MIME headers + body */
    private static function compose(array $cfg, string $to, ?string $toName, string $subject, string $html, string $text): array
    {
        $from = self::clean($cfg['from']); $fromName = self::clean($cfg['from_name'] ?? '');
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $b1 = '=_rel_' . bin2hex(random_bytes(8)); $b2 = '=_alt_' . bin2hex(random_bytes(8));
        $logo = (!empty($cfg['logo']) && is_file($cfg['logo'])) ? $cfg['logo'] : null;

        $h  = 'Date: ' . date('r') . "\r\n";
        $h .= 'From: ' . ($fromName !== '' ? self::enc($fromName) . ' ' : '') . "<$from>\r\n";
        $h .= 'To: ' . ($toName ? self::enc(self::clean($toName)) . ' ' : '') . "<$to>\r\n";
        $h .= 'Subject: ' . self::enc(self::clean($subject)) . "\r\n";
        $h .= 'Message-ID: <' . bin2hex(random_bytes(10)) . "@$domain>\r\n";
        $h .= "MIME-Version: 1.0\r\n";

        $alt  = "--$b2\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . self::b64($text);
        $alt .= "--$b2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . self::b64($html);
        $alt .= "--$b2--\r\n";

        if ($logo) {
            $mime = (getimagesize($logo)['mime'] ?? 'image/png');
            $h .= "Content-Type: multipart/related; boundary=\"$b1\"; type=\"multipart/alternative\"\r\n";
            $body = "--$b1\r\nContent-Type: multipart/alternative; boundary=\"$b2\"\r\n\r\n" . $alt
                  . "--$b1\r\nContent-Type: $mime; name=\"logo\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <vetsmart-logo>\r\nContent-Disposition: inline; filename=\"logo\"\r\n\r\n"
                  . self::b64(file_get_contents($logo)) . "--$b1--\r\n";
        } else {
            $h .= "Content-Type: multipart/alternative; boundary=\"$b2\"\r\n";
            $body = $alt;
        }
        return [$h, $body];
    }

    private static function viaMail(string $to, string $subject, string $headers, string $body): array
    {
        // mail() adds To/Subject itself, so strip them from the header block
        $h = preg_replace('/^(To|Subject): .*\r\n/m', '', $headers);
        $ok = @mail($to, self::enc(self::clean($subject)), $body, rtrim($h));
        return [$ok, $ok ? '' : 'PHP mail() failed - configure SMTP in lib/mail_config.php.'];
    }

    private static function viaSmtp(array $cfg, string $to, string $headers, string $body): array
    {
        $secure = $cfg['secure'] ?? ''; $host = $cfg['host']; $port = (int)$cfg['port']; $timeout = (int)($cfg['timeout'] ?? 15);
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, $timeout);
        if (!$fp) return [false, "Cannot connect to $host:$port ($errstr)"];
        stream_set_timeout($fp, $timeout);

        $read = function () use ($fp): array {
            $code = 0; $msg = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $msg .= $line; $code = (int)substr($line, 0, 3);
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return [$code, trim($msg)];
        };
        $cmd = function (string $c, array $expect) use ($fp, $read): void {
            fwrite($fp, $c . "\r\n");
            [$code, $msg] = $read();
            if (!in_array($code, $expect, true)) throw new RuntimeException('SMTP error after "' . (stripos($c, 'AUTH') === 0 || strlen($c) > 60 ? '...' : $c) . '": ' . $msg);
        };

        [$code, $msg] = $read();
        if ($code !== 220) { fclose($fp); return [false, 'SMTP greeting failed: ' . $msg]; }
        try {
            $me = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $cmd("EHLO $me", [250]);
            if ($secure === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS negotiation failed.');
                $cmd("EHLO $me", [250]);
            }
            if (!empty($cfg['username'])) {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($cfg['username']), [334]);
                $cmd(base64_encode($cfg['password']), [235]);
            }
            $cmd('MAIL FROM:<' . self::clean($cfg['from']) . '>', [250]);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', [354]);
            $msgData = preg_replace('/\r\n|\r|\n/', "\r\n", $headers . "\r\n" . $body);
            $msgData = preg_replace('/^\./m', '..', $msgData);
            fwrite($fp, $msgData . "\r\n.\r\n");
            [$code, $msg] = $read();
            if ($code !== 250) throw new RuntimeException('Message rejected: ' . $msg);
            @fwrite($fp, "QUIT\r\n");
            fclose($fp);
            return [true, ''];
        } catch (Throwable $e) {
            @fclose($fp);
            return [false, $e->getMessage()];
        }
    }
}
