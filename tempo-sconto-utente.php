<?php
/**
 * Plugin Name: Tempo Sconto Utente
 * Description: Applica uno sconto personale e temporaneo a un prodotto WooCommerce. Configurabile dal backend.
 * Version: 1.0
 * Author: Angelo Amenta
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, function () {
    add_option('tsu_prodotto_id', '');
    add_option('tsu_durata_minuti', 5);
    add_option('tsu_prezzo_scontato', '');
    add_option('tsu_timer_version', time());
});

add_action('admin_menu', function () {
    add_menu_page('Tempo Sconto Utente', 'Tempo Sconto', 'manage_options', 'tsu_settings', 'tsu_settings_page', 'dashicons-clock', 60);
});

function tsu_settings_page() {
    if (isset($_POST['tsu_save_settings']) && check_admin_referer('tsu_save_settings', 'tsu_nonce')) {
        update_option('tsu_prodotto_id', intval($_POST['tsu_prodotto_id']));
        update_option('tsu_durata_minuti', intval($_POST['tsu_durata_minuti']));
        update_option('tsu_prezzo_scontato', floatval($_POST['tsu_prezzo_scontato']));
    }

    if (isset($_POST['tsu_reset_timer'])) {
        update_option('tsu_timer_version', time());
    }

    $prodotto_id = get_option('tsu_prodotto_id');
    $durata = get_option('tsu_durata_minuti');
    $scontato = get_option('tsu_prezzo_scontato');
    $version = get_option('tsu_timer_version');
    $prodotti = function_exists('wc_get_products') ? wc_get_products(['limit' => -1]) : [];

    ?>
    <div class="wrap">
        <h1>Impostazioni Tempo Sconto Utente</h1>
        <form method="post">
            <?php wp_nonce_field('tsu_save_settings', 'tsu_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Prodotto da scontare</th>
                    <td>
                        <select name="tsu_prodotto_id">
                            <?php foreach ($prodotti as $p): ?>
                                <option value="<?= esc_attr($p->get_id()) ?>" <?= $p->get_id() == $prodotto_id ? 'selected' : '' ?>>
                                    <?= esc_html($p->get_name()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Durata del timer (minuti)</th>
                    <td><input type="number" name="tsu_durata_minuti" value="<?= esc_attr($durata) ?>" min="1"></td>
                </tr>
                <tr>
                    <th scope="row">Prezzo scontato</th>
                    <td><input type="number" step="0.01" name="tsu_prezzo_scontato" value="<?= esc_attr($scontato) ?>"></td>
                </tr>
            </table>
            <p><input type="submit" name="tsu_save_settings" class="button-primary" value="Salva impostazioni"></p>
        </form>
        <form method="post">
            <p><input type="submit" name="tsu_reset_timer" class="button" value="Resetta timer per tutti"></p>
        </form>

        <h2>Shortcode disponibili</h2>
        <ul>
            <li><code>[tsu_timer]</code> – Avvia il timer per l'utente</li>
            <li><code>[tsu_timer_display]</code> – Mostra solo il countdown se attivo</li>
            <li><code>[tsu_prezzo]</code> – Mostra il prezzo originale e quello scontato</li>
            <li><code>[tsu_sconto_display]</code> – Mostra solo il prezzo scontato se attivo</li>
            <li><code>[tsu_prezzo_originale]</code> – Mostra solo il prezzo originale (per esempio barrato)</li>
        </ul>
    </div>
    <?php
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('tsu-countdown', plugin_dir_url(__FILE__) . 'js/tsu-countdown.js', [], null, true);
    $durata = get_option('tsu_durata_minuti');
    $version = get_option('tsu_timer_version');
    echo "<script>document.addEventListener('DOMContentLoaded', function() {
        document.body.dataset.tsuDurata = '$durata';
        document.body.dataset.tsuVersion = '$version';
    });</script>";
});

add_shortcode('tsu_timer', fn() => '<div class="tsu-timer" data-start="true"></div>');
add_shortcode('tsu_timer_display', fn() => '<div class="tsu-timer" data-start="false"></div>');

add_shortcode('tsu_prezzo', function () {
    $p = wc_get_product(get_option('tsu_prodotto_id'));
    if (!$p) return '';
    $pieno = wc_price($p->get_price());
    $scontato = wc_price(get_option('tsu_prezzo_scontato'));
    return '<div class="tsu-prezzi" data-prezzo-originale="' . esc_attr(strip_tags($pieno)) . '" data-prezzo-scontato="' . esc_attr(strip_tags($scontato)) . '"><span class="tsu-original-price"></span> <span class="tsu-discounted-price"></span></div>';
});

add_shortcode('tsu_sconto_display', function () {
    $scontato = wc_price(get_option('tsu_prezzo_scontato'));
    return '<div class="tsu-solo-sconto" data-prezzo-scontato="' . esc_attr(strip_tags($scontato)) . '" style="display:none;"><span class="tsu-discounted-price"></span></div>';
});

add_shortcode('tsu_prezzo_originale', function () {
    $p = wc_get_product(get_option('tsu_prodotto_id'));
    if (!$p) return '';
    return '<span class="tsu-prezzo-originale">' . wc_price($p->get_price()) . '</span>';
});

add_action('woocommerce_add_to_cart', function ($key, $pid) {
    if ($pid != get_option('tsu_prodotto_id') || !isset($_COOKIE['tsu_start_time'])) return;
    $start = intval($_COOKIE['tsu_start_time']) / 1000;
    if (time() < $start + get_option('tsu_durata_minuti') * 60) {
        WC()->session->set('tsu_sconto_attivo', true);
        WC()->session->set('tsu_timer_version', get_option('tsu_timer_version'));
    }
}, 10, 2);

add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!WC()->session->get('tsu_sconto_attivo') || !isset($_COOKIE['tsu_start_time'])) return;
    $start = intval($_COOKIE['tsu_start_time']) / 1000;
    if (time() > $start + get_option('tsu_durata_minuti') * 60) {
        WC()->session->__unset('tsu_sconto_attivo');
        return;
    }
    $id = get_option('tsu_prodotto_id');
    $sconto = floatval(get_option('tsu_prezzo_scontato'));
    foreach ($cart->get_cart() as $key => $item) {
        if ($item['product_id'] == $id) {
            $item['data']->set_price($sconto);
        }
    }
}, 20);

add_filter('woocommerce_product_get_price', 'tsu_modifica_prezzo_globale', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'tsu_modifica_prezzo_globale', 10, 2);
function tsu_modifica_prezzo_globale($price, $product) {
    if ($product->get_id() != get_option('tsu_prodotto_id') || !isset($_COOKIE['tsu_start_time'])) return $price;
    $start = intval($_COOKIE['tsu_start_time']) / 1000;
    if (time() < $start + get_option('tsu_durata_minuti') * 60) {
        return floatval(get_option('tsu_prezzo_scontato'));
    }
    return $price;
}
