<?php
/* ═══════════════════════════════════════════════════════
   Este in Turkey — Menu JSON API
   Yüklenecek yer: public_html/ru/este-menu.php
   WordPress menüsünü JSON olarak döndürür.
   ═══════════════════════════════════════════════════════ */

/* ── CORS ── */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

/* ── WordPress bootstrap ── */
$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    http_response_code(500);
    echo json_encode(['error' => 'wp-load.php not found']);
    exit;
}
define('SHORTINIT', false);
require_once $wp_load;

/* ── Menü adını buraya yazın (wp-admin > Görünüm > Menü > Menü Adı) ── */
$menu_name = 'Основное меню RU';

$locations = get_nav_menu_locations();
$menu_obj  = null;

/* Önce location'dan bul, yoksa isme göre ara */
foreach ($locations as $loc => $id) {
    $m = wp_get_nav_menu_object($id);
    if ($m && (
        strtolower($m->slug) === strtolower($menu_name) ||
        strtolower($m->name) === strtolower($menu_name) ||
        strtolower($loc)    === strtolower($menu_name)
    )) {
        $menu_obj = $m;
        break;
    }
}

/* Bulunamazsa ilk menüyü döndür */
if (!$menu_obj) {
    $menus = wp_get_nav_menus();
    if (!empty($menus)) $menu_obj = $menus[0];
}

if (!$menu_obj) {
    echo json_encode(['items' => [], 'debug' => 'no menu found']);
    exit;
}

$raw = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);

if (!$raw) {
    echo json_encode(['items' => [], 'debug' => 'no items']);
    exit;
}

/* ── Düz listeyi ağaç yapısına çevir ── */
$map    = [];
$tree   = [];

foreach ($raw as $item) {
    $map[$item->ID] = [
        'id'       => $item->ID,
        'parent'   => (int) $item->menu_item_parent,
        'order'    => (int) $item->menu_order,
        'title'    => $item->title,
        'url'      => $item->url,
        'target'   => $item->target ?: '',
        'classes'  => implode(' ', array_filter((array) $item->classes)),
        'children' => [],
    ];
}

foreach ($map as $id => &$node) {
    if ($node['parent'] && isset($map[$node['parent']])) {
        $map[$node['parent']]['children'][] = &$node;
    } else {
        $tree[] = &$node;
    }
}
unset($node);

/* Sıralama */
usort($tree, fn($a, $b) => $a['order'] - $b['order']);
foreach ($tree as &$top) {
    usort($top['children'], fn($a, $b) => $a['order'] - $b['order']);
}
unset($top);

echo json_encode(['menu' => $menu_obj->name, 'items' => $tree], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
