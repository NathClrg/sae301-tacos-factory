<?php
/**
 * Tunnel Click & Collect (maquettes 05 → 08) :
 *   1. Panier   : articles, réception (retrait et créneau, ou livraison :
 *                 adresse, jour et plage), coordonnées, code promo
 *   2. Paiement : moyen de paiement + récapitulatif
 *   3. Confirmation (woocommerce/checkout/thankyou.php)
 *
 * La réception et les coordonnées sont saisies dans le panier, gardées en
 * session, puis reprises par la page de paiement (champs pré-remplis et masqués).
 * La livraison elle-même (tarif, zone, plages) est dans inc/livraison.php.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Étapes Panier → Paiement → Confirmation
 * ---------------------------------------------------------------------- */

/**
 * Indicateur d'étapes.
 *
 * @param int $current Étape en cours (1 à 3).
 */
function tf_steps( $current ) {
	$steps = array(
		__( 'Panier', 'tacos-factory' ),
		__( 'Paiement', 'tacos-factory' ),
		__( 'Confirmation', 'tacos-factory' ),
	);
	echo '<ol class="etapes">';
	foreach ( $steps as $index => $label ) {
		$n     = $index + 1;
		$state = $n < $current ? 'etapes__etape--faite' : ( $n === $current ? 'etapes__etape--en-cours' : '' );
		printf(
			'<li class="etapes__etape %1$s"%2$s><span class="etapes__pastille">%3$s</span><span class="etapes__libelle">%4$s</span></li>',
			esc_attr( $state ),
			$n === $current ? ' aria-current="step"' : '',
			$n < $current ? tf_icon( 'check', 14 ) : absint( $n ), // phpcs:ignore
			esc_html( $label )
		);
	}
	echo '</ol>';
}

/**
 * En-tête de la page de paiement.
 */
function tf_checkout_head() {
	tf_back_link( __( 'Retour au panier', 'tacos-factory' ), wc_get_cart_url() );
	tf_steps( 2 );
	?>
	<header class="page-titre">
		<p class="surtitre"><?php esc_html_e( 'Étape 2 sur 3', 'tacos-factory' ); ?></p>
		<h1 class="page-titre__titre"><?php esc_html_e( 'Paiement', 'tacos-factory' ); ?></h1>
		<p class="page-titre__texte"><?php esc_html_e( 'Choisissez votre moyen de paiement.', 'tacos-factory' ); ?></p>
	</header>
	<?php
}
add_action( 'woocommerce_before_checkout_form', 'tf_checkout_head', 1 );

/* -------------------------------------------------------------------------
 * Champs : prénom + téléphone (+ e-mail facultatif pour le reçu)
 * ---------------------------------------------------------------------- */

/**
 * Réduit les champs de facturation.
 *
 * @param array $fields Champs.
 * @return array
 */
function tf_checkout_fields( $fields ) {
	$keep = array( 'billing_first_name', 'billing_phone', 'billing_email' );
	foreach ( array_keys( $fields['billing'] ) as $key ) {
		if ( ! in_array( $key, $keep, true ) ) {
			unset( $fields['billing'][ $key ] );
		}
	}

	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name'] = array_merge(
			$fields['billing']['billing_first_name'],
			array(
				'label'    => __( 'Prénom', 'tacos-factory' ),
				'class'    => array( 'form-row-wide' ),
				'priority' => 10,
			)
		);
	}
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone'] = array_merge(
			$fields['billing']['billing_phone'],
			array(
				'label'    => __( 'Téléphone', 'tacos-factory' ),
				'required' => true,
				'class'    => array( 'form-row-wide' ),
				'priority' => 20,
			)
		);
	}
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email'] = array_merge(
			$fields['billing']['billing_email'],
			array(
				'label'    => __( 'E-mail', 'tacos-factory' ),
				'required' => false,
				'class'    => array( 'form-row-wide' ),
				'priority' => 30,
			)
		);
	}

	unset( $fields['shipping'] );
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'tf_checkout_fields', 20 );
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

/* -------------------------------------------------------------------------
 * Créneaux de retrait
 * ---------------------------------------------------------------------- */

/**
 * « 18:30 » → array( 18, 30 ).
 *
 * @param string $value    Heure.
 * @param array  $fallback Valeur de repli.
 * @return int[]
 */
function tf_parse_hm( $value, $fallback ) {
	if ( preg_match( '/^(\d{1,2})[:hH](\d{2})$/', trim( (string) $value ), $m ) ) {
		return array( min( 23, (int) $m[1] ), min( 59, (int) $m[2] ) );
	}
	return $fallback;
}

