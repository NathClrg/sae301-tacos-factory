<?php
/**
 * Surcharge des hooks Storefront : header, menu, footer, fil d'Ariane, sidebar.
 *
 * Le functions.php du thème enfant est chargé AVANT celui de Storefront :
 * les retraits de hooks se font donc sur `init`.
 *
 * Classes CSS : méthode BEM (bloc__element--modificateur).
 *   .entete → header        .menu → menu plein écran (téléphone)
 *   .pied   → footer
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace les hooks d'affichage de Storefront.
 */
function tf_storefront_hooks() {
	// Header : on repart de zéro (logo + menu + panier + burger).
	remove_all_actions( 'storefront_header' );
	if ( function_exists( 'storefront_skip_links' ) ) {
		add_action( 'storefront_header', 'storefront_skip_links', 5 );
	}
	add_action( 'storefront_header', 'tf_header_markup', 10 );

	// Footer : widgets, crédit et barre mobile Storefront remplacés.
	remove_all_actions( 'storefront_footer' );
	add_action( 'storefront_footer', 'tf_footer_markup', 10 );
	remove_action( 'storefront_after_footer', 'storefront_sticky_single_add_to_cart', 999 );

	// Pas de fil d'Ariane, pas de sidebar, pas de zone widget sous le header.
	remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
	remove_action( 'storefront_before_content', 'storefront_header_widget_region', 10 );
	remove_action( 'storefront_sidebar', 'storefront_get_sidebar', 10 );
}
add_action( 'init', 'tf_storefront_hooks' );

/**
 * Panier / commande : le titre de page est remplacé par l'en-tête maison.
 */
function tf_remove_page_header() {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		remove_action( 'storefront_page', 'storefront_page_header', 10 );
	}
}
add_action( 'template_redirect', 'tf_remove_page_header' );

/**
 * URL d'une page par son slug (accueil si elle n'existe pas).
 *
 * @param string $slug Slug.
 * @return string
 */
function tf_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' );
}

/**
 * Liens du menu.
 *
 * @param string $where « mobile » (menu plein écran) ou « bureau » (header).
 * @return array[] { label, url, actif, alerte }
 */
function tf_menu_items( $where ) {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return array();
	}
	$order      = function_exists( 'tf_current_order' ) ? tf_current_order() : null;
	$on_carte   = is_page_template( 'page-templates/template-carte.php' ) || ( function_exists( 'is_product' ) && ( is_product() || is_product_category() ) );
	$on_account = function_exists( 'is_account_page' ) && is_account_page();

	$items = array(
		array( __( 'Accueil', 'tacos-factory' ), home_url( '/' ), is_front_page(), false ),
		array( __( 'À propos', 'tacos-factory' ), tf_page_url( 'a-propos' ), is_page( 'a-propos' ), false ),
	);

	if ( 'bureau' === $where ) {
		$items[] = array( __( 'La carte', 'tacos-factory' ), tf_get_carte_url(), $on_carte, false );
		$items[] = array( __( 'Infos & contact', 'tacos-factory' ), tf_page_url( 'infos-contact' ), is_page( 'infos-contact' ), false );
	} else {
		$items[] = array( __( 'Click & Collect', 'tacos-factory' ), tf_get_carte_url(), $on_carte, false );
		$items[] = array(
			__( 'Ma commande', 'tacos-factory' ),
			$order ? $order->get_checkout_order_received_url() : wc_get_account_endpoint_url( 'orders' ),
			false,
			(bool) $order,
		);
		$items[] = array( __( 'Mon Compte', 'tacos-factory' ), wc_get_page_permalink( 'myaccount' ), $on_account, false );
		$items[] = array( __( 'Infos & Contact', 'tacos-factory' ), tf_page_url( 'infos-contact' ), is_page( 'infos-contact' ), false );
	}

	return array_map(
		static function ( $item ) {
			return array_combine( array( 'label', 'url', 'actif', 'alerte' ), $item );
		},
		$items
	);
}

/**
 * Pastille du nombre d'articles (header). Mise à jour en AJAX par les fragments WooCommerce.
 *
 * @return string
 */
