<?php
/**
 * Suivi de commande : numéro « TF-0427 », statut « Prête », commande en cours
 * (barre « Ma commande » en bas de l'écran et carte dans le menu).
 *
 * Étapes affichées au client :
 *   1. Reçue          → en attente (pending, on-hold)
 *   2. En préparation → en cours (processing)
 *   3. Prête          → statut « Prête » (à passer depuis l'admin), ou terminée
 *      En livraison / Livrée pour une commande livrée à domicile
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Numéro de commande « TF-0427 »
 * ---------------------------------------------------------------------- */

/**
 * @param string   $number Numéro.
 * @param WC_Order $order  Commande.
 * @return string
 */
function tf_order_number( $number, $order ) {
	return 'TF-' . str_pad( (string) $order->get_id(), 4, '0', STR_PAD_LEFT );
}
add_filter( 'woocommerce_order_number', 'tf_order_number', 10, 2 );

/* -------------------------------------------------------------------------
 * Statut « Prête » (la commande attend le client au comptoir)
 * ---------------------------------------------------------------------- */

/**
 * Enregistre le statut.
 */
function tf_register_ready_status() {
	register_post_status(
		'wc-prete',
		array(
			'label'                     => _x( 'Prête', 'Statut de commande', 'tacos-factory' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s : nombre de commandes. */
			'label_count'               => _n_noop( 'Prête <span class="count">(%s)</span>', 'Prêtes <span class="count">(%s)</span>', 'tacos-factory' ),
		)
	);
}
add_action( 'init', 'tf_register_ready_status' );

/**
 * Ajoute « Prête » juste après « En cours ».
 *
 * @param array $statuses Statuts.
 * @return array
 */
function tf_order_statuses( $statuses ) {
	$result = array();
	foreach ( $statuses as $key => $label ) {
		$result[ $key ] = $label;
		if ( 'wc-processing' === $key ) {
			$result['wc-prete'] = _x( 'Prête', 'Statut de commande', 'tacos-factory' );
		}
	}
	return $result;
}
add_filter( 'wc_order_statuses', 'tf_order_statuses' );

/**
 * Action groupée « Marquer comme prête » dans la liste des commandes.
 *
 * @param array $actions Actions.
 * @return array
 */
function tf_ready_bulk_action( $actions ) {
	$actions['mark_prete'] = __( 'Marquer comme prête', 'tacos-factory' );
	return $actions;
}
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'tf_ready_bulk_action' );
add_filter( 'bulk_actions-edit-shop_order', 'tf_ready_bulk_action' );

/* -------------------------------------------------------------------------
 * Commande en cours du visiteur
 * ---------------------------------------------------------------------- */

/**
 * Retient la commande sur la page de confirmation (cookie d'un jour),
 * pour l'afficher ensuite dans la barre « Ma commande » et le menu.
 */
function tf_remember_order() {
	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return;
	}
	$order_id = absint( get_query_var( 'order-received' ) );
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( $order && $key && hash_equals( $order->get_order_key(), $key ) ) {
		wc_setcookie( 'tf_commande', $order->get_id() . '|' . $order->get_order_key(), time() + DAY_IN_SECONDS );
		$_COOKIE['tf_commande'] = $order->get_id() . '|' . $order->get_order_key();
	}
}
add_action( 'template_redirect', 'tf_remember_order' );

/**
 * Le visiteur qui a passé la commande peut la revoir (même annulée) sans ressaisir son e-mail.
 *
 * @param bool     $required Vérification demandée.
 * @param WC_Order $order    Commande.
 * @return bool
 */
function tf_skip_email_verification( $required, $order ) {
	$remembered = tf_cookie_order();
	return ( $remembered && $order && $remembered->get_id() === $order->get_id() ) ? false : $required;
}
add_filter( 'woocommerce_order_email_verification_required', 'tf_skip_email_verification', 10, 2 );

/**
 * Commande retenue par le cookie, quel que soit son statut.
 *
 * @return WC_Order|null
 */
