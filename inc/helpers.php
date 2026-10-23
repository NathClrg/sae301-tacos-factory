<?php
/**
 * Fonctions utilitaires partagées par les templates.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valeurs par défaut des réglages du restaurant (Personnaliser > Tacos Factory).
 *
 * @return array
 */
function tf_defaults() {
	return array(
		'nom'             => 'Tacos Factory Limoges',
		'adresse'         => '44 Av. du Maréchal de Lattre de Tassigny, 87000 Limoges',
		'horaires'        => 'Mar – Dim · 18h30 – 23h00',
		'jours'           => '2,3,4,5,6,7',
		'ouverture'       => '18:30',
		'dernier_retrait' => '22:30',
		'delai'           => 20,
		'pas'             => 15,
		'telephone'       => '09 88 47 24 57',
		'accroche_footer' => 'Tacos généreux, burgers gourmands, wraps savoureux. À Limoges depuis 2020.',
	);
}

/**
 * Lit un réglage du restaurant.
 *
 * @param string $key Clé (voir tf_defaults()).
 * @return mixed
 */
function tf_opt( $key ) {
	$defaults = tf_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$value    = get_theme_mod( 'tf_' . $key, $default );
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Lit un champ SCF (ou une méta si SCF est désactivé).
 *
 * @param string   $name    Nom du champ.
 * @param int|bool $post_id ID du contenu.
 * @param mixed    $default Valeur de repli.
 * @return mixed
 */
function tf_field( $name, $post_id = false, $default = '' ) {
	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $name, $post_id );
	} else {
		$value = get_post_meta( $post_id ? $post_id : get_the_ID(), $name, true );
	}
	if ( null === $value || false === $value || '' === $value || array() === $value ) {
		return $default;
	}
	return $value;
}

/**
 * Icônes (tracés Lucide, licence ISC) en SVG inline — couleur via currentColor.
 *
 * @param string $name  Nom de l'icône.
 * @param int    $size  Taille en px.
 * @param string $class Classes supplémentaires.
 * @return string
 */
function tf_icon( $name, $size = 24, $class = '' ) {
	$paths = array(
		'plus'          => '<path d="M5 12h14"/><path d="M12 5v14"/>',
		'minus'         => '<path d="M5 12h14"/>',
		'check'         => '<path d="M20 6 9 17l-5-5"/>',
		'x'             => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'menu'          => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
		'arrow-left'    => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'shopping-bag'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		'pencil'        => '<path d="M21.17 6.81a1 1 0 0 0-3.98-3.98L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/><path d="m15 5 4 4"/>',
		'trash'         => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
		'store'         => '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
		'info'          => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'lock'          => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'shield-check'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'credit-card'   => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
		'hamburger'     => '<path d="M12 16H4a2 2 0 1 1 0-4h16a2 2 0 1 1 0 4h-4.25"/><path d="M5 12a2 2 0 0 1-2-2 9 7 0 0 1 18 0 2 2 0 0 1-2 2"/><path d="M5 16a2 2 0 0 0-2 2 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 2 2 0 0 0-2-2"/><path d="m6.67 12 6.13 4.6a2 2 0 0 0 2.8-.4l3.15-4.2"/>',
		'fries'         => '<path d="M5.5 10h13L17 21H7z"/><path d="M8 10V4.5"/><path d="M11 10V3"/><path d="M14 10V4"/><path d="M17 10V6"/><path d="M7.6 14.5a4.4 4.4 0 0 0 8.8 0"/>',
		'cup-soda'      => '<path d="m6 8 1.75 12.28a2 2 0 0 0 2 1.72h4.54a2 2 0 0 0 2-1.72L18 8"/><path d="M5 8h14"/><path d="M7 15a6.47 6.47 0 0 1 5 0 6.47 6.47 0 0 0 5 0"/><path d="m12 8 1-6h2"/>',
		'ice-cream'     => '<path d="m7 11 4.08 10.35a1 1 0 0 0 1.84 0L17 11"/><path d="M17 7A5 5 0 0 0 7 7"/><path d="M17 7a2 2 0 0 1 0 4H7a2 2 0 0 1 0-4"/>',
		'utensils'      => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
		'map-pin'       => '<path d="M20 10c0 4.99-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
		'leaf'          => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
		'alert'         => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
		'receipt'       => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8"/><path d="M16 12H8"/><path d="M13 16H8"/>',
		'chef-hat'      => '<path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/>',
		'navigation'    => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
		'phone'         => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'tag'           => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5"/>',
		'truck'         => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
		'star'          => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
		'user'          => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="icone icone--%1$s %2$s" width="%3$d" height="%3$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%4$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		absint( $size ),
		$paths[ $name ] // Tracés statiques définis ci-dessus.
	);
}

