# PANIER ET COMMANDE SIMULÉE – TP 2quater

Panier et tunnel de commande ajoutés au fork InfoLeader.
**Tout fonctionne dans le navigateur** : aucune base de données, aucun stockage
côté serveur, aucune bibliothèque externe.

| | |
|---|---|
| **Fork** | `C:\wamp64\www\infoleader-tp2ter` → http://localhost/infoleader-tp2ter/ |
| **Site d'origine** | intact, réservé au TP 3 — vérifié : 0 fichier de code modifié |
| **Stockage** | `localStorage` du navigateur uniquement |
| **Paiement** | **aucun** — la commande est une simulation complète |
| **Dépendances ajoutées** | **aucune** — JavaScript natif |

---

## Table des matières

- [1. Le parcours, étape par étape](#1-le-parcours-étape-par-étape)
- [2. Fichiers créés et modifiés](#2-fichiers-créés-et-modifiés)
- [3. Ce qui est stocké, et où](#3-ce-qui-est-stocké-et-où)
- [4. Les calculs : TVA et arrondis](#4-les-calculs--tva-et-arrondis)
- [5. Accessibilité](#5-accessibilité)
- [6. Tests réalisés](#6-tests-réalisés)
- [7. Limites assumées](#7-limites-assumées)
- [8. Comment tester vous-même](#8-comment-tester-vous-même)

---

## 1. Le parcours, étape par étape

1. **Catalogue** (`products.php` ou `index.php`) — chaque fiche porte un bouton
   « Ajouter au panier ». Les informations du produit voyagent dans des attributs
   `data-*` : `data-id`, `data-nom`, `data-prix`, `data-image`.
2. **Ajout** — `assets/js/panier.js` lit ces attributs, valide les valeurs et
   enregistre la ligne. Un second clic sur le même produit **augmente la quantité**
   au lieu de créer une deuxième ligne. Le bouton affiche brièvement
   « Ajouté (2) » : l'utilisateur voit que son clic a eu un effet même si le badge
   est hors de son champ de vision.
3. **Badge** — l'icône panier de l'en-tête affiche le nombre total d'articles, sur
   toutes les pages. Il se met à jour immédiatement après un ajout, un changement
   de quantité ou une suppression, et disparaît quand le panier est vide.
4. **Page panier** (`panier.php`) — une carte par article : vignette, nom, prix
   unitaire, quantité modifiable (`−` / champ / `+`), sous-total, bouton « Retirer ».
   À droite, le récapitulatif : nombre d'articles, total HT, TVA 20 %, total TTC.
   Un bouton « Vider le panier » demande confirmation avant d'agir.
5. **Commande** (`commande.php`) — formulaire nom, e-mail, adresse, code postal,
   ville, validé dans le navigateur avec des messages d'erreur précis. Le
   récapitulatif de la commande reste affiché à côté du formulaire **pendant** la
   saisie.
6. **Confirmation** — à la validation : un numéro de commande est généré
   (`CMD-2026-0001`), le détail et les coordonnées sont affichés, **puis le panier
   est vidé**. Un bouton « Imprimer le récapitulatif » ouvre la boîte d'impression
   du navigateur, qui permet aussi d'enregistrer en PDF.

La page `commande.php` a **trois états** présents dans le HTML et révélés un à un
par le script : panier vide, formulaire, confirmation. Il n'y a donc ni
rechargement, ni requête réseau, ni page intermédiaire.

---

## 2. Fichiers créés et modifiés

### Créés (4)

| Fichier | Rôle |
|---|---|
| `assets/js/panier.js` | Cœur du panier : stockage, validation, opérations, totaux, badge, boutons d'ajout, rendu de la page panier. Expose `window.Panier`. |
| `assets/js/commande.js` | Tunnel de commande : validation du formulaire, récapitulatif, numéro de commande, écran de confirmation, impression. |
| `panier.php` | Structure de la page panier. Ne contient **aucune** donnée : tout est rempli par le script. |
| `commande.php` | Les trois états du tunnel de commande. |

### Modifiés (5)

| Fichier | Modification |
|---|---|
| `includes/layout-public.php` | Icône panier + badge + région `aria-live` dans l'en-tête de toutes les pages ; chargement de `panier.js`. |
| `products.php` | Bouton « Ajouter au panier » avec attributs `data-*` sur les 6 fiches ; mention « TTC » près du prix. |
| `index.php` | Même bouton sur les 3 produits phares. Au passage, le bouton « Commander » qui subsistait sur cette page a été aligné sur « Demander un devis », comme au TP 2ter. |
| `style.css` | Bloc 17 : en-tête, boutons, page panier, tunnel de commande, confirmation, responsive, et feuille d'impression `@media print`. |
| `robots.txt` | `panier.php` et `commande.php` interdits à l'indexation. |

Les deux nouvelles pages portent aussi `<meta name="robots" content="noindex">` :
un panier est propre à chaque visiteur, il n'a rien à faire dans un moteur de
recherche.

**Aucune modification dans `admin/`**, aucun fichier supprimé.

---

## 3. Ce qui est stocké, et où

Trois clés dans le `localStorage` du navigateur. Rien ne part vers le serveur,
rien n'est écrit sur le disque du site.

### `il-panier` — le panier

Un tableau d'objets. Exemple réel après deux clics sur le clavier et un sur l'écran :

```json
[
  {
    "id": "p412cd524",
    "nom": "Clavier mécanique RGB",
    "prix": 59.99,
    "qte": 2,
    "image": "assets/products/clavier.jpg"
  },
  {
    "id": "peced4d8c",
    "nom": "Écran 4K UHD 27\"",
    "prix": 289.99,
    "qte": 1,
    "image": "assets/products/ecran.jpg"
  }
]
```

`prix` est le prix unitaire **TTC** en euros ; `qte` est un entier entre 1 et 99.

### `il-commande-sequence` — le compteur de numéro

Un simple entier, par exemple `"2"`. Il alimente la partie chiffrée du numéro de
commande.

### `il-derniere-commande` — la dernière commande validée

Un instantané pris **avant** le vidage du panier : numéro, date, coordonnées du
client, lignes et totaux. C'est ce qui permet à l'écran de confirmation de
survivre à un rechargement (F5) et de rester imprimable.

### Robustesse de la lecture

Le contenu du `localStorage` peut être absent, tronqué, modifié à la main ou
provenir d'une version antérieure du site. **Chaque ligne est donc revalidée à
chaque lecture** par la fonction `nettoyerLigne()` :

- `id` et `nom` non vides, tronqués à 64 et 150 caractères ;
- `prix` : nombre fini, entre 0 et 1 000 000 ;
- `qte` : entier ramené dans l'intervalle 1–99 ;
- une ligne invalide est **écartée**, pas corrigée au hasard ;
- deux lignes de même `id` sont **fusionnées** ;
- un JSON illisible renvoie un panier vide au lieu de casser la page.

Tous les accès au `localStorage` sont entourés d'un `try / catch` : en navigation
privée ou avec le stockage bloqué, le panier reste utilisable pour la page
courante, il ne survit simplement pas au rechargement.

### Sécurité de l'affichage

Les lignes de panier sont construites avec `createElement` et `textContent`,
**jamais avec `innerHTML`**. Une valeur modifiée à la main dans le `localStorage`
ne peut donc pas être interprétée comme du HTML.

---

## 4. Les calculs : TVA et arrondis

### Les prix du catalogue sont des prix TTC

C'est la convention d'un site grand public : le prix affiché sur la fiche est
celui que le client paie. Le HT et la TVA s'en **déduisent** :

```
TTC = somme(prix unitaire × quantité)
HT  = TTC / 1,20
TVA = TTC − HT
```

L'inverse (prix catalogue HT, puis ajout de 20 %) aurait fait apparaître au
récapitulatif un montant différent de celui affiché sur la fiche produit.

### Tous les calculs se font en centimes

Additionner des nombres à virgule flottante fait dériver un total de quelques
centimes sur un gros panier. Les montants sont donc manipulés en **entiers de
centimes**, et convertis en euros seulement à l'affichage.

Vérification sur le panier de test : **341,64 € HT + 68,33 € TVA = 409,97 € TTC**,
exactement le total des sous-totaux (119,98 + 289,99). Aucun centime perdu.

### Formatage

`Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })`, fourni par
le navigateur : séparateur décimal français, espace insécable, symbole €.
Aucune bibliothèque de formatage.

---

## 5. Accessibilité

| Point | Mise en œuvre |
|---|---|
| **Badge annoncé** | Le badge chiffré est `aria-hidden`. L'information passe par une région `role="status" aria-live="polite"` distincte, qui annonce « Panier : 3 articles » plutôt qu'un « 3 » sans contexte. |
| **Boutons explicites** | Chaque bouton d'ajout, de quantité et de retrait porte un `aria-label` nommant le produit : « Augmenter la quantité de Clavier mécanique RGB ». Sans cela, six boutons « Ajouter au panier » seraient indiscernables dans la liste des liens d'un lecteur d'écran. |
| **Libellés de formulaire** | Chaque champ a son `<label for>`, son `aria-required` et son `aria-describedby` pointant vers son message d'erreur. |
| **Erreurs utilisables** | Le résumé d'erreurs reçoit le focus et chaque message est un **lien vers le champ fautif**. Le champ est marqué `aria-invalid` et coloré. |
| **Focus sur la confirmation** | Le titre « Commande enregistrée » reçoit le focus après validation : le clavier et le lecteur d'écran arrivent sur la confirmation, pas en haut de page. |
| **Focus visible** | Les 25 éléments interactifs actifs de la page panier ont un indicateur de focus (mesuré). Les boutons « − » désactivés à la quantité 1 ne sont pas focusables, ce qui est le comportement attendu. |
| **Zones tactiles** | Mesuré à 320 px : **0 cible sous 24 px** sur `panier.php` et `commande.php`. Le lien « Modifier le panier » faisait 115 × 21 px, il a été porté à 44 px de haut. |
| **Sans JavaScript** | Un bloc `<noscript>` explique sur les deux pages que le panier a besoin de JavaScript, et renvoie vers le catalogue et le formulaire de contact. |

---

## 6. Tests réalisés

Tous ces résultats ont été **mesurés dans un navigateur** sur le fork en
fonctionnement, pas supposés.

### Panier

| Test | Résultat |
|---|---|
| Panier vide au départ | Badge masqué, région d'annonce : « Panier vide » |
| 6 boutons d'ajout détectés sur le catalogue | `data-id`, `data-nom`, `data-prix`, `data-image` corrects |
| 2 clics sur le même produit | **1 seule ligne, quantité 2** — pas de doublon |
| Badge après 2 + 1 clics | « 3 », annonce « Panier : 3 articles » |
| Changement de page | Panier conservé : 2 lignes retrouvées sur `panier.php` |
| Bouton `+` | Quantité 2 → 3, badge 3 → 4, TTC 409,97 → 469,96 € |
| Bouton `−` | Quantité 3 → 2, badge revenu à 3 |
| Saisie directe « 5 » | Quantité 5, badge 6, TTC 589,94 € |
| Saisie aberrante « 9999 » | **Ramenée à 99** (plafond) |
| `−` à la quantité 1 | Bouton désactivé |
| Retrait d'une ligne | 1 ligne restante, badge 2, message « Écran 4K UHD 27" retiré du panier. » |
| Totaux | 341,64 HT + 68,33 TVA = **409,97 TTC** — exact |
| Panier vidé | Message « Votre panier est vide », lien vers le catalogue, récapitulatif et actions masqués |

### Commande

| Test | Résultat |
|---|---|
| Ouverture avec panier garni | État « formulaire », récapitulatif affiché à côté |
| Envoi du formulaire vide | **5 erreurs**, focus sur le résumé, les 5 champs marqués `aria-invalid`, aucune validation |
| Nom « A », e-mail « pas-un-email », CP « 750 » | **3 erreurs ciblées**, les champs valides ne sont pas signalés |
| Envoi valide | Confirmation, numéro **CMD-2026-0001**, date, coordonnées et détail corrects |
| Après validation | **Panier vidé** (0 article), badge masqué, focus sur le titre de confirmation |
| Rechargement (F5) de la confirmation | Confirmation **toujours affichée**, numéro et détail intacts, panier toujours vide |
| Deuxième commande | **CMD-2026-0002**, compteur à 2 |
| Retour sur `commande.php` sans ancre | Le formulaire s'affiche, pas l'ancienne confirmation |
| Panier vide | État « Il n'y a rien à commander », lien vers le catalogue |

### Qualité

| Test | Résultat |
|---|---|
| Erreurs dans la console du navigateur | **0** sur l'ensemble du parcours |
| Syntaxe PHP des 5 fichiers touchés | `No syntax errors detected` |
| Débordement horizontal à 320 px | **0** sur `panier.php`, `commande.php`, `products.php`, `index.php` |
| Cibles tactiles sous 24 px à 320 px | **0** après correction |
| Focus visible | 25 / 25 éléments actifs |
| Feuille d'impression | Bloc `@media print` bien interprété par le navigateur : 11 règles, dont le masquage de l'en-tête, du pied de page, du titre et des boutons |
| Données écrites côté serveur | **0** — `contact.json` inchangé, aucun fichier du site modifié |
| Site d'origine | **0 fichier de code modifié** |

---

## 7. Limites assumées

Ces limites découlent directement des contraintes du TP. Les connaître fait
partie de l'exercice.

**1. Les prix viennent du navigateur.** Les attributs `data-prix` sont lisibles et
modifiables par n'importe quel visiteur avec les outils de développement, comme le
contenu du `localStorage`. Un vrai site de vente **doit recalculer tous les
montants côté serveur** à partir de son propre catalogue, et ne jamais faire
confiance aux prix transmis par le client. Ici, aucun paiement n'existe et rien
n'est enregistré : la conséquence est nulle.

**2. Le numéro de commande est propre à chaque navigateur.** Le compteur vit dans
le `localStorage` : deux visiteurs différents obtiendraient tous les deux
`CMD-2026-0001`. Sur un vrai site, la numérotation est tenue par le serveur, qui
garantit son unicité.

**3. Rien n'est conservé.** La commande n'est enregistrée nulle part côté serveur,
aucun e-mail n'est envoyé, et vider les données du navigateur efface le panier
comme l'historique de commande. C'est exactement ce que demande le TP.

**4. Aucun paiement, à aucun moment.** Pas de passerelle bancaire, pas de champ de
carte, pas de débit. L'écran de confirmation porte une mention « Document de
démonstration » qui **reste visible à l'impression** : un récapitulatif imprimé ne
doit jamais pouvoir passer pour une facture.

**5. Aucune gestion de stock.** Rien ne limite la quantité commandée en fonction
d'un stock, puisqu'il n'existe aucun stock à consulter.

**6. Le panier a besoin de JavaScript.** C'est inhérent à un panier côté
navigateur. Un bloc `<noscript>` l'explique et propose le formulaire de contact
comme solution de repli.

---

## 8. Comment tester vous-même

1. Ouvrez **http://localhost/infoleader-tp2ter/products.php**
2. Cliquez deux fois sur « Ajouter au panier » du premier produit, une fois sur un
   autre → le badge de l'en-tête affiche **3**.
3. Changez de page, revenez : le badge affiche toujours 3. **Le panier survit.**
4. Cliquez sur l'icône **Panier** → deux cartes, les quantités, et les totaux
   HT / TVA / TTC.
5. Jouez avec `+`, `−` et le champ de quantité : les totaux et le badge suivent.
   Essayez de saisir `9999` : la valeur est ramenée à 99.
6. Cliquez sur **Vider le panier** → une confirmation est demandée.
7. Remettez des articles, puis **Commander**.
8. Validez le formulaire **vide** → 5 messages d'erreur, chacun cliquable pour
   aller au champ concerné.
9. Saisissez un code postal à 3 chiffres → le message l'explique.
10. Remplissez correctement et validez → **numéro de commande**, récapitulatif,
    panier vidé.
11. Faites **F5** : la confirmation est toujours là.
12. Cliquez sur **Imprimer le récapitulatif** (ou Ctrl+P) : l'aperçu ne montre que
    le récapitulatif, sans menu, sans en-tête, sans pied de page et sans boutons.
    Vous pouvez enregistrer en PDF depuis cette boîte.
13. Ouvrez les outils de développement (F12) → onglet Application → Local Storage :
    vous voyez les clés `il-panier`, `il-commande-sequence` et
    `il-derniere-commande`.
14. Réduisez la fenêtre à la largeur d'un téléphone : les lignes du panier
    deviennent des cartes empilées, sans défilement horizontal.

---

*Tous les chiffres de ce document ont été mesurés sur le fork en fonctionnement.
Les limites connues sont listées en section 7 plutôt que passées sous silence.*