function tf_cookie_order() {
	if ( empty( $_COOKIE['tf_commande'] ) ) {
		return null;
	}
	$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['tf_commande'] ) ) );
	$order = count( $parts ) === 2 ? wc_get_order( absint( $parts[0] ) ) : false;
	return ( $order && hash_equals( $order->get_order_key(), $parts[1] ) ) ? $order : null;
}

/**
 * Commande en cours (pas encore retirée) : cookie, sinon dernière commande du
 * client connecté si elle est encore en cours. Une fois celle-ci annulée ou
 * retirée, on ne remonte pas à une commande plus ancienne.
 *
 * @return WC_Order|null
 */
function tf_current_order() {
	static $order = false;
	if ( false !== $order ) {
		return $order;
	}
	$order  = null;
	$active = array( 'pending', 'on-hold', 'processing', 'prete' );

	$candidate = tf_cookie_order();
	if ( $candidate && in_array( $candidate->get_status(), $active, true ) ) {
		$order = $candidate;
	}

	if ( ! $order && is_user_logged_in() ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'status'      => array_merge( $active, array( 'completed', 'cancelled', 'refunded' ) ),
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$order  = ( $orders && in_array( $orders[0]->get_status(), $active, true ) ) ? $orders[0] : null;
	}

	return $order;
}

/**
 * Étape de la commande (1 Reçue, 2 En préparation, 3 Prête).
 *
 * @param WC_Order $order Commande.
 * @return int
 */
function tf_order_step( $order ) {
	$status = $order->get_status();
	if ( in_array( $status, array( 'prete', 'completed' ), true ) ) {
		return 3;
	}
	return 'processing' === $status ? 2 : 1;
}

/* -------------------------------------------------------------------------
 * Annulation par le client
 * ---------------------------------------------------------------------- */

/**
 * Le client peut annuler tant que la commande n'est pas prête (par défaut,
 * WooCommerce ne le permet qu'en attente de paiement ou après un échec).
 *
 * @param array $statuses Statuts annulables.
 * @return array
 */
function tf_cancellable_statuses( $statuses ) {
	return array_unique( array_merge( $statuses, array( 'pending', 'on-hold', 'processing' ) ) );
}
add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'tf_cancellable_statuses' );

/**
 * Lien « Annuler ma commande », qui ramène sur la page de suivi.
 * Chaîne vide si la commande ne peut plus être annulée.
 *
 * @param WC_Order $order Commande.
 * @return string
 */
function tf_order_cancel_url( $order ) {
	$statuses = apply_filters( 'woocommerce_valid_order_statuses_for_cancel', array( 'pending', 'failed' ), $order ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
	if ( ! $order->has_status( $statuses ) || ! current_user_can( 'cancel_order', $order->get_id() ) ) {
		return '';
	}
	return $order->get_cancel_order_url( rawurlencode( $order->get_checkout_order_received_url() ) );
}

/**
 * Libellé d'une étape. Pour une livraison, l'étape 3 est « En livraison »
 * (statut « Prête »), puis « Livrée » (terminée).
 *
 * @param int           $step  Étape.
 * @param WC_Order|null $order Commande.
 * @return string
 */
function tf_step_label( $step, $order = null ) {
	$ready = __( 'Prête', 'tacos-factory' );
	if ( tf_order_is_delivery( $order ) ) {
		$ready = $order->has_status( 'completed' ) ? __( 'Livrée', 'tacos-factory' ) : __( 'En livraison', 'tacos-factory' );
	}
	$labels = array(
		1 => __( 'Reçue', 'tacos-factory' ),
		2 => __( 'En préparation', 'tacos-factory' ),
		3 => $ready,
	);
	return isset( $labels[ $step ] ) ? $labels[ $step ] : '';
}

/**
 * Heure de retrait prévue (début de la plage pour une livraison).
 *
 * @param WC_Order $order Commande.
 * @return DateTimeImmutable|null
 */
function tf_order_pickup_time( $order ) {
	$value = (string) $order->get_meta( tf_order_is_delivery( $order ) ? '_tf_livraison_at' : '_tf_retrait_at' );
	$time  = $value ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $value, wp_timezone() ) : false;
	return $time ? $time : null;
}

