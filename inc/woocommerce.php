<?php
/**
 * WooCommerce : cartes produits, stock, panier, frais de préparation,
 * fenêtre « Ajouté au panier ! », fragments, textes.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Pas de formulaire d'adresse de livraison sur la page de paiement :
 * l'adresse se saisit dans le panier (inc/livraison.php).
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

/* -------------------------------------------------------------------------
 * Catégories / produits
 * ---------------------------------------------------------------------- */

/**
 * Catégories de premier niveau affichées sur la carte (ordre WooCommerce).
 *
 * @return WP_Term[]
 */
function tf_menu_categories() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'orderby'    => 'menu_order',
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Produits publiés d'une catégorie.
 *
 * @param string $slug  Slug de catégorie.
 * @param int    $limit Nombre max.
 * @return WC_Product[]
 */
function tf_category_products( $slug, $limit = -1 ) {
	return wc_get_products(
		array(
			'status'     => 'publish',
			'limit'      => $limit,
			'category'   => array( $slug ),
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
			'visibility' => 'catalog',
		)
	);
}

/**
 * Catégorie principale d'un produit.
 *
 * @param WC_Product $product Produit.
 * @return WP_Term|null
 */
function tf_product_main_category( $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	$default = (int) get_option( 'default_product_cat' );
	foreach ( $terms as $term ) {
		if ( (int) $term->term_id !== $default ) {
			return $term;
		}
	}
	return $terms[0];
}

/**
 * Icône de remplacement quand le produit n'a pas de photo.
 *
 * @param WC_Product $product Produit.
 * @return string Nom d'icône pour tf_icon().
 */
function tf_product_icon_name( $product ) {
	$icon = tf_field( 'tf_icone', $product->get_id(), 'auto' );
	if ( 'auto' !== $icon ) {
		return $icon;
	}

	$term = tf_product_main_category( $product );
	$slug = $term ? $term->slug : '';
	$map  = array(
		'burger'    => 'hamburger',
		'accompagn' => 'fries',
		'frite'     => 'fries',
		'boisson'   => 'cup-soda',
		'dessert'   => 'ice-cream',
	);
	foreach ( $map as $needle => $name ) {
		if ( false !== strpos( $slug, $needle ) ) {
			return $name;
		}
	}
	return 'utensils';
}

/**
 * Le produit peut-il être ajouté directement (sans passer par sa fiche) ?
 *
 * @param WC_Product $product Produit.
 * @return bool
 */
function tf_can_quick_add( $product ) {
	return $product->is_type( 'simple' )
		&& $product->is_purchasable()
		&& $product->is_in_stock()
		&& ! tf_get_product_options( $product->get_id() );
}

/**
 * Bouton rond « + » (ajout AJAX, ou lien vers la fiche s'il y a des choix à faire).
 *
 * @param WC_Product $product Produit.
 * @param int        $size    Taille de l'icône.
 * @param string     $block   Bloc BEM parent (carte, tuile ou mini-carte).
 */
function tf_card_add_button( $product, $size = 14, $block = 'carte' ) {
	$name  = $product->get_name();
	$class = $block . '__ajouter';

	if ( tf_can_quick_add( $product ) ) {
		printf(
			'<a href="%1$s" data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="%7$s add_to_cart_button ajax_add_to_cart product_type_simple" aria-label="%4$s" rel="nofollow">%5$s%6$s</a>',
			esc_url( $product->add_to_cart_url() ),
			absint( $product->get_id() ),
			esc_attr( $product->get_sku() ),
			/* translators: %s : nom du produit. */
			esc_attr( sprintf( __( 'Ajouter « %s » au panier', 'tacos-factory' ), $name ) ),
			tf_icon( 'plus', $size, 'icone-plus' ), // phpcs:ignore
			tf_icon( 'check', $size, 'icone-coche' ), // phpcs:ignore
			esc_attr( $class )
		);
		return;
	}

	printf(
		'<a href="%1$s" class="%4$s" aria-label="%2$s">%3$s</a>',
		esc_url( $product->get_permalink() ),
		/* translators: %s : nom du produit. */
		esc_attr( sprintf( __( 'Composer « %s »', 'tacos-factory' ), $name ) ),
		tf_icon( 'plus', $size ), // phpcs:ignore
		esc_attr( $class )
	);
}

