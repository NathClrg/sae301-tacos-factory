<?php
/**
 * Options de composition (taille, viandes, sauces, suppléments, boisson…)
 * saisies avec SCF sur chaque produit, sans plugin d'add-ons.
 *
 * Les prix sont toujours recalculés côté serveur à partir de la configuration
 * du produit : les valeurs envoyées par le formulaire ne sont que des index.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Configuration normalisée des groupes d'options d'un produit.
 *
 * @param int $product_id Produit.
 * @return array
 */
function tf_get_product_options( $product_id ) {
	static $cache = array();
	$product_id   = absint( $product_id );

	if ( isset( $cache[ $product_id ] ) ) {
		return $cache[ $product_id ];
	}

	$rows   = function_exists( 'get_field' ) ? get_field( 'tf_options', $product_id ) : array();
	$groups = array();

	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$choices = array();
			foreach ( (array) ( isset( $row['choix'] ) ? $row['choix'] : array() ) as $choice ) {
				$label = isset( $choice['label'] ) ? trim( (string) $choice['label'] ) : '';
				if ( '' === $label ) {
					continue;
				}
				$choices[] = array(
					'label'  => $label,
					'prix'   => round( (float) str_replace( ',', '.', (string) ( isset( $choice['prix'] ) ? $choice['prix'] : 0 ) ), 2 ),
					'defaut' => ! empty( $choice['defaut'] ),
				);
			}
			if ( ! $choices ) {
				continue;
			}

			$type      = ( isset( $row['type'] ) && 'checkbox' === $row['type'] ) ? 'checkbox' : 'radio';
			$required  = ! empty( $row['obligatoire'] );
			$min       = absint( isset( $row['min'] ) ? $row['min'] : 0 );
			$max       = 'radio' === $type ? 1 : absint( isset( $row['max'] ) ? $row['max'] : 0 );
			$affichage = isset( $row['affichage'] ) && in_array( $row['affichage'], array( 'supplement', 'inclus', 'absolu' ), true ) ? $row['affichage'] : 'supplement';

			if ( $required && $min < 1 ) {
				$min = 1;
			}
			if ( $max && $min > $max ) {
				$min = $max;
			}

			$groups[] = array(
				'titre'     => trim( (string) ( isset( $row['titre'] ) ? $row['titre'] : '' ) ),
				'aide'      => trim( (string) ( isset( $row['aide'] ) ? $row['aide'] : '' ) ),
				'prefixe'   => trim( (string) ( isset( $row['prefixe'] ) ? $row['prefixe'] : '' ) ),
				'type'      => $type,
				'required'  => $required,
				'min'       => $min,
				'max'       => $max,
				'affichage' => $affichage,
				'choix'     => $choices,
			);
		}
	}

	$cache[ $product_id ] = apply_filters( 'tf_product_options', $groups, $product_id );
	return $cache[ $product_id ];
}

/**
 * Prix affiché à droite d'un choix.
 *
 * @param array $group  Groupe.
 * @param array $choice Choix.
 * @param float $base   Prix de base du produit.
 * @return string HTML.
 */
function tf_option_price_label( $group, $choice, $base ) {
	if ( 'absolu' === $group['affichage'] ) {
		return wc_price( $base + $choice['prix'] );
	}
	if ( $choice['prix'] > 0 ) {
		return '+' . wc_price( $choice['prix'] );
	}
	if ( $choice['prix'] < 0 ) {
		return '−' . wc_price( abs( $choice['prix'] ) );
	}
	return 'inclus' === $group['affichage'] ? esc_html__( 'Inclus', 'tacos-factory' ) : '';
}

/**
 * Index des choix cochés par défaut dans un groupe.
 *
 * @param array $group Groupe.
 * @return int[]
 */
function tf_option_defaults( $group ) {
	$selected = array();
	foreach ( $group['choix'] as $j => $choice ) {
		if ( $choice['defaut'] ) {
			$selected[] = $j;
		}
	}
	if ( 'radio' === $group['type'] ) {
		$selected = array_slice( $selected, 0, 1 );
	} elseif ( $group['max'] ) {
		$selected = array_slice( $selected, 0, $group['max'] );
	}
	return $selected;
}

/**
 * Prix initial (base + choix par défaut) pour le bouton « Ajouter · 13,99 € ».
 *
 * @param WC_Product $product Produit.
 * @return float
 */
