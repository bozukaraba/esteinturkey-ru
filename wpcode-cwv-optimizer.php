<?php
/* ═══════════════════════════════════════════════════
   Este in Turkey — Core Web Vitals Optimizer v2
   WPCode Lite → PHP Snippet → Her Yerde Çalıştır
   ═══════════════════════════════════════════════════ */

/* ── 1. LCP resmi preload — img src ile tam eşleşen URL ── */
add_action('wp_head', function () {

    /* Elementor'daki hero img src'si ile AYNI URL olmalı */
    $lcp_jpg = 'https://esteinturkey.com/ru/wp-content/uploads/2026/06/sld-1.jpg';

    echo '<link rel="preload" as="image" href="' . esc_url($lcp_jpg) . '" fetchpriority="high">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

}, 1);

/* ── 2. Google Fonts → font-display: swap ── */
add_filter('style_loader_tag', function ($html, $handle, $href) {
    if (strpos($href, 'fonts.googleapis.com/css') !== false) {
        if (strpos($href, 'display=') === false) {
            $href = add_query_arg('display', 'swap', $href);
        } else {
            $href = preg_replace('/display=[^&"\']+/', 'display=swap', $href);
        }
        $html = preg_replace(
            '/href=["\'][^"\']*fonts\.googleapis[^"\']*["\']/',
            'href="' . esc_url($href) . '"',
            $html
        );
    }
    return $html;
}, 10, 3);

/* ── 3. Output buffer: Elementor hero img'den lzl lazy-load kaldır ──
   wp_content_img_tag Elementor'u yakalamaz — ob_start gerekli ── */
add_action('template_redirect', function () {
    ob_start(function ($html) {

        /* Hero img'den lzl sınıflarını ve data- özniteliklerini kaldır */
        $html = preg_replace_callback(
            '/<img([^>]*fetchpriority=["\']high["\'][^>]*)>/i',
            function ($m) {
                $tag = $m[1];
                /* lzl class'larını temizle */
                $tag = preg_replace('/\s*(lzl-cached|lzl-ed)\s*/', ' ', $tag);
                /* data-lzl-src'yi kaldır (src zaten var) */
                $tag = preg_replace('/\s*data-lzl-src=["\'][^"\']*["\']/', '', $tag);
                /* loading="lazy" yerine eager yap */
                $tag = str_replace('loading="lazy"', 'loading="eager"', $tag);
                /* decoding="async" → sync yapma, "auto" yeterli */
                return '<img' . $tag . '>';
            },
            $html
        );

        return $html;
    });
});

/* ── 4. WebP editor desteği ── */
add_filter('wp_image_editors', function ($editors) {
    array_unshift($editors, 'WP_Image_Editor_Imagick');
    return $editors;
});