/**
 * Jours d'ouverture (1 = lundi … 7 = dimanche).
 *
 * @return int[]
 */
function tf_opening_days() {
	$days = array_filter( array_map( 'absint', explode( ',', (string) tf_opt( 'jours' ) ) ) );
	return $days ? array_values( $days ) : array( 1, 2, 3, 4, 5, 6, 7 );
}

/**
 * Prochain service disponible et ses créneaux.
 *
 * @return array { offset, asap (DateTimeImmutable), slots (DateTimeImmutable[]), end }
 */
function tf_pickup_schedule() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$now   = new DateTimeImmutable( 'now', wp_timezone() );
	$delay = absint( tf_opt( 'delai' ) );
	$step  = max( 5, absint( tf_opt( 'pas' ) ) );
	$days  = tf_opening_days();
	$open  = tf_parse_hm( tf_opt( 'ouverture' ), array( 18, 30 ) );
	$last  = tf_parse_hm( tf_opt( 'dernier_retrait' ), array( 22, 30 ) );

	$earliest = $now->modify( "+{$delay} minutes" );

	for ( $d = 0; $d < 8; $d++ ) {
		$day = $now->modify( "+{$d} days" );
		if ( ! in_array( (int) $day->format( 'N' ), $days, true ) ) {
			continue;
		}

		$start = $day->setTime( $open[0], $open[1] );
		$end   = $day->setTime( $last[0], $last[1] );
		if ( $end < $start ) {
			$end = $end->modify( '+1 day' ); // Dernier retrait après minuit.
		}
		if ( $start < $earliest ) {
			$start = $earliest;
		}

		// Arrondi au créneau suivant.
		$start = $start->setTime( (int) $start->format( 'H' ), (int) $start->format( 'i' ), 0 );
		$mod   = (int) $start->format( 'i' ) % $step;
		if ( $mod ) {
			$start = $start->modify( '+' . ( $step - $mod ) . ' minutes' );
		}
		if ( $start > $end ) {
			continue;
		}

		$slots = array();
		for ( $t = $start; $t <= $end; $t = $t->modify( "+{$step} minutes" ) ) {
			$slots[] = $t;
		}

		$cache = array(
			'offset' => $d,
			'asap'   => $start,
			'slots'  => $slots,
			'end'    => $end,
		);
		return $cache;
	}

	$cache = array(
		'offset' => 0,
		'asap'   => null,
		'slots'  => array(),
		'end'    => null,
	);
	return $cache;
}

/**
 * « Ce soir », « Demain », « Jeudi »…
 *
 * @param DateTimeImmutable $time Moment.
 * @return string
 */
function tf_day_label( $time ) {
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$days  = (int) $today->diff( $time->setTime( 0, 0 ) )->format( '%r%a' );

	if ( 0 === $days ) {
		return (int) $time->format( 'H' ) >= 17 ? __( 'Ce soir', 'tacos-factory' ) : __( 'Aujourd’hui', 'tacos-factory' );
	}
	if ( 1 === $days ) {
		return __( 'Demain', 'tacos-factory' );
	}
	return ucfirst( wp_date( 'l', $time->getTimestamp() ) );
}

/**
 * « 19h45 ».
 *
 * @param DateTimeImmutable $time Moment.
 * @return string
 */
function tf_hm( $time ) {
	return wp_date( 'G\hi', $time->getTimestamp() );
}

/**
 * « Prête vers 19h45 · environ 20 min » si le restaurant est ouvert,
 * sinon « Ce soir, dès 18h30 ».
 *
 * @param DateTimeImmutable $asap Premier créneau.
 * @return string
 */
function tf_asap_label( $asap ) {
	$minutes = (int) ceil( ( $asap->getTimestamp() - time() ) / MINUTE_IN_SECONDS );
	if ( $minutes <= absint( tf_opt( 'delai' ) ) + absint( tf_opt( 'pas' ) ) ) {
		/* translators: 1 : heure, 2 : délai en minutes. */
		return sprintf( __( 'Prête vers %1$s · environ %2$d min', 'tacos-factory' ), tf_hm( $asap ), absint( tf_opt( 'delai' ) ) );
	}
	/* translators: 1 : jour, 2 : heure. */
	return sprintf( __( '%1$s, dès %2$s', 'tacos-factory' ), tf_day_label( $asap ), tf_hm( $asap ) );
}

/**
 * Créneau choisi (en session), avec ses valeurs par défaut.
 *
 * @return array { mode: asap|slot, heure: 'Y-m-d H:i' }
 */
