<?php
/**
 * Récapitulatif de la page de paiement (maquette 07, bloc « Votre commande »).
 * WooCommerce le recharge en AJAX grâce à la classe
 * .woocommerce-checkout-review-order-table.
 *
 * @package TacosFactory
 * @version 5.2.0
 */

defined( 'ABSPATH' ) || exit;

$tf_delivery = 'livraison' === tf_reception_mode();
$tf_pickup   = $tf_delivery ? tf_delivery_summary( tf_delivery_choice() ) : tf_pickup_summary( tf_pickup_choice() );
$tf_rate     = tf_cart_delivery_rate();
?>
<div class="woocommerce-checkout-review-order-table recap section-tunnel">
	<h2 class="titre-section"><?php esc_html_e( 'Votre commande', 'tacos-factory' ); ?></h2>

	<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>

	<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : ?>
		<?php
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
			continue;
		}
		?>
		<p class="totaux__ligne">
			<span><?php echo absint( $cart_item['quantity'] ); ?>× <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
			<strong><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?></strong>
		</p>
	<?php endforeach; ?>

	<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>

	<?php if ( $tf_rate ) : ?>
		<p class="totaux__ligne">
			<span>
				<?php
				/* translators: %s : poids total. */
				printf( esc_html__( 'Frais de livraison (%s)', 'tacos-factory' ), esc_html( wc_format_weight( WC()->cart->get_cart_contents_weight() ) ) );
				?>
			</span>
			<strong><?php echo wp_kses_post( wc_price( $tf_rate->get_cost() ) ); ?></strong>
		</p>
	<?php endif; ?>

	<?php foreach ( WC()->cart->get_fees() as $tf_fee ) : ?>
		<p class="totaux__ligne">
			<span><?php echo esc_html( $tf_fee->name ); ?></span>
			<strong><?php wc_cart_totals_fee_html( $tf_fee ); ?></strong>
		</p>
	<?php endforeach; ?>

	<?php foreach ( WC()->cart->get_coupons() as $tf_code => $tf_coupon ) : ?>
		<p class="totaux__ligne">
			<span>
				<?php
				/* translators: %s : code promo. */
				echo esc_html( sprintf( __( 'Remise %s', 'tacos-factory' ), mb_strtoupper( $tf_code ) ) );
				?>
			</span>
			<strong class="totaux__offert">−<?php echo wp_kses_post( wc_price( WC()->cart->get_coupon_discount_amount( $tf_code, WC()->cart->display_cart_ex_tax ) ) ); ?></strong>
		</p>
	<?php endforeach; ?>

	<?php if ( $tf_pickup ) : ?>
		<p class="totaux__ligne">
			<span><?php echo esc_html( $tf_delivery ? __( 'Livraison', 'tacos-factory' ) : __( 'Retrait', 'tacos-factory' ) ); ?></span>
			<strong><?php echo esc_html( $tf_pickup ); ?></strong>
		</p>
	<?php endif; ?>

	<?php if ( $tf_delivery ) : ?>
		<p class="totaux__ligne">
			<span><?php esc_html_e( 'Adresse', 'tacos-factory' ); ?></span>
			<strong><?php echo esc_html( tf_delivery_address_line( tf_delivery_choice() ) ); ?></strong>
		</p>
	<?php endif; ?>

	<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

	<p class="totaux__ligne totaux__ligne--total">
		<span>
			<span class="recap__libelle recap__libelle--en-ligne"><?php esc_html_e( 'Total à payer', 'tacos-factory' ); ?></span>
			<span class="recap__libelle recap__libelle--sur-place"><?php esc_html_e( 'À payer au retrait', 'tacos-factory' ); ?></span>
		</span>
		<strong><?php wc_cart_totals_order_total_html(); ?></strong>
	</p>

	<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
</div>
