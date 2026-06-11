<?php
$wp_load = null;
$dir = __DIR__;
for ($i = 0; $i < 8; $i++) {
    if (file_exists($dir . '/wp-load.php')) { $wp_load = $dir . '/wp-load.php'; break; }
    $dir = dirname($dir);
}
if (!$wp_load) { die('wp-load.php bulunamadı.'); }
require_once $wp_load;

if (!function_exists('wp_create_nav_menu')) {
    require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
}

$site = untrailingslashit(get_site_url());

$menus = [
    'Footer EN' => [
        ['title' => 'Hair Transplant', 'url' => '#', 'children' => [
            ['title' => 'Hair Transplant FUE',     'url' => $site . '/hair-transplant-fue/'],
            ['title' => 'Hair Transplant DHI Choi', 'url' => $site . '/hair-transplant-dhi-choi/'],
            ['title' => 'Beard Transplant',         'url' => $site . '/beard-transplant/'],
            ['title' => 'Eyebrow Transplant',       'url' => $site . '/eyebrow-transplant/'],
        ]],
        ['title' => 'Dentistry', 'url' => '#', 'children' => [
            ['title' => 'Dental Implants',  'url' => $site . '/dental-implants/'],
            ['title' => 'Dental Crowns',    'url' => $site . '/dental-crowns/'],
            ['title' => 'Dental Veneers',   'url' => $site . '/dental-veneers/'],
            ['title' => 'Dental Treatment', 'url' => $site . '/dental-treatment/'],
            ['title' => 'Aligners',         'url' => $site . '/aligners/'],
            ['title' => 'Teeth Whitening',  'url' => $site . '/teeth-whitening/'],
        ]],
        ['title' => 'Before / After', 'url' => '#', 'children' => [
            ['title' => 'Dentistry',        'url' => $site . '/before-after-dentistry/'],
            ['title' => 'Hair Transplant',  'url' => $site . '/before-after-hair-transplant/'],
        ]],
        ['title' => 'About', 'url' => '#', 'children' => [
            ['title' => 'About Us',       'url' => $site . '/about-us/'],
            ['title' => 'Blog',           'url' => $site . '/blog/'],
            ['title' => 'Contact',        'url' => $site . '/contact/'],
            ['title' => 'Privacy Policy', 'url' => $site . '/privacy-policy/'],
        ]],
    ],
    'Footer RU' => [
        ['title' => 'Пересадка волос', 'url' => '#', 'children' => [
            ['title' => 'Пересадка волос FUE',     'url' => $site . '/ru/peresadka-volos-fue/'],
            ['title' => 'Пересадка волос DHI Choi', 'url' => $site . '/ru/peresadka-volos-dhi-choi/'],
            ['title' => 'Пересадка бороды',         'url' => $site . '/ru/peresadka-borody/'],
            ['title' => 'Пересадка бровей',         'url' => $site . '/ru/peresadka-brovej/'],
        ]],
        ['title' => 'Стоматология', 'url' => '#', 'children' => [
            ['title' => 'Импланты',   'url' => $site . '/ru/implanty/'],
            ['title' => 'Коронки',    'url' => $site . '/ru/koronki/'],
            ['title' => 'Виниры',     'url' => $site . '/ru/viniry/'],
            ['title' => 'Лечение',    'url' => $site . '/ru/lechenie/'],
            ['title' => 'Элайнеры',   'url' => $site . '/ru/elainery/'],
            ['title' => 'Отбеливание','url' => $site . '/ru/otbelivanie/'],
        ]],
        ['title' => 'До / После', 'url' => '#', 'children' => [
            ['title' => 'Стоматология',    'url' => $site . '/ru/stomatologiya/'],
            ['title' => 'Пересадка волос', 'url' => $site . '/ru/peresadka-volos/'],
        ]],
        ['title' => 'О компании', 'url' => '#', 'children' => [
            ['title' => 'О нас',                        'url' => $site . '/ru/o-nas/'],
            ['title' => 'Блог',                         'url' => $site . '/ru/blog/'],
            ['title' => 'Контакты',                     'url' => $site . '/ru/kontakty/'],
            ['title' => 'Политика конфиденциальности',  'url' => $site . '/ru/politika-konfidencialnosti/'],
        ]],
    ],
    'Footer DE' => [
        ['title' => 'Haartransplantation', 'url' => '#', 'children' => [
            ['title' => 'Haartransplantation FUE',      'url' => $site . '/de/haartransplantation-fue/'],
            ['title' => 'Haartransplantation DHI Choi', 'url' => $site . '/de/haartransplantation-dhi-choi/'],
            ['title' => 'Barttransplantation',          'url' => $site . '/de/barttransplantation/'],
            ['title' => 'Augenbrauentransplantation',   'url' => $site . '/de/augenbrauentransplantation/'],
        ]],
        ['title' => 'Zahnmedizin', 'url' => '#', 'children' => [
            ['title' => 'Zahnimplantate', 'url' => $site . '/de/zahnimplantate/'],
            ['title' => 'Zahnkronen',     'url' => $site . '/de/zahnkronen/'],
            ['title' => 'Veneers',        'url' => $site . '/de/veneers/'],
            ['title' => 'Zahnbehandlung', 'url' => $site . '/de/zahnbehandlung/'],
            ['title' => 'Aligner',        'url' => $site . '/de/aligner/'],
            ['title' => 'Zahnaufhellung', 'url' => $site . '/de/zahnaufhellung/'],
        ]],
        ['title' => 'Vorher / Nachher', 'url' => '#', 'children' => [
            ['title' => 'Zahnmedizin',        'url' => $site . '/de/vorher-nachher-zahnarzt/'],
            ['title' => 'Haartransplantation', 'url' => $site . '/de/vorher-nachher-haare/'],
        ]],
        ['title' => 'Über uns', 'url' => '#', 'children' => [
            ['title' => 'Über uns',   'url' => $site . '/de/ueber-uns/'],
            ['title' => 'Blog',       'url' => $site . '/de/blog/'],
            ['title' => 'Kontakt',    'url' => $site . '/de/kontakt/'],
            ['title' => 'Datenschutz','url' => $site . '/de/datenschutz/'],
        ]],
    ],
    'Footer RO' => [
        ['title' => 'Transplant de păr', 'url' => '#', 'children' => [
            ['title' => 'Transplant păr FUE',      'url' => $site . '/ro/transplant-par-fue/'],
            ['title' => 'Transplant păr DHI Choi', 'url' => $site . '/ro/transplant-par-dhi-choi/'],
            ['title' => 'Transplant barbă',        'url' => $site . '/ro/transplant-barba/'],
            ['title' => 'Transplant sprâncene',    'url' => $site . '/ro/transplant-sprancene/'],
        ]],
        ['title' => 'Stomatologie', 'url' => '#', 'children' => [
            ['title' => 'Implanturi dentare', 'url' => $site . '/ro/implanturi-dentare/'],
            ['title' => 'Coroane dentare',    'url' => $site . '/ro/coroane-dentare/'],
            ['title' => 'Fațete dentare',     'url' => $site . '/ro/fatete-dentare/'],
            ['title' => 'Tratament dentar',   'url' => $site . '/ro/tratament-dentar/'],
            ['title' => 'Aparate dentare',    'url' => $site . '/ro/aparate-dentare/'],
            ['title' => 'Albire dentară',     'url' => $site . '/ro/albire-dentara/'],
        ]],
        ['title' => 'Înainte / După', 'url' => '#', 'children' => [
            ['title' => 'Stomatologie',    'url' => $site . '/ro/inainte-dupa-stomatologie/'],
            ['title' => 'Transplant de păr','url' => $site . '/ro/inainte-dupa-par/'],
        ]],
        ['title' => 'Despre noi', 'url' => '#', 'children' => [
            ['title' => 'Despre noi',            'url' => $site . '/ro/despre-noi/'],
            ['title' => 'Blog',                  'url' => $site . '/ro/blog/'],
            ['title' => 'Contact',               'url' => $site . '/ro/contact/'],
            ['title' => 'Politică de confidențialitate', 'url' => $site . '/ro/politica-de-confid/'],
        ]],
    ],
];

