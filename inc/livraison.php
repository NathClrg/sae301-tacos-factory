<?php
/**
 * Livraison à domicile, en plus du retrait au restaurant.
 *
 * Réglages dans WooCommerce > Réglages > Expédition : la zone « Limoges »
 * propose « Retrait au restaurant » (gratuit) et « Livraison à domicile »,
 * dont le tarif dépend du poids total de la commande (3 tranches).
 *
 * Le client choisit Retrait ou Livraison dans le panier (mode gardé en
 * session) : seul le tarif de ce mode est proposé à WooCommerce. Pour une
 * livraison, il donne son adresse, un jour et une plage horaire.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Mode d'expédition « Livraison selon le poids »
 * ---------------------------------------------------------------------- */

if ( class_exists( 'WC_Shipping_Method' ) ) {

	/**
	 * Livraison à domicile, tarif selon le poids.
	 */
	class TF_Shipping_Livraison extends WC_Shipping_Method {

		/**
		 * Réglages.
		 *
		 * @param int $instance_id Instance dans la zone.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'tf_livraison';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Livraison selon le poids', 'tacos-factory' );
			$this->method_description = __( 'Livraison à domicile. Le tarif dépend du poids total de la commande, en 3 tranches.', 'tacos-factory' );
			$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

			$this->instance_form_fields = array(
				'title'   => array(
					'title'   => __( 'Nom', 'tacos-factory' ),
					'type'    => 'text',
					'default' => __( 'Livraison à domicile', 'tacos-factory' ),
				),
				'poids_1' => array(
					'title'   => __( 'Tranche 1 : jusqu’à (kg)', 'tacos-factory' ),
					'type'    => 'decimal',
					'default' => '1',
				),
				'prix_1'  => array(
					'title'   => __( 'Tranche 1 : tarif', 'tacos-factory' ),
					'type'    => 'price',
					'default' => '2.90',
				),
				'poids_2' => array(
					'title'   => __( 'Tranche 2 : jusqu’à (kg)', 'tacos-factory' ),
					'type'    => 'decimal',
					'default' => '3',
				),
				'prix_2'  => array(
					'title'   => __( 'Tranche 2 : tarif', 'tacos-factory' ),
					'type'    => 'price',
					'default' => '4.90',
				),
				'prix_3'  => array(
					'title'       => __( 'Tranche 3 : tarif au-delà', 'tacos-factory' ),
					'type'        => 'price',
					'default'     => '6.90',
					'description' => __( 'Au-delà du poids de la tranche 2.', 'tacos-factory' ),
				),
			);
			$this->title = $this->get_option( 'title' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Tranches : array( poids max en kg (null pour la dernière), tarif ).
		 *
		 * @return array[]
		 */
		public function get_tranches() {
			return array(
				array( (float) $this->get_option( 'poids_1' ), (float) $this->get_option( 'prix_1' ) ),
				array( (float) $this->get_option( 'poids_2' ), (float) $this->get_option( 'prix_2' ) ),
				array( null, (float) $this->get_option( 'prix_3' ) ),
			);
		}

		/**
		 * Tarif pour un poids.
		 *
		 * @param float $weight Poids en kg.
		 * @return float
		 */
		public function cost_for_weight( $weight ) {
			foreach ( $this->get_tranches() as $tranche ) {
				if ( null === $tranche[0] || $weight <= $tranche[0] ) {
					return $tranche[1];
				}
			}
			return 0.0;
		}

		/**
		 * Tarif du colis, selon le poids de ses articles.
		 *
		 * @param array $package Colis.
		 */
		public function calculate_shipping( $package = array() ) {
			$weight = 0.0; // Dans l'unité de la boutique.
			foreach ( $package['contents'] as $item ) {
				if ( $item['data']->needs_shipping() ) {
					$weight += (float) $item['data']->get_weight() * $item['quantity'];
				}
			}

			$this->add_rate(
				array(
					'label'     => $this->title,
					'cost'      => $this->cost_for_weight( wc_get_weight( $weight, 'kg' ) ),
					'package'   => $package,
					'meta_data' => array( __( 'Poids', 'tacos-factory' ) => wc_format_weight( $weight ) ),
				)
			);
		}
	}
}

