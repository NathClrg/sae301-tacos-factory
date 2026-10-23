<?php
/**
 * Réglages du thème, chargement des styles/scripts, Classic Editor.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supports, menus, tailles d'images.
 */
function tf_setup() {
	load_child_theme_textdomain( 'tacos-factory', TF_DIR . '/languages' );

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 132,
			'width'       => 112,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'footer' => __( 'Pied de page', 'tacos-factory' ),
		)
	);

	add_image_size( 'tf-card', 480, 360, true );
	add_image_size( 'tf-product', 1200, 736, true );
	add_image_size( 'tf-hero', 2048, 1300, true );
}
add_action( 'after_setup_theme', 'tf_setup', 20 );

/**
 * Classic Editor : on force l'éditeur classique même si le plugin est désactivé,
 * et on désactive l'éditeur de widgets en blocs.
 */
add_filter( 'use_block_editor_for_post', '__return_false', 100 );
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false' );

/**
 * Styles et scripts (après Storefront, priorité 40).
 */
function tf_enqueue_assets() {
	// Ce que l'on remplace ou n'utilise pas côté Storefront / blocs.
	foreach ( array( 'storefront-child-style', 'storefront-fonts', 'storefront-gutenberg-blocks', 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles', 'wc-blocks-style' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
	foreach ( array( 'storefront-navigation', 'storefront-header-cart', 'storefront-handheld-footer-bar', 'storefront-sticky-add-to-cart' ) as $handle ) {
		wp_dequeue_script( $handle );
	}

	// Typographies de la maquette : Anton (titres), Inter (texte), Hubot Sans (surtitres).
	wp_enqueue_style( 'tf-fonts', 'https://fonts.googleapis.com/css2?family=Anton&family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'tf-fonts-hubot', 'https://fonts.googleapis.com/css2?family=Hubot+Sans:wdth,wght@75..125,400..800&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

	$deps = array( 'tf-fonts' );
	foreach ( array( 'storefront-style', 'storefront-woocommerce-style' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}

	// Variables (générées depuis Figma), puis le CSS du thème qui les utilise.
	wp_enqueue_style( 'tacos-factory-variables', TF_URI . '/assets/css/variables.css', $deps, filemtime( TF_DIR . '/assets/css/variables.css' ) );
	wp_enqueue_style( 'tacos-factory', TF_URI . '/assets/css/theme.css', array( 'tacos-factory-variables' ), filemtime( TF_DIR . '/assets/css/theme.css' ) );

	$js_deps = array( 'jquery' );
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'wc-add-to-cart' );
		$js_deps[] = 'wc-add-to-cart';
	}

	// QR code de retrait (qrcodejs 1.0.0, licence MIT), seulement sur la confirmation.
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		wp_enqueue_script( 'qrcodejs', TF_URI . '/assets/js/vendor/qrcode.min.js', array(), '1.0.0', true );
		$js_deps[] = 'qrcodejs';
	}

	wp_enqueue_script( 'tacos-factory', TF_URI . '/assets/js/theme.js', $js_deps, filemtime( TF_DIR . '/assets/js/theme.js' ), true );

	$price = array(
		'symbol'    => '€',
		'decimals'  => 2,
		'decimal'   => ',',
		'thousand'  => ' ',
		'format'    => '%2$s %1$s',
	);
	if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
		$price = array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'decimals' => wc_get_price_decimals(),
			'decimal'  => wc_get_price_decimal_separator(),
			'thousand' => wc_get_price_thousand_separator(),
			'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
		);
	}

	wp_localize_script(
		'tacos-factory',
		'tfData',
		array(
			'price'  => $price,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tf_enqueue_assets', 40 );

/**
 * Storefront ajoute en CSS inline les couleurs de son Customizer : elles
 * passaient devant celles du thème (titres et liens du footer, boutons…).
 */
add_filter( 'storefront_customizer_css', '__return_empty_string' );
add_filter( 'storefront_customizer_woocommerce_css', '__return_empty_string' );

/**
 * Préconnexion à Google Fonts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Type.
 * @return array
 */
function tf_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'tf_resource_hints', 10, 2 );

/**
 * Classes du body : pleine largeur partout (pas de sidebar Storefront).
 *
 * @param array $classes Classes.
 * @return array
 */
function tf_body_classes( $classes ) {
	$classes = array_diff( $classes, array( 'left-sidebar', 'right-sidebar' ) );
	$classes[] = 'storefront-full-width-content';
	return $classes;
}
add_filter( 'body_class', 'tf_body_classes', 20 );
