<?php
/**
 * Panier (maquette « 05 Panier ») : articles, « Un petit dessert ? »,
 * réception et créneau, coordonnées, code promo. Les totaux et le bouton
 * « Passer au paiement » sont dans cart/cart-totals.php.
 *
 * Tout est dans le formulaire WooCommerce du panier : les quantités et le code
 * promo se mettent à jour en AJAX, « Passer au paiement » envoie le reste
 * (vérifié par tf_cart_proceed()).
 *
 * @package TacosFactory
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$tf_count = WC()->cart->get_cart_contents_count();
?>

<form id="panier" class="woocommerce-cart-form panier" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php // Touche Entrée dans un champ : on passe au paiement (et pas « Appliquer » le code promo). ?>
	<button type="submit" name="proceed" value="1" class="screen-reader-text" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'Passer au paiement', 'tacos-factory' ); ?></button>

	<?php tf_back_link( __( 'Continuer mes achats', 'tacos-factory' ), tf_get_carte_url() ); ?>

	<header class="page-titre">
		<p class="surtitre"><?php esc_html_e( 'Click & Collect', 'tacos-factory' ); ?></p>
		<h1 class="page-titre__titre"><?php echo tf_split_title( __( 'Mon panier', 'tacos-factory' ) ); // phpcs:ignore ?></h1>
		<p class="page-titre__texte">
			<?php
			if ( 'livraison' === tf_reception_mode() ) {
				/* translators: %d : nombre d'articles. */
				printf( esc_html( _n( '%d article · livraison à domicile', '%d articles · livraison à domicile', $tf_count, 'tacos-factory' ) ), absint( $tf_count ) );
			} else {
				/* translators: %d : nombre d'articles. */
				printf( esc_html( _n( '%d article · retrait au restaurant', '%d articles · retrait au restaurant', $tf_count, 'tacos-factory' ) ), absint( $tf_count ) );
			}
			?>
		</p>
	</header>

	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<div class="panier__articles woocommerce-cart-form__contents">
		<?php do_action( 'woocommerce_before_cart_contents' ); ?>

		<?php
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
			$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
			$summary           = tf_cart_item_summary( $cart_item );
			?>
			<div class="panier-article <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

				<div class="panier-article__image">
					<?php
					if ( $_product->get_image_id() ) {
						$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'thumbnail' ), $cart_item, $cart_item_key );
						echo $product_permalink ? '<a href="' . esc_url( $product_permalink ) . '" tabindex="-1">' . $thumbnail . '</a>' : $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput
					} else {
						tf_product_visual( $_product, 'thumbnail', 26 );
					}
					?>
				</div>

				<div class="panier-article__contenu">
					<p class="panier-article__ligne">
						<span class="panier-article__nom">
							<?php
							echo $product_permalink ? '<a href="' . esc_url( $product_permalink ) . '">' . wp_kses_post( $product_name ) . '</a>' : wp_kses_post( $product_name ); // phpcs:ignore

							do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
							?>
						</span>
						<span class="panier-article__prix">
							<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
						</span>
					</p>

					<?php if ( $summary ) : ?>
						<p class="panier-article__details"><?php echo $summary; // phpcs:ignore -- échappé dans tf_cart_item_summary(). ?></p>
					<?php endif; ?>

					<div class="panier-article__actions">
						<div class="panier-article__liens">
							<?php if ( $product_permalink ) : ?>
								<a class="panier-article__modifier" href="<?php echo esc_url( $product_permalink ); ?>"><?php echo tf_icon( 'pencil', 14 ); // phpcs:ignore ?><?php esc_html_e( 'Modifier', 'tacos-factory' ); ?></a>
							<?php endif; ?>
							<?php
							echo apply_filters( // phpcs:ignore
								'woocommerce_cart_item_remove_link',
								sprintf(
									'<a href="%s" class="panier-article__supprimer" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>',
									esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
									/* translators: %s is the product name */
									esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
									esc_attr( $product_id ),
									esc_attr( $_product->get_sku() ),
									tf_icon( 'trash', 17 )
								),
								$cart_item_key
							);
							?>
						</div>

						<?php
						if ( $_product->is_sold_individually() ) {
							$min_quantity = 1;
							$max_quantity = 1;
						} else {
							$min_quantity = 0;
							$max_quantity = $_product->get_max_purchase_quantity();
						}

						$product_quantity = woocommerce_quantity_input(
							array(
								'input_name'   => "cart[{$cart_item_key}][qty]",
								'input_value'  => $cart_item['quantity'],
								'max_value'    => $max_quantity,
								'min_value'    => $min_quantity,
								'product_name' => $product_name,
							),
							$_product,
							false
						);

						echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore
						?>
					</div>
				</div>
			</div>
			<?php
		}
		?>

		<?php do_action( 'woocommerce_cart_contents' ); ?>
		<?php do_action( 'woocommerce_after_cart_contents' ); ?>
	</div>

	<?php tf_cart_upsell(); ?>

	<?php tf_cart_pickup_sections(); ?>

	<?php if ( wc_coupons_enabled() ) : ?>
		<section class="section-tunnel code-promo">
			<h2 class="titre-section titre-section--grand"><?php esc_html_e( 'Code promo', 'tacos-factory' ); ?></h2>
			<div class="code-promo__ligne">
				<p class="champ">
					<label class="champ__label champ__label--fonce" for="coupon_code"><?php esc_html_e( 'Vous avez un code ?', 'tacos-factory' ); ?></label>
					<input type="text" name="coupon_code" class="champ__saisie" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'TACOS10', 'tacos-factory' ); ?>">
				</p>
				<button type="submit" class="bouton bouton--sombre code-promo__bouton" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Appliquer', 'tacos-factory' ); ?></button>
			</div>
			<?php foreach ( WC()->cart->get_coupons() as $tf_code => $tf_coupon ) : ?>
				<p class="code-promo__applique">
					<?php echo tf_icon( 'tag', 16 ); // phpcs:ignore ?>
					<span><?php echo esc_html( mb_strtoupper( $tf_code ) . ( $tf_coupon->get_description() ? ' · ' . $tf_coupon->get_description() : '' ) ); ?></span>
					<a href="<?php echo esc_url( add_query_arg( 'remove_coupon', rawurlencode( $tf_code ), wc_get_cart_url() ) ); ?>" class="woocommerce-remove-coupon" data-coupon="<?php echo esc_attr( $tf_code ); ?>" aria-label="<?php esc_attr_e( 'Retirer le code', 'tacos-factory' ); ?>"><?php echo tf_icon( 'x', 16 ); // phpcs:ignore ?></a>
				</p>
			<?php endforeach; ?>
			<?php do_action( 'woocommerce_cart_coupon' ); ?>
		</section>
	<?php endif; ?>

	<button type="submit" class="screen-reader-text" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>
	<?php do_action( 'woocommerce_cart_actions' ); ?>
	<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>

	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals">
	<?php do_action( 'woocommerce_cart_collaterals' ); ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