/**
 * Déclare le mode d'expédition.
 *
 * @param array $methods Modes.
 * @return array
 */
function tf_register_livraison_method( $methods ) {
	$methods['tf_livraison'] = 'TF_Shipping_Livraison';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'tf_register_livraison_method' );

/* -------------------------------------------------------------------------
 * Zone de livraison
 * ---------------------------------------------------------------------- */

/**
 * Livraison active d'une zone.
 *
 * @param WC_Shipping_Zone $zone Zone.
 * @return TF_Shipping_Livraison|null
 */
function tf_zone_livraison_method( $zone ) {
	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		if ( 'tf_livraison' === $method->id ) {
			return $method;
		}
	}
	return null;
}

/**
 * Première zone (dans l'ordre de WooCommerce) qui propose la livraison.
 *
 * @return WC_Shipping_Zone|null
 */
function tf_livraison_zone() {
	static $zone = false;
	if ( false !== $zone ) {
		return $zone;
	}
	$zone = null;
	foreach ( WC_Shipping_Zones::get_zones() as $data ) {
		$candidate = WC_Shipping_Zones::get_zone( $data['id'] );
		if ( $candidate && tf_zone_livraison_method( $candidate ) ) {
			$zone = $candidate;
			break;
		}
	}
	return $zone;
}

/**
 * Mode « Livraison » de cette zone, ou null si la livraison n'est pas proposée.
 *
 * @return TF_Shipping_Livraison|null
 */
function tf_livraison_method() {
	$zone = tf_livraison_zone();
	return $zone ? tf_zone_livraison_method( $zone ) : null;
}

/**
 * Livre-t-on ce code postal ? (sa zone propose la livraison)
 *
 * @param string $postcode Code postal.
 * @return bool
 */
function tf_livraison_covers( $postcode ) {
	$zone = WC_Shipping_Zones::get_zone_matching_package(
		array(
			'destination' => array(
				'country'  => WC()->countries->get_base_country(),
				'state'    => '',
				'postcode' => $postcode,
			),
		)
	);
	return (bool) tf_zone_livraison_method( $zone );
}

/**
 * Codes postaux de la zone de livraison.
 *
 * @return string[]
 */
function tf_livraison_postcodes() {
	$zone  = tf_livraison_zone();
	$codes = array();
	foreach ( $zone ? $zone->get_zone_locations() : array() as $location ) {
		if ( 'postcode' === $location->type ) {
			$codes[] = $location->code;
		}
	}
	return $codes;
}

/**
 * « Jusqu’à 1 kg : 2.90€ · jusqu’à 3 kg : 4.90€ · au-delà : 6.90€ ».
 *
 * @param TF_Shipping_Livraison $method Livraison.
 * @return string
 */
function tf_livraison_tarifs( $method ) {
	$parts = array();
	foreach ( $method->get_tranches() as $tranche ) {
		$price   = html_entity_decode( wp_strip_all_tags( wc_price( $tranche[1] ) ), ENT_QUOTES, 'UTF-8' );
		$parts[] = null === $tranche[0]
			/* translators: %s : tarif. */
			? sprintf( __( 'au-delà : %s', 'tacos-factory' ), $price )
			/* translators: 1 : poids, 2 : tarif. */
			: sprintf( __( 'jusqu’à %1$s : %2$s', 'tacos-factory' ), wc_format_weight( wc_get_weight( $tranche[0], get_option( 'woocommerce_weight_unit' ), 'kg' ) ), $price );
	}
	return ucfirst( implode( ' · ', $parts ) );
}

/* -------------------------------------------------------------------------
 * Choix du client (en session)
 * ---------------------------------------------------------------------- */

/**
 * Mode de réception choisi dans le panier : « retrait » (par défaut) ou « livraison ».
 *
 * @return string
 */
function tf_reception_mode() {
	$mode = WC()->session ? WC()->session->get( 'tf_reception' ) : '';
	return ( 'livraison' === $mode && tf_livraison_method() ) ? 'livraison' : 'retrait';
}

/**
 * Adresse et plage de livraison (en session). Par défaut, l'adresse de
 * livraison enregistrée sur le compte du client.
 *
 * @return array { adresse, complement, code_postal, ville, plage: 'Y-m-d H:i' }
 */
