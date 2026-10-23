<?php
/**
 * Tacos Factory — thème enfant de Storefront.
 *
 * Plugins attendus : WooCommerce, Secure Custom Fields (SCF), Classic Editor.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

define( 'TF_VERSION', '1.1.0' );
define( 'TF_DIR', get_stylesheet_directory() );
define( 'TF_URI', get_stylesheet_directory_uri() );

// Frais de préparation : 1,50 € sous 15 € d'achat (maquette 05).
define( 'TF_FRAIS_PREPARATION', 1.5 );
define( 'TF_FRAIS_SEUIL', 15 );

// Livraison : plages de 30 min sur les 4 prochains jours d'ouverture,
// 20 min de trajet en plus du délai de préparation.
define( 'TF_LIVRAISON_PLAGE', 30 );
define( 'TF_LIVRAISON_JOURS', 4 );
define( 'TF_LIVRAISON_TRAJET', 20 );

require_once TF_DIR . '/inc/helpers.php';
require_once TF_DIR . '/inc/setup.php';
require_once TF_DIR . '/inc/customizer.php';
require_once TF_DIR . '/inc/storefront.php';
require_once TF_DIR . '/inc/scf-fields.php';

if ( class_exists( 'WooCommerce' ) ) {
	require_once TF_DIR . '/inc/woocommerce.php';
	require_once TF_DIR . '/inc/product-options.php';
	require_once TF_DIR . '/inc/livraison.php';
	require_once TF_DIR . '/inc/checkout.php';
	require_once TF_DIR . '/inc/commande.php';
	require_once TF_DIR . '/inc/gateway-carte.php';
}
