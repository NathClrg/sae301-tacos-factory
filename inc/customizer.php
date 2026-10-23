<?php
/**
 * Personnaliser > Tacos Factory : infos du restaurant et créneaux de retrait.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Déclare la section et les réglages.
 *
 * @param WP_Customize_Manager $wp_customize Gestionnaire.
 */
function tf_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'tf_restaurant',
		array(
			'title'       => __( 'Tacos Factory — Restaurant', 'tacos-factory' ),
			'description' => __( 'Infos affichées sur le site et calcul des créneaux de retrait.', 'tacos-factory' ),
			'priority'    => 30,
		)
	);

	$fields = array(
		'nom'             => array( __( 'Nom du point de retrait', 'tacos-factory' ), 'text', '' ),
		'adresse'         => array( __( 'Adresse', 'tacos-factory' ), 'text', '' ),
		'telephone'       => array( __( 'Téléphone', 'tacos-factory' ), 'text', '' ),
		'horaires'        => array( __( 'Horaires (texte affiché)', 'tacos-factory' ), 'text', '' ),
		'jours'           => array( __( 'Jours d’ouverture', 'tacos-factory' ), 'text', __( 'Numéros séparés par des virgules : 1 = lundi … 7 = dimanche.', 'tacos-factory' ) ),
		'ouverture'       => array( __( 'Ouverture (HH:MM)', 'tacos-factory' ), 'text', '' ),
		'dernier_retrait' => array( __( 'Dernier retrait (HH:MM)', 'tacos-factory' ), 'text', '' ),
		'delai'           => array( __( 'Délai de préparation (min)', 'tacos-factory' ), 'number', '' ),
		'pas'             => array( __( 'Intervalle entre créneaux (min)', 'tacos-factory' ), 'number', '' ),
		'accroche_footer' => array( __( 'Texte du pied de page', 'tacos-factory' ), 'textarea', '' ),
	);

	$defaults = tf_defaults();

	foreach ( $fields as $key => $field ) {
		$wp_customize->add_setting(
			'tf_' . $key,
			array(
				'default'           => $defaults[ $key ],
				'sanitize_callback' => 'number' === $field[1] ? 'absint' : ( 'textarea' === $field[1] ? 'sanitize_textarea_field' : 'sanitize_text_field' ),
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'tf_' . $key,
			array(
				'label'       => $field[0],
				'description' => $field[2],
				'section'     => 'tf_restaurant',
				'type'        => $field[1],
			)
		);
	}
}
add_action( 'customize_register', 'tf_customize_register' );