function tf_cart_count_markup() {
	$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	return sprintf(
		'<span class="entete__compteur"%1$s>%2$d</span>',
		$count ? '' : ' hidden',
		absint( $count )
	);
}

/**
 * Header (maquette : composant « Header »).
 */
function tf_header_markup() {
	?>
	<div class="col-full entete">
		<div class="entete__logo">
			<?php echo tf_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<nav class="entete__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'tacos-factory' ); ?>">
			<ul class="entete__liste">
				<?php foreach ( tf_menu_items( 'bureau' ) as $item ) : ?>
					<li><a class="entete__lien<?php echo $item['actif'] ? ' entete__lien--actif' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="entete__actions">
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="entete__compte" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
					<?php echo tf_icon( 'user', 18 ); // phpcs:ignore ?>
					<?php esc_html_e( 'Mon compte', 'tacos-factory' ); ?>
				</a>
				<a class="entete__panier" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<?php echo tf_icon( 'shopping-bag', 28 ); // phpcs:ignore ?>
					<span class="entete__panier-texte"><?php esc_html_e( 'Panier', 'tacos-factory' ); ?></span>
					<?php echo tf_cart_count_markup(); // phpcs:ignore ?>
				</a>
			<?php endif; ?>

			<button type="button" class="entete__burger" aria-controls="menu" aria-expanded="false">
				<?php echo tf_icon( 'menu', 40, 'entete__icone-ouvrir' ); // phpcs:ignore ?>
				<?php echo tf_icon( 'x', 30, 'entete__icone-fermer' ); // phpcs:ignore ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'tacos-factory' ); ?></span>
			</button>
		</div>
	</div>
	<?php
	tf_menu_markup();
}

/**
 * Menu plein écran du téléphone (maquette « 10 Menu »).
 */
function tf_menu_markup() {
	$order = function_exists( 'tf_current_order' ) ? tf_current_order() : null;
	?>
	<nav id="menu" class="menu" aria-label="<?php esc_attr_e( 'Menu', 'tacos-factory' ); ?>">
		<?php if ( $order ) : ?>
			<?php
			$tf_step = tf_order_step( $order );
			$tf_time = tf_order_pickup_time( $order );
			?>
			<div class="menu__commande">
				<p class="menu__commande-entete">
					<span class="menu__commande-titre"><?php esc_html_e( 'Ma commande en cours', 'tacos-factory' ); ?></span>
					<span class="menu__commande-numero"><?php echo esc_html( $order->get_order_number() ); ?></span>
				</p>
				<div class="menu__commande-statut">
					<span class="menu__commande-icone"><?php echo tf_icon( 'chef-hat', 22 ); // phpcs:ignore ?></span>
					<div>
						<p class="menu__commande-heure">
							<?php
							if ( 3 === $tf_step || ! $tf_time ) {
								echo esc_html( tf_step_label( $tf_step, $order ) );
							} elseif ( tf_order_is_delivery( $order ) ) {
								/* translators: %s : plage horaire. */
								printf( esc_html__( 'Livraison %s', 'tacos-factory' ), esc_html( tf_plage_label( $tf_time ) ) );
							} else {
								/* translators: %s : heure. */
								printf( esc_html__( 'Prête vers %s', 'tacos-factory' ), esc_html( tf_hm( $tf_time ) ) );
							}
							?>
						</p>
						<p class="menu__commande-detail">
							<?php
							echo esc_html( tf_step_label( $tf_step, $order ) );
							if ( $tf_step < 3 && tf_order_minutes_left( $order ) ) {
								/* translators: %s : durée (« 18 min »). */
								printf( esc_html__( ' · dans ~%s', 'tacos-factory' ), esc_html( tf_duration( tf_order_minutes_left( $order ) ) ) );
							}
							?>
						</p>
					</div>
				</div>
				<a class="menu__commande-bouton" href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>">
					<?php esc_html_e( 'Suivre ma commande', 'tacos-factory' ); ?>
					<?php echo tf_icon( 'chevron-right', 16 ); // phpcs:ignore ?>
				</a>
			</div>
		<?php endif; ?>

		<ul class="menu__liste">
			<?php foreach ( tf_menu_items( 'mobile' ) as $item ) : ?>
				<li>
					<a class="menu__lien<?php echo $item['alerte'] ? ' menu__lien--alerte' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['actif'] ? ' aria-current="page"' : ''; ?>>
						<span class="menu__libelle">
							<?php echo esc_html( $item['label'] ); ?>
							<?php if ( $item['alerte'] ) : ?>
								<span class="menu__pastille">1</span>
							<?php endif; ?>
						</span>
						<?php echo tf_icon( 'chevron-right', 20, 'menu__chevron' ); // phpcs:ignore ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php tf_contact_box( 'menu__infos' ); ?>
	</nav>
	<?php
}

