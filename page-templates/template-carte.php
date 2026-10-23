<?php
/**
 * Template Name: La carte (Click & Collect)
 *
 * Maquette « iPhone 13 mini - 3 » : pastilles de catégories, carrousels
 * « Nos Tacos / Nos Wraps / … » et encart de retrait.
 * Les sections suivent les catégories produit WooCommerce (ordre glisser-déposer).
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tf_categories = function_exists( 'tf_menu_categories' ) ? tf_menu_categories() : array();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main carte-menu">

		<?php while ( have_posts() ) : the_post(); ?>
			<header class="carte-menu__entete">
				<p class="surtitre surtitre--minuscules"><?php esc_html_e( 'Notre Carte', 'tacos-factory' ); ?></p>
				<h1 class="carte-menu__titre">Click <span class="accent">&amp; Collect</span></h1>
				<div class="carte-menu__intro">
					<?php
					if ( '' !== trim( get_the_content() ) ) {
						the_content();
					} else {
						echo '<p>' . esc_html__( 'Tacos généreux, burgers gourmands, wraps savoureux. Composez ou laissez-vous tenter par nos signatures.', 'tacos-factory' ) . '</p>';
					}
					?>
				</div>
			</header>
		<?php endwhile; ?>

		<?php if ( ! $tf_categories ) : ?>
			<p class="carte-menu__vide"><?php esc_html_e( 'La carte arrive bientôt : ajoutez des produits dans des catégories WooCommerce.', 'tacos-factory' ); ?></p>
		<?php else : ?>

			<nav class="pastilles" aria-label="<?php esc_attr_e( 'Catégories de la carte', 'tacos-factory' ); ?>">
				<div class="pastilles__liste">
					<?php foreach ( $tf_categories as $tf_index => $tf_cat ) : ?>
						<a class="pastille<?php echo 0 === $tf_index ? ' pastille--active' : ''; ?>" href="#<?php echo esc_attr( $tf_cat->slug ); ?>"><?php echo esc_html( $tf_cat->name ); ?></a>
					<?php endforeach; ?>
				</div>
			</nav>

			<?php foreach ( $tf_categories as $tf_cat ) : ?>
				<?php $tf_products = tf_category_products( $tf_cat->slug ); ?>
				<?php if ( ! $tf_products ) { continue; } ?>
				<section id="<?php echo esc_attr( $tf_cat->slug ); ?>" class="section-menu" aria-labelledby="titre-<?php echo esc_attr( $tf_cat->slug ); ?>">
					<h2 id="titre-<?php echo esc_attr( $tf_cat->slug ); ?>" class="section-menu__titre">
						<span class="accent"><?php esc_html_e( 'Nos', 'tacos-factory' ); ?></span> <?php echo esc_html( $tf_cat->name ); ?>
					</h2>
					<div class="section-menu__defilement" role="list">
						<?php
						foreach ( $tf_products as $tf_product ) {
							echo '<div role="listitem" class="section-menu__element">';
							tf_product_card( $tf_product );
							echo '</div>';
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>

		<?php endif; ?>

		<?php tf_infos_box(); ?>

	</main>
</div>

<?php
get_footer();
