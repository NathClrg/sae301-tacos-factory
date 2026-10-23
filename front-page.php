<?php
/**
 * Accueil (maquette « 11 Accueil — complet ») : hero « Click and Collect »,
 * les 3 nouveautés, le produit star et « À propos ».
 * Hero éditable dans Pages > Accueil (champs SCF « Accueil — Hero »).
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tf_page_id  = (int) get_option( 'page_on_front' );
$tf_surtitre = tf_field( 'tf_hero_surtitre', $tf_page_id, __( 'Limoges · depuis 2020', 'tacos-factory' ) );
$tf_ligne1   = tf_field( 'tf_hero_ligne1', $tf_page_id, 'Click' );
$tf_liaison  = tf_field( 'tf_hero_liaison', $tf_page_id, 'and' );
$tf_ligne2   = tf_field( 'tf_hero_ligne2', $tf_page_id, 'Collect' );
$tf_texte    = tf_field( 'tf_hero_texte', $tf_page_id, __( "Le Click & Collect est enfin là! Les meilleurs tacos, burger et wraps de Limoges désormais préparé à l’avance.\nToujours aussi généreux et savoureux!", 'tacos-factory' ) );
$tf_image_id = (int) tf_field( 'tf_hero_image', $tf_page_id, 0 );
$tf_btn1     = tf_field(
	'tf_hero_bouton_1',
	$tf_page_id,
	array(
		'url'   => tf_get_carte_url(),
		'title' => __( 'Voir le menu', 'tacos-factory' ),
	)
);
$tf_btn2     = tf_field(
	'tf_hero_bouton_2',
	$tf_page_id,
	array(
		'url'   => tf_get_carte_url(),
		'title' => __( 'Click and Collect', 'tacos-factory' ),
	)
);

if ( ! $tf_image_id && $tf_page_id ) {
	$tf_image_id = (int) get_post_thumbnail_id( $tf_page_id );
}

// Les 3 derniers produits ajoutés, et le produit star (mis en avant, sinon le plus vendu).
$tf_nouveautes = function_exists( 'wc_get_products' ) ? wc_get_products(
	array(
		'status'     => 'publish',
		'limit'      => 3,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'visibility' => 'catalog',
	)
) : array();
$tf_star_ids   = function_exists( 'wc_get_featured_product_ids' ) ? wc_get_featured_product_ids() : array();
$tf_star       = $tf_star_ids ? wc_get_product( $tf_star_ids[0] ) : null;
if ( ! $tf_star && function_exists( 'wc_get_products' ) ) {
	$tf_best = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 1,
			'orderby'  => 'popularity',
			'order'    => 'DESC',
		)
	);
	$tf_star = $tf_best ? $tf_best[0] : null;
}
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main accueil">

		<section class="hero">
			<div class="hero__fond" aria-hidden="true">
				<?php
				if ( $tf_image_id ) {
					echo wp_get_attachment_image(
						$tf_image_id,
						'tf-hero',
						false,
						array(
							'class'         => 'hero__image',
							'alt'           => '',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'sizes'         => '100vw',
						)
					);
				} elseif ( file_exists( TF_DIR . '/assets/img/hero.jpg' ) ) {
					printf( '<img class="hero__image" src="%s" alt="" fetchpriority="high">', esc_url( TF_URI . '/assets/img/hero.jpg' ) );
				}
				?>
			</div>

			<div class="col-full hero__interieur">
				<p class="hero__surtitre"><?php echo esc_html( $tf_surtitre ); ?></p>
				<h1 class="hero__titre">
					<span class="hero__ligne">
						<span class="hero__click"><?php echo esc_html( $tf_ligne1 ); ?></span>
						<?php if ( $tf_liaison ) : ?>
							<span class="hero__and"><?php echo esc_html( $tf_liaison ); ?></span>
						<?php endif; ?>
					</span>
					<span class="hero__collect"><?php echo esc_html( $tf_ligne2 ); ?></span>
				</h1>
				<p class="hero__texte"><?php echo nl2br( esc_html( $tf_texte ) ); ?></p>

				<div class="hero__actions">
					<?php if ( ! empty( $tf_btn1['url'] ) ) : ?>
						<a class="bouton bouton--primaire bouton--hero" href="<?php echo esc_url( $tf_btn1['url'] ); ?>"<?php echo ! empty( $tf_btn1['target'] ) ? ' target="' . esc_attr( $tf_btn1['target'] ) . '"' : ''; ?>><?php echo esc_html( $tf_btn1['title'] ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $tf_btn2['url'] ) ) : ?>
						<a class="bouton bouton--contour bouton--hero" href="<?php echo esc_url( $tf_btn2['url'] ); ?>"<?php echo ! empty( $tf_btn2['target'] ) ? ' target="' . esc_attr( $tf_btn2['target'] ) . '"' : ''; ?>><?php echo esc_html( $tf_btn2['title'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<?php if ( $tf_nouveautes ) : ?>
			<section class="col-full bloc-accueil">
				<p class="surtitre"><?php esc_html_e( 'Tout juste arrivés', 'tacos-factory' ); ?></p>
				<h2 class="bloc-accueil__titre"><?php echo tf_split_title( __( 'Les nouveautés', 'tacos-factory' ) ); // phpcs:ignore ?></h2>
				<p class="bloc-accueil__texte"><?php esc_html_e( 'Les 3 derniers produits ajoutés à la carte.', 'tacos-factory' ); ?></p>
				<div class="tuiles">
					<?php
					foreach ( $tf_nouveautes as $tf_product ) {
						tf_product_tile( $tf_product, __( 'Nouveau', 'tacos-factory' ) );
					}
					?>
				</div>
				<a class="bouton bouton--secondaire bouton--large" href="<?php echo esc_url( tf_get_carte_url() ); ?>"><?php esc_html_e( 'Voir toute la carte', 'tacos-factory' ); ?></a>
			</section>
		<?php endif; ?>

		<?php if ( $tf_star ) : ?>
			<?php
			$tf_sales   = (int) $tf_star->get_total_sales();
			$tf_reviews = (int) $tf_star->get_review_count();
			$tf_meta    = array();
			if ( $tf_sales ) {
				/* translators: %s : nombre de commandes. */
				$tf_meta[] = sprintf( _n( 'Commandé %s fois', 'Commandé %s fois', $tf_sales, 'tacos-factory' ), number_format_i18n( $tf_sales ) );
			}
			if ( $tf_reviews ) {
				/* translators: 1 : note, 2 : nombre d'avis. */
				$tf_meta[] = sprintf( _n( '★ %1$s (%2$d avis)', '★ %1$s (%2$d avis)', $tf_reviews, 'tacos-factory' ), number_format_i18n( (float) $tf_star->get_average_rating(), 1 ), $tf_reviews );
			}
			?>
			<section class="col-full bloc-accueil bloc-accueil--star">
				<div class="star">
					<a class="star__image" href="<?php echo esc_url( $tf_star->get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
						<?php tf_product_visual( $tf_star, 'tf-product', 48 ); ?>
						<span class="badge badge--promo star__badge">★ <?php esc_html_e( 'Produit star', 'tacos-factory' ); ?></span>
					</a>
					<p class="surtitre"><?php esc_html_e( 'Le plus vendu ce mois-ci', 'tacos-factory' ); ?></p>
					<h2 class="star__nom"><?php echo esc_html( $tf_star->get_name() ); ?></h2>
					<?php if ( $tf_meta ) : ?>
						<p class="star__meta"><?php echo esc_html( implode( ' · ', $tf_meta ) ); ?></p>
					<?php endif; ?>
					<div class="star__achat">
						<p class="star__prix"><?php echo wp_kses_post( tf_product_price( $tf_star ) ); ?></p>
						<a class="bouton bouton--primaire" href="<?php echo esc_url( $tf_star->get_permalink() ); ?>"><?php esc_html_e( 'Je le veux', 'tacos-factory' ); ?></a>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="bloc-accueil bloc-accueil--creme">
			<div class="col-full">
				<p class="surtitre"><?php esc_html_e( 'À propos', 'tacos-factory' ); ?></p>
				<h2 class="bloc-accueil__titre"><?php esc_html_e( 'Bienvenue chez', 'tacos-factory' ); ?> <span class="accent">Tacos Factory</span></h2>
				<p class="bloc-accueil__texte"><?php esc_html_e( 'Depuis 2020, on prépare à Limoges des tacos généreux, des burgers gourmands et des wraps savoureux, avec des sauces maison. Le Click & Collect vous évite l’attente : commandez, on prépare, vous récupérez.', 'tacos-factory' ); ?></p>
				<ul class="chiffres">
					<li class="chiffres__item"><strong>4,3 ★</strong><?php esc_html_e( 'Avis Google', 'tacos-factory' ); ?></li>
					<li class="chiffres__item"><strong>2000+</strong><?php esc_html_e( 'Clients', 'tacos-factory' ); ?></li>
					<li class="chiffres__item"><strong>6j/7</strong><?php esc_html_e( 'Ouvert', 'tacos-factory' ); ?></li>
				</ul>
				<a class="bouton bouton--secondaire bouton--large" href="<?php echo esc_url( tf_page_url( 'a-propos' ) ); ?>"><?php esc_html_e( 'Rencontrer l’équipe', 'tacos-factory' ); ?></a>
			</div>
		</section>

		<?php
		while ( have_posts() ) :
			the_post();
			if ( '' !== trim( get_the_content() ) ) :
				?>
				<div class="col-full page-contenu entry-content">
					<?php the_content(); ?>
				</div>
				<?php
			endif;
		endwhile;
		?>

	</main>
</div>

<?php
get_footer();