/**
 * Visuel produit : photo ou fond sable + icône.
 *
 * @param WC_Product $product Produit.
 * @param string     $size    Taille d'image.
 * @param int        $icon    Taille de l'icône.
 */
function tf_product_visual( $product, $size = 'tf-card', $icon = 38 ) {
	if ( $product->get_image_id() ) {
		echo wp_get_attachment_image( $product->get_image_id(), $size, false, array( 'alt' => esc_attr( $product->get_name() ), 'loading' => 'lazy' ) );
		return;
	}
	echo '<span class="visuel-vide">' . tf_icon( tf_product_icon_name( $product ), $icon ) . '</span>'; // phpcs:ignore
}

/**
 * Prix affiché (avec réduction éventuelle).
 *
 * @param WC_Product $product Produit.
 * @return string HTML.
 */
function tf_product_price( $product ) {
	return wc_price( wc_get_price_to_display( $product ) );
}

/**
 * État du stock : « En stock », « Stock faible · plus que 3 », « Épuisé ».
 *
 * @param WC_Product $product Produit.
 * @return string HTML.
 */
function tf_stock_markup( $product ) {
	if ( ! $product->is_in_stock() ) {
		return '<p class="stock-etat stock-etat--epuise">' . esc_html__( 'Épuisé', 'tacos-factory' ) . '</p>';
	}
	$qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
	if ( null !== $qty && $qty <= (int) get_option( 'woocommerce_notify_low_stock_amount', 5 ) ) {
		/* translators: %d : quantité restante. */
		return '<p class="stock-etat stock-etat--faible">' . esc_html( sprintf( __( 'Stock faible · plus que %d', 'tacos-factory' ), $qty ) ) . '</p>';
	}
	return '<p class="stock-etat">' . esc_html__( 'En stock', 'tacos-factory' ) . '</p>';
}

/**
 * Carte produit de « La carte » (maquette 02 : petites cartes des carrousels).
 *
 * @param WC_Product $product Produit.
 * @param string     $tag     Balise conteneur (li dans les boucles WooCommerce).
 */
function tf_product_card( $product, $tag = 'div' ) {
	$tag      = in_array( $tag, array( 'div', 'li', 'article' ), true ) ? $tag : 'div';
	$link     = $product->get_permalink();
	$accroche = tf_field( 'tf_accroche', $product->get_id(), '' );
	$classes  = 'li' === $tag ? wc_get_product_class( 'carte', $product ) : array( 'carte' );
	?>
	<<?php echo esc_html( $tag ); ?> class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<div class="carte__image">
			<a href="<?php echo esc_url( $link ); ?>" class="carte__lien-image" tabindex="-1" aria-hidden="true">
				<?php tf_product_visual( $product ); ?>
			</a>
			<?php tf_card_add_button( $product ); ?>
		</div>
		<a href="<?php echo esc_url( $link ); ?>" class="carte__corps">
			<span class="carte__ligne">
				<span class="carte__nom"><?php echo esc_html( $product->get_name() ); ?></span>
				<span class="carte__prix"><?php echo wp_kses_post( tf_product_price( $product ) ); ?></span>
			</span>
			<?php if ( $accroche ) : ?>
				<span class="carte__accroche">-<?php echo esc_html( $accroche ); ?></span>
			<?php endif; ?>
		</a>
	</<?php echo esc_html( $tag ); ?>>
	<?php
}

/**
 * Tuile produit (accueil « Les nouveautés », fiche « Souvent pris avec »).
 *
 * @param WC_Product $product Produit.
 * @param string     $badge   Badge sur la photo (« NOUVEAU »).
 */