echo '<pre style="font-family:monospace;font-size:13px;">';
echo "Site URL: {$site}\n\n";

foreach ($menus as $menu_name => $sections) {
    $existing = get_term_by('name', $menu_name, 'nav_menu');
    if ($existing) {
        wp_delete_nav_menu($existing->term_id);
        echo "🗑  Silindi (yeniden oluşturulacak): {$menu_name}\n";
    }

    $menu_id = wp_create_nav_menu($menu_name);
    if (is_wp_error($menu_id)) {
        echo "❌ Oluşturulamadı: {$menu_name} — " . $menu_id->get_error_message() . "\n";
        continue;
    }
    echo "✅ Menü oluşturuldu: {$menu_name} (ID: {$menu_id})\n";

    foreach ($sections as $section) {
        $parent_id = wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'   => $section['title'],
            'menu-item-url'     => $section['url'],
            'menu-item-status'  => 'publish',
            'menu-item-type'    => 'custom',
        ]);
        echo "   ▸ {$section['title']} (ID: {$parent_id})\n";

        foreach ($section['children'] as $child) {
            $child_id = wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title'      => $child['title'],
                'menu-item-url'        => $child['url'],
                'menu-item-status'     => 'publish',
                'menu-item-type'       => 'custom',
                'menu-item-parent-id'  => $parent_id,
            ]);
            echo "     └─ {$child['title']}\n";
        }
    }
    echo "\n";
}

echo "✅ Tüm footer menüleri oluşturuldu.\n";
echo "⚠️  Bu dosyayı şimdi sunucudan sil: create-footer-menus.php\n";
echo '</pre>';
