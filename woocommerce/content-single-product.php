<?php
/**
 * Fiche produit (maquette « 03 Produit variable — Tacos ») : photo + badge, titre,
 * informations produit, prix et stock, groupes d'options SCF, précision,
 * « Souvent pris avec », avis et barre « Ajouter · 13.99€ ».
 *
 * @package TacosFactory
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$tf_id          = $product->get_id();
$tf_category    = tf_product_main_category( $product );
$tf_badge       = tf_field( 'tf_badge', $tf_id, '' );
$tf_inclus      = tf_field( 'tf_inclus', $tf_id, '' );
$tf_temps       = tf_field( 'tf_temps', $tf_id, '' );
$tf_composition = tf_field( 'tf_composition', $tf_id, '' );
$tf_nutrition   = tf_field( 'tf_nutrition', $tf_id, '' );
$tf_allergenes  = (array) tf_field( 'tf_allergenes', $tf_id, array() );
$tf_desc        = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
$tf_back_url    = tf_get_carte_url() . ( $tf_category ? '#' . $tf_category->slug : '' );
$tf_custom      = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock();

// Libellés des allergènes (« oeuf » → « Œuf »), tels que déclarés dans SCF.
$tf_allergen_field  = function_exists( 'acf_get_field' ) ? acf_get_field( 'field_tf_allergenes' ) : array();
$tf_allergen_labels = isset( $tf_allergen_field['choices'] ) ? $tf_allergen_field['choices'] : array();
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'produit', $product ); ?>>

	<?php tf_back_link( __( 'Retour à la carte', 'tacos-factory' ), $tf_back_url ); ?>

	<div class="produit__grille">

		<div class="produit__image">
			<?php tf_product_visual( $product, 'tf-product', 64 ); ?>
			<?php if ( $tf_badge ) : ?>
				<span class="badge badge--promo produit__badge"><?php echo esc_html( $tf_badge ); ?></span>
			<?php endif; ?>
		</div>

		<div class="produit__contenu">
			<div class="produit__infos">
				<?php if ( $tf_category ) : ?>
					<p class="surtitre">
						<?php
						/* translators: %s : catégorie. */
						printf( esc_html__( 'Nos %s', 'tacos-factory' ), esc_html( $tf_category->name ) );
						?>
					</p>
				<?php endif; ?>

				<h1 class="produit__titre"><?php echo tf_split_title( $product->get_name() ); // phpcs:ignore ?></h1>

				<?php if ( $tf_desc ) : ?>
					<div class="produit__description"><?php echo wp_kses_post( wpautop( $tf_desc ) ); ?></div>
				<?php endif; ?>

				<?php if ( $tf_inclus || $tf_temps ) : ?>
					<ul class="puces">
						<?php if ( $tf_inclus ) : ?>
							<li class="puces__item"><?php echo tf_icon( 'fries', 15 ); // phpcs:ignore ?><?php echo esc_html( $tf_inclus ); ?></li>
						<?php endif; ?>
						<?php if ( $tf_temps ) : ?>
							<li class="puces__item"><?php echo tf_icon( 'clock', 15 ); // phpcs:ignore ?><?php echo esc_html( $tf_temps ); ?></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $tf_composition || $tf_allergenes || $tf_nutrition ) : ?>
					<div class="infos-produit">
						<?php if ( $tf_composition ) : ?>
							<details class="infos-produit__ligne">
								<summary class="infos-produit__titre"><?php echo tf_icon( 'leaf', 18 ); // phpcs:ignore ?><?php esc_html_e( 'Composition', 'tacos-factory' ); ?><?php echo tf_icon( 'chevron-right', 16, 'infos-produit__chevron' ); // phpcs:ignore ?></summary>
								<p class="infos-produit__texte"><?php echo esc_html( $tf_composition ); ?></p>
							</details>
						<?php endif; ?>
						<?php if ( $tf_allergenes ) : ?>
							<details class="infos-produit__ligne" open>
								<summary class="infos-produit__titre"><?php echo tf_icon( 'alert', 18 ); // phpcs:ignore ?><?php esc_html_e( 'Allergènes', 'tacos-factory' ); ?><?php echo tf_icon( 'chevron-right', 16, 'infos-produit__chevron' ); // phpcs:ignore ?></summary>
								<ul class="allergenes">
									<?php foreach ( $tf_allergenes as $tf_allergen ) : ?>
										<li class="badge badge--obligatoire"><?php echo esc_html( mb_strtoupper( isset( $tf_allergen_labels[ $tf_allergen ] ) ? $tf_allergen_labels[ $tf_allergen ] : $tf_allergen ) ); ?></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
						<?php if ( $tf_nutrition ) : ?>
							<details class="infos-produit__ligne">
								<summary class="infos-produit__titre"><?php echo tf_icon( 'info', 18 ); // phpcs:ignore ?><?php esc_html_e( 'Valeurs nutritionnelles', 'tacos-factory' ); ?><?php echo tf_icon( 'chevron-right', 16, 'infos-produit__chevron' ); // phpcs:ignore ?></summary>
								<p class="infos-produit__texte"><?php echo nl2br( esc_html( $tf_nutrition ) ); ?></p>
							</details>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="produit__achat">
					<p class="produit__prix"><?php echo wp_kses_post( tf_product_price( $product ) ); ?></p>
					<?php echo tf_stock_markup( $product ); // phpcs:ignore ?>
				</div>
			</div>

			<?php if ( $tf_custom ) : ?>

				<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

				<form class="produit__formulaire" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-base="<?php echo esc_attr( wc_get_price_to_display( $product ) ); ?>">

					<input type="hidden" name="tf_options_form" value="1">

					<?php tf_render_product_options( $product ); ?>

					<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

					<div class="barre-action barre-commande">
						<?php
						do_action( 'woocommerce_before_add_to_cart_quantity' );
						woocommerce_quantity_input(
							array(
								'min_value'   => $product->get_min_purchase_quantity(),
								'max_value'   => $product->get_max_purchase_quantity(),
								'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
							)
						);
						do_action( 'woocommerce_after_add_to_cart_quantity' );
						?>
						<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button bouton bouton--primaire barre-commande__bouton">
							<?php esc_html_e( 'Ajouter', 'tacos-factory' ); ?> · <span class="barre-commande__total"><?php echo wp_kses_post( wc_price( tf_product_initial_price( $product ) ) ); ?></span>
						</button>
					</div>

					<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
				</form>

				<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

			<?php else : ?>

				<div class="produit__achat-standard">
					<?php woocommerce_template_single_add_to_cart(); ?>
				</div>

			<?php endif; ?>

			<?php
			$tf_cross_sells = array_filter( array_map( 'wc_get_product', $product->get_cross_sell_ids() ) );
			if ( $tf_cross_sells ) :
				?>
				<section class="section-tunnel">
					<h2 class="titre-section titre-section--grand"><?php esc_html_e( 'Souvent pris avec', 'tacos-factory' ); ?></h2>
					<p class="titre-section__aide"><?php esc_html_e( 'Complétez votre menu', 'tacos-factory' ); ?></p>
					<div class="tuiles">
						<?php
						foreach ( array_slice( $tf_cross_sells, 0, 4 ) as $tf_cross_sell ) {
							tf_product_tile( $tf_cross_sell );
						}
						?>
					</div>
				</section>
			<?php endif; ?>

			<?php tf_product_reviews( $product ); ?>
		</div>
	</div>
</div>

<?php
// Données structurées produit (normalement émises par woocommerce_single_product_summary).
if ( isset( WC()->structured_data ) ) {
	WC()->structured_data->generate_product_data( $product );
}

do_action( 'woocommerce_after_single_product' );
