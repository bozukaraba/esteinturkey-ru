<?php
/* ═══════════════════════════════════════════════
   Este in Turkey — Standalone Mail Handler
   Yüklenecek yer: public_html/este-mail.php
   ═══════════════════════════════════════════════ */

define('SMTP_HOST', 'mail.esteinturkey.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'info@esteinturkey.com');
define('SMTP_PASS', 'Este2025Este');
define('SMTP_FROM', 'info@esteinturkey.com');
define('SMTP_NAME', 'Este in Turkey');
define('MAIL_TO',   'info@esteinturkey.com');

/* ── CORS ── */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }

/* ── Veri al ── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$name    = trim(strip_tags($data['name']    ?? ''));
$phone   = trim(strip_tags($data['phone']   ?? ''));
$email   = trim(strip_tags($data['email']   ?? ''));
$gender  = trim(strip_tags($data['gender']  ?? '—'));
$age     = intval($data['age']   ?? 0);
$years   = intval($data['years'] ?? 0);
$prev    = trim(strip_tags($data['prev']    ?? '—'));
$norwood = trim(strip_tags($data['norwood'] ?? '—'));
$when    = trim(strip_tags($data['when']    ?? '—'));
$source  = trim(strip_tags($data['source']  ?? 'Сайт'));

if (!$name || !$phone) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'name and phone required']);
    exit;
}

/* ── HTML mail gövdesi ── */
$date = date('d.m.Y H:i');
$body = '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#eef2f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.10);">

  <tr><td style="background:linear-gradient(135deg,#0d2b2b 0%,#1D5C5C 55%,#2E8B8B 100%);padding:36px 40px;">
    <div style="font-size:11px;color:rgba(255,255,255,.55);letter-spacing:.14em;text-transform:uppercase;margin-bottom:8px;">ESTE IN TURKEY · СТАМБУЛ</div>
    <div style="font-size:26px;font-weight:900;color:#fff;line-height:1.2;">Новая заявка<br>с сайта</div>
    <div style="margin-top:10px;display:inline-block;background:rgba(255,255,255,.12);border-radius:20px;padding:5px 14px;font-size:13px;color:rgba(255,255,255,.8);">
      📋 ' . htmlspecialchars($source) . ' &nbsp;·&nbsp; ' . $date . '
    </div>
  </td></tr>

  <tr><td style="height:4px;background:linear-gradient(90deg,#2E8B8B,#4AACAC,#2E8B8B);"></td></tr>

  <tr><td style="padding:32px 40px 0;">
    <div style="font-size:11px;font-weight:800;color:#2E8B8B;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">👤 Контактные данные</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e8e0d8;border-radius:10px;overflow:hidden;">
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;width:38%;border-bottom:1px solid #e8e0d8;">ИМЯ</td>
        <td style="padding:12px 18px;font-size:15px;font-weight:800;color:#154444;border-bottom:1px solid #e8e0d8;">' . htmlspecialchars($name) . '</td>
      </tr>
      <tr>
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ТЕЛЕФОН</td>
        <td style="padding:12px 18px;border-bottom:1px solid #e8e0d8;"><a href="tel:' . htmlspecialchars($phone) . '" style="font-size:15px;font-weight:800;color:#1D5C5C;text-decoration:none;">' . htmlspecialchars($phone) . '</a></td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;">E-MAIL</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;">' . htmlspecialchars($email ?: '—') . '</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:24px 40px 0;">
    <div style="font-size:11px;font-weight:800;color:#2E8B8B;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px;">📊 Данные анализа</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e8e0d8;border-radius:10px;overflow:hidden;">
      <tr style="background:#faf6f1;">
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;width:38%;border-bottom:1px solid #e8e0d8;">ПОЛ</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#154444;border-bottom:1px solid #e8e0d8;">' . htmlspecialchars($gender) . '</td>
      </tr>
      <tr>
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ВОЗРАСТ</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#154444;border-bottom:1px solid #e8e0d8;">' . ($age ? $age . ' лет' : '—') . '</td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ВЫПАДЕНИЕ (лет)</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#154444;border-bottom:1px solid #e8e0d8;">' . ($years ? $years . ' лет' : '—') . '</td>
      </tr>
      <tr>
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">БЫЛА ПЕРЕСАДКА</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#154444;border-bottom:1px solid #e8e0d8;">' . htmlspecialchars($prev) . '</td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">СТЕПЕНЬ ОБЛЫСЕНИЯ</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#1D5C5C;border-bottom:1px solid #e8e0d8;">' . htmlspecialchars($norwood) . '</td>
      </tr>
      <tr>
        <td style="padding:11px 18px;font-size:12px;color:#888;font-weight:700;">КОГДА ПЛАНИРУЕТ</td>
        <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#154444;">' . htmlspecialchars($when) . '</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:24px 40px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding-right:6px;"><a href="https://wa.me/905468189180" style="display:block;text-align:center;background:linear-gradient(135deg,#154444,#2E8B8B);color:#fff;text-decoration:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;">📱 WhatsApp</a></td>
        <td style="padding-left:6px;"><a href="tel:+905468189180" style="display:block;text-align:center;background:#f0f7f7;color:#154444;text-decoration:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;border:2px solid #d0e8e8;">📞 Позвонить</a></td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:18px 40px;border-top:1px solid #eee;text-align:center;font-size:12px;color:#aaa;">
    Este in Turkey · info@esteinturkey.com · +90 546 818 91 80
  </td></tr>

