
<?php
/**
 * Plugin Name: Tempo Sconto Utente
 * Description: Applica uno sconto personale e temporaneo a un prodotto WooCommerce. Configurabile dal backend.
 * Version: 4.1
 * Author: Il Tuo Nome
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
                        <select name="tsu_prodotto_id">
                            <?php foreach ($prodotti as $p): ?>
                                <option value="<?= $p->get_id() ?>" <?= $p->get_id() == $prodotto_id ? 'selected' : '' ?>>
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
        </ul>
    </div>
    <?php
}

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

add_shortcode('tsu_timer', function () {
    return '<div class="tsu-timer" data-start="true"></div>';
});

add_shortcode('tsu_timer_display', function () {
    return '<div class="tsu-timer" data-start="false"></div>';
});

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

add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id) {
    if ($product_id == get_option('tsu_prodotto_id') && isset($_COOKIE['tsu_start_time'])) {
        $start = intval($_COOKIE['tsu_start_time']) / 1000;
        $end = $start + get_option('tsu_durata_minuti') * 60;
        if (time() < $end) {
            WC()->session->set('tsu_sconto_attivo', true);
        }
    }
}, 10, 2);

add_action('woocommerce_cart_calculate_fees', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $sconto_attivo = WC()->session->get('tsu_sconto_attivo');
    $prodotto_id = get_option('tsu_prodotto_id');
    $sconto = wc_get_product($prodotto_id)->get_price() - get_option('tsu_prezzo_scontato');
    $sconto = max(0, $sconto);
    $contiene_prodotto = false;

    foreach ($cart->get_cart() as $item) {
        if ($item['product_id'] == $prodotto_id) {
            $contiene_prodotto = true;
            break;
        }
    }

    $valido = false;
    if (isset($_COOKIE['tsu_start_time'])) {
        $start = intval($_COOKIE['tsu_start_time']) / 1000;
        $fine = $start + get_option('tsu_durata_minuti') * 60;
        $valido = time() < $fine;
    }

    if ($sconto_attivo && !$valido) {
        WC()->session->__unset('tsu_sconto_attivo');
        $sconto_attivo = false;
    }

    if ($sconto_attivo && $contiene_prodotto && $sconto > 0) {
        $cart->add_fee('Sconto temporaneo', -$sconto, false);
    }
});
