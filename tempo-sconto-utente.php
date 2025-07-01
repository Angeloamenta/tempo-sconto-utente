<?php
/**
 * Plugin Name: Tempo Sconto Utente
 * Plugin URI: https://github.com/tuo-username/tempo-sconto-utente
 * Description: Applica uno sconto personale e temporaneo a un prodotto WooCommerce. Configurabile dal backend con timer e shortcode.
 * Version: 1.0
 * Author: Angelo Amenta
 * Author URI: https://angeloamenta.it
 * Text Domain: tempo-sconto-utente
 */

if (!defined('ABSPATH')) exit;

// Attivazione plugin: set default options
register_activation_hook(__FILE__, function () {
    add_option('tsu_prodotto_id', '');
    add_option('tsu_durata_minuti', 5);
    add_option('tsu_prezzo_scontato', '');
    add_option('tsu_timer_version', time());
});

// Menu backend
add_action('admin_menu', function () {
    add_menu_page('Tempo Sconto Utente', 'Tempo Sconto', 'manage_options', 'tsu_settings', 'tsu_settings_page', 'dashicons-clock', 60);
});

function tsu_settings_page() {
    if (isset($_POST['tsu_save_settings'])) {
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
            <table class="form-table">
                <tr>
                    <th scope="row">Prodotto da scontare</th>
                    <td>
                        <select name="tsu_prodotto_id" required>
                            <option value="">-- Seleziona un prodotto --</option>
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
                    <td><input type="number" name="tsu_durata_minuti" value="<?= esc_attr($durata) ?>" min="1" required></td>
                </tr>
                <tr>
                    <th scope="row">Prezzo scontato</th>
                    <td><input type="number" step="0.01" name="tsu_prezzo_scontato" value="<?= esc_attr($scontato) ?>" required></td>
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
            <li><code>[tsu_prezzo]</code> – Mostra il prezzo originale (barrato) e quello scontato</li>
            <li><code>[tsu_sconto_display]</code> – Mostra solo il prezzo scontato se attivo</li>
            <li><code>[tsu_prezzo_originale]</code> – Mostra solo il prezzo originale (barrato)</li>
        </ul>
    </div>
    <?php
}

// Enqueue scripts e style
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tsu-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('tsu-countdown', plugin_dir_url(__FILE__) . 'js/tsu-countdown.js', [], null, true);

    $durata = get_option('tsu_durata_minuti');
    $version = get_option('tsu_timer_version');
    echo "<script>document.addEventListener('DOMContentLoaded', function() {
        document.body.dataset.tsuDurata = '$durata';
        document.body.dataset.tsuVersion = '$version';
    });</script>";
});

// Shortcode timer con start=true
add_shortcode('tsu_timer', function () {
    return '<div class="tsu-timer" data-start="true"></div>';
});

// Shortcode timer display (senza start)
add_shortcode('tsu_timer_display', function () {
    return '<div class="tsu-timer" data-start="false"></div>';
});

// Shortcode prezzo originale + scontato
add_shortcode('tsu_prezzo', function () {
    $prodotto = wc_get_product(get_option('tsu_prodotto_id'));
    if (!$prodotto) return '';

    $prezzo_pieno = wc_price($prodotto->get_price());
    $prezzo_scontato = wc_price(get_option('tsu_prezzo_scontato'));

    return '<div class="tsu-prezzi" data-prezzo-originale="' . esc_attr(strip_tags($prezzo_pieno)) . '" data-prezzo-scontato="' . esc_attr(strip_tags($prezzo_scontato)) . '">
        <span class="tsu-original-price"></span>
        <span class="tsu-discounted-price"></span>
    </div>';
});

// Shortcode solo prezzo scontato
add_shortcode('tsu_sconto_display', function () {
    $prezzo_scontato = wc_price(get_option('tsu_prezzo_scontato'));
    return '<div class="tsu-solo-sconto" data-prezzo-scontato="' . esc_attr(strip_tags($prezzo_scontato)) . '" style="display:none;">
        <span class="tsu-discounted-price"></span>
    </div>';
});

// Shortcode solo prezzo originale (barrato)
add_shortcode('tsu_prezzo_originale', function () {
    $prodotto = wc_get_product(get_option('tsu_prodotto_id'));
    if (!$prodotto) return '';

    $prezzo_pieno = wc_price($prodotto->get_price());

    return '<span class="tsu-prezzo-originale">' . $prezzo_pieno . '</span>';
});

// Gestione timer e sessione sconto quando aggiungi al carrello
add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id) {
    if ($product_id == get_option('tsu_prodotto_id') && isset($_COOKIE['tsu_start_time'])) {
        $start = intval($_COOKIE['tsu_start_time']) / 1000;
        $end = $start + get_option('tsu_durata_minuti') * 60;
        if (time() < $end) {
            WC()->session->set('tsu_sconto_attivo', true);
            WC()->session->set('tsu_timer_version', get_option('tsu_timer_version'));
        }
    }
}, 10, 2);

// Controllo timer in pagine carrello e checkout per sessione sconto
add_action('template_redirect', function () {
    if (!is_cart() && !is_checkout()) return;
    $prodotto_id = get_option('tsu_prodotto_id');
    if (!$prodotto_id || !isset($_COOKIE['tsu_start_time'])) return;
    $start = intval($_COOKIE['tsu_start_time']) / 1000;
    $fine = $start + get_option('tsu_durata_minuti') * 60;
    $valido = time() < $fine;
    if (!$valido) return;
    $presente = false;
    foreach (WC()->cart->get_cart() as $item) {
        if ($item['product_id'] == $prodotto_id) {
            $presente = true;
            break;
        }
    }
    if ($presente && !WC()->session->get('tsu_sconto_attivo')) {
        WC()->session->set('tsu_sconto_attivo', true);
        WC()->session->set('tsu_timer_version', get_option('tsu_timer_version'));
    }
});

// Modifica globale prezzo prodotto per utente con timer attivo
add_filter('woocommerce_product_get_price', 'tsu_modifica_prezzo_globale', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'tsu_modifica_prezzo_globale', 10, 2);
function tsu_modifica_prezzo_globale($price, $product) {
    $prodotto_id = get_option('tsu_prodotto_id');
    $prezzo_scontato = get_option('tsu_prezzo_scontato');
    if ($product->get_id() != $prodotto_id || !$prezzo_scontato) return $price;
    if (isset($_COOKIE['tsu_start_time'])) {
        $start = intval($_COOKIE['tsu_start_time']) / 1000;
        $fine = $start + get_option('tsu_durata_minuti') * 60;
        if (time() < $fine) return floatval($prezzo_scontato);
    }
    return $price;
}

// Modifica prezzo nel carrello
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $sconto_attivo = WC()->session->get('tsu_sconto_attivo');
    if (!$sconto_attivo) return;

    if (!isset($_COOKIE['tsu_start_time'])) return;

    $start = intval($_COOKIE['tsu_start_time']) / 1000;
    $durata = get_option('tsu_durata_minuti');
    $fine = $start + ($durata * 60);
    if (time() > $fine) {
        WC()->session->__unset('tsu_sconto_attivo');
        return;
    }

    $prodotto_id = get_option('tsu_prodotto_id');
    $prezzo_scontato = floatval(get_option('tsu_prezzo_scontato'));
    if (!$prodotto_id || !$prezzo_scontato) return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['product_id'] == $prodotto_id) {
            $cart_item['data']->set_price($prezzo_scontato);
        }
    }
}, 20);
