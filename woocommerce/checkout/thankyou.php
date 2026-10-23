<?php
/**
 * Confirmation et suivi de commande (maquette « 08 Confirmation »).
 * La même page sert à « Suivre ma commande » (barre du bas, menu).
 *
 * @package TacosFactory
 * @version 8.1.0
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
	wc_get_template( 'checkout/order-received.php', array( 'order' => false ) );
	return;
}

do_action( 'woocommerce_before_thankyou', $order->get_id() );

if ( $order->has_status( 'failed' ) ) :
	?>
	<div class="confirmation">
		<h1 class="page-titre__titre"><?php esc_html_e( 'Paiement refusé', 'tacos-factory' ); ?></h1>
		<p class="page-titre__texte"><?php esc_html_e( 'Votre banque a refusé le paiement. Vous pouvez réessayer.', 'tacos-factory' ); ?></p>
		<a class="bouton bouton--primaire bouton--large" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Réessayer', 'tacos-factory' ); ?></a>
	</div>
	<?php
	return;
endif;

if ( $order->has_status( 'cancelled' ) ) :
	// Cette page dit déjà que la commande est annulée : inutile de garder l'avis
	// de WooCommerce, qui s'afficherait sur la page suivante.
	wc_clear_notices();
	?>
	<div class="confirmation">
		<header class="confirmation__entete">
			<span class="confirmation__coche confirmation__coche--annulee" aria-hidden="true"><?php echo tf_icon( 'x', 32 ); // phpcs:ignore ?></span>
			<h1 class="page-titre__titre"><?php esc_html_e( 'Commande annulée', 'tacos-factory' ); ?></h1>
			<p class="page-titre__texte">
				<?php
				/* translators: %s : numéro de commande. */
				printf( esc_html__( 'Votre commande n° %s a bien été annulée.', 'tacos-factory' ), esc_html( $order->get_order_number() ) );
				?>
			</p>
		</header>
		<a class="bouton bouton--primaire bouton--large" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Continuer sur le site', 'tacos-factory' ); ?></a>
	</div>
	<?php
	return;
endif;