function tf_product_tile( $product, $badge = '' ) {
	$link     = $product->get_permalink();
	$accroche = tf_field( 'tf_accroche', $product->get_id(), '' );
	?>
	<div class="tuile">
		<div class="tuile__image">
			<a href="<?php echo esc_url( $link ); ?>" class="tuile__lien-image" tabindex="-1" aria-hidden="true">
				<?php tf_product_visual( $product ); ?>
			</a>
			<?php if ( $badge ) : ?>
				<span class="badge badge--promo tuile__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
			<?php tf_card_add_button( $product, 18, 'tuile' ); ?>
		</div>
		<a href="<?php echo esc_url( $link ); ?>" class="tuile__corps">
			<span class="tuile__ligne">
				<span class="tuile__nom"><?php echo esc_html( $product->get_name() ); ?></span>
				<span class="tuile__prix"><?php echo wp_kses_post( tf_product_price( $product ) ); ?></span>
			</span>
			<?php if ( $accroche ) : ?>
				<span class="tuile__accroche"><?php echo esc_html( $accroche ); ?></span>
			<?php endif; ?>
		</a>
		<?php echo tf_stock_markup( $product ); // phpcs:ignore ?>
	</div>
	<?php
}

/**
 * « Avis clients » de la fiche produit : le dernier avis, les autres derrière un bouton.
 *
 * @param WC_Product $product Produit.
 */