function tf_product_initial_price( $product ) {
	$total = (float) wc_get_price_to_display( $product );
	foreach ( tf_get_product_options( $product->get_id() ) as $group ) {
		foreach ( tf_option_defaults( $group ) as $j ) {
			$total += $group['choix'][ $j ]['prix'];
		}
	}
	return $total;
}

/**
 * Produits de la même famille (Tacos Simple / Double / Triple), du moins cher au plus cher.
 *
 * @param WC_Product $product Produit.
 * @return WC_Product[] Vide s'il n'y a pas d'autre taille.
 */
function tf_size_siblings( $product ) {
	$family = tf_field( 'tf_famille', $product->get_id(), '' );
	if ( '' === $family ) {
		return array();
	}
	$ids      = get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => 10,
			'fields'      => 'ids',
			'meta_key'    => 'tf_famille', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $family, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$products = array_values( array_filter( array_map( 'wc_get_product', $ids ) ) );
	usort(
		$products,
		static function ( $a, $b ) {
			return (float) $a->get_price() <=> (float) $b->get_price();
		}
	);
	return count( $products ) > 1 ? $products : array();
}

/**
 * Groupe « Taille » : chaque taille est un produit, choisir une taille ouvre sa fiche.
 *
 * @param WC_Product $product Produit affiché.
 */
function tf_render_size_choice( $product ) {
	$siblings = tf_size_siblings( $product );
	if ( ! $siblings ) {
		return;
	}
	?>
	<fieldset class="groupe-options groupe-options--taille">
		<legend class="screen-reader-text"><?php esc_html_e( 'Taille', 'tacos-factory' ); ?></legend>
		<div class="groupe-options__entete" aria-hidden="true">
			<div>
				<p class="groupe-options__titre"><?php esc_html_e( 'Taille', 'tacos-factory' ); ?></p>
				<p class="groupe-options__aide"><?php esc_html_e( 'Choisissez 1 option', 'tacos-factory' ); ?></p>
			</div>
			<span class="badge badge--obligatoire groupe-options__badge"><?php esc_html_e( 'Obligatoire', 'tacos-factory' ); ?></span>
		</div>
		<?php foreach ( $siblings as $sibling ) : ?>
			<?php $current = $sibling->get_id() === $product->get_id(); ?>
			<label class="option<?php echo $current ? ' option--cochee' : ''; ?>">
				<input class="option__input" type="radio" name="tf_taille" value="<?php echo esc_url( $sibling->get_permalink() ); ?>" <?php checked( $current ); ?>>
				<span class="option__libelle"><?php echo esc_html( tf_field( 'tf_taille', $sibling->get_id(), $sibling->get_name() ) ); ?></span>
				<span class="option__prix"><?php echo wp_kses_post( wc_price( wc_get_price_to_display( $sibling ) ) ); ?></span>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<?php
}

/**
 * Affiche les groupes d'options et le champ « Une précision ? ».
 *
 * @param WC_Product $product Produit.
 */
