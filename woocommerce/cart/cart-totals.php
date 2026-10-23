<?php
/**
 * Totaux du panier (maquette « 05 Panier », bloc « Total ») et barre
 * « Passer au paiement · 22.58€ ». WooCommerce recharge ce bloc (.cart_totals)
 * à chaque changement du panier.
 *
 * @package TacosFactory
 * @version 2.3.6
 */

defined( 'ABSPATH' ) || exit;

$tf_cart  = WC()->cart;
$tf_count = $tf_cart->get_cart_contents_count();
$tf_fees  = $tf_cart->get_fees();
$tf_rate  = tf_cart_delivery_rate();
$tf_total = html_entity_decode( wp_strip_all_tags( wc_price( $tf_cart->get_total( 'edit' ) ) ), ENT_QUOTES, 'UTF-8' );
?>
<div class="cart_totals totaux section-tunnel">

	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<p class="totaux__ligne">
		<span>
			<?php
			/* translators: %d : nombre d'articles. */
			printf( esc_html( _n( 'Sous-total (%d article)', 'Sous-total (%d articles)', $tf_count, 'tacos-factory' ) ), absint( $tf_count ) );
			?>
		</span>
		<strong><?php wc_cart_totals_subtotal_html(); ?></strong>
	</p>

	<?php if ( $tf_rate ) : ?>
		<p class="totaux__ligne">
			<span>
				<?php
				/* translators: %s : poids total. */
				printf( esc_html__( 'Frais de livraison (%s)', 'tacos-factory' ), esc_html( wc_format_weight( $tf_cart->get_cart_contents_weight() ) ) );
				?>
			</span>
			<strong><?php echo wp_kses_post( wc_price( $tf_rate->get_cost() ) ); ?></strong>
		</p>
	<?php endif; ?>

	<?php if ( $tf_fees ) : ?>
		<?php foreach ( $tf_fees as $tf_fee ) : ?>
			<p class="totaux__ligne">
				<span><?php echo esc_html( $tf_fee->name ); ?></span>
				<strong><?php wc_cart_totals_fee_html( $tf_fee ); ?></strong>
			</p>
		<?php endforeach; ?>
	<?php else : ?>
		<p class="totaux__ligne">
			<span><?php esc_html_e( 'Frais de préparation', 'tacos-factory' ); ?></span>
			<strong class="totaux__offert"><?php esc_html_e( 'Offerts', 'tacos-factory' ); ?></strong>
		</p>
	<?php endif; ?>

	<?php foreach ( $tf_cart->get_coupons() as $tf_code => $tf_coupon ) : ?>
		<p class="totaux__ligne totaux__ligne--remise">
			<span>
				<?php
				/* translators: %s : code promo. */
				echo esc_html( sprintf( __( 'Remise %s', 'tacos-factory' ), mb_strtoupper( $tf_code ) ) );
				?>
			</span>
			<strong class="totaux__offert">−<?php echo wp_kses_post( wc_price( $tf_cart->get_coupon_discount_amount( $tf_code, $tf_cart->display_cart_ex_tax ) ) ); ?></strong>
		</p>
	<?php endforeach; ?>

	<p class="note note--claire">
		<?php echo tf_icon( 'info', 14 ); // phpcs:ignore ?>
		<?php
		/* translators: 1 : frais, 2 : seuil. */
		echo esc_html( sprintf( __( 'Frais de préparation de %1$s offerts dès %2$s d’achat.', 'tacos-factory' ), html_entity_decode( wp_strip_all_tags( wc_price( TF_FRAIS_PREPARATION ) ), ENT_QUOTES, 'UTF-8' ), html_entity_decode( wp_strip_all_tags( wc_price( TF_FRAIS_SEUIL, array( 'decimals' => 0 ) ) ), ENT_QUOTES, 'UTF-8' ) ) );
		?>
	</p>

	<p class="totaux__ligne totaux__ligne--total">
		<span><?php esc_html_e( 'Total', 'tacos-factory' ); ?></span>
		<strong><?php wc_cart_totals_order_total_html(); ?></strong>
	</p>

	<div class="barre-action">
		<button type="submit" form="panier" name="proceed" value="1" class="bouton bouton--primaire bouton--large">
			<?php
			/* translators: %s : total. */
			echo esc_html( sprintf( __( 'Passer au paiement · %s', 'tacos-factory' ), $tf_total ) );
			?>
		</button>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