</table>
</td></tr></table>
</body></html>';

/* ── SMTP gönder ── */
$result = este_smtp_send(MAIL_TO, 'Заявка: ' . $name . ' | Este in Turkey', $body);

echo json_encode(['success' => $result['ok'], 'debug' => $result['log']]);

/* ════════════════════════════════════════════════
   SMTP fonksiyonu — library gerektirmez
   ════════════════════════════════════════════════ */
function este_smtp_send($to, $subject, $htmlBody) {
    $log = [];
    $sock = @stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT
    );

    if (!$sock) {
        return ['ok' => false, 'log' => ['CONNECT ERROR: ' . $errstr . ' (' . $errno . ')']];
    }

    stream_set_timeout($sock, 15);

    $readLine = function() use ($sock, &$log) {
        $r = fgets($sock, 4096);
        $log[] = '< ' . trim($r);
        return $r;
    };
    $send = function($cmd) use ($sock, &$log) {
        $log[] = '> ' . trim($cmd);
        fwrite($sock, $cmd . "\r\n");
    };
    /* EHLO gibi çok satır dönen yanıtları tam oku */
    $readAll = function() use ($readLine) {
        $last = '';
        while (true) {
            $line = $readLine();
            if (!$line) break;
            /* "250 " (boşlukla) → son satır; "250-" → devam ediyor */
            if (strlen($line) >= 4 && $line[3] === ' ') { $last = $line; break; }
            if (strlen($line) >= 4 && $line[3] === '-')  { continue; }
            break;
        }
        return $last;
    };

    $readLine(); // 220 banner satır 1
    /* Sunucu 220 multi-line banner gönderebilir — hepsini oku */
    while (true) {
        $peek = fgets($sock, 4096);
        $log[] = '< ' . trim($peek);
        /* "220 " (son satır boşlukla) → çık; "220-" → devam */
        if (!$peek || (strlen($peek) >= 4 && $peek[3] === ' ')) break;
    }

    $send('EHLO esteinturkey.com');
    $ehloResp = $readAll();

    $send('AUTH LOGIN');
    $readLine(); // 334 username prompt
    $send(base64_encode(SMTP_USER));
    $readLine(); // 334 password prompt
    $send(base64_encode(SMTP_PASS));
    $authResp = $readLine(); // 235 veya 535

    if (strpos($authResp, '235') === false) {
        fclose($sock);
        return ['ok' => false, 'log' => $log];
    }

    $send('MAIL FROM:<' . SMTP_FROM . '>');
    $readLine();
    $send('RCPT TO:<' . $to . '>');
    $readLine();
    $send('DATA');
    $readLine(); // 354

    $subjectB64 = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $msg  = 'From: ' . SMTP_NAME . ' <' . SMTP_FROM . '>' . "\r\n";
    $msg .= 'To: ' . $to . "\r\n";
    $msg .= 'Subject: ' . $subjectB64 . "\r\n";
    $msg .= 'MIME-Version: 1.0' . "\r\n";
    $msg .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $msg .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.";

    fwrite($sock, $msg . "\r\n");
    $dataResp = $readLine(); // 250 OK

    $send('QUIT');
    fclose($sock);

    $ok = strpos($dataResp, '250') !== false;
    return ['ok' => $ok, 'log' => $log];
}