/**
 * Logo : logo du Customizer, sinon assets/img/logo.png, sinon logotype texte.
 *
 * @return string
 */
function tf_logo() {
	if ( has_custom_logo() ) {
		return get_custom_logo();
	}

	$file = '/assets/img/logo.png';
	if ( file_exists( TF_DIR . $file ) ) {
		return sprintf(
			'<a href="%1$s" class="custom-logo-link" rel="home"><img src="%2$s" class="custom-logo" alt="%3$s" width="112" height="132"></a>',
			esc_url( home_url( '/' ) ),
			esc_url( TF_URI . $file ),
			esc_attr( get_bloginfo( 'name' ) )
		);
	}

	return sprintf(
		'<a href="%s" class="logo-texte" rel="home"><span class="logo-texte__accent">Tacos</span> Factory</a>',
		esc_url( home_url( '/' ) )
	);
}

/**
 * Met le dernier mot d'un titre en rouge (« Tacos <span>Double</span> »).
 *
 * @param string $title Titre brut.
 * @return string HTML échappé.
 */
function tf_split_title( $title ) {
	$title = trim( wp_strip_all_tags( $title ) );
	$pos   = strrpos( $title, ' ' );
	if ( false === $pos ) {
		return esc_html( $title );
	}
	return esc_html( substr( $title, 0, $pos ) ) . ' <span class="accent">' . esc_html( substr( $title, $pos + 1 ) ) . '</span>';
}

/**
 * URL de la page « La carte » (template page-templates/template-carte.php).
 *
 * @return string
 */
function tf_get_carte_url() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}

	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => 'page-templates/template-carte.php', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	if ( $pages ) {
		$url = get_permalink( $pages[0] );
	} elseif ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'shop' );
	} else {
		$url = home_url( '/' );
	}

	return $url;
}

/**
 * Lien retour (« ← Retour à la carte »).
 *
 * @param string $label Libellé.
 * @param string $url   Destination.
 */
function tf_back_link( $label, $url ) {
	printf(
		'<a class="retour" href="%1$s">%2$s<span class="retour__texte">%3$s</span></a>',
		esc_url( $url ),
		tf_icon( 'arrow-left', 16 ), // phpcs:ignore WordPress.Security.EscapeOutput
		esc_html( $label )
	);
}

/**
 * Encart « Prêt en ~20 min » (bas de la carte).
 */
function tf_infos_box() {
	?>
	<aside class="infos">
		<span class="infos__icone"><?php echo tf_icon( 'clock', 20 ); // phpcs:ignore ?></span>
		<div class="infos__texte">
			<p class="infos__titre">
				<?php
				/* translators: %d : délai en minutes. */
				printf( esc_html__( 'Prêt en ~%d min', 'tacos-factory' ), absint( tf_opt( 'delai' ) ) );
				?>
			</p>
			<p class="infos__details">
				<?php
				/* translators: 1 : adresse, 2 : horaires. */
				printf( esc_html__( 'Retrait au %1$s · %2$s', 'tacos-factory' ), esc_html( tf_opt( 'adresse' ) ), esc_html( tf_opt( 'horaires' ) ) );
				?>
			</p>
		</div>
	</aside>
	<?php
}
