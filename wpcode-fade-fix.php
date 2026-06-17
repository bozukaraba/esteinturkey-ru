<?php
/* ═══════════════════════════════════════════════════════════
   Este in Turkey — Global Fix v4
   WPCode Lite → PHP Snippet → Her Yerde Çalıştır

   1. Header gecikmesi: CSS inline → 0ms bekleme
   2. .elementor-invisible override: anında görünür
   3. .eit-fue-fade: scroll animasyonu (viewport dışı için)
   4. Floating WhatsApp + Telegram: tüm sayfalarda
   ═══════════════════════════════════════════════════════════ */

/* ── 1. HEAD: Visibility override (önce yükle, priority 1) ── */
add_action('wp_head', function () {
    ?>
<style id="eit-vis-fix">
.elementor-invisible{visibility:visible!important;opacity:1!important}
.elementor-section:has(#eitHeader),.elementor-column:has(#eitHeader),
.e-con:has(#eitHeader),.e-child:has(#eitHeader),
.elementor-widget-container:has(#eitHeader){
  visibility:visible!important;opacity:1!important;
  padding:0!important;margin:0!important;
}
#eitHeader,#eitHeader *{visibility:visible!important;opacity:1!important}
.eit-fue-fade{opacity:1!important;transform:none!important;transition:none!important}
.eit-fue-fade.eit-anim-ready{opacity:0!important;transform:translateY(28px)!important;transition:opacity .6s cubic-bezier(.4,0,.2,1),transform .6s cubic-bezier(.4,0,.2,1)!important}
.eit-fue-fade.eit-anim-ready.eit-fue-visible{opacity:1!important;transform:none!important}
@media(prefers-reduced-motion:reduce){.eit-fue-fade.eit-anim-ready{opacity:1!important;transform:none!important;transition:none!important}}
</style>
<script>
(function(){
  if(!('IntersectionObserver' in window))return;
  function init(){
    var vh=window.innerHeight||document.documentElement.clientHeight;
    document.querySelectorAll('.eit-fue-fade').forEach(function(el){
      var r=el.getBoundingClientRect();
      if(r.top>vh+60){
        el.classList.add('eit-anim-ready');
        new IntersectionObserver(function(en,ob){
          en.forEach(function(e){
            if(e.isIntersecting){e.target.classList.add('eit-fue-visible');ob.unobserve(e.target);}
          });
        },{threshold:0.05}).observe(el);
      }
    });
  }
  document.readyState==='loading'
    ?document.addEventListener('DOMContentLoaded',init)
    :init();
})();
</script>
    <?php
}, 1);

/* ── 2. FOOTER: Floating WhatsApp + Telegram butonları ── */
add_action('wp_footer', function () {
    /* Sadece bir kez render et — sayfa içinde zaten varsa atlat */
    if (did_action('eit_float_btns_rendered')) return;
    do_action('eit_float_btns_rendered');

    /* Dil tespiti */
    $lang = 'ru';
    if (function_exists('pll_current_language')) {
        $lang = pll_current_language('slug') ?: 'ru';
    } elseif (defined('ICL_LANGUAGE_CODE')) {
        $lang = ICL_LANGUAGE_CODE ?: 'ru';
    }

    $tg_label = $lang === 'en' ? 'Write on Telegram'  : 'Написать в Telegram';
    $wa_label = $lang === 'en' ? 'Write on WhatsApp'  : 'Написать в WhatsApp';
    ?>
<style id="eit-float-style">
.eit-float-btns{position:fixed;bottom:28px;left:24px;display:flex;flex-direction:column;gap:10px;z-index:9000;}
.eit-float-btn{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 22px rgba(0,0,0,.22);transition:transform .25s ease,box-shadow .25s ease;text-decoration:none;}
.eit-float-btn:hover{transform:translateY(-4px) scale(1.1);box-shadow:0 12px 30px rgba(0,0,0,.28);}
.eit-float-btn svg{width:26px;height:26px;fill:#fff;}
.eit-float-tg{background:#26A5E4;}
.eit-float-wa{background:#25D366;}
</style>
<div class="eit-float-btns" id="eitFloatBtns">
  <a href="https://t.me/esteinturkey" class="eit-float-btn eit-float-tg" aria-label="<?php echo esc_attr($tg_label); ?>" target="_blank" rel="noopener noreferrer">
    <svg viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
  </a>
  <a href="https://wa.me/905468189180" class="eit-float-btn eit-float-wa" aria-label="<?php echo esc_attr($wa_label); ?>" target="_blank" rel="noopener noreferrer">
    <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
  </a>
</div>
    <?php
}, 999);