/**
 * Encart adresse / téléphone / horaires (menu, page Infos & contact).
 *
 * @param string $class Classe du conteneur.
 */
function tf_contact_box( $class = '' ) {
	$phone = tf_opt( 'telephone' );
	?>
	<ul class="contact <?php echo esc_attr( $class ); ?>">
		<li class="contact__ligne"><?php echo tf_icon( 'map-pin', 15 ); // phpcs:ignore ?><?php echo esc_html( tf_opt( 'adresse' ) ); ?></li>
		<?php if ( $phone ) : ?>
			<li class="contact__ligne"><?php echo tf_icon( 'phone', 15 ); // phpcs:ignore ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
		<?php endif; ?>
		<li class="contact__ligne"><?php echo tf_icon( 'clock', 15 ); // phpcs:ignore ?><?php echo esc_html( tf_opt( 'horaires' ) ); ?></li>
	</ul>
	<?php
}

/**
 * Footer (maquette « 11 Accueil », bloc « Pied de page »).
 */
function tf_footer_markup() {
	$phone   = tf_opt( 'telephone' );
	$order   = function_exists( 'tf_current_order' ) ? tf_current_order() : null;
	$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
	$columns = array(
		__( 'Navigation', 'tacos-factory' ) => array(
			__( 'Accueil', 'tacos-factory' )         => home_url( '/' ),
			__( 'La carte', 'tacos-factory' )        => tf_get_carte_url(),
			__( 'L’équipe', 'tacos-factory' )        => tf_page_url( 'a-propos' ),
			__( 'Infos & contact', 'tacos-factory' ) => tf_page_url( 'infos-contact' ),
		),
		__( 'Mon espace', 'tacos-factory' ) => array(
			__( 'Mon compte', 'tacos-factory' )         => $account,
			__( 'Mes commandes', 'tacos-factory' )      => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : $account,
			__( 'Suivre ma commande', 'tacos-factory' ) => $order ? $order->get_checkout_order_received_url() : $account,
		),
	);
	?>
	<div class="pied">
		<div class="pied__logo"><?php echo tf_logo(); // phpcs:ignore ?></div>

		<div class="pied__adresse">
			<p class="pied__nom"><?php echo esc_html( tf_opt( 'nom' ) ); ?></p>
			<p class="pied__texte"><?php echo esc_html( tf_opt( 'adresse' ) ); ?></p>
			<p class="pied__texte"><?php echo esc_html( tf_opt( 'horaires' ) . ( $phone ? ' · ' . $phone : '' ) ); ?></p>
		</div>

		<div class="pied__colonnes">
			<?php foreach ( $columns as $title => $links ) : ?>
				<nav class="pied__colonne" aria-label="<?php echo esc_attr( $title ); ?>">
					<p class="pied__titre"><?php echo esc_html( $title ); ?></p>
					<ul class="pied__liste">
						<?php foreach ( $links as $label => $url ) : ?>
							<li><a class="pied__lien" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>

		<p class="pied__legal">
			<a href="<?php echo esc_url( tf_page_url( 'mentions-legales' ) ); ?>"><?php esc_html_e( 'Mentions légales', 'tacos-factory' ); ?></a>
			<a href="<?php echo esc_url( tf_page_url( 'cgv' ) ); ?>"><?php esc_html_e( 'CGV', 'tacos-factory' ); ?></a>
			<a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Confidentialité', 'tacos-factory' ); ?></a>
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Tacos Factory</span>
		</p>
	</div>
	<?php
}