function tf_delivery_choice() {
	$choice   = WC()->session ? WC()->session->get( 'tf_livraison' ) : null;
	$customer = WC()->customer;
	return wp_parse_args(
		is_array( $choice ) ? $choice : array(),
		array(
			'adresse'     => $customer ? $customer->get_shipping_address_1() : '',
			'complement'  => $customer ? $customer->get_shipping_address_2() : '',
			'code_postal' => $customer ? $customer->get_shipping_postcode() : '',
			'ville'       => $customer ? $customer->get_shipping_city() : '',
			'plage'       => '',
		)
	);
}

/* -------------------------------------------------------------------------
 * Colis et tarifs
 * ---------------------------------------------------------------------- */

/**
 * Destination des colis : le restaurant pour un retrait, l'adresse du client
 * pour une livraison (le restaurant tant qu'elle n'est pas saisie, pour
 * afficher un tarif). Le mode fait partie du colis : WooCommerce recalcule
 * donc les tarifs quand il change.
 *
 * @param array $packages Colis.
 * @return array
 */
function tf_shipping_packages( $packages ) {
	$mode      = tf_reception_mode();
	$choice    = tf_delivery_choice();
	$countries = WC()->countries;
	$home      = 'livraison' === $mode && '' !== $choice['code_postal'];

	foreach ( $packages as $i => $package ) {
		$packages[ $i ]['destination']  = array(
			'country'   => $countries->get_base_country(),
			'state'     => $countries->get_base_state(),
			'postcode'  => $home ? $choice['code_postal'] : $countries->get_base_postcode(),
			'city'      => $home ? $choice['ville'] : $countries->get_base_city(),
			'address'   => $home ? $choice['adresse'] : $countries->get_base_address(),
			'address_1' => $home ? $choice['adresse'] : $countries->get_base_address(),
			'address_2' => $home ? $choice['complement'] : $countries->get_base_address_2(),
		);
		$packages[ $i ]['tf_reception'] = $mode;
	}
	return $packages;
}
add_filter( 'woocommerce_cart_shipping_packages', 'tf_shipping_packages' );

/**
 * Un seul tarif proposé : celui du mode choisi dans le panier.
 *
 * @param WC_Shipping_Rate[] $rates   Tarifs.
 * @param array              $package Colis.
 * @return WC_Shipping_Rate[]
 */
function tf_package_rates( $rates, $package ) {
	$wanted = ( isset( $package['tf_reception'] ) && 'livraison' === $package['tf_reception'] ) ? 'tf_livraison' : 'local_pickup';
	foreach ( $rates as $id => $rate ) {
		if ( $wanted !== $rate->get_method_id() ) {
			unset( $rates[ $id ] );
		}
	}
	return $rates;
}
add_filter( 'woocommerce_package_rates', 'tf_package_rates', 10, 2 );

/**
 * Tarif de livraison du panier, ou null (retrait, ou adresse non livrée).
 *
 * @return WC_Shipping_Rate|null
 */
function tf_cart_delivery_rate() {
	if ( 'livraison' !== tf_reception_mode() || ! WC()->cart ) {
		return null;
	}
	$packages = WC()->shipping()->get_packages();
	$chosen   = (array) WC()->session->get( 'chosen_shipping_methods' );
	$rate     = isset( $packages[0], $chosen[0], $packages[0]['rates'][ $chosen[0] ] ) ? $packages[0]['rates'][ $chosen[0] ] : null;
	return ( $rate && 'tf_livraison' === $rate->get_method_id() ) ? $rate : null;
}

/**
 * Commande en livraison ?
 *
 * @param WC_Order $order Commande.
 * @return bool
 */
function tf_order_is_delivery( $order ) {
	return $order instanceof WC_Order && $order->has_shipping_method( 'tf_livraison' );
}

/* -------------------------------------------------------------------------
 * Jours et plages de livraison
 * ---------------------------------------------------------------------- */

/**
 * Jours proposés (les TF_LIVRAISON_JOURS prochains jours d'ouverture) et
 * leurs plages de TF_LIVRAISON_PLAGE minutes, de l'ouverture au dernier
 * retrait, en laissant le temps de préparer puis de livrer.
 *
 * @return array[] { date: 'Y-m-d', plages: DateTimeImmutable[] (débuts) }
 */