/**
 * Minutes restantes avant l'heure de retrait (0 si elle est passée).
 *
 * @param WC_Order $order Commande.
 * @return int
 */
function tf_order_minutes_left( $order ) {
	$time = tf_order_pickup_time( $order );
	if ( ! $time ) {
		return 0;
	}
	return max( 0, (int) ceil( ( $time->getTimestamp() - time() ) / MINUTE_IN_SECONDS ) );
}

/**
 * Durée lisible : « 18 min », « 1 h 05 », « 7 h ».
 *
 * @param int $minutes Minutes.
 * @return string
 */
function tf_duration( $minutes ) {
	if ( $minutes < 60 ) {
		/* translators: %d : minutes. */
		return sprintf( __( '%d min', 'tacos-factory' ), $minutes );
	}
	$hours = intdiv( $minutes, 60 );
	$rest  = $minutes % 60;
	return $rest ? sprintf( '%d h %02d', $hours, $rest ) : sprintf( '%d h', $hours );
}

/**
 * « En préparation · prête vers 19h45 » (« · livraison 19h00 – 19h30 »).
 *
 * @param WC_Order $order Commande.
 * @return string
 */
function tf_order_status_line( $order ) {
	$step     = tf_order_step( $order );
	$time     = tf_order_pickup_time( $order );
	$delivery = tf_order_is_delivery( $order );
	if ( 3 === $step || ! $time ) {
		return ( 3 === $step && ! $delivery ) ? __( 'Prête · à retirer au comptoir', 'tacos-factory' ) : tf_step_label( $step, $order );
	}
	if ( $delivery ) {
		/* translators: 1 : étape, 2 : plage horaire. */
		return sprintf( __( '%1$s · livraison %2$s', 'tacos-factory' ), tf_step_label( $step, $order ), tf_plage_label( $time ) );
	}
	/* translators: 1 : étape, 2 : heure. */
	return sprintf( __( '%1$s · prête vers %2$s', 'tacos-factory' ), tf_step_label( $step ), tf_hm( $time ) );
}

/**
 * Pages où la barre « Ma commande » s'affiche : pas dans le tunnel ni sur
 * la fiche produit (la barre « Ajouter » y est déjà en bas de l'écran).
 *
 * @return bool
 */
function tf_show_order_bar() {
	return tf_current_order() && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_product() ) );
}

/**
 * Barre « Ma commande » en bas de l'écran (maquette 09).
 */
function tf_order_bar() {
	if ( ! tf_show_order_bar() ) {
		return;
	}
	$order = tf_current_order();
	$step = tf_order_step( $order );
	?>
	<a class="suivi" href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>">
		<span class="suivi__icone"><?php echo tf_icon( 3 === $step ? ( tf_order_is_delivery( $order ) ? 'truck' : 'shopping-bag' ) : 'chef-hat', 22 ); // phpcs:ignore ?></span>
		<span class="suivi__texte">
			<span class="suivi__numero">
				<?php
				/* translators: %s : numéro de commande. */
				printf( esc_html__( 'Ma commande · %s', 'tacos-factory' ), esc_html( $order->get_order_number() ) );
				?>
			</span>
			<span class="suivi__statut"><?php echo esc_html( tf_order_status_line( $order ) ); ?></span>
			<span class="suivi__progression"><span class="suivi__avancee" style="width:<?php echo esc_attr( array( 1 => 25, 2 => 60, 3 => 100 )[ $step ] ); ?>%"></span></span>
		</span>
		<?php echo tf_icon( 'chevron-right', 20, 'suivi__chevron' ); // phpcs:ignore ?>
	</a>
	<?php
}
add_action( 'wp_footer', 'tf_order_bar', 5 );

/**
 * Body class : de la place pour la barre en bas de page.
 *
 * @param array $classes Classes.
 * @return array
 */
function tf_order_body_class( $classes ) {
	if ( tf_show_order_bar() ) {
		$classes[] = 'avec-suivi';
	}
	return $classes;
}
add_filter( 'body_class', 'tf_order_body_class' );