function tf_render_product_options( $product ) {
	$groups = tf_get_product_options( $product->get_id() );
	$base   = (float) wc_get_price_to_display( $product );

	tf_render_size_choice( $product );

	foreach ( $groups as $i => $group ) :
		$defaults = tf_option_defaults( $group );
		$tag      = $group['required'] ? __( 'Obligatoire', 'tacos-factory' ) : __( 'Optionnel', 'tacos-factory' );
		?>
		<fieldset class="groupe-options" data-type="<?php echo esc_attr( $group['type'] ); ?>" data-min="<?php echo absint( $group['min'] ); ?>" data-max="<?php echo absint( $group['max'] ); ?>">
			<legend class="screen-reader-text"><?php echo esc_html( $group['titre'] ); ?></legend>
			<div class="groupe-options__entete" aria-hidden="true">
				<div>
					<p class="groupe-options__titre"><?php echo esc_html( $group['titre'] ); ?></p>
					<?php if ( $group['aide'] ) : ?>
						<p class="groupe-options__aide"><?php echo esc_html( $group['aide'] ); ?></p>
					<?php endif; ?>
				</div>
				<span class="badge groupe-options__badge <?php echo $group['required'] ? 'badge--obligatoire' : 'badge--optionnel'; ?>" data-label="<?php echo esc_attr( $tag ); ?>"><?php echo esc_html( $tag ); ?></span>
			</div>

			<?php
			foreach ( $group['choix'] as $j => $choice ) :
				$checked = in_array( $j, $defaults, true );
				$name    = 'radio' === $group['type'] ? "tf_opt[{$i}]" : "tf_opt[{$i}][]";
				?>
				<label class="option<?php echo $checked ? ' option--cochee' : ''; ?>">
					<input class="option__input" type="<?php echo esc_attr( $group['type'] ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo absint( $j ); ?>" data-price="<?php echo esc_attr( $choice['prix'] ); ?>" <?php checked( $checked ); ?>>
					<span class="option__libelle"><?php echo esc_html( $choice['label'] ); ?></span>
					<span class="option__prix"><?php echo wp_kses_post( tf_option_price_label( $group, $choice, $base ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	endforeach;

	if ( tf_field( 'tf_precision', $product->get_id(), true ) ) :
		?>
		<div class="groupe-options groupe-options--precision">
			<div class="groupe-options__entete">
				<div>
					<label class="groupe-options__titre" for="tf_precision"><?php esc_html_e( 'Une précision ?', 'tacos-factory' ); ?></label>
					<p class="groupe-options__aide"><?php esc_html_e( 'Allergies, cuisson, sans oignons…', 'tacos-factory' ); ?></p>
				</div>
				<span class="badge badge--optionnel groupe-options__badge"><?php esc_html_e( 'Optionnel', 'tacos-factory' ); ?></span>
			</div>
			<textarea class="groupe-options__saisie" id="tf_precision" name="tf_precision" rows="3" maxlength="200" placeholder="<?php esc_attr_e( 'Ex : bien grillé, sauce à part…', 'tacos-factory' ); ?>"></textarea>
		</div>
		<?php
	endif;
}

/**
 * Lit et valide les options envoyées pour un produit.
 *
 * @param int   $product_id Produit.
 * @param array $posted     Données (déjà unslashées).
 * @return array { groups, supplement, precision, errors }
 */
function tf_parse_product_options( $product_id, $posted ) {
	$config = tf_get_product_options( $product_id );
	$raw    = isset( $posted['tf_opt'] ) && is_array( $posted['tf_opt'] ) ? $posted['tf_opt'] : array();
	$result = array(
		'groups'     => array(),
		'supplement' => 0.0,
		'precision'  => '',
		'errors'     => array(),
	);

	foreach ( $config as $i => $group ) {
		$value    = isset( $raw[ $i ] ) ? $raw[ $i ] : null;
		$selected = array();

		if ( 'checkbox' === $group['type'] ) {
			foreach ( (array) $value as $v ) {
				$v = absint( $v );
				if ( isset( $group['choix'][ $v ] ) ) {
					$selected[ $v ] = true;
				}
			}
			$selected = array_keys( $selected );
		} elseif ( null !== $value && '' !== $value && ! is_array( $value ) && isset( $group['choix'][ absint( $value ) ] ) ) {
			$selected = array( absint( $value ) );
		}

		$count = count( $selected );

		if ( $count < $group['min'] ) {
			$result['errors'][] = 1 === $group['min']
				/* translators: %s : groupe d'options. */
				? sprintf( __( 'Choisissez une option pour « %s ».', 'tacos-factory' ), $group['titre'] )
				/* translators: 1 : nombre, 2 : groupe d'options. */
				: sprintf( __( 'Choisissez au moins %1$d options pour « %2$s ».', 'tacos-factory' ), $group['min'], $group['titre'] );
		}
		if ( $group['max'] && $count > $group['max'] ) {
			/* translators: 1 : nombre, 2 : groupe d'options. */
			$result['errors'][] = sprintf( __( '%1$d choix maximum pour « %2$s ».', 'tacos-factory' ), $group['max'], $group['titre'] );
		}

		if ( $count ) {
			$chosen = array();
			$sum    = 0.0;
			foreach ( $selected as $j ) {
				$chosen[] = array(
					'label' => $group['choix'][ $j ]['label'],
					'prix'  => $group['choix'][ $j ]['prix'],
				);
				$sum     += $group['choix'][ $j ]['prix'];
			}
			$result['groups'][]    = array(
				'titre'     => $group['titre'],
				'prefixe'   => $group['prefixe'],
				'affichage' => $group['affichage'],
				'choix'     => $chosen,
			);
			$result['supplement'] += $sum;
		}
	}

	if ( ! empty( $posted['tf_precision'] ) && is_string( $posted['tf_precision'] ) ) {
		$result['precision'] = mb_substr( sanitize_textarea_field( $posted['tf_precision'] ), 0, 200 );
	}

	$result['supplement'] = round( $result['supplement'], 2 );
	return $result;
}

/**
 * Validation à l'ajout au panier.
 *
 * @param bool $passed     Validation en cours.
 * @param int  $product_id Produit.
 * @return bool
 */
function tf_validate_add_to_cart( $passed, $product_id ) {
	$config = tf_get_product_options( $product_id );
	if ( ! $config ) {
		return $passed;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- formulaire d'ajout au panier WooCommerce (sans nonce).
	if ( empty( $_POST['tf_options_form'] ) ) {
		// Ajout rapide (AJAX, lien ?add-to-cart=) : impossible si des choix sont requis.
		foreach ( $config as $group ) {
			if ( $group['min'] > 0 ) {
				wc_add_notice( __( 'Composez votre commande avant de l’ajouter au panier.', 'tacos-factory' ), 'error' );
				return false;
			}
		}
		return $passed;
	}

	$parsed = tf_parse_product_options( $product_id, wp_unslash( $_POST ) );
	// phpcs:enable

	foreach ( $parsed['errors'] as $error ) {
		wc_add_notice( $error, 'error' );
	}
	return $parsed['errors'] ? false : $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'tf_validate_add_to_cart', 10, 2 );

/**
 * Stocke les choix dans la ligne du panier.
 *
 * @param array $cart_item_data Données.
 * @param int   $product_id     Produit.
 * @return array
 */
function tf_add_cart_item_data( $cart_item_data, $product_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['tf_options_form'] ) || ( ! tf_get_product_options( $product_id ) && empty( $_POST['tf_precision'] ) ) ) {
		return $cart_item_data;
	}
	$parsed = tf_parse_product_options( $product_id, wp_unslash( $_POST ) );
	// phpcs:enable

	if ( $parsed['groups'] ) {
		$cart_item_data['tf_options']    = $parsed['groups'];
		$cart_item_data['tf_supplement'] = $parsed['supplement'];
	}
	if ( '' !== $parsed['precision'] ) {
		$cart_item_data['tf_precision'] = $parsed['precision'];
	}
	return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'tf_add_cart_item_data', 10, 2 );

/**
 * Prix de la ligne = prix du produit + suppléments (recalcul idempotent).
 *
 * @param WC_Cart $cart Panier.
 */
function tf_apply_supplements( $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['tf_supplement'] ) ) {
			continue;
		}
		$fresh = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
		if ( $fresh ) {
			$item['data']->set_price( (float) $fresh->get_price( 'edit' ) + (float) $item['tf_supplement'] );
		}
	}
}
add_action( 'woocommerce_before_calculate_totals', 'tf_apply_supplements', 20 );

