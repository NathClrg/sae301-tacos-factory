# Tacos Factory — thème enfant Storefront

Thème WordPress du site Click & Collect de Tacos Factory (Limoges), fait à partir de la maquette Figma
*CLERGE Nathan — Tacos Factory*, page « 04 · Maquettes & prototype », section **A · Parcours Click & Collect**.
C'est un **thème enfant de Storefront** : Storefront doit être installé, celui-ci le surcharge.

## Plugins nécessaires

| Plugin | Rôle |
|---|---|
| **WooCommerce** | Produits, panier, commande, paiement |
| **Secure Custom Fields (SCF)** | Champs du hero et des produits (taille, options, allergènes…) |
| **Classic Editor** | Éditeur classique (le thème le force aussi par sécurité) |

## Le parcours (maquettes A)

| Maquette | Page | Fichiers |
|---|---|---|
| 11 Accueil | Hero, « Les nouveautés » (3 derniers produits), produit star (produit **mis en avant**), à propos | `front-page.php` |
| 02 Carte | Page avec le modèle « La carte (Click & Collect) » | `page-templates/template-carte.php` |
| 03 Produit | Infos produit, taille, options, « Souvent pris avec » (ventes croisées), avis | `woocommerce/content-single-product.php`, `inc/product-options.php` |
| 04 Ajouté au panier | Fenêtre après l'ajout depuis une fiche | `inc/woocommerce.php` (`tf_added_to_cart_modal`) |
| 05 Panier | Étape 1 : articles, dessert, **retrait**, **coordonnées**, code promo, totaux | `woocommerce/cart/cart.php`, `cart/cart-totals.php`, `inc/checkout.php` |
| 07 Paiement | Étape 2 : carte bancaire ou « Payer sur place » | `woocommerce/checkout/payment-method.php`, `review-order.php` |
| 08 Confirmation | Heure de retrait, suivi, QR code, plan, détail | `woocommerce/checkout/thankyou.php` |
| 09 / 10 | Barre « Ma commande » en bas de l'écran, carte rouge dans le menu | `inc/commande.php`, `inc/storefront.php` |

Le créneau et les coordonnées sont saisis **dans le panier**, gardés en session, puis repris par la page
de paiement (champs pré-remplis et masqués).

### Suivi de commande
- Numéro affiché : **TF-0427** (numéro WooCommerce sur 4 chiffres).
- Étapes : **Reçue** (en attente) → **En préparation** (« En cours ») → **Prête** (statut ajouté par le thème).
  Le restaurant passe la commande en **Prête** depuis Commandes (action groupée « Marquer comme prête »).
- La commande en cours est retenue un jour dans le navigateur (cookie), ou trouvée sur le compte client.

### Paiement par carte : démonstration
Le moyen « Carte bancaire » (`inc/gateway-carte.php`) **simule** le paiement pour la maquette : aucun débit,
la commande est marquée payée. Les numéros de carte ne sont jamais envoyés au serveur (seuls les 4 derniers
chiffres, pour « Payé · Visa •••• 4242 »). **À remplacer par un vrai moyen de paiement (Stripe, PayPlug…)
avant la mise en ligne.**

### Frais et code promo
- Frais de préparation : 1,50 € sous 15 € d'achat, offerts au-delà (`TF_FRAIS_PREPARATION`, `TF_FRAIS_SEUIL`
  dans `functions.php`).
- Code **TACOS10** : −10 % sur la catégorie Tacos (WooCommerce › Marketing › Codes promo).

## Produits

Dans chaque produit, bloc **Tacos Factory — Fiche produit** :

- *Accroche*, *Badge* (« Best-seller »), *Mention incluse*, *Temps de préparation*.
- *Famille* + *Libellé de la taille* : même famille (`tacos`) sur Tacos Simple / Double / Triple → la fiche
  propose « Taille », chaque taille ouvre sa fiche.
- *Composition*, *Allergènes*, *Valeurs nutritionnelles* (ligne masquée si vide).
- *Groupes d'options* : Viandes, Sauces, Suppléments, Accompagnement, Boisson…
  - choix unique (rond) ou multiple (case), obligatoire ou non, minimum / maximum ;
  - supplément en € par choix ; « Préfixe dans le panier » (« Sauce » → « Sauce Algérienne »).

Les prix sont toujours recalculés par le serveur à partir de ces réglages. L'état du stock (« Stock faible ·
plus que 3 ») vient de la gestion de stock WooCommerce (seuil : 5).

## CSS

`assets/css/variables.css` reprend les variables Figma (`couleur/rouge` → `var(--couleur-rouge)`).
`assets/css/theme.css` suit la maquette mobile (375 px), en px, avec la méthode **BEM**
(`bloc`, `bloc__element`, `bloc--modificateur`) et un sommaire en tête de fichier. Les réglages pour
ordinateur sont regroupés à la fin (section 18).

## Images

`assets/img/` : logo, photo du hero, plan de la confirmation (exportés de Figma). Les photos des produits
s'ajoutent dans chaque produit ; sans photo, une icône sur fond sable s'affiche, comme dans la maquette.

## Mise en ligne

Depuis le projet DDEV : `ddev export-serveur`. La commande crée, à côté du projet, le dossier
`sae-but2-serveur/` avec `site.zip` (tous les fichiers), `base-de-donnees.sql` et `LISEZMOI.txt` (les 4 étapes
chez l'hébergeur). Le même `wp-config.php` sert en local et sur le serveur : seule la base de données est à
renseigner, l'adresse du site est détectée toute seule.