function tf_product_reviews( $product ) {
	if ( ! wc_reviews_enabled() ) {
		return;
	}
	$count   = $product->get_review_count();
	$reviews = get_comments(
		array(
			'post_id' => $product->get_id(),
			'status'  => 'approve',
			'type'    => 'review',
			'number'  => 10,
		)
	);
	?>
	<section class="section-tunnel avis" id="avis">
		<h2 class="titre-section titre-section--grand"><?php esc_html_e( 'Avis clients', 'tacos-factory' ); ?></h2>
		<p class="titre-section__aide">
			<?php
			if ( $count ) {
				/* translators: 1 : note moyenne, 2 : nombre d'avis. */
				printf( esc_html( _n( '★ %1$s · %2$d avis', '★ %1$s · %2$d avis', $count, 'tacos-factory' ) ), esc_html( number_format_i18n( (float) $product->get_average_rating(), 1 ) ), absint( $count ) );
			} else {
				esc_html_e( 'Aucun avis pour le moment.', 'tacos-factory' );
			}
			?>
		</p>
		<?php foreach ( $reviews as $index => $review ) : ?>
			<?php
			$rating = (int) get_comment_meta( $review->comment_ID, 'rating', true );
			$names  = preg_split( '/\s+/', trim( $review->comment_author ) );
			$author = $names[0] . ( isset( $names[1] ) ? ' ' . mb_substr( $names[1], 0, 1 ) . '.' : '' );
			?>
			<article class="avis__carte"<?php echo $index > 0 ? ' hidden' : ''; ?>>
				<p class="avis__entete">
					<strong class="avis__auteur"><?php echo esc_html( $author ); ?></strong>
					<?php if ( wc_review_is_from_verified_owner( $review->comment_ID ) ) : ?>
						<span class="badge badge--verifie"><?php esc_html_e( 'Achat vérifié', 'tacos-factory' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="avis__note">
					<span class="avis__etoiles" aria-label="<?php echo esc_attr( sprintf( /* translators: %d : note. */ __( '%d sur 5', 'tacos-factory' ), $rating ) ); ?>">
						<?php
						for ( $i = 1; $i <= 5; $i++ ) {
							echo tf_icon( 'star', 12, $i <= $rating ? 'avis__etoile--pleine' : '' ); // phpcs:ignore
						}
						?>
					</span>
					<?php echo esc_html( wp_date( 'j M Y', strtotime( $review->comment_date_gmt . ' UTC' ) ) ); ?>
				</p>
				<p class="avis__texte"><?php echo esc_html( wp_strip_all_tags( $review->comment_content ) ); ?></p>
			</article>
		<?php endforeach; ?>
		<?php if ( count( $reviews ) > 1 ) : ?>
			<button type="button" class="bouton bouton--secondaire bouton--large avis__plus">
				<?php
				/* translators: %d : nombre d'avis. */
				echo esc_html( sprintf( __( 'Voir les %d avis', 'tacos-factory' ), $count ) );
				?>
			</button>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Archives de catégorie : « Nos Tacos ».
 *
 * @param string $title Titre.
 * @return string
 */
function tf_archive_title( $title ) {
	if ( is_product_category() ) {
		return '<span class="accent">' . esc_html__( 'Nos', 'tacos-factory' ) . '</span> ' . esc_html( single_term_title( '', false ) );
	}
	return $title;
}
add_filter( 'woocommerce_page_title', 'tf_archive_title' );

/* -------------------------------------------------------------------------
 * Header : badge du panier mis à jour en AJAX
 * ---------------------------------------------------------------------- */

/**
 * Fragments panier.
 *
 * @param array $fragments Fragments.
 * @return array
 */
function tf_cart_fragments( $fragments ) {
	$fragments['span.entete__compteur'] = tf_cart_count_markup();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'tf_cart_fragments' );

/* -------------------------------------------------------------------------
 * Panier
 * ---------------------------------------------------------------------- */

/**
 * Résumé d'une ligne panier : « Poulet mariné, Kebab · Sauce Algérienne · + Cheddar ».
 *
 * @param array $cart_item Ligne du panier.
 * @return string HTML échappé.
 */
function tf_cart_item_summary( $cart_item ) {
	if ( empty( $cart_item['tf_options'] ) ) {
		$data = wc_get_formatted_cart_item_data( $cart_item, true );
		return $data ? esc_html( wp_strip_all_tags( $data ) ) : '';
	}

	$parts = array();
	foreach ( $cart_item['tf_options'] as $group ) {
		$labels = array();
		foreach ( $group['choix'] as $choice ) {
			$labels[] = ( $choice['prix'] > 0 && 'absolu' !== $group['affichage'] ) ? '+ ' . $choice['label'] : $choice['label'];
		}
		$text    = implode( ', ', $labels );
		$parts[] = esc_html( empty( $group['prefixe'] ) ? $text : $group['prefixe'] . ' ' . $text );
	}
	$html = implode( ' · ', $parts );

	if ( ! empty( $cart_item['tf_precision'] ) ) {
		$html .= ' · <em>« ' . esc_html( $cart_item['tf_precision'] ) . ' »</em>';
	}
	return $html;
}

/**
 * « Un petit dessert ? » (maquette 05).
 */
function tf_cart_upsell() {
	$slug     = apply_filters( 'tf_upsell_category', 'desserts' );
	$products = tf_category_products( $slug, 3 );
	if ( ! $products ) {
		return;
	}
	?>
	<section class="section-tunnel suggestion">
		<h2 class="titre-section"><?php esc_html_e( 'Un petit dessert ?', 'tacos-factory' ); ?></h2>
		<div class="suggestion__grille">
			<?php foreach ( $products as $product ) : ?>
				<div class="mini-carte">
					<div class="mini-carte__image">
						<?php tf_product_visual( $product, 'tf-card', 28 ); ?>
						<?php tf_card_add_button( $product, 13, 'mini-carte' ); ?>
					</div>
					<a class="mini-carte__corps" href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<span class="mini-carte__nom"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="mini-carte__prix"><?php echo wp_kses_post( tf_product_price( $product ) ); ?></span>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Pas de « Vous serez peut-être intéressé par… » sous le panier :
 * la maquette propose « Un petit dessert ? » à la place.
 */
function tf_cart_layout() {
	remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
}
add_action( 'init', 'tf_cart_layout' );

/**
 * Frais de préparation : 1,50 € sous 15 € d'achat, offerts au-delà.
 *
 * @param WC_Cart $cart Panier.
 */
function tf_preparation_fee( $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	if ( (float) $cart->get_subtotal() < TF_FRAIS_SEUIL ) {
		$cart->add_fee( __( 'Frais de préparation', 'tacos-factory' ), TF_FRAIS_PREPARATION );
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'tf_preparation_fee' );

/**
 * « Retour à la boutique » mène à la carte.
 *
 * @return string
 */
function tf_return_to_shop_url() {
	return tf_get_carte_url();
}
add_filter( 'woocommerce_return_to_shop_redirect', 'tf_return_to_shop_url' );

/* -------------------------------------------------------------------------
 * Fenêtre « Ajouté au panier ! » (maquette 04)
 * Après l'ajout depuis une fiche produit, la page se recharge et affiche
 * la fenêtre au lieu du message WooCommerce.
 * ---------------------------------------------------------------------- */

/**
 * Retient la ligne ajoutée (ajout classique, pas l'ajout rapide en AJAX).
 *
 * @param string $cart_item_key Ligne du panier.
 */
function tf_remember_added_item( $cart_item_key ) {
	if ( ! wp_doing_ajax() && WC()->session ) {
		WC()->session->set( 'tf_ajout', $cart_item_key );
	}
}
add_action( 'woocommerce_add_to_cart', 'tf_remember_added_item' );

/**
 * Pas de message « … a été ajouté à votre panier » : la fenêtre le remplace.
 *
 * @param string $message Message.
 * @return string
 */
function tf_hide_added_message( $message ) {
	return wp_doing_ajax() ? $message : '';
}
add_filter( 'wc_add_to_cart_message_html', 'tf_hide_added_message' );

/**
 * Affiche la fenêtre si un article vient d'être ajouté.
 */
function tf_added_to_cart_modal() {
	if ( ! WC()->session || ! WC()->cart ) {
		return;
	}
	$key = WC()->session->get( 'tf_ajout' );
	WC()->session->set( 'tf_ajout', null );
	$item = $key ? WC()->cart->get_cart_item( $key ) : array();
	if ( ! $item ) {
		return;
	}
	$product = $item['data'];
	$summary = tf_cart_item_summary( $item );
	$total   = html_entity_decode( wp_strip_all_tags( wc_price( WC()->cart->get_total( 'edit' ) ) ), ENT_QUOTES, 'UTF-8' );
	?>
	<div class="ajout" role="dialog" aria-modal="true" aria-labelledby="ajout-titre">
		<div class="ajout__fond" data-fermer></div>
		<div class="ajout__feuille">
			<span class="ajout__poignee" aria-hidden="true"></span>
			<span class="ajout__coche" aria-hidden="true"><?php echo tf_icon( 'check', 28 ); // phpcs:ignore ?></span>
			<h2 id="ajout-titre" class="ajout__titre"><?php esc_html_e( 'Ajouté au panier !', 'tacos-factory' ); ?></h2>
			<div class="ajout__article">
				<div class="ajout__image"><?php tf_product_visual( $product, 'thumbnail', 24 ); ?></div>
				<div class="ajout__texte">
					<p class="ajout__ligne">
						<span><?php echo esc_html( $product->get_name() ); ?></span>
						<span><?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, $item['quantity'] ) ); ?></span>
					</p>
					<?php if ( $summary ) : ?>
						<p class="ajout__details"><?php echo $summary; // phpcs:ignore -- échappé dans tf_cart_item_summary(). ?></p>
					<?php endif; ?>
				</div>
			</div>
			<p class="ajout__question"><?php esc_html_e( 'Votre commande est-elle terminée ?', 'tacos-factory' ); ?></p>
			<p class="ajout__aide"><?php esc_html_e( 'Vous pouvez encore ajouter une boisson ou un dessert.', 'tacos-factory' ); ?></p>
			<a class="bouton bouton--primaire bouton--large" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
				<?php
				/* translators: %s : total du panier. */
				echo esc_html( sprintf( __( 'Oui, voir mon panier · %s', 'tacos-factory' ), $total ) );
				?>
			</a>
			<a class="bouton bouton--secondaire bouton--large" href="<?php echo esc_url( tf_get_carte_url() ); ?>"><?php esc_html_e( 'Non, continuer mes achats', 'tacos-factory' ); ?></a>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_after_single_product', 'tf_added_to_cart_modal' );

/* -------------------------------------------------------------------------
 * Libellés WooCommerce adaptés au Click & Collect
 * ---------------------------------------------------------------------- */

/**
 * Remplace quelques chaînes WooCommerce (quelle que soit la langue du site).
 *
 * @param string $translation Traduction.
 * @param string $text        Texte original (anglais).
 * @param string $domain      Domaine.
 * @return string
 */
function tf_wc_strings( $translation, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) {
		return $translation;
	}
	static $map = null;
	if ( null === $map ) {
		$map = array(
			'Billing details'               => __( 'Vos coordonnées', 'tacos-factory' ),
			'Billing &amp; Shipping'        => __( 'Vos coordonnées', 'tacos-factory' ),
			'Your order'                    => __( 'Votre commande', 'tacos-factory' ),
			'Return to shop'                => __( 'Voir la carte', 'tacos-factory' ),
			'Your cart is currently empty.' => __( 'Votre panier est vide… pour l’instant !', 'tacos-factory' ),
		);
	}
	return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
}
add_filter( 'gettext', 'tf_wc_strings', 10, 3 );