/**
 * Affichage des choix (récapitulatif de commande, mini-panier…).
 *
 * @param array $item_data Données affichées.
 * @param array $cart_item Ligne.
 * @return array
 */
function tf_display_cart_item_data( $item_data, $cart_item ) {
	if ( ! empty( $cart_item['tf_options'] ) ) {
		foreach ( $cart_item['tf_options'] as $group ) {
			$item_data[] = array(
				'key'   => $group['titre'],
				'value' => implode( ', ', wp_list_pluck( $group['choix'], 'label' ) ),
			);
		}
	}
	if ( ! empty( $cart_item['tf_precision'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Précision', 'tacos-factory' ),
			'value' => $cart_item['tf_precision'],
		);
	}
	return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'tf_display_cart_item_data', 10, 2 );

/**
 * Copie les choix dans la commande (admin, e-mails, compte client).
 *
 * @param WC_Order_Item_Product $item          Ligne de commande.
 * @param string                $cart_item_key Clé.
 * @param array                 $values        Ligne du panier.
 */
function tf_order_line_item_meta( $item, $cart_item_key, $values ) {
	if ( ! empty( $values['tf_options'] ) ) {
		foreach ( $values['tf_options'] as $group ) {
			$labels = array();
			foreach ( $group['choix'] as $choice ) {
				$labels[] = $choice['prix'] > 0 ? sprintf( '%s (+%s)', $choice['label'], wp_strip_all_tags( wc_price( $choice['prix'] ) ) ) : $choice['label'];
			}
			$item->add_meta_data( $group['titre'], html_entity_decode( implode( ', ', $labels ), ENT_QUOTES, 'UTF-8' ) );
		}
	}
	if ( ! empty( $values['tf_precision'] ) ) {
		$item->add_meta_data( __( 'Précision', 'tacos-factory' ), $values['tf_precision'] );
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'tf_order_line_item_meta', 10, 3 );
