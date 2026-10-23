<?php
/**
 * Un moyen de paiement (maquette 07) : rond, titre, sous-titre, icône ;
 * une fois choisi, le formulaire de carte ou l'info « Payer sur place ».
 *
 * La classe .payment_box et le champ payment_method sont gardés :
 * le JavaScript de WooCommerce s'en sert pour ouvrir le bon bloc.
 *
 * @package TacosFactory
 * @version 3.5.0
 *
 * @var WC_Payment_Gateway $gateway
 */

defined( 'ABSPATH' ) || exit;

$tf_display = tf_gateway_display( $gateway );
$tf_button  = 'cod' === $gateway->id ? __( 'Confirmer la commande', 'tacos-factory' ) : $gateway->order_button_text;
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> moyen">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio moyen__input" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $tf_button ); ?>" />

	<label class="moyen__entete" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<span class="moyen__texte">
			<strong class="moyen__titre"><?php echo wp_kses_post( $gateway->get_title() ); ?></strong>
			<?php if ( $tf_display['aide'] ) : ?>
				<small class="moyen__aide"><?php echo esc_html( $tf_display['aide'] ); ?></small>
			<?php endif; ?>
		</span>
		<span class="moyen__icone"><?php echo $tf_display['icone']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</label>

	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?> moyen__details" <?php echo $gateway->chosen ? '' : 'style="display:none;"'; ?>>
			<?php if ( 'cod' === $gateway->id ) : ?>
				<p class="moyen__info"><?php echo tf_icon( 'info', 18 ); // phpcs:ignore ?><?php echo esc_html( $gateway->get_description() ); ?></p>
			<?php else : ?>
				<?php $gateway->payment_fields(); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</li>
