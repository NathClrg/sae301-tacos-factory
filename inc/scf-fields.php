<?php
/**
 * Groupes de champs Secure Custom Fields (API compatible ACF), déclarés en PHP
 * pour être versionnés avec le thème. Ils apparaissent dans l'admin sans import.
 *
 * - Page d'accueil : contenu du hero.
 * - Produits : accroche, badge, temps, icône et groupes d'options (taille, viandes, sauces…).
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Déclare les groupes de champs.
 */
function tf_register_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	/* ---------- Accueil : hero ---------- */
	acf_add_local_field_group(
		array(
			'key'      => 'group_tf_hero',
			'title'    => __( 'Accueil — Hero', 'tacos-factory' ),
			'fields'   => array(
				array(
					'key'         => 'field_tf_hero_surtitre',
					'label'       => __( 'Surtitre', 'tacos-factory' ),
					'name'        => 'tf_hero_surtitre',
					'type'        => 'text',
					'placeholder' => 'Limoges · depuis 2020',
				),
				array(
					'key'          => 'field_tf_hero_ligne1',
					'label'        => __( 'Titre — ligne 1', 'tacos-factory' ),
					'name'         => 'tf_hero_ligne1',
					'type'         => 'text',
					'placeholder'  => 'Click',
					'wrapper'      => array( 'width' => 33 ),
				),
				array(
					'key'         => 'field_tf_hero_liaison',
					'label'       => __( 'Mot de liaison (rouge)', 'tacos-factory' ),
					'name'        => 'tf_hero_liaison',
					'type'        => 'text',
					'placeholder' => 'and',
					'wrapper'     => array( 'width' => 33 ),
				),
				array(
					'key'         => 'field_tf_hero_ligne2',
					'label'       => __( 'Titre — ligne 2 (contour)', 'tacos-factory' ),
					'name'        => 'tf_hero_ligne2',
					'type'        => 'text',
					'placeholder' => 'Collect',
					'wrapper'     => array( 'width' => 34 ),
				),
				array(
					'key'   => 'field_tf_hero_texte',
					'label' => __( 'Texte', 'tacos-factory' ),
					'name'  => 'tf_hero_texte',
					'type'  => 'textarea',
					'rows'  => 3,
				),
				array(
					'key'           => 'field_tf_hero_image',
					'label'         => __( 'Image de fond', 'tacos-factory' ),
					'name'          => 'tf_hero_image',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => __( 'Photo plein écran (2048 px de large conseillés). Un dégradé noir → rouge est appliqué par-dessus.', 'tacos-factory' ),
				),
				array(
					'key'           => 'field_tf_hero_bouton_1',
					'label'         => __( 'Bouton principal (rouge)', 'tacos-factory' ),
					'name'          => 'tf_hero_bouton_1',
					'type'          => 'link',
					'return_format' => 'array',
					'wrapper'       => array( 'width' => 50 ),
				),
				array(
					'key'           => 'field_tf_hero_bouton_2',
					'label'         => __( 'Bouton secondaire (contour)', 'tacos-factory' ),
					'name'          => 'tf_hero_bouton_2',
					'type'          => 'link',
					'return_format' => 'array',
					'wrapper'       => array( 'width' => 50 ),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'page_type',
						'operator' => '==',
						'value'    => 'front_page',
					),
				),
			),
			'position' => 'acf_after_title',
		)
	);

	/* ---------- Produits ---------- */
	acf_add_local_field_group(
		array(
			'key'      => 'group_tf_produit',
			'title'    => __( 'Tacos Factory — Fiche produit', 'tacos-factory' ),
			'fields'   => array(
				array(
					'key'          => 'field_tf_accroche',
					'label'        => __( 'Accroche (carte)', 'tacos-factory' ),
					'name'         => 'tf_accroche',
					'type'         => 'text',
					'placeholder'  => '-Frite et boisson incluse',
					'instructions' => __( 'Petite ligne grise sous le nom, sur la carte.', 'tacos-factory' ),
					'wrapper'      => array( 'width' => 50 ),
				),
				array(
					'key'         => 'field_tf_badge',
					'label'       => __( 'Badge sur la photo', 'tacos-factory' ),
					'name'        => 'tf_badge',
					'type'        => 'text',
					'placeholder' => 'Best-seller',
					'wrapper'     => array( 'width' => 50 ),
				),
				array(
					'key'         => 'field_tf_inclus',
					'label'       => __( 'Mention « inclus »', 'tacos-factory' ),
					'name'        => 'tf_inclus',
					'type'        => 'text',
					'placeholder' => 'Frites + boisson incluses',
					'wrapper'     => array( 'width' => 50 ),
				),
				array(
					'key'         => 'field_tf_temps',
					'label'       => __( 'Temps de préparation', 'tacos-factory' ),
					'name'        => 'tf_temps',
					'type'        => 'text',
					'placeholder' => '~15 min',
					'wrapper'     => array( 'width' => 50 ),
				),
				array(
					'key'           => 'field_tf_icone',
					'label'         => __( 'Icône si pas de photo', 'tacos-factory' ),
					'name'          => 'tf_icone',
					'type'          => 'select',
					'choices'       => array(
						'auto'      => __( 'Automatique (selon la catégorie)', 'tacos-factory' ),
						'hamburger' => __( 'Burger', 'tacos-factory' ),
						'fries'     => __( 'Frites', 'tacos-factory' ),
						'cup-soda'  => __( 'Boisson', 'tacos-factory' ),
						'ice-cream' => __( 'Dessert', 'tacos-factory' ),
						'utensils'  => __( 'Couverts', 'tacos-factory' ),
					),
					'default_value' => 'auto',
					'wrapper'       => array( 'width' => 50 ),
				),
				array(
					'key'          => 'field_tf_famille',
					'label'        => __( 'Famille (tailles)', 'tacos-factory' ),
					'name'         => 'tf_famille',
					'type'         => 'text',
					'placeholder'  => 'tacos',
					'instructions' => __( 'Même mot sur Tacos Simple, Double et Triple : leur fiche propose alors le choix « Taille ».', 'tacos-factory' ),
					'wrapper'      => array( 'width' => 50 ),
				),
				array(
					'key'         => 'field_tf_taille',
					'label'       => __( 'Libellé de la taille', 'tacos-factory' ),
					'name'        => 'tf_taille',
					'type'        => 'text',
					'placeholder' => 'Double · 2 viandes',
					'wrapper'     => array( 'width' => 50 ),
				),
				array(
					'key'     => 'field_tf_composition',
					'label'   => __( 'Composition', 'tacos-factory' ),
					'name'    => 'tf_composition',
					'type'    => 'textarea',
					'rows'    => 2,
					'wrapper' => array( 'width' => 50 ),
				),
				array(
					'key'          => 'field_tf_nutrition',
					'label'        => __( 'Valeurs nutritionnelles', 'tacos-factory' ),
					'name'         => 'tf_nutrition',
					'type'         => 'textarea',
					'rows'         => 2,
					'instructions' => __( 'Laissez vide pour masquer la ligne.', 'tacos-factory' ),
					'wrapper'      => array( 'width' => 50 ),
				),
				array(
					'key'     => 'field_tf_allergenes',
					'label'   => __( 'Allergènes', 'tacos-factory' ),
					'name'    => 'tf_allergenes',
					'type'    => 'checkbox',
					'layout'  => 'horizontal',
					'choices' => array(
						'gluten'    => 'Gluten',
						'lait'      => 'Lait',
						'oeuf'      => 'Œuf',
						'moutarde'  => 'Moutarde',
						'soja'      => 'Soja',
						'sesame'    => 'Sésame',
						'celeri'    => 'Céleri',
						'arachide'  => 'Arachide',
						'fruits'    => 'Fruits à coque',
						'poisson'   => 'Poisson',
						'crustaces' => 'Crustacés',
						'sulfites'  => 'Sulfites',
					),
				),
				array(
					'key'           => 'field_tf_precision',
					'label'         => __( 'Champ « Une précision ? »', 'tacos-factory' ),
					'name'          => 'tf_precision',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 1,
					'wrapper'       => array( 'width' => 50 ),
				),
				array(
					'key'          => 'field_tf_options',
					'label'        => __( 'Groupes d’options', 'tacos-factory' ),
					'name'         => 'tf_options',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => __( 'Ajouter un groupe (Taille, Viandes, Sauces…)', 'tacos-factory' ),
					'instructions' => __( 'Les prix des choix s’ajoutent au prix du produit. Laissez vide pour un produit simple (boisson, dessert…).', 'tacos-factory' ),
					'sub_fields'   => array(
						array(
							'key'      => 'field_tf_opt_titre',
							'label'    => __( 'Titre', 'tacos-factory' ),
							'name'     => 'titre',
							'type'     => 'text',
							'required' => 1,
							'wrapper'  => array( 'width' => 30 ),
						),
						array(
							'key'         => 'field_tf_opt_aide',
							'label'       => __( 'Sous-titre', 'tacos-factory' ),
							'name'        => 'aide',
							'type'        => 'text',
							'placeholder' => 'Choisissez 2 viandes',
							'wrapper'     => array( 'width' => 40 ),
						),
						array(
							'key'           => 'field_tf_opt_type',
							'label'         => __( 'Type', 'tacos-factory' ),
							'name'          => 'type',
							'type'          => 'select',
							'choices'       => array(
								'radio'    => __( 'Choix unique (rond)', 'tacos-factory' ),
								'checkbox' => __( 'Choix multiple (case)', 'tacos-factory' ),
							),
							'default_value' => 'radio',
							'wrapper'       => array( 'width' => 30 ),
						),
						array(
							'key'     => 'field_tf_opt_obligatoire',
							'label'   => __( 'Obligatoire', 'tacos-factory' ),
							'name'    => 'obligatoire',
							'type'    => 'true_false',
							'ui'      => 1,
							'wrapper' => array( 'width' => 25 ),
						),
						array(
							'key'           => 'field_tf_opt_min',
							'label'         => __( 'Minimum', 'tacos-factory' ),
							'name'          => 'min',
							'type'          => 'number',
							'min'           => 0,
							'default_value' => 0,
							'wrapper'       => array( 'width' => 25 ),
						),
						array(
							'key'           => 'field_tf_opt_max',
							'label'         => __( 'Maximum (0 = illimité)', 'tacos-factory' ),
							'name'          => 'max',
							'type'          => 'number',
							'min'           => 0,
							'default_value' => 0,
							'wrapper'       => array( 'width' => 25 ),
						),
						array(
							'key'          => 'field_tf_opt_prefixe',
							'label'        => __( 'Préfixe dans le panier', 'tacos-factory' ),
							'name'         => 'prefixe',
							'type'         => 'text',
							'placeholder'  => 'Sauce',
							'instructions' => __( '« Sauce » affiche « Sauce Algérienne » dans le panier.', 'tacos-factory' ),
							'wrapper'      => array( 'width' => 25 ),
						),
						array(
							'key'           => 'field_tf_opt_affichage',
							'label'         => __( 'Affichage des prix', 'tacos-factory' ),
							'name'          => 'affichage',
							'type'          => 'select',
							'choices'       => array(
								'supplement' => __( 'Supplément (+1,00 €)', 'tacos-factory' ),
								'inclus'     => __( 'Supplément, « Inclus » si 0', 'tacos-factory' ),
								'absolu'     => __( 'Prix total (taille)', 'tacos-factory' ),
							),
							'default_value' => 'supplement',
							'wrapper'       => array( 'width' => 25 ),
						),
						array(
							'key'          => 'field_tf_opt_choix',
							'label'        => __( 'Choix', 'tacos-factory' ),
							'name'         => 'choix',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => __( 'Ajouter un choix', 'tacos-factory' ),
							'sub_fields'   => array(
								array(
									'key'      => 'field_tf_choix_label',
									'label'    => __( 'Libellé', 'tacos-factory' ),
									'name'     => 'label',
									'type'     => 'text',
									'required' => 1,
								),
								array(
									'key'           => 'field_tf_choix_prix',
									'label'         => __( 'Supplément (€)', 'tacos-factory' ),
									'name'          => 'prix',
									'type'          => 'number',
									'step'          => 0.01,
									'default_value' => 0,
								),
								array(
									'key'   => 'field_tf_choix_defaut',
									'label' => __( 'Coché par défaut', 'tacos-factory' ),
									'name'  => 'defaut',
									'type'  => 'true_false',
									'ui'    => 1,
								),
							),
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'product',
					),
				),
			),
			'position' => 'normal',
		)
	);
}
add_action( 'acf/init', 'tf_register_fields' );
