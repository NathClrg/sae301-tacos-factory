<?php
/**
 * Carte produit dans les boucles WooCommerce (boutique, catégories).
 * Même rendu que les carrousels de la carte.
 *
 * @package TacosFactory
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

tf_product_card( $product, 'li' );
