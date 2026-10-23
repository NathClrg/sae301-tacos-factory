<?php
/**
 * Moyen de paiement « Carte bancaire » de la maquette (07) — DÉMONSTRATION.
 *
 * Aucun paiement réel : le numéro de carte, la date et le code ne sont jamais
 * envoyés au serveur (les champs n'ont pas d'attribut « name »). Seuls les
 * 4 derniers chiffres partent, pour afficher « Payé · Visa •••• 4242 ».
 * À remplacer par un vrai moyen de paiement (Stripe, PayPlug…) avant la mise en ligne.
 *
 * @package TacosFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

/**
 * Carte bancaire (démonstration).
 */
class TF_Gateway_Carte extends WC_Payment_Gateway {

	/**
	 * Réglages.
	 */
	public function __construct() {
		$this->id                 = 'tf_carte';
		$this->has_fields         = true;
		$this->method_title       = __( 'Carte bancaire (démonstration)', 'tacos-factory' );
		$this->method_description = __( 'Simulation pour la maquette : aucun paiement réel, la commande est marquée payée. Le numéro de carte n’est jamais envoyé au serveur. À remplacer par un vrai moyen de paiement avant la mise en ligne.', 'tacos-factory' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Champs de l'admin.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Activer', 'tacos-factory' ),
				'type'    => 'checkbox',
				'label'   => __( 'Carte bancaire (démonstration)', 'tacos-factory' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Titre', 'tacos-factory' ),
				'type'    => 'text',
				'default' => __( 'Carte bancaire', 'tacos-factory' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'tacos-factory' ),
				'type'    => 'text',
				'default' => __( 'Paiement sécurisé en ligne', 'tacos-factory' ),
			),
		);
	}

	/**
	 * Formulaire de carte (maquette 07). theme.js vérifie la saisie et
	 * remplit les deux champs cachés.
	 */
	public function payment_fields() {
		$holder = WC()->customer ? WC()->customer->get_billing_first_name() : '';
		?>
		<div class="carte-bancaire">
			<div class="carte-test">
				<p class="note">
					<?php echo tf_icon( 'info', 14 ); // phpcs:ignore ?>
					<span>
						<?php
						printf(
							/* translators: %s: numéro de la carte de test */
							esc_html__( 'Mode test : aucun débit. Carte %s, date future et code au choix.', 'tacos-factory' ),
							'<strong>4242 4242 4242 4242</strong>'
						);
						?>
					</span>
				</p>
				<button type="button" class="bouton bouton--secondaire bouton--petit" data-carte-test><?php esc_html_e( 'Remplir avec la carte de test', 'tacos-factory' ); ?></button>
			</div>
			<p class="champ">
				<label class="champ__label" for="tf-carte-numero"><?php esc_html_e( 'Numéro de carte', 'tacos-factory' ); ?></label>
				<span class="champ__groupe">
					<input class="champ__saisie" id="tf-carte-numero" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 1234 1234 1234" maxlength="23" data-carte="numero">
					<span class="visa">VISA</span>
				</span>
			</p>
			<div class="carte-bancaire__ligne">
				<p class="champ">
					<label class="champ__label" for="tf-carte-expiration"><?php esc_html_e( 'Expiration', 'tacos-factory' ); ?></label>
					<input class="champ__saisie" id="tf-carte-expiration" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM / AA" maxlength="7" data-carte="expiration">
				</p>
				<p class="champ">
					<label class="champ__label" for="tf-carte-cvc"><?php esc_html_e( 'CVC', 'tacos-factory' ); ?></label>
					<span class="champ__groupe">
						<input class="champ__saisie" id="tf-carte-cvc" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="•••" maxlength="4" data-carte="cvc">
						<?php echo tf_icon( 'credit-card', 18 ); // phpcs:ignore ?>
					</span>
				</p>
			</div>
			<p class="champ">
				<label class="champ__label" for="tf-carte-titulaire"><?php esc_html_e( 'Titulaire de la carte', 'tacos-factory' ); ?></label>
				<input class="champ__saisie champ__saisie--majuscules" id="tf-carte-titulaire" type="text" autocomplete="cc-name" value="<?php echo esc_attr( $holder ); ?>" data-carte="titulaire">
			</p>
			<label class="case">
				<input type="checkbox" name="tf_carte_memoriser" value="1">
				<?php esc_html_e( 'Enregistrer ma carte pour la prochaine fois', 'tacos-factory' ); ?>
			</label>
			<input type="hidden" name="tf_carte_fin" value="">
			<input type="hidden" name="tf_carte_type" value="">
		</div>
		<?php
	}

	/**
	 * La carte a-t-elle été validée côté navigateur ?
	 *
	 * @return bool
	 */
	public function validate_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par WooCommerce.
		$last4 = isset( $_POST['tf_carte_fin'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_carte_fin'] ) ) : '';
		if ( ! preg_match( '/^\d{4}$/', $last4 ) ) {
			wc_add_notice( __( 'Vérifiez les informations de votre carte.', 'tacos-factory' ), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Paiement simulé : la commande passe « En cours » (en préparation).
	 *
	 * @param int $order_id Commande.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$last4 = isset( $_POST['tf_carte_fin'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_carte_fin'] ) ) : '';
		$type  = isset( $_POST['tf_carte_type'] ) ? sanitize_text_field( wp_unslash( $_POST['tf_carte_type'] ) ) : '';
		// phpcs:enable

		$order->update_meta_data( '_tf_carte', trim( ( $type ? $type : __( 'Carte', 'tacos-factory' ) ) . ' •••• ' . $last4 ) );
		$order->add_order_note( __( 'Paiement par carte simulé (démonstration, aucun débit).', 'tacos-factory' ) );
		$order->payment_complete();
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}

/**
 * La carte bancaire en premier, comme sur la maquette.
 *
 * @param array $gateways Moyens de paiement.
 * @return array
 */
function tf_register_card_gateway( $gateways ) {
	array_unshift( $gateways, 'TF_Gateway_Carte' );
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'tf_register_card_gateway' );
