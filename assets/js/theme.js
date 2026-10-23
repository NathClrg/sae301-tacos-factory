/**
 * Tacos Factory — interactions du thème.
 *
 * 1. Menu plein écran (header .menu-ouvert)
 * 2. Quantité − / + (.quantite__bouton)
 * 3. Fiche produit : options cochées, maximum atteint, prix du bouton, taille, avis
 * 4. Fenêtre « Ajouté au panier ! »
 * 5. Carte : pastille active selon la section visible
 * 6. Panier : retrait ou livraison, créneau, saisie gardée pendant les mises à jour AJAX
 * 7. Paiement : moyen choisi, formulaire de carte (démonstration)
 * 8. Confirmation : QR code de retrait
 */
( function ( $ ) {
	'use strict';

	var data  = window.tfData || {};
	var price = data.price || { symbol: '€', decimals: 2, decimal: '.', thousand: ' ', format: '%2$s%1$s' };
	var $body = $( document.body );

	var ICONES = {
		moins: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>',
		plus: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>',
		cadenas: '<svg class="icone" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
	};

	/* Affiche un prix au format de WooCommerce (ex. « 13.99€ ») */
	function formatPrix( montant ) {
		var decimales = parseInt( price.decimals, 10 ) || 0;
		var parties   = Math.abs( montant ).toFixed( decimales ).split( '.' );
		parties[ 0 ]  = parties[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, price.thousand );
		var nombre    = decimales ? parties[ 0 ] + price.decimal + parties[ 1 ] : parties[ 0 ];
		return String( price.format ).replace( '%1$s', price.symbol ).replace( '%2$s', nombre );
	}

	/* ------------------------------------------------------------------
	 * 1. Menu plein écran
	 * ---------------------------------------------------------------- */
	function ouvrirMenu( ouvert ) {
		$( '.site-header' ).toggleClass( 'menu-ouvert', ouvert );
		$( '.entete__burger' ).attr( 'aria-expanded', ouvert ? 'true' : 'false' );
		$body.css( 'overflow', ouvert ? 'hidden' : '' );
	}

	$( document ).on( 'click', '.entete__burger', function () {
		ouvrirMenu( ! $( '.site-header' ).hasClass( 'menu-ouvert' ) );
	} );

	$( document ).on( 'click', '.menu a', function () {
		ouvrirMenu( false );
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			ouvrirMenu( false );
			fermerAjout();
		}
	} );

	$( window ).on( 'resize', function () {
		if ( window.innerWidth >= 960 ) {
			ouvrirMenu( false );
		}
	} );

	/* ------------------------------------------------------------------
	 * 2. Quantité − / +
	 * ---------------------------------------------------------------- */
	function limites( $input ) {
		var min = parseFloat( $input.attr( 'min' ) );
		var max = parseFloat( $input.attr( 'max' ) );
		return {
			pas: parseFloat( $input.attr( 'step' ) ) || 1,
			min: isNaN( min ) ? 0 : min,
			max: isNaN( max ) || max <= 0 ? Infinity : max
		};
	}

	function majBoutonsQuantite( $quantite ) {
		var $input = $quantite.find( '.qty' );
		var l      = limites( $input );
		var valeur = parseFloat( $input.val() ) || 0;
		$quantite.find( '.quantite__bouton--moins' ).prop( 'disabled', valeur <= l.min );
		$quantite.find( '.quantite__bouton--plus' ).prop( 'disabled', valeur >= l.max );
	}

	function ajouterBoutonsQuantite() {
		$( '.quantity' ).each( function () {
			var $quantite = $( this );
			var $input    = $quantite.find( '.qty' );

			// Déjà fait, ou champ caché (produit vendu à l'unité)
			if ( $quantite.find( '.quantite__bouton' ).length || ! $input.length || 'hidden' === $input.attr( 'type' ) ) {
				return;
			}

			$input.before( '<button type="button" class="quantite__bouton quantite__bouton--moins" aria-label="Retirer un">' + ICONES.moins + '</button>' );
			$input.after( '<button type="button" class="quantite__bouton quantite__bouton--plus" aria-label="Ajouter un">' + ICONES.plus + '</button>' );
			majBoutonsQuantite( $quantite );
		} );
	}

	$( document ).on( 'click', '.quantite__bouton', function () {
		var $quantite = $( this ).closest( '.quantity' );
		var $input    = $quantite.find( '.qty' );
		var l         = limites( $input );
		var valeur    = parseFloat( $input.val() ) || 0;

		valeur = $( this ).hasClass( 'quantite__bouton--plus' ) ? valeur + l.pas : valeur - l.pas;
		valeur = Math.max( l.min, Math.min( l.max, valeur ) );

		$input.val( valeur ).trigger( 'change' );
		majBoutonsQuantite( $quantite );
	} );

	// Panier : on met à jour automatiquement après un changement de quantité
	var minuteur;
	$( document ).on( 'change', '.woocommerce-cart-form .qty', function () {
		clearTimeout( minuteur );
		minuteur = setTimeout( function () {
			$( '.woocommerce-cart-form [name="update_cart"]' ).prop( 'disabled', false ).trigger( 'click' );
		}, 450 );
	} );

	// Dessert ajouté depuis le panier : on recharge le panier
	$body.on( 'added_to_cart', function () {
		if ( $( '.woocommerce-cart-form' ).length ) {
			$body.trigger( 'wc_update_cart' );
		}
	} );

	/* ------------------------------------------------------------------
	 * 3. Fiche produit
	 * ---------------------------------------------------------------- */
	function initFicheProduit() {
		var $form = $( '.produit__formulaire' );
		if ( ! $form.length ) {
			return;
		}

		var prixDeBase = parseFloat( $form.data( 'base' ) ) || 0;

		function mettreAJour() {
			var supplements = 0;

			$form.find( '.groupe-options[data-type]' ).each( function () {
				var $groupe = $( this );
				var max     = parseInt( $groupe.data( 'max' ), 10 ) || 0;
				var $inputs = $groupe.find( '.option__input' );
				var $coches = $inputs.filter( ':checked' );
				var $badge  = $groupe.find( '.groupe-options__badge' );

				// Somme des suppléments cochés
				$coches.each( function () {
					supplements += parseFloat( $( this ).data( 'price' ) ) || 0;
				} );

				// Ligne cochée : libellé en gras, prix en rouge
				$inputs.each( function () {
					$( this ).closest( '.option' ).toggleClass( 'option--cochee', this.checked );
				} );

				// Cases à cocher : au maximum, on grise les autres et le badge devient « 2/2 ✓ »
				if ( 'checkbox' === $groupe.data( 'type' ) && max > 0 ) {
					var complet = $coches.length >= max;

					$inputs.each( function () {
						var bloque    = complet && ! this.checked;
						this.disabled = bloque;
						$( this ).closest( '.option' ).toggleClass( 'option--desactivee', bloque );
					} );

					$badge.toggleClass( 'badge--complet', complet );
					$badge.text( complet ? $coches.length + '/' + max + ' ✓' : $badge.data( 'label' ) );
				}
			} );

			var quantite = parseFloat( $form.find( '.qty' ).val() ) || 1;
			$form.find( '.barre-commande__total' ).text( formatPrix( ( prixDeBase + supplements ) * quantite ) );
		}

		$form.on( 'change input', '.option__input, .qty', mettreAJour );

		// Taille : chaque taille est un produit, on ouvre sa fiche
		$form.on( 'change', 'input[name="tf_taille"]', function () {
			$( this ).closest( '.groupe-options' ).find( '.option' ).removeClass( 'option--cochee' );
			$( this ).closest( '.option' ).addClass( 'option--cochee' );
			window.location.href = this.value;
		} );

		// Avant d'envoyer : un choix obligatoire manque ? (le serveur revérifie)
		$form.on( 'submit', function ( e ) {
			var $manquant = $form.find( '.groupe-options[data-type]' ).filter( function () {
				var min = parseInt( $( this ).data( 'min' ), 10 ) || 0;
				return $( this ).find( '.option__input:checked' ).length < min;
			} ).first();

			if ( $manquant.length ) {
				e.preventDefault();
				var $badge = $manquant.find( '.groupe-options__badge' ).addClass( 'badge--erreur' );
				$manquant[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
				setTimeout( function () {
					$badge.removeClass( 'badge--erreur' );
				}, 1600 );
			}
		} );

		mettreAJour();
	}

	// « Voir les N avis »
	$( document ).on( 'click', '.avis__plus', function () {
		$( '.avis__carte' ).prop( 'hidden', false );
		$( this ).remove();
	} );

	/* ------------------------------------------------------------------
	 * 4. Fenêtre « Ajouté au panier ! »
	 * ---------------------------------------------------------------- */
	function fermerAjout() {
		$( '.ajout' ).remove();
		$body.removeClass( 'ajout-ouvert' );
	}

	$( document ).on( 'click', '.ajout [data-fermer]', function ( e ) {
		e.preventDefault();
		fermerAjout();
	} );

	/* ------------------------------------------------------------------
	 * 5. Carte : pastille active
	 * ---------------------------------------------------------------- */
	function initPastilles() {
		var $pastilles = $( '.pastille' );
		if ( ! $pastilles.length ) {
			return;
		}

		function activer( id ) {
			var $pastille = $pastilles.filter( '[href="#' + id + '"]' );
			if ( ! $pastille.length ) {
				return;
			}
			$pastilles.removeClass( 'pastille--active' );
			$pastille.addClass( 'pastille--active' );

			// Fait défiler la liste pour que la pastille reste visible
			var liste = $pastille.parent()[ 0 ];
			liste.scrollTo( { left: Math.max( 0, $pastille[ 0 ].offsetLeft - 20 ), behavior: 'smooth' } );
		}

		$pastilles.on( 'click', function () {
			activer( this.getAttribute( 'href' ).slice( 1 ) );
		} );

		if ( 'IntersectionObserver' in window ) {
			var observateur = new IntersectionObserver( function ( entrees ) {
				entrees.forEach( function ( entree ) {
					if ( entree.isIntersecting ) {
						activer( entree.target.id );
					}
				} );
			}, { rootMargin: '-40% 0px -55% 0px' } );

			$( '.section-menu' ).each( function () {
				observateur.observe( this );
			} );
		}
	}

	/* ------------------------------------------------------------------
	 * 6. Panier : créneau de retrait et saisie
	 * ---------------------------------------------------------------- */
	function majChoixRetrait() {
		$( '.choix' ).each( function () {
			var actif = $( this ).find( 'input[name="tf_retrait_mode"]' ).is( ':checked' );
			$( this ).toggleClass( 'choix--actif', actif );
		} );
	}

	// Cliquer sur un horaire sélectionne « Programmer un horaire »
	$( document ).on( 'change', 'input[name="tf_retrait_heure"]', function () {
		$( 'input[name="tf_retrait_mode"][value="slot"]' ).prop( 'checked', true );
		majChoixRetrait();
	} );

	$( document ).on( 'change', 'input[name="tf_retrait_mode"]', function () {
		var $heures = $( 'input[name="tf_retrait_heure"]' );
		if ( 'asap' === this.value ) {
			$heures.prop( 'checked', false );
		} else if ( ! $heures.filter( ':checked' ).length ) {
			$heures.first().prop( 'checked', true );
		}
		majChoixRetrait();
	} );

	// Retrait / Livraison : carte choisie et panneau affiché
	function majReception() {
		var mode = $( 'input[name="tf_reception"]:checked' ).val() || 'retrait';
		$( '.reception__choix' ).each( function () {
			$( this ).toggleClass( 'reception__choix--actif', $( this ).find( 'input[name="tf_reception"]' ).is( ':checked' ) );
		} );
		$( '.reception__panneau' ).each( function () {
			this.hidden = $( this ).attr( 'data-reception' ) !== mode;
		} );
	}

	// Livraison : seules les plages du jour choisi sont affichées
	function majJourLivraison() {
		var jour = $( 'input[name="tf_livraison_jour"]:checked' ).val();
		$( '.creneaux[data-jour]' ).each( function () {
			this.hidden = $( this ).attr( 'data-jour' ) !== jour;
		} );
	}

	// Changer de mode recalcule le total (frais de livraison selon le poids)
	$( document ).on( 'change', 'input[name="tf_reception"]', function () {
		majReception();
		$( '.woocommerce-cart-form [name="update_cart"]' ).prop( 'disabled', false ).trigger( 'click' );
	} );

	// Nouveau jour : on coche sa première plage
	$( document ).on( 'change', 'input[name="tf_livraison_jour"]', function () {
		majJourLivraison();
		var $plages = $( '.creneaux[data-jour="' + this.value + '"] input[name="tf_livraison_plage"]' );
		if ( ! $plages.filter( ':checked' ).length ) {
			$plages.first().prop( 'checked', true ).trigger( 'change' );
		}
	} );

	// WooCommerce remplace le formulaire du panier après chaque mise à jour :
	// on garde ce que le client a déjà saisi (réception, créneau, coordonnées).
	var saisie = {};
	var CHAMPS = '.retrait input, .coordonnees input';

	$( document ).on( 'input change', CHAMPS, function () {
		saisie[ this.name ] = 'radio' === this.type ? $( 'input[name="' + this.name + '"]:checked' ).val() : this.value;
	} );

	$body.on( 'updated_wc_div', function () {
		$.each( saisie, function ( nom, valeur ) {
			var $champs = $( CHAMPS ).filter( '[name="' + nom + '"]' );
			if ( 'radio' === $champs.attr( 'type' ) ) {
				$champs.filter( '[value="' + valeur + '"]' ).prop( 'checked', true );
			} else {
				$champs.val( valeur );
			}
		} );
		majChoixRetrait();
		majReception();
		majJourLivraison();
		ajouterBoutonsQuantite();
	} );

	$body.on( 'updated_cart_totals', ajouterBoutonsQuantite );

	// « Passer au paiement » est hors du formulaire (barre du bas) : WooCommerce
	// croirait sinon que le dernier bouton cliqué (« Appliquer ») est envoyé.
	$( document ).on( 'click', '[name="proceed"]', function () {
		$( '.woocommerce-cart-form :input[type="submit"]' ).removeAttr( 'clicked' );
	} );

	/* ------------------------------------------------------------------
	 * 7. Paiement
	 * ---------------------------------------------------------------- */
	function moyenChoisi() {
		return $( 'input[name="payment_method"]:checked' ).val() || '';
	}

	// Carte choisie (classe .moyen-actif) ; total « à payer au retrait » ;
	// cadenas sur « Payer 23.98€ » (WooCommerce réécrit le texte du bouton).
	function majPaiement() {
		var moyen = moyenChoisi();
		$( '#payment .payment_methods > li' ).each( function () {
			$( this ).toggleClass( 'moyen-actif', $( this ).children( 'input' ).is( ':checked' ) );
		} );
		$( 'form.checkout' ).toggleClass( 'paiement-sur-place', 'cod' === moyen );

		var $bouton = $( '#place_order' );
		$bouton.find( '.icone' ).remove();
		if ( 'tf_carte' === moyen ) {
			$bouton.prepend( ICONES.cadenas );
		}
	}

	$( document ).on( 'change', 'input[name="payment_method"]', function () {
		setTimeout( majPaiement, 0 ); // après WooCommerce
	} );
	$body.on( 'updated_checkout payment_method_selected', majPaiement );

	// Formulaire de carte (démonstration) : mise en forme et vérification.
	// Les numéros ne quittent jamais le navigateur : seuls les 4 derniers
	// chiffres partent, dans un champ caché.
	function chiffres( texte ) {
		return String( texte ).replace( /\D/g, '' );
	}

	function luhn( numero ) {
		var somme = 0;
		for ( var i = 0; i < numero.length; i++ ) {
			var n = parseInt( numero.charAt( numero.length - 1 - i ), 10 );
			if ( i % 2 ) {
				n *= 2;
				if ( n > 9 ) {
					n -= 9;
				}
			}
			somme += n;
		}
		return numero.length >= 13 && 0 === somme % 10;
	}

	function typeCarte( numero ) {
		if ( /^4/.test( numero ) ) {
			return 'Visa';
		}
		if ( /^(5[1-5]|2[2-7])/.test( numero ) ) {
			return 'Mastercard';
		}
		if ( /^3[47]/.test( numero ) ) {
			return 'Amex';
		}
		return 'Carte';
	}

	$( document ).on( 'input', '[data-carte="numero"]', function () {
		this.value = chiffres( this.value ).slice( 0, 19 ).replace( /(\d{4})(?=\d)/g, '$1 ' );
		var type = typeCarte( chiffres( this.value ) );
		$( this ).siblings( '.visa' ).text( 'Carte' === type ? 'VISA' : type.toUpperCase() );
	} );

	$( document ).on( 'input', '[data-carte="expiration"]', function () {
		var c = chiffres( this.value ).slice( 0, 4 );
		this.value = c.length > 2 ? c.slice( 0, 2 ) + ' / ' + c.slice( 2 ) : c;
	} );

	$( document ).on( 'input', '[data-carte="cvc"]', function () {
		this.value = chiffres( this.value ).slice( 0, 4 );
	} );

	// « Remplir avec la carte de test » : carte Visa de test, expire dans 2 ans
	$( document ).on( 'click', '[data-carte-test]', function () {
		var annee = String( ( new Date().getFullYear() + 2 ) % 100 );
		var $nom  = $( '[data-carte="titulaire"]' );
		$( '[data-carte="numero"]' ).val( '4242424242424242' ).trigger( 'input' );
		$( '[data-carte="expiration"]' ).val( '12' + ( annee.length < 2 ? '0' + annee : annee ) ).trigger( 'input' );
		$( '[data-carte="cvc"]' ).val( '123' );
		if ( ! $.trim( $nom.val() || '' ) ) {
			$nom.val( $.trim( $( '#billing_first_name' ).val() || '' ) || 'Client test' );
		}
		$( '.carte-bancaire .champ__saisie' ).removeClass( 'champ__saisie--erreur' );
	} );

	function carteValide() {
		var numero = chiffres( $( '[data-carte="numero"]' ).val() );
		var exp    = chiffres( $( '[data-carte="expiration"]' ).val() );
		var cvc    = chiffres( $( '[data-carte="cvc"]' ).val() );
		var nom    = $.trim( $( '[data-carte="titulaire"]' ).val() || '' );
		var mois   = parseInt( exp.slice( 0, 2 ), 10 );
		var annee  = 2000 + parseInt( exp.slice( 2 ), 10 );
		var fin    = new Date( annee, mois, 1 ); // 1er jour du mois suivant
		var erreurs = [];

		$( '.carte-bancaire .champ__saisie' ).removeClass( 'champ__saisie--erreur' );

		if ( ! luhn( numero ) ) {
			erreurs.push( '[data-carte="numero"]' );
		}
		if ( 4 !== exp.length || mois < 1 || mois > 12 || fin <= new Date() ) {
			erreurs.push( '[data-carte="expiration"]' );
		}
		if ( cvc.length < 3 ) {
			erreurs.push( '[data-carte="cvc"]' );
		}
		if ( ! nom ) {
			erreurs.push( '[data-carte="titulaire"]' );
		}

		$( erreurs.join( ',' ) ).addClass( 'champ__saisie--erreur' );
		$( 'input[name="tf_carte_fin"]' ).val( erreurs.length ? '' : numero.slice( -4 ) );
		$( 'input[name="tf_carte_type"]' ).val( typeCarte( numero ) );
		return ! erreurs.length;
	}

	// WooCommerce appelle ce gestionnaire avant d'envoyer la commande
	$( 'form.checkout' ).on( 'checkout_place_order_tf_carte', function () {
		carteValide();
		return true; // le serveur refuse la commande si la carte n'est pas valide
	} );

	/* ------------------------------------------------------------------
	 * 8. Confirmation : QR code de retrait
	 * ---------------------------------------------------------------- */
	function initQrCode() {
		var el = document.querySelector( '.code-retrait__qr[data-qr]' );
		if ( ! el || ! window.QRCode ) {
			return;
		}
		new window.QRCode( el, {
			text: el.getAttribute( 'data-qr' ),
			width: 172,
			height: 172,
			colorDark: '#111111',
			colorLight: '#ffffff',
			correctLevel: window.QRCode.CorrectLevel.M
		} );
		el.removeAttribute( 'title' ); // qrcode.js ajoute le texte en infobulle
	}

	// « Annuler ma commande » : on demande confirmation avant de suivre le lien
	$( document ).on( 'click', '[data-confirmer]', function ( e ) {
		if ( ! window.confirm( $( this ).attr( 'data-confirmer' ) ) ) {
			e.preventDefault();
		}
	} );

	/* ------------------------------------------------------------------
	 * Démarrage
	 * ---------------------------------------------------------------- */
	$( function () {
		ajouterBoutonsQuantite();
		initFicheProduit();
		initPastilles();
		majChoixRetrait();
		majPaiement();
		initQrCode();

		if ( $( '.ajout' ).length ) {
			$body.addClass( 'ajout-ouvert' );
			$( '.ajout__feuille' ).attr( 'tabindex', '-1' ).trigger( 'focus' );
		}
	} );
}( jQuery ) );