function tf_delivery_schedule() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$now      = new DateTimeImmutable( 'now', wp_timezone() );
	$open     = tf_parse_hm( tf_opt( 'ouverture' ), array( 18, 30 ) );
	$last     = tf_parse_hm( tf_opt( 'dernier_retrait' ), array( 22, 30 ) );
	$earliest = $now->modify( '+' . ( absint( tf_opt( 'delai' ) ) + TF_LIVRAISON_TRAJET ) . ' minutes' );
	$cache    = array();

	for ( $d = 0; $d < 14 && count( $cache ) < TF_LIVRAISON_JOURS; $d++ ) {
		$day = $now->modify( "+{$d} days" );
		if ( ! in_array( (int) $day->format( 'N' ), tf_opening_days(), true ) ) {
			continue;
		}

		$start = $day->setTime( $open[0], $open[1] );
		$end   = $day->setTime( $last[0], $last[1] );
		if ( $end < $start ) {
			$end = $end->modify( '+1 day' ); // Dernier retrait après minuit.
		}

		$plages = array();
		for ( $t = $start; $t <= $end; $t = $t->modify( '+' . TF_LIVRAISON_PLAGE . ' minutes' ) ) {
			if ( $t >= $earliest ) {
				$plages[] = $t;
			}
		}
		if ( $plages ) {
			$cache[] = array(
				'date'   => $day->format( 'Y-m-d' ),
				'plages' => $plages,
			);
		}
	}
	return $cache;
}

/**
 * Début de la plage choisie (null si elle n'est plus proposée).
 *
 * @param array $choice Choix (tf_delivery_choice()).
 * @return DateTimeImmutable|null
 */
function tf_delivery_slot_from_choice( $choice ) {
	foreach ( tf_delivery_schedule() as $day ) {
		foreach ( $day['plages'] as $plage ) {
			if ( $plage->format( 'Y-m-d H:i' ) === $choice['plage'] ) {
				return $plage;
			}
		}
	}
	return null;
}

/**
 * « 19h00 – 19h30 ».
 *
 * @param DateTimeImmutable $start Début de la plage.
 * @return string
 */
function tf_plage_label( $start ) {
	return sprintf( '%1$s – %2$s', tf_hm( $start ), tf_hm( $start->modify( '+' . TF_LIVRAISON_PLAGE . ' minutes' ) ) );
}

/**
 * « Demain · 19h00 – 19h30 ».
 *
 * @param array $choice Choix.
 * @return string
 */
function tf_delivery_summary( $choice ) {
	$start = tf_delivery_slot_from_choice( $choice );
	return $start ? sprintf( '%1$s · %2$s', tf_day_label( $start ), tf_plage_label( $start ) ) : '';
}

/**
 * « 12 rue Jean Jaurès, Bât. B, 87000 Limoges ».
 *
 * @param array $choice Choix.
 * @return string
 */
function tf_delivery_address_line( $choice ) {
	return implode( ', ', array_filter( array( $choice['adresse'], $choice['complement'], trim( $choice['code_postal'] . ' ' . $choice['ville'] ) ) ) );
}

/* -------------------------------------------------------------------------
 * Commande
 * ---------------------------------------------------------------------- */

/**
 * L'adresse est saisie dans le panier, pas sur la page de paiement : on la
 * donne à WooCommerce, qui l'enregistre dans la commande. Pour un retrait,
 * seul le pays est nécessaire (WooCommerce le demande pour toute expédition).
 *
 * @param array $data Données envoyées.
 * @return array
 */
function tf_checkout_posted_address( $data ) {
	$data['shipping_country'] = WC()->countries->get_base_country();

	if ( 'livraison' === tf_reception_mode() ) {
		$choice = tf_delivery_choice();
		$data   = array_merge(
			$data,
			array(
				'shipping_first_name' => isset( $data['billing_first_name'] ) ? $data['billing_first_name'] : '',
				'shipping_phone'      => isset( $data['billing_phone'] ) ? $data['billing_phone'] : '',
				'shipping_address_1'  => $choice['adresse'],
				'shipping_address_2'  => $choice['complement'],
				'shipping_postcode'   => $choice['code_postal'],
				'shipping_city'       => $choice['ville'],
			)
		);
	}
	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'tf_checkout_posted_address' );