function tf_pickup_choice() {
	$choice = WC()->session ? WC()->session->get( 'tf_retrait' ) : null;
	return wp_parse_args( is_array( $choice ) ? $choice : array(), array( 'mode' => 'asap', 'heure' => '' ) );
}

/**
 * Moment de retrait d'un choix (null si le créneau n'existe plus).
 *
 * @param array $choice Choix (tf_pickup_choice()).
 * @return DateTimeImmutable|null
 */
function tf_pickup_time_from_choice( $choice ) {
	$schedule = tf_pickup_schedule();
	if ( ! $schedule['slots'] ) {
		return null;
	}
	if ( 'slot' !== $choice['mode'] ) {
		return $schedule['asap'];
	}
	foreach ( $schedule['slots'] as $slot ) {
		if ( $slot->format( 'Y-m-d H:i' ) === $choice['heure'] ) {
			return $slot;
		}
	}
	return null;
}

/**
 * « Dès que possible · ~19h45 » ou « Ce soir à 20h15 ».
 *
 * @param array $choice Choix.
 * @return string
 */
function tf_pickup_summary( $choice ) {
	$time = tf_pickup_time_from_choice( $choice );
	if ( ! $time ) {
		return '';
	}
	return 'slot' === $choice['mode']
		/* translators: 1 : jour, 2 : heure. */
		? sprintf( __( '%1$s à %2$s', 'tacos-factory' ), tf_day_label( $time ), tf_hm( $time ) )
		/* translators: %s : heure. */
		: sprintf( __( 'Dès que possible · ~%s', 'tacos-factory' ), tf_hm( $time ) );
}

/* -------------------------------------------------------------------------
 * Étape 1 : le panier (réception, créneau, coordonnées)
 * ---------------------------------------------------------------------- */

/**
 * Valeur d'un champ du panier : saisie envoyée, sinon client WooCommerce.
 *
 * @param string $name    Nom du champ.
 * @param string $default Valeur enregistrée.
 * @return string
 */
function tf_cart_field_value( $name, $default ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- simple réaffichage de la saisie.
	return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : (string) $default;
}

/**
 * Blocs « Réception » et « Vos coordonnées » du panier (maquette 05).
 */
