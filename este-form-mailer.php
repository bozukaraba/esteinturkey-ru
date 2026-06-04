<?php
/**
 * Plugin Name: Este in Turkey — Form Mailer
 * Description: Quiz form verilerini HTML e-posta olarak gönderir
 * Version: 1.0.0
 * Author: Este in Turkey
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
    register_rest_route( 'este/v1', '/quiz', [
        'methods'             => 'POST',
        'callback'            => 'este_quiz_handler',
        'permission_callback' => '__return_true',
    ] );
} );

function este_quiz_handler( WP_REST_Request $req ) {
    $data = $req->get_json_params();
    if ( empty( $data ) ) $data = $req->get_params();

    $name    = sanitize_text_field( $data['name']    ?? '' );
    $phone   = sanitize_text_field( $data['phone']   ?? '' );
    $email   = sanitize_email(      $data['email']   ?? '' );
    $gender  = sanitize_text_field( $data['gender']  ?? '—' );
    $age     = intval(              $data['age']     ?? 0 );
    $years   = intval(              $data['years']   ?? 0 );
    $prev    = sanitize_text_field( $data['prev']    ?? '—' );
    $norwood = sanitize_text_field( $data['norwood'] ?? '—' );
    $when    = sanitize_text_field( $data['when']    ?? '—' );
    $source  = sanitize_text_field( $data['source']  ?? 'Сайт' );

    if ( ! $name || ! $phone ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'name and phone required' ], 400 );
    }

    $to      = 'info@esteinturkey.com';
    $subject = "Анализ волос — {$name} | Este in Turkey";
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Este in Turkey <info@esteinturkey.com>',
    ];

    $body = este_email_html( compact( 'name','phone','email','gender','age','years','prev','norwood','when','source' ) );
    $sent = wp_mail( $to, $subject, $body, $headers );

    return new WP_REST_Response( [ 'success' => $sent ], $sent ? 200 : 500 );
}

function este_email_html( $d ) {
    $name    = esc_html( $d['name'] );
    $phone   = esc_html( $d['phone'] );
    $email   = esc_html( $d['email'] ?: '—' );
    $gender  = esc_html( $d['gender'] );
    $age     = intval( $d['age'] );
    $years   = intval( $d['years'] );
    $prev    = esc_html( $d['prev'] );
    $norwood = esc_html( $d['norwood'] );
    $when    = esc_html( $d['when'] );
    $source  = esc_html( $d['source'] );
    $date    = date_i18n( 'd.m.Y H:i', current_time('timestamp') );

    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Анализ волос — {$name}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f2;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f2;padding:40px 0;">
<tr><td align="center">

<table width="600" cellpadding="0" cellspacing="0" border="0"
  style="max-width:600px;width:100%;background:#ffffff;border-radius:20px;overflow:hidden;
         box-shadow:0 8px 40px rgba(0,0,0,.12);">

  <!-- ── HEADER ── -->
  <tr>
    <td style="background:linear-gradient(135deg,#0d2b2b 0%,#154444 45%,#1D5C5C 75%,#2E8B8B 100%);
               padding:40px 48px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td>
            <div style="font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.16em;
                        text-transform:uppercase;margin-bottom:10px;">ESTE IN TURKEY · СТАМБУЛ</div>
            <div style="font-size:30px;font-weight:900;color:#ffffff;line-height:1.15;
                        letter-spacing:-.5px;">Новая заявка<br>на анализ волос</div>
            <div style="margin-top:12px;display:inline-block;background:rgba(255,255,255,.12);
                        border-radius:20px;padding:5px 14px;font-size:13px;color:rgba(255,255,255,.8);">
              📋 {$source} &nbsp;·&nbsp; {$date}
            </div>
          </td>
          <td align="right" style="vertical-align:top;padding-left:20px;">
            <div style="width:68px;height:68px;background:rgba(255,255,255,.1);border-radius:50%;
                        text-align:center;line-height:68px;font-size:32px;">💎</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── DIVIDER ── -->
  <tr>
    <td style="height:4px;background:linear-gradient(90deg,#2E8B8B,#4AACAC,#2E8B8B);"></td>
  </tr>

  <!-- ── CLIENT CARD ── -->
  <tr>
    <td style="padding:36px 48px 0;">
      <div style="font-size:11px;font-weight:800;color:#2E8B8B;letter-spacing:.12em;
                  text-transform:uppercase;margin-bottom:16px;">👤 Контактные данные</div>
      <table width="100%" cellpadding="0" cellspacing="0" border="0"
             style="border-radius:12px;overflow:hidden;border:1.5px solid #e8e0d8;">
        <tr style="background:#faf6f1;">
          <td style="padding:14px 20px;border-bottom:1px solid #e8e0d8;width:40%;">
            <span style="font-size:12px;color:#8A9A9A;font-weight:600;">ИМЯ</span>
          </td>
          <td style="padding:14px 20px;border-bottom:1px solid #e8e0d8;">
            <span style="font-size:15px;font-weight:800;color:#154444;">{$name}</span>
          </td>
        </tr>
        <tr style="background:#ffffff;">
          <td style="padding:14px 20px;border-bottom:1px solid #e8e0d8;">
            <span style="font-size:12px;color:#8A9A9A;font-weight:600;">ТЕЛЕФОН</span>
          </td>
          <td style="padding:14px 20px;border-bottom:1px solid #e8e0d8;">
            <a href="tel:{$phone}" style="font-size:15px;font-weight:800;color:#1D5C5C;
               text-decoration:none;">{$phone}</a>
          </td>
        </tr>
        <tr style="background:#faf6f1;">
          <td style="padding:14px 20px;">
            <span style="font-size:12px;color:#8A9A9A;font-weight:600;">E-MAIL</span>
          </td>
          <td style="padding:14px 20px;">
            <span style="font-size:15px;font-weight:800;color:#154444;">{$email}</span>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── ANALYSIS DATA ── -->
  <tr>
    <td style="padding:28px 48px 0;">
      <div style="font-size:11px;font-weight:800;color:#2E8B8B;letter-spacing:.12em;
                  text-transform:uppercase;margin-bottom:16px;">📊 Данные анализа</div>
      <table width="100%" cellpadding="0" cellspacing="0" border="0">

        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:13px;color:#8A9A9A;">Пол</span>
          </td>
          <td align="right" style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:14px;font-weight:700;color:#154444;
                         background:#f0ebe3;border-radius:6px;padding:3px 10px;">{$gender}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:13px;color:#8A9A9A;">Возраст</span>
          </td>
          <td align="right" style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:14px;font-weight:700;color:#154444;
                         background:#f0ebe3;border-radius:6px;padding:3px 10px;">{$age} лет</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:13px;color:#8A9A9A;">Выпадение (лет)</span>
          </td>
          <td align="right" style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:14px;font-weight:700;color:#154444;
                         background:#f0ebe3;border-radius:6px;padding:3px 10px;">{$years} лет</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:13px;color:#8A9A9A;">Была пересадка ранее</span>
          </td>
          <td align="right" style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:14px;font-weight:700;color:#154444;
                         background:#f0ebe3;border-radius:6px;padding:3px 10px;">{$prev}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:13px;color:#8A9A9A;">Степень облысения</span>
          </td>
          <td align="right" style="padding:12px 0;border-bottom:1px solid #f0ebe3;">
            <span style="font-size:14px;font-weight:700;color:#1D5C5C;
                         background:#d8eeee;border-radius:6px;padding:3px 10px;">{$norwood}</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 0;">
            <span style="font-size:13px;color:#8A9A9A;">Когда планирует</span>
          </td>
          <td align="right" style="padding:12px 0;">
            <span style="font-size:14px;font-weight:700;color:#154444;
                         background:#f0ebe3;border-radius:6px;padding:3px 10px;">{$when}</span>
          </td>
        </tr>

      </table>
    </td>
  </tr>

  <!-- ── QUICK ACTIONS ── -->
  <tr>
    <td style="padding:28px 48px 0;">
      <div style="font-size:11px;font-weight:800;color:#2E8B8B;letter-spacing:.12em;
                  text-transform:uppercase;margin-bottom:16px;">⚡ Быстрый ответ</div>
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td style="padding-right:8px;">
            <a href="https://wa.me/905468189180"
               style="display:block;text-align:center;
                      background:linear-gradient(135deg,#154444,#2E8B8B);
                      color:#ffffff;text-decoration:none;padding:15px 20px;
                      border-radius:10px;font-weight:800;font-size:14px;
                      letter-spacing:.02em;">
              📱 WhatsApp
            </a>
          </td>
          <td style="padding-left:8px;">
            <a href="tel:+905468189180"
               style="display:block;text-align:center;
                      background:#F8F4EF;color:#154444;text-decoration:none;
                      padding:15px 20px;border-radius:10px;font-weight:800;font-size:14px;
                      border:2px solid rgba(29,92,92,.15);">
              📞 Позвонить
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── FOOTER ── -->
  <tr>
    <td style="padding:36px 48px;margin-top:28px;">
      <div style="border-top:1px solid #ede8e0;padding-top:28px;text-align:center;">
        <div style="font-size:15px;font-weight:900;color:#154444;letter-spacing:.04em;">
          ESTE IN TURKEY
        </div>
        <div style="font-size:12px;color:#9AAAAA;margin-top:6px;line-height:1.6;">
          Клиника медицинского туризма в Стамбуле, Турция<br>
          <a href="mailto:info@esteinturkey.com"
             style="color:#2E8B8B;text-decoration:none;">info@esteinturkey.com</a>
          &nbsp;·&nbsp;
          <a href="tel:+905468189180"
             style="color:#2E8B8B;text-decoration:none;">+90 546 818 91 80</a>
        </div>
        <div style="font-size:11px;color:#b8c4c4;margin-top:8px;">
          Altunizade, Nuhkuyusu Cd No:94, 34714 Üsküdar / İstanbul
        </div>
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}