$tf_step    = tf_order_step( $order );
$tf_time    = tf_order_pickup_time( $order );
$tf_minutes = tf_order_minutes_left( $order );
$tf_number  = $order->get_order_number();
$tf_card    = (string) $order->get_meta( '_tf_carte' );
$tf_phone   = tf_opt( 'telephone' );
$tf_maps    = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( tf_opt( 'nom' ) . ', ' . tf_opt( 'adresse' ) );
$tf_cancel  = tf_order_cancel_url( $order );
$tf_deliver = tf_order_is_delivery( $order );
$tf_address = implode( ', ', array_filter( array( $order->get_shipping_address_1(), $order->get_shipping_address_2(), trim( $order->get_shipping_postcode() . ' ' . $order->get_shipping_city() ) ) ) );
$tf_icons   = array(
	1 => 'receipt',
	2 => 'chef-hat',
	3 => $tf_deliver ? 'truck' : 'shopping-bag',
);
if ( 3 === $tf_step ) {
	$tf_heading = __( 'Votre commande est', 'tacos-factory' );
} elseif ( $tf_deliver && $tf_time ) {
	/* translators: %s : jour (« Ce soir », « Demain »…). */
	$tf_heading = sprintf( __( 'Livraison · %s', 'tacos-factory' ), tf_day_label( $tf_time ) );
} else {
	$tf_heading = __( 'Prête vers', 'tacos-factory' );
}
?>
<div class="confirmation">

	<header class="confirmation__entete">
		<span class="confirmation__coche" aria-hidden="true"><?php echo tf_icon( 'check', 32 ); // phpcs:ignore ?></span>
		<p class="surtitre"><?php esc_html_e( 'Commande confirmée', 'tacos-factory' ); ?></p>
		<h1 class="page-titre__titre">
			<?php esc_html_e( 'Merci', 'tacos-factory' ); ?>
			<span class="accent"><?php echo esc_html( $order->get_billing_first_name() ? $order->get_billing_first_name() . ' !' : '!' ); ?></span>
		</h1>
		<p class="page-titre__texte">
			<?php
			/* translators: %s : numéro de commande. */
			printf( esc_html__( 'Votre commande n° %s a bien été transmise au restaurant.', 'tacos-factory' ), esc_html( $tf_number ) );
			?>
		</p>
	</header>

	<section class="heure">
		<p class="heure__entete">
			<span class="heure__libelle"><?php echo tf_icon( 'clock', 18 ); // phpcs:ignore ?><?php echo esc_html( $tf_heading ); ?></span>
			<span class="heure__statut"><?php echo esc_html( tf_step_label( $tf_step, $order ) ); ?></span>
		</p>
		<div class="heure__principale">
			<p class="heure__valeur<?php echo ( $tf_deliver && 3 !== $tf_step && $tf_time ) ? ' heure__valeur--plage' : ''; ?>">
				<?php
				if ( 3 === $tf_step || ! $tf_time ) {
					echo esc_html( tf_step_label( $tf_step, $order ) );
				} else {
					echo esc_html( $tf_deliver ? tf_plage_label( $tf_time ) : tf_hm( $tf_time ) );
				}
				?>
			</p>
			<?php if ( $tf_step < 3 && $tf_minutes ) : ?>
				<p class="heure__delai">
					<?php esc_html_e( 'dans environ', 'tacos-factory' ); ?>
					<strong><?php echo esc_html( tf_duration( $tf_minutes ) ); ?></strong>
				</p>
			<?php endif; ?>
		</div>
		<ol class="suivi-etapes">
			<?php foreach ( $tf_icons as $tf_n => $tf_icon_name ) : ?>
				<?php $tf_state = $tf_n < $tf_step ? 'faite' : ( $tf_n === $tf_step ? 'en-cours' : 'a-venir' ); ?>
				<?php if ( $tf_n > 1 ) : ?>
					<li class="suivi-etapes__lien<?php echo $tf_n <= $tf_step ? ' suivi-etapes__lien--fait' : ''; ?>" aria-hidden="true"></li>
				<?php endif; ?>
				<li class="suivi-etapes__etape suivi-etapes__etape--<?php echo esc_attr( $tf_state ); ?>"<?php echo $tf_n === $tf_step ? ' aria-current="step"' : ''; ?>>
					<span class="suivi-etapes__icone"><?php echo tf_icon( $tf_icon_name, 18 ); // phpcs:ignore ?></span>
					<?php echo esc_html( tf_step_label( $tf_n, $order ) ); ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<?php if ( $tf_deliver ) : ?>
		<section class="carte-blanche adresse">
			<div class="adresse__contenu">
				<p class="adresse__lieu">
					<?php echo tf_icon( 'map-pin', 20 ); // phpcs:ignore ?>
					<span>
						<strong><?php esc_html_e( 'Livraison à', 'tacos-factory' ); ?></strong>
						<?php echo esc_html( $tf_address ); ?>
					</span>
				</p>
				<?php if ( $tf_phone ) : ?>
					<a class="bouton bouton--secondaire bouton--large bouton--petit" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $tf_phone ) ); ?>"><?php echo tf_icon( 'phone', 16 ); // phpcs:ignore ?><?php esc_html_e( 'Appeler le restaurant', 'tacos-factory' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<section class="carte-blanche code-retrait">
			<p class="surtitre"><?php esc_html_e( 'Votre code de retrait', 'tacos-factory' ); ?></p>
			<div class="code-retrait__qr" data-qr="<?php echo esc_attr( $tf_number ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s : numéro de commande. */ __( 'QR code de la commande %s', 'tacos-factory' ), $tf_number ) ); ?>"></div>
			<p class="code-retrait__numero"><?php echo esc_html( $tf_number ); ?></p>
			<p class="code-retrait__aide"><?php esc_html_e( 'Présentez ce QR code au comptoir pour récupérer votre commande.', 'tacos-factory' ); ?></p>
		</section>

		<section class="carte-blanche adresse">
			<img class="adresse__plan" src="<?php echo esc_url( TF_URI . '/assets/img/plan.png' ); ?>" alt="" width="333" height="150" loading="lazy">
			<div class="adresse__contenu">
				<p class="adresse__lieu">
					<?php echo tf_icon( 'map-pin', 20 ); // phpcs:ignore ?>
					<span>
						<strong><?php echo esc_html( tf_opt( 'nom' ) ); ?></strong>
						<?php echo esc_html( tf_opt( 'adresse' ) ); ?>
					</span>
				</p>
				<a class="bouton bouton--sombre bouton--large" href="<?php echo esc_url( $tf_maps ); ?>" target="_blank" rel="noopener"><?php echo tf_icon( 'navigation', 17 ); // phpcs:ignore ?><?php esc_html_e( 'Ouvrir dans Google Maps', 'tacos-factory' ); ?></a>
				<?php if ( $tf_phone ) : ?>
					<a class="bouton bouton--secondaire bouton--large bouton--petit" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $tf_phone ) ); ?>"><?php echo tf_icon( 'phone', 16 ); // phpcs:ignore ?><?php esc_html_e( 'Appeler le restaurant', 'tacos-factory' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="carte-blanche detail">
		<h2 class="titre-section"><?php esc_html_e( 'Détail', 'tacos-factory' ); ?></h2>
		<?php foreach ( $order->get_items() as $tf_item ) : ?>
			<p class="totaux__ligne">
				<span><?php echo absint( $tf_item->get_quantity() ); ?>× <?php echo esc_html( $tf_item->get_name() ); ?></span>
				<strong><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $tf_item ) ); ?></strong>
			</p>
		<?php endforeach; ?>
		<?php foreach ( $order->get_fees() as $tf_fee ) : ?>
			<p class="totaux__ligne">
				<span><?php echo esc_html( $tf_fee->get_name() ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $tf_fee->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></strong>
			</p>
		<?php endforeach; ?>
		<?php if ( $tf_deliver ) : ?>
			<p class="totaux__ligne">
				<span><?php esc_html_e( 'Frais de livraison', 'tacos-factory' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) ); ?></strong>
			</p>
		<?php endif; ?>
		<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
			<p class="totaux__ligne">
				<span><?php esc_html_e( 'Remise', 'tacos-factory' ); ?></span>
				<strong class="totaux__offert">−<?php echo wp_kses_post( wc_price( $order->get_discount_total(), array( 'currency' => $order->get_currency() ) ) ); ?></strong>
			</p>
		<?php endif; ?>
		<p class="totaux__ligne totaux__ligne--total">
			<?php if ( $order->is_paid() && 'cod' !== $order->get_payment_method() ) : ?>
				<span class="detail__paye">
					<?php echo tf_icon( 'check', 14 ); // phpcs:ignore ?>
					<?php
					/* translators: %s : carte (« Visa •••• 4242 »). */
					echo esc_html( $tf_card ? sprintf( __( 'Payé · %s', 'tacos-factory' ), $tf_card ) : __( 'Payé', 'tacos-factory' ) );
					?>
				</span>
			<?php elseif ( 'cod' === $order->get_payment_method() ) : ?>
				<span><?php esc_html_e( 'À payer au retrait', 'tacos-factory' ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'En attente de paiement', 'tacos-factory' ); ?></span>
			<?php endif; ?>
			<strong><?php echo wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></strong>
		</p>
	</section>

	<p class="note note--encadre">
		<?php echo tf_icon( 'info', 18 ); // phpcs:ignore ?>
		<?php esc_html_e( 'Continuez à visiter le site : votre commande reste accessible à tout moment depuis la barre « Ma commande » en bas de l’écran ou dans le menu.', 'tacos-factory' ); ?>
	</p>

	<a class="bouton bouton--secondaire bouton--large" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Continuer sur le site', 'tacos-factory' ); ?></a>

	<?php if ( $tf_cancel ) : ?>
		<a class="bouton bouton--annuler bouton--large bouton--petit" href="<?php echo esc_url( $tf_cancel ); ?>" data-confirmer="<?php esc_attr_e( 'Voulez-vous vraiment annuler votre commande ?', 'tacos-factory' ); ?>"><?php echo tf_icon( 'x', 16 ); // phpcs:ignore ?><?php esc_html_e( 'Annuler ma commande', 'tacos-factory' ); ?></a>
	<?php endif; ?>
</div>
<?php
// Pas de do_action( 'woocommerce_thankyou' ) : il ajouterait les tableaux
// « Détails de la commande » de WooCommerce, déjà résumés ci-dessus.