function tf_cart_pickup_sections() {
	$schedule = tf_pickup_schedule();
	$choice   = tf_pickup_choice();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- réaffichage de la saisie.
	$mode  = isset( $_POST['tf_retrait_mode'] ) ? sanitize_key( wp_unslash( $_POST['tf_retrait_mode'] ) ) : $choice['mode'];
	$heure = isset( $_POST['tf_retrait_heure'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_retrait_heure'] ) ) : $choice['heure'];
	// phpcs:enable
	$customer = WC()->customer;

	// Livraison : la saisie est déjà en session (tf_cart_proceed()).
	$reception = tf_reception_mode();
	$livraison = tf_livraison_method();
	$delivery  = tf_delivery_choice();
	$days      = tf_delivery_schedule();
	$plage     = tf_delivery_slot_from_choice( $delivery );
	$plage     = $plage ? $plage : ( $days ? $days[0]['plages'][0] : null );
	$jour      = $plage ? $plage->format( 'Y-m-d' ) : '';
	$hors_zone = 'livraison' === $reception && preg_match( '/^\d{5}$/', $delivery['code_postal'] ) && ! tf_livraison_covers( $delivery['code_postal'] );
	$codes     = tf_livraison_postcodes();
	?>
	<section class="section-tunnel retrait">
		<h2 class="titre-section"><?php esc_html_e( 'Réception', 'tacos-factory' ); ?></h2>

		<div class="reception" role="radiogroup" aria-label="<?php esc_attr_e( 'Mode de réception', 'tacos-factory' ); ?>">
			<label class="reception__choix<?php echo 'retrait' === $reception ? ' reception__choix--actif' : ''; ?>">
				<input class="reception__input" type="radio" name="tf_reception" value="retrait" <?php checked( 'retrait', $reception ); ?>>
				<?php echo tf_icon( 'store', 20 ); // phpcs:ignore ?>
				<strong class="reception__titre"><?php esc_html_e( 'Retrait', 'tacos-factory' ); ?></strong>
				<span class="reception__aide"><?php esc_html_e( 'Gratuit · Click & Collect', 'tacos-factory' ); ?></span>
			</label>
			<?php if ( $livraison ) : ?>
				<label class="reception__choix<?php echo 'livraison' === $reception ? ' reception__choix--actif' : ''; ?>">
					<input class="reception__input" type="radio" name="tf_reception" value="livraison" <?php checked( 'livraison', $reception ); ?>>
					<?php echo tf_icon( 'truck', 20 ); // phpcs:ignore ?>
					<strong class="reception__titre"><?php esc_html_e( 'Livraison', 'tacos-factory' ); ?></strong>
					<span class="reception__aide">
						<?php
						/* translators: %s : tarif de la première tranche de poids. */
						printf( esc_html__( 'Dès %s · selon le poids', 'tacos-factory' ), esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $livraison->get_tranches()[0][1] ) ), ENT_QUOTES, 'UTF-8' ) ) );
						?>
					</span>
				</label>
			<?php else : ?>
				<p class="reception__choix" aria-disabled="true" title="<?php esc_attr_e( 'Bientôt disponible', 'tacos-factory' ); ?>">
					<?php echo tf_icon( 'truck', 20 ); // phpcs:ignore ?>
					<strong class="reception__titre"><?php esc_html_e( 'Livraison', 'tacos-factory' ); ?></strong>
					<span class="reception__aide"><?php esc_html_e( 'Bientôt disponible', 'tacos-factory' ); ?></span>
				</p>
			<?php endif; ?>
		</div>

		<div class="reception__panneau" data-reception="retrait"<?php echo 'retrait' !== $reception ? ' hidden' : ''; ?>>
			<div class="magasin">
				<span class="magasin__icone"><?php echo tf_icon( 'store', 20 ); // phpcs:ignore ?></span>
				<div>
					<p class="magasin__nom"><?php echo esc_html( tf_opt( 'nom' ) ); ?></p>
					<p class="magasin__adresse"><?php echo esc_html( tf_opt( 'adresse' ) ); ?></p>
				</div>
			</div>

			<?php if ( ! $schedule['slots'] ) : ?>
				<p class="retrait__ferme"><?php esc_html_e( 'Aucun créneau de retrait n’est disponible pour le moment.', 'tacos-factory' ); ?></p>
			<?php else : ?>
				<label class="choix<?php echo 'slot' !== $mode ? ' choix--actif' : ''; ?>">
					<input class="choix__input" type="radio" name="tf_retrait_mode" value="asap" <?php checked( 'slot' !== $mode ); ?>>
					<span class="choix__texte">
						<strong class="choix__titre"><?php esc_html_e( 'Dès que possible', 'tacos-factory' ); ?></strong>
						<small class="choix__aide"><?php echo esc_html( tf_asap_label( $schedule['asap'] ) ); ?></small>
					</span>
					<?php echo tf_icon( 'clock', 20, 'choix__icone' ); // phpcs:ignore ?>
				</label>

				<div class="choix choix--creneaux<?php echo 'slot' === $mode ? ' choix--actif' : ''; ?>">
					<label class="choix__principal">
						<input class="choix__input" type="radio" name="tf_retrait_mode" value="slot" <?php checked( 'slot', $mode ); ?>>
						<span class="choix__texte">
							<strong class="choix__titre"><?php esc_html_e( 'Programmer un horaire', 'tacos-factory' ); ?></strong>
							<small class="choix__aide">
								<?php
								/* translators: 1 : jour, 2 : heure du dernier retrait. */
								printf( esc_html__( '%1$s, jusqu’à %2$s', 'tacos-factory' ), esc_html( tf_day_label( $schedule['asap'] ) ), esc_html( tf_hm( $schedule['end'] ) ) );
								?>
							</small>
						</span>
					</label>
					<div class="creneaux" role="radiogroup" aria-label="<?php esc_attr_e( 'Horaires de retrait', 'tacos-factory' ); ?>">
						<?php foreach ( $schedule['slots'] as $slot ) : ?>
							<?php $value = $slot->format( 'Y-m-d H:i' ); ?>
							<label class="creneaux__creneau">
								<input class="creneaux__input" type="radio" name="tf_retrait_heure" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $heure ); ?>>
								<span class="creneaux__heure"><?php echo esc_html( $slot->format( 'H:i' ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $livraison ) : ?>
			<div class="reception__panneau livraison" data-reception="livraison"<?php echo 'livraison' !== $reception ? ' hidden' : ''; ?>>
				<p class="champ">
					<label class="champ__label" for="tf_livraison_adresse"><?php esc_html_e( 'Adresse', 'tacos-factory' ); ?></label>
					<input class="champ__saisie" type="text" id="tf_livraison_adresse" name="tf_livraison_adresse" autocomplete="address-line1" placeholder="12 rue Jean Jaurès" value="<?php echo esc_attr( $delivery['adresse'] ); ?>">
				</p>
				<p class="champ">
					<label class="champ__label" for="tf_livraison_complement"><?php esc_html_e( 'Complément (facultatif)', 'tacos-factory' ); ?></label>
					<input class="champ__saisie" type="text" id="tf_livraison_complement" name="tf_livraison_complement" autocomplete="address-line2" placeholder="<?php esc_attr_e( 'Bât. B, 2e étage, digicode…', 'tacos-factory' ); ?>" value="<?php echo esc_attr( $delivery['complement'] ); ?>">
				</p>
				<div class="livraison__ville">
					<p class="champ">
						<label class="champ__label" for="tf_livraison_code_postal"><?php esc_html_e( 'Code postal', 'tacos-factory' ); ?></label>
						<input class="champ__saisie" type="text" id="tf_livraison_code_postal" name="tf_livraison_code_postal" inputmode="numeric" maxlength="5" autocomplete="postal-code" placeholder="87000" value="<?php echo esc_attr( $delivery['code_postal'] ); ?>">
					</p>
					<p class="champ">
						<label class="champ__label" for="tf_livraison_ville"><?php esc_html_e( 'Ville', 'tacos-factory' ); ?></label>
						<input class="champ__saisie" type="text" id="tf_livraison_ville" name="tf_livraison_ville" autocomplete="address-level2" placeholder="Limoges" value="<?php echo esc_attr( $delivery['ville'] ); ?>">
					</p>
				</div>
				<?php if ( $hors_zone ) : ?>
					<p class="retrait__ferme">
						<?php
						/* translators: %s : code postal. */
						printf( esc_html__( 'Nous ne livrons pas encore au %s : choisissez le retrait au restaurant.', 'tacos-factory' ), esc_html( $delivery['code_postal'] ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $codes ) : ?>
					<p class="note">
						<?php echo tf_icon( 'map-pin', 14 ); // phpcs:ignore ?>
						<?php
						/* translators: 1 : zone, 2 : codes postaux. */
						printf( esc_html__( 'Livraison à %1$s (%2$s).', 'tacos-factory' ), esc_html( tf_livraison_zone()->get_zone_name() ), esc_html( implode( ', ', $codes ) ) );
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! $days ) : ?>
					<p class="retrait__ferme"><?php esc_html_e( 'Aucune plage de livraison n’est disponible pour le moment.', 'tacos-factory' ); ?></p>
				<?php else : ?>
					<p class="champ__label champ__label--fonce" id="tf-livraison-jour"><?php esc_html_e( 'Jour de livraison', 'tacos-factory' ); ?></p>
					<div class="creneaux" role="radiogroup" aria-labelledby="tf-livraison-jour">
						<?php foreach ( $days as $day ) : ?>
							<label class="creneaux__creneau">
								<input class="creneaux__input" type="radio" name="tf_livraison_jour" value="<?php echo esc_attr( $day['date'] ); ?>" <?php checked( $day['date'], $jour ); ?>>
								<span class="creneaux__heure"><?php echo esc_html( tf_day_label( $day['plages'][0] ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="champ__label champ__label--fonce" id="tf-livraison-plage"><?php esc_html_e( 'Plage horaire', 'tacos-factory' ); ?></p>
					<?php foreach ( $days as $day ) : ?>
						<div class="creneaux" role="radiogroup" aria-labelledby="tf-livraison-plage" data-jour="<?php echo esc_attr( $day['date'] ); ?>"<?php echo $day['date'] !== $jour ? ' hidden' : ''; ?>>
							<?php foreach ( $day['plages'] as $start ) : ?>
								<?php $value = $start->format( 'Y-m-d H:i' ); ?>
								<label class="creneaux__creneau">
									<input class="creneaux__input" type="radio" name="tf_livraison_plage" value="<?php echo esc_attr( $value ); ?>" <?php checked( $plage && $value === $plage->format( 'Y-m-d H:i' ) ); ?>>
									<span class="creneaux__heure"><?php echo esc_html( tf_plage_label( $start ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<p class="note note--claire"><?php echo tf_icon( 'info', 14 ); // phpcs:ignore ?><?php echo esc_html( tf_livraison_tarifs( $livraison ) ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<section class="section-tunnel coordonnees">
		<h2 class="titre-section"><?php esc_html_e( 'Vos coordonnées', 'tacos-factory' ); ?></h2>
		<p class="champ">
			<label class="champ__label" for="tf_prenom"><?php esc_html_e( 'Prénom', 'tacos-factory' ); ?></label>
			<input class="champ__saisie" type="text" id="tf_prenom" name="tf_prenom" autocomplete="given-name" placeholder="Lucas" value="<?php echo esc_attr( tf_cart_field_value( 'tf_prenom', $customer->get_billing_first_name() ) ); ?>">
		</p>
		<p class="champ">
			<label class="champ__label" for="tf_telephone"><?php esc_html_e( 'Téléphone', 'tacos-factory' ); ?></label>
			<input class="champ__saisie" type="tel" id="tf_telephone" name="tf_telephone" autocomplete="tel" placeholder="06 12 34 56 78" value="<?php echo esc_attr( tf_cart_field_value( 'tf_telephone', $customer->get_billing_phone() ) ); ?>">
		</p>
		<p class="champ">
			<label class="champ__label champ__label--fonce" for="tf_email"><?php esc_html_e( 'E-mail', 'tacos-factory' ); ?></label>
			<input class="champ__saisie" type="email" id="tf_email" name="tf_email" autocomplete="email" placeholder="lucas.martin@mail.fr" value="<?php echo esc_attr( tf_cart_field_value( 'tf_email', $customer->get_billing_email() ) ); ?>">
			<span class="champ__aide"><?php esc_html_e( 'Confirmation et facture envoyées ici.', 'tacos-factory' ); ?></span>
		</p>
		<p class="note"><?php echo tf_icon( 'info', 14 ); // phpcs:ignore ?><?php esc_html_e( 'On vous envoie un SMS dès que c’est prêt.', 'tacos-factory' ); ?></p>
	</section>
	<?php
}

/**
 * Ce qui manque à la réception choisie (créneau, ou adresse et plage de livraison).
 *
 * @return string[] Messages d'erreur.
 */
function tf_reception_errors() {
	$errors = array();

	if ( 'livraison' === tf_reception_mode() ) {
		$choice = tf_delivery_choice();
		if ( '' === $choice['adresse'] || '' === $choice['ville'] || ! preg_match( '/^\d{5}$/', $choice['code_postal'] ) ) {
			$errors[] = __( 'Indiquez votre adresse de livraison complète.', 'tacos-factory' );
		} elseif ( ! tf_livraison_covers( $choice['code_postal'] ) ) {
			/* translators: %s : code postal. */
			$errors[] = sprintf( __( 'Nous ne livrons pas encore au %s : choisissez le retrait au restaurant.', 'tacos-factory' ), $choice['code_postal'] );
		}
		if ( ! tf_delivery_schedule() ) {
			$errors[] = __( 'Aucune plage de livraison n’est disponible pour le moment.', 'tacos-factory' );
		} elseif ( ! tf_delivery_slot_from_choice( $choice ) ) {
			$errors[] = __( 'Choisissez un jour et une plage horaire de livraison.', 'tacos-factory' );
		}
		return $errors;
	}

	if ( ! tf_pickup_schedule()['slots'] ) {
		$errors[] = __( 'Aucun créneau de retrait n’est disponible pour le moment.', 'tacos-factory' );
	} elseif ( ! tf_pickup_time_from_choice( tf_pickup_choice() ) ) {
		$errors[] = __( 'Choisissez un horaire de retrait.', 'tacos-factory' );
	}
	return $errors;
}

/**
 * Mise à jour du panier (AJAX de WooCommerce) ou « Passer au paiement » :
 * garde la réception et les coordonnées en session, pour que le total tienne
 * compte de la livraison. Pour « Passer au paiement », vérifie aussi la saisie,
 * puis WooCommerce enchaîne vers la page de paiement
 * (WC_Form_Handler::update_cart_action, priorité 20).
 */
function tf_cart_proceed() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessous.
	$proceed = ! empty( $_POST['proceed'] );
	if ( ( ! $proceed && empty( $_POST['update_cart'] ) ) || ! WC()->cart ) {
		return;
	}
	$nonce = isset( $_POST['woocommerce-cart-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-cart-nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
		return;
	}

	$first_name = isset( $_POST['tf_prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_prenom'] ) ) : '';
	$phone      = isset( $_POST['tf_telephone'] ) ? wc_sanitize_phone_number( wp_unslash( $_POST['tf_telephone'] ) ) : '';
	$email      = isset( $_POST['tf_email'] ) ? sanitize_email( wp_unslash( $_POST['tf_email'] ) ) : '';
	$reception  = ( isset( $_POST['tf_reception'] ) && 'livraison' === $_POST['tf_reception'] ) ? 'livraison' : 'retrait';
	$choice     = array(
		'mode'  => ( isset( $_POST['tf_retrait_mode'] ) && 'slot' === $_POST['tf_retrait_mode'] ) ? 'slot' : 'asap',
		'heure' => isset( $_POST['tf_retrait_heure'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_retrait_heure'] ) ) : '',
	);
	$delivery   = array();
	foreach ( array( 'adresse', 'complement', 'code_postal', 'ville', 'plage' ) as $key ) {
		$delivery[ $key ] = isset( $_POST[ 'tf_livraison_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'tf_livraison_' . $key ] ) ) : '';
	}
	$delivery['code_postal'] = preg_replace( '/\D/', '', $delivery['code_postal'] );
	$has_delivery            = isset( $_POST['tf_livraison_adresse'] ); // Le bloc Livraison est affiché.
	// phpcs:enable

	WC()->session->set( 'tf_reception', $reception );
	WC()->session->set( 'tf_retrait', $choice );
	if ( $has_delivery ) {
		WC()->session->set( 'tf_livraison', $delivery );
	}

	if ( ! $proceed ) {
		return; // Simple mise à jour : la saisie est vérifiée au passage au paiement.
	}

	$errors = tf_reception_errors();
	if ( '' === $first_name ) {
		$errors[] = __( 'Indiquez votre prénom.', 'tacos-factory' );
	}
	if ( '' === $phone || ! WC_Validation::is_phone( $phone ) ) {
		$errors[] = __( 'Indiquez un numéro de téléphone valide.', 'tacos-factory' );
	}
	if ( '' !== $email && ! is_email( $email ) ) {
		$errors[] = __( 'L’adresse e-mail n’est pas valide.', 'tacos-factory' );
	}

	WC()->customer->set_billing_first_name( $first_name );
	WC()->customer->set_billing_phone( $phone );
	WC()->customer->set_billing_email( $email );
	WC()->customer->save();

	if ( $errors ) {
		foreach ( $errors as $error ) {
			wc_add_notice( $error, 'error' );
		}
		unset( $_POST['proceed'] ); // WooCommerce reste sur le panier.
	}
}
add_action( 'wp_loaded', 'tf_cart_proceed', 15 );

/* -------------------------------------------------------------------------
 * Étape 2 : le paiement
 * ---------------------------------------------------------------------- */

/**
 * Pas de paiement sans réception (créneau, ou adresse et plage) ni coordonnées :
 * retour au panier.
 */
function tf_checkout_guard() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	$customer = WC()->customer;
	if ( tf_reception_errors() || ! $customer->get_billing_first_name() || ! $customer->get_billing_phone() ) {
		wc_add_notice(
			'livraison' === tf_reception_mode()
				? __( 'Indiquez votre adresse et votre plage de livraison, ainsi que vos coordonnées.', 'tacos-factory' )
				: __( 'Choisissez votre créneau de retrait et indiquez vos coordonnées.', 'tacos-factory' ),
			'notice'
		);
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
}
add_action( 'template_redirect', 'tf_checkout_guard' );

/**
 * Moyens de paiement avant le récapitulatif ; mention de sécurité après.
 */
function tf_checkout_layout() {
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	add_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 5 );
	add_action( 'woocommerce_checkout_order_review', 'tf_secure_payment_note', 15 );

	// Le code promo et la connexion se font avant (panier, compte).
	remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
}
add_action( 'init', 'tf_checkout_layout' );

/**
 * Coordonnées déjà saisies dans le panier : la page de paiement les masque.
 *
 * @param array $classes Classes du body.
 * @return array
 */
function tf_checkout_body_class( $classes ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() && WC()->customer && WC()->customer->get_billing_first_name() && WC()->customer->get_billing_phone() ) {
		$classes[] = 'coordonnees-ok';
	}
	return $classes;
}
add_filter( 'body_class', 'tf_checkout_body_class' );

/**
 * Le créneau (ou la plage de livraison) a-t-il expiré entre le panier et le paiement ?
 */
function tf_checkout_validate_pickup() {
	if ( ! tf_reception_errors() ) {
		return;
	}
	wc_add_notice(
		'livraison' === tf_reception_mode()
			? __( 'Cette livraison n’est plus possible (adresse ou plage horaire) : vérifiez-la dans le panier.', 'tacos-factory' )
			: __( 'Cet horaire de retrait n’est plus disponible : choisissez-en un autre dans le panier.', 'tacos-factory' ),
		'error'
	);
}
add_action( 'woocommerce_checkout_process', 'tf_checkout_validate_pickup' );

/**
 * Enregistre le créneau, ou la plage de livraison, dans la commande (compatible
 * HPOS). L'adresse de livraison est enregistrée par WooCommerce
 * (tf_checkout_posted_address()).
 *
 * @param WC_Order $order Commande.
 */
function tf_checkout_save_pickup( $order ) {
	if ( 'livraison' === tf_reception_mode() ) {
		$delivery = tf_delivery_choice();
		$start    = tf_delivery_slot_from_choice( $delivery );
		if ( $start ) {
			$order->update_meta_data( '_tf_livraison', tf_delivery_summary( $delivery ) );
			$order->update_meta_data( '_tf_livraison_at', $start->format( 'Y-m-d H:i' ) );
		}
		return;
	}

	$choice = tf_pickup_choice();
	$time   = tf_pickup_time_from_choice( $choice );
	if ( ! $time ) {
		return;
	}
	$order->update_meta_data( '_tf_retrait', tf_pickup_summary( $choice ) );
	$order->update_meta_data( '_tf_retrait_at', $time->format( 'Y-m-d H:i' ) );
}
add_action( 'woocommerce_checkout_create_order', 'tf_checkout_save_pickup' );

/**
 * Libellé du créneau d'une commande (ou de sa plage de livraison).
 *
 * @param WC_Order $order Commande.
 * @return string
 */
function tf_order_pickup_label( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}
	return (string) $order->get_meta( tf_order_is_delivery( $order ) ? '_tf_livraison' : '_tf_retrait' );
}

/**
 * Admin : créneau sous l'adresse de facturation.
 *
 * @param WC_Order $order Commande.
 */
function tf_admin_order_pickup( $order ) {
	$label = tf_order_pickup_label( $order );
	if ( $label ) {
		printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html( tf_order_is_delivery( $order ) ? __( 'Livraison :', 'tacos-factory' ) : __( 'Retrait :', 'tacos-factory' ) ), esc_html( $label ) );
	}
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'tf_admin_order_pickup' );

/**
 * E-mails : créneau de retrait.
 *
 * @param array    $fields        Champs.
 * @param bool     $sent_to_admin Pour l'admin.
 * @param WC_Order $order         Commande.
 * @return array
 */
function tf_email_pickup( $fields, $sent_to_admin, $order ) {
	$label = tf_order_pickup_label( $order );
	if ( $label ) {
		$fields['tf_retrait'] = array(
			'label' => tf_order_is_delivery( $order ) ? __( 'Livraison', 'tacos-factory' ) : __( 'Retrait', 'tacos-factory' ),
			'value' => $label,
		);
	}
	return $fields;
}
add_filter( 'woocommerce_email_order_meta_fields', 'tf_email_pickup', 10, 3 );

/**
 * Sous-titre et icône d'un moyen de paiement (maquette 07).
 *
 * @param WC_Payment_Gateway $gateway Moyen de paiement.
 * @return array { aide, icone }
 */
function tf_gateway_display( $gateway ) {
	if ( 'cod' === $gateway->id ) {
		return array(
			'aide'  => __( 'Espèces ou carte, au comptoir', 'tacos-factory' ),
			'icone' => tf_icon( 'store', 22 ),
		);
	}
	if ( 'tf_carte' === $gateway->id ) {
		return array(
			'aide'  => __( 'Paiement sécurisé en ligne', 'tacos-factory' ),
			'icone' => '<span class="visa">VISA</span>',
		);
	}
	return array(
		'aide'  => '',
		'icone' => $gateway->get_icon(),
	);
}

/**
 * Texte du bouton : « Payer 23.98€ ».
 *
 * @return string
 */
function tf_order_button_text() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return __( 'Payer', 'tacos-factory' );
	}
	$total = html_entity_decode( wp_strip_all_tags( wc_price( WC()->cart->get_total( 'edit' ) ) ), ENT_QUOTES, 'UTF-8' );
	/* translators: %s : total. */
	return sprintf( __( 'Payer %s', 'tacos-factory' ), $total );
}
add_filter( 'woocommerce_order_button_text', 'tf_order_button_text' );

/**
 * Bouton « Payer » aux classes du thème. L'id #place_order est gardé :
 * c'est lui que le JavaScript de WooCommerce utilise.
 *
 * @return string
 */
function tf_order_button_html() {
	$text = tf_order_button_text();
	return sprintf(
		'<div class="barre-action"><button type="submit" class="bouton bouton--primaire bouton--large" name="woocommerce_checkout_place_order" id="place_order" value="%1$s" data-value="%1$s">%2$s</button></div>',
		esc_attr( $text ),
		esc_html( $text )
	);
}
add_filter( 'woocommerce_order_button_html', 'tf_order_button_html' );

/**
 * « Paiement 100 % sécurisé · 3D Secure ».
 */
function tf_secure_payment_note() {
	printf(
		'<p class="securite">%1$s<span>%2$s</span></p>',
		tf_icon( 'shield-check', 16, 'securite__icone' ), // phpcs:ignore
		esc_html__( 'Paiement 100 % sécurisé · 3D Secure', 'tacos-factory' )
	);
}
