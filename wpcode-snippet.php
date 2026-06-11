<?php
add_action( 'rest_api_init', function () {
    register_rest_route( 'este/v1', '/quiz', [
        'methods'             => 'POST',
        'callback'            => 'este_quiz_handler',
        'permission_callback' => '__return_true',
    ] );
} );

if ( ! function_exists( 'este_quiz_handler' ) ) :
function este_quiz_handler( WP_REST_Request $req ) {
    $data = $req->get_json_params();
    if ( empty( $data ) ) $data = $req->get_params();

    $name    = sanitize_text_field( $data['name']    ?? '' );
    $phone   = sanitize_text_field( $data['phone']   ?? '' );
    $email   = sanitize_email(      $data['email']   ?? '' );
    $gender  = sanitize_text_field( $data['gender']  ?? '' );
    $age     = intval(              $data['age']     ?? 0 );
    $years   = intval(              $data['years']   ?? 0 );
    $prev    = sanitize_text_field( $data['prev']    ?? '' );
    $norwood = sanitize_text_field( $data['norwood'] ?? '' );
    $when    = sanitize_text_field( $data['when']    ?? '' );
    $source  = sanitize_text_field( $data['source']  ?? 'Сайт' );

    if ( ! $name || ! $phone ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'name and phone required' ], 400 );
    }

    $to      = 'info@esteinturkey.com';
    $subject = "Новая заявка — {$name} | Este in Turkey";
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Este in Turkey <info@esteinturkey.com>',
    ];

    $date = date( 'd.m.Y H:i' );

    $body = '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#eef2f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.1);">
  <tr><td style="background:linear-gradient(135deg,#0d2b2b,#1D5C5C,#2E8B8B);padding:36px 40px;">
    <div style="font-size:13px;color:rgba(255,255,255,.6);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px;">ESTE IN TURKEY</div>
    <div style="font-size:26px;font-weight:900;color:#fff;">Новая заявка с сайта</div>
    <div style="margin-top:10px;color:rgba(255,255,255,.7);font-size:13px;">' . esc_html($source) . ' &nbsp;·&nbsp; ' . $date . '</div>
  </td></tr>
  <tr><td style="padding:32px 40px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8e0d8;border-radius:10px;overflow:hidden;">
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;width:40%;border-bottom:1px solid #e8e0d8;">ИМЯ</td>
        <td style="padding:12px 18px;font-size:15px;font-weight:800;color:#154444;border-bottom:1px solid #e8e0d8;">' . esc_html($name) . '</td>
      </tr>
      <tr>
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ТЕЛЕФОН</td>
        <td style="padding:12px 18px;border-bottom:1px solid #e8e0d8;"><a href="tel:' . esc_attr($phone) . '" style="font-size:15px;font-weight:800;color:#1D5C5C;text-decoration:none;">' . esc_html($phone) . '</a></td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">E-MAIL</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;border-bottom:1px solid #e8e0d8;">' . esc_html($email ?: '—') . '</td>
      </tr>
      <tr>
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ПОЛ</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;border-bottom:1px solid #e8e0d8;">' . esc_html($gender ?: '—') . '</td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ВОЗРАСТ</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;border-bottom:1px solid #e8e0d8;">' . ($age ? $age . ' лет' : '—') . '</td>
      </tr>
      <tr>
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">ВЫПАДЕНИЕ (лет)</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;border-bottom:1px solid #e8e0d8;">' . ($years ? $years . ' лет' : '—') . '</td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">БЫЛА ПЕРЕСАДКА</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;border-bottom:1px solid #e8e0d8;">' . esc_html($prev ?: '—') . '</td>
      </tr>
      <tr>
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;border-bottom:1px solid #e8e0d8;">СТЕПЕНЬ ОБЛЫСЕНИЯ</td>
        <td style="padding:12px 18px;font-size:14px;color:#1D5C5C;font-weight:700;border-bottom:1px solid #e8e0d8;">' . esc_html($norwood ?: '—') . '</td>
      </tr>
      <tr style="background:#faf6f1;">
        <td style="padding:12px 18px;font-size:12px;color:#888;font-weight:700;">КОГДА ПЛАНИРУЕТ</td>
        <td style="padding:12px 18px;font-size:14px;color:#154444;">' . esc_html($when ?: '—') . '</td>
      </tr>
    </table>
  </td></tr>
  <tr><td style="padding:0 40px 32px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding-right:8px;">
          <a href="https://wa.me/905468189180" style="display:block;text-align:center;background:linear-gradient(135deg,#154444,#2E8B8B);color:#fff;text-decoration:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;">📱 WhatsApp</a>
        </td>
        <td style="padding-left:8px;">
          <a href="tel:+905468189180" style="display:block;text-align:center;background:#f0f7f7;color:#154444;text-decoration:none;padding:14px;border-radius:10px;font-weight:800;font-size:14px;border:2px solid #d0e8e8;">📞 Позвонить</a>
        </td>
      </tr>
    </table>
  </td></tr>
  <tr><td style="padding:20px 40px;border-top:1px solid #eee;text-align:center;font-size:12px;color:#aaa;">
    Este in Turkey · info@esteinturkey.com · +90 546 818 91 80
  </td></tr>
</table>
</td></tr></table>
</body></html>';

    $sent = wp_mail( $to, $subject, $body, $headers );
    return new WP_REST_Response( [ 'success' => $sent ], $sent ? 200 : 500 );
}
endif;
