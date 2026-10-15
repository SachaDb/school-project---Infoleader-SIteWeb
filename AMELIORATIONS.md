# AMÉLIORATIONS – TP 2ter

Fork du site InfoLeader destiné à l'amélioration de la qualité.
Le site d'origine n'a **pas** été modifié : il reste intact pour le TP 3.

| | |
|---|---|
| **Site d'origine** | `C:\wamp64\www\infoleader` → http://localhost/infoleader/ |
| **Fork de travail** | `C:\wamp64\www\infoleader-tp2ter` → http://localhost/infoleader-tp2ter/ |
| **Preuve de non-régression** | aucun fichier de code de l'original modifié (contrôlé par date de modification) ; les 3 pages d'origine répondent toujours avec une taille identique à l'octet |
| **Base de départ** | `TODO_LIST.md`, produit au TP 2bis. Les actions reprises sont citées par leur numéro. |
| **Suite** | Le TP 2quater a ajouté un panier et une commande simulée à ce même fork : voir [PANIER.md](PANIER.md). |

---

## Table des matières

- [1. Ce qui a été fait, par thème](#1-ce-qui-a-été-fait-par-thème)
- [2. Fonctionnalité ajoutée : thème clair / sombre](#2-fonctionnalité-ajoutée--thème-clair--sombre)
- [3. Mesures avant / après](#3-mesures-avant--après)
- [4. Deux vrais bugs trouvés dans le site d'origine](#4-deux-vrais-bugs-trouvés-dans-le-site-dorigine)
- [5. Fichiers ajoutés et modifiés](#5-fichiers-ajoutés-et-modifiés)
- [6. Ce que je n'ai volontairement pas touché](#6-ce-que-je-nai-volontairement-pas-touché)
- [7. Comment tout revérifier](#7-comment-tout-revérifier)

---

## 1. Ce qui a été fait, par thème

### C.1 Accessibilité

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 1 | **Anneau de focus visible sur tout élément interactif**, avec une couleur qui s'adapte au fond : bleu foncé sur fond clair, cyan sur fond bleu nuit | Seuls le menu et les boutons principaux en avaient un. Les liens du pied de page et les liens de contact s'appuyaient sur le style par défaut du navigateur, peu visible sur fond foncé. | `style.css` (bloc 15.1) | Appuyez plusieurs fois sur la touche Tab sur chaque page : chaque élément atteint affiche un contour net. Mesuré : **23 / 23** éléments focusables de `contact.php` ont un indicateur. |
| 2 | **Champs obligatoires annoncés** : astérisque visible, attribut `aria-required`, et légende « Les champs marqués d'un * sont obligatoires » | Le formulaire est en `novalidate` : rien n'indiquait quels champs étaient requis, ni visuellement ni pour un lecteur d'écran. | `contact.php`, `style.css` | Les 3 champs requis portent un astérisque rouge. Dans l'inspecteur, `nom`, `email` et `message` ont `aria-required="true"`. |
| 3 | **Messages d'erreur rattachés à leur champ** par `aria-describedby` (`err-nom`, `err-email`, `err-message`) | Les paragraphes d'erreur existaient mais n'étaient liés à aucun champ : un lecteur d'écran ne les lisait pas en arrivant sur le champ fautif. | `contact.php` | Inspecteur : chaque champ pointe vers l'identifiant de son message d'erreur. |
| 4 | **Le focus est porté sur le résumé d'erreurs** après un refus du serveur (`tabindex="-1"` puis `focus()`) | L'utilisateur au clavier restait en haut de page sans savoir ce qui avait échoué. | `contact.php`, `script.js` (section 7) | Envoyez le formulaire sans cocher le consentement : le bloc rouge reçoit le focus. Vérifié : `document.activeElement` vaut `resume-erreurs`. |
| 5 | **`aria-label` distinct sur chaque bouton de devis** | Six liens au texte identique « Demander un devis » sont indiscernables dans la liste des liens d'un lecteur d'écran. | `products.php` | Inspecteur : `aria-label="Demander un devis pour Clavier mécanique RGB"`. |
| 6 | **`aria-label` sur la navigation** et `aria-current="page"` sur l'entrée active | Annonce le rôle de la barre de navigation et la page en cours de consultation. | `includes/layout-public.php` | Source de la page : `<nav aria-label="Navigation principale">`. |

### C.2 Responsive / mobile

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 7 | **Zones tactiles du pied de page portées de 18–21 px à 44 px de haut** | Mesure réelle à 320 px de large : les liens du pied de page et des blocs de contact faisaient 18 à 21 px de haut, **sous le minimum de 24 px** exigé par le critère WCAG 2.5.8. Difficiles à atteindre au doigt. | `style.css` (bloc 15.3) | Outils de développement, mode mobile 320 px. Avant : 6 à 8 cibles trop petites par page. Après : **0** sur les 5 pages. |
| 8 | **Case à cocher du consentement portée à 24 × 24 px** | Même exigence WCAG 2.5.8. | `style.css` | Mode mobile : la case mesure 24 px de côté. |
| 9 | **Aucun débordement horizontal**, vérifié à 320 px sur les 5 pages publiques | Contrôle de non-régression : le site d'origine était déjà correct sur ce point, mes ajouts (tableau de la page confidentialité, bouton de thème) ne devaient pas le casser. Le tableau est placé dans un conteneur `.table-scroll`. | `style.css`, `confidentialite.php` | Mode mobile 320 px : `scrollWidth - clientWidth = 0` sur les 5 pages. |

### C.3 Performances

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 10 | **`width` et `height` sur toutes les images** : 1200×400 pour la bannière, 600×400 pour les fiches | Sans dimensions, le navigateur ne réserve pas la place de l'image : la mise en page sursaute quand elle arrive. C'est le décalage cumulatif, l'un des indicateurs Core Web Vitals. | `index.php`, `products.php` | Inspecteur : les 4 images de l'accueil et les 6 du catalogue portent `width` et `height`. |
| 11 | **`loading="lazy"` et `decoding="async"`** sur la bannière et toutes les fiches | La bannière de 26 Ko se trouve sous la ligne de flottaison : elle n'a pas à retarder le premier affichage. | `index.php`, `products.php` | Onglet Réseau : la bannière n'est demandée qu'au défilement. |
| 12 | **Filtre de recherche sorti dans `products.js`** | Le code était écrit en ligne dans la page : retéléchargé à chaque visite, impossible à mettre en cache. Comportement **inchangé** : le code a été déplacé, pas réécrit. | `products.php`, `products.js` (nouveau) | Le filtre fonctionne toujours : « clavier » donne 1 produit sur 6, « zzzzz » affiche le message « aucun résultat ». |
| 13 | **En-tête et pied de page factorisés** dans un gabarit unique | Le même balisage était recopié 3 fois, et il l'aurait été 7 fois avec les nouvelles pages. Gain : **−234 lignes** dans les 3 pages existantes. | `includes/layout-public.php` (nouveau), `index.php`, `products.php`, `contact.php` | Ajoutez une entrée au menu dans `il_public_nav()` : elle apparaît sur les 7 pages d'un coup. |
| 14 | **Zéro style et zéro script en ligne** dans les 7 pages publiques | Les attributs `style="..."` et les balises `<script>` en ligne ne peuvent pas être mis en cache et interdisent un en-tête `Content-Security-Policy` strict. Le marqueur `js` a été déplacé dans `theme.js`, chargé de façon bloquante, donc exécuté au même moment qu'avant. | les 7 pages, `style.css`, `theme.js` | Comptage : 0 occurrence de `style="` et 0 balise `<script>` sans `src` dans les pages. |

### C.4 Référencement (SEO)

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 15 | **`title` et `meta description` uniques par page**, produits par le gabarit | Les 3 pages d'origine en avaient déjà ; les 4 nouvelles devaient en avoir aussi, sans dupliquer le code. | `includes/layout-public.php` et les 7 pages | Affichez le code source de chaque page : chaque titre et chaque description diffèrent. |
| 16 | **Balise `canonical`** sur chaque page, en URL absolue calculée | Évite qu'une même page soit vue comme deux pages distinctes (`/` et `/index.php`). L'URL est calculée et non codée en dur, pour survivre au renommage du dossier du fork. | `includes/layout-public.php` | Source : `<link rel="canonical" href="http://localhost/infoleader-tp2ter/">`. |
| 17 | **Open Graph et Twitter Card complets** : type, nom du site, langue, titre, description, URL, image et texte alternatif de l'image | Sans ces balises, un partage du lien n'affiche ni titre ni aperçu. | `includes/layout-public.php` | Source : 8 balises `og:` et 4 balises `twitter:`. L'image pointe en absolu vers `assets/banner.jpg`. |
| 18 | **`robots.txt`** : pages publiques autorisées ; `admin/`, `includes/`, `storage/`, `vendor/` et `products.json` interdits ; lien vers le sitemap | Aucun fichier n'existait. Un robot aurait pu indexer la page de connexion de l'administration. | `robots.txt` (nouveau) | http://localhost/infoleader-tp2ter/robots.txt |
| 19 | **`sitemap.xml`** déclarant les 5 pages publiques, avec date, fréquence et priorité | Aide un moteur à découvrir les pages. L'espace d'administration en est volontairement absent. | `sitemap.xml` (nouveau) | http://localhost/infoleader-tp2ter/sitemap.xml — XML validé par `DOMDocument`, 5 URL déclarées. |
| 20 | **`noindex` sur les pages d'erreur**, avec conservation du vrai code HTTP | Une page 404 renvoyée en code 200 et indexable finirait indexée comme une vraie page. | `404.php`, `403.php` | Une URL inexistante renvoie `HTTP 404` **et** `<meta name="robots" content="noindex, follow">`. |

### C.5 UX et cohérence

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 21 | **Correction d'un bug réel : le message « Aucun produit ne correspond à votre recherche » s'affichait sous les 6 produits** dès l'ouverture du catalogue | Le message n'avait aucun état masqué par défaut, et le filtre ne s'exécutait qu'à la première saisie. Le site d'origine affiche donc en permanence un message contradictoire. Voir la section 4. | `products.php`, `products.js` | Ouvrez le catalogue : le message est absent. Tapez « zzzzz » : il apparaît. Effacez : il disparaît. |
| 22 | **« Commander » renommé « Demander un devis »**, avec l'objet du message pré-rempli au nom du produit | Le bouton ne commandait rien : il menait au formulaire de contact. L'intitulé promettait plus que ce que le site fait. Aucun panier ni paiement n'a été ajouté. | `products.php`, `contact.php` | Cliquez depuis une fiche : `contact.php` s'ouvre avec le champ Objet déjà rempli (« Devis : Clavier mécanique RGB »). |
| 23 | **Messages uniformisés** : une seule classe `.success-msg` et trois modificateurs (succès, alerte, erreur), au lieu de couleurs écrites en ligne | Chaque variante portait ses couleurs dans un attribut `style="..."` au milieu du HTML. Impossible à faire évoluer, et incompatible avec une CSP stricte. | `contact.php`, `style.css` (bloc 15.4) | Les trois états de message ont la même forme et des couleurs cohérentes, en thème clair comme en thème sombre. |
| 24 | **Pages d'erreur 403 et 404 aux couleurs du site** | Une URL inexistante affichait la page brute d'Apache, qui annonce « Apache/2.4.65 ». Information inutile donnée à un attaquant, et rupture complète de l'expérience. | `404.php`, `403.php` (nouveaux), `.htaccess` | http://localhost/infoleader-tp2ter/page-qui-nexiste-pas affiche votre page 404 avec le menu. À comparer avec la même URL sur le site d'origine. |
| 25 | **Page « Mentions légales »**, liée depuis le pied de page de toutes les pages | Action 2.1 de `TODO_LIST.md`, priorité P0. Aucune information légale n'existait. La page précise que la société est fictive et que le site est un projet scolaire non hébergé. | `mentions-legales.php` (nouveau), `includes/layout-public.php` | Lien présent en bas de chaque page. Un champ reste à compléter : votre nom comme responsable de publication. |
| 26 | **Page « Politique de confidentialité »** décrivant le traitement réel | Action 2.2, priorité P0. Le site collectait nom, e-mail, message **et adresse IP** sans en informer personne. Le contenu décrit exactement les champs écrits par `il_contact_save()` : ni plus, ni moins. | `confidentialite.php` (nouveau) | Comparez le tableau de la page avec le code de `includes/store.php` : les champs correspondent. |

### C.5 bis — Conformité RGPD et fiabilité

| # | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|---|---|---|---|
| 27 | **Case de consentement obligatoire, validée côté serveur** | Action 2.3, priorité P0. Sans consentement, la collecte n'a aucune base légale. La validation est faite en PHP et pas seulement par l'attribut HTML `required`, qu'on peut retirer depuis les outils du navigateur. | `contact.php` | Retirez l'attribut `required` dans l'inspecteur puis envoyez : le serveur refuse quand même, avec le message « Vous devez accepter l'enregistrement de vos données ». **Testé.** |
| 28 | **Adresse IP tronquée avant enregistrement** d'un message | Action 2.6. L'IP est une donnée personnelle. Le journal de sécurité en a besoin complète pour le blocage anti-force-brute, mais un message de contact non. IPv4 : dernier nombre mis à zéro. IPv6 : masquage des 80 derniers bits, en conservant le préfixe /48. | `includes/store.php` | Envoyez un message, ouvrez `storage/data/contact.json` : l'IP est tronquée. Testé sur `192.168.1.42` → `192.168.1.0`, `::1` → `::`, `2a01:cb00:1234:5678:…` → `2a01:cb00:1234::`. |
| 29 | **Redirection après envoi du formulaire** (motif « POST puis redirection ») | Action 3.1, priorité P0. Avant, un simple F5 après l'envoi renvoyait le formulaire et **enregistrait le message une seconde fois**. | `contact.php` | Envoyez un message, puis rechargez deux fois avec F5 : aucune boîte « renvoyer les informations », et `contact.json` ne contient qu'**un** message. **Testé : 1 message après 2 rechargements.** |
| 30 | **`products.json` n'est plus servi publiquement** | Action 1.4. Le catalogue brut était téléchargeable à l'adresse `/products.json`. Les données ne sont pas confidentielles, mais cette exposition n'était pas voulue. Les pages PHP lisent le fichier sur le disque, elles ne sont donc pas affectées. | `.htaccess` | `/products.json` sur le fork renvoie **403**. Sur le site d'origine, il renvoie encore **200**. Le catalogue s'affiche normalement dans les deux cas. |
| 31 | **Fichiers de documentation du projet bloqués en HTTP** | `DOCUMENTATION.md` décrit l'architecture et les faiblesses du site : ce n'est pas du contenu public. | `.htaccess` | `/DOCUMENTATION.md` renvoie 403. |

---

## 2. Fonctionnalité ajoutée : thème clair / sombre

C'est la fonctionnalité demandée par la partie D du TP. Elle correspond à
l'action 5.4 de `TODO_LIST.md`. **Aucune base de données, aucun module
d'administration, aucune dépendance** : tout tient dans trois fichiers.

### Comment c'est construit

| Fichier | Rôle |
|---|---|
| `theme.js` (29 lignes) | Chargé **sans `defer`**, avant la feuille de style. Lit le choix dans `localStorage` et pose l'attribut `data-theme` sur `<html>` **avant le premier affichage**, ce qui évite le flash blanc au chargement d'une page en thème sombre. |
| `style.css` (bloc 16) | Redéfinit la palette. Le CSS du site était déjà piloté par variables : **151 usages de `var()`**, et chaque couleur de la palette n'apparaissait qu'une fois. Le thème sombre ne touche donc à aucune des 1266 lignes de règles existantes. |
| `script.js` (section 6) | Gère le bouton : bascule, mise à jour de `aria-pressed` et du libellé, et enregistrement du choix. |

### Les trois états gérés

1. **Aucun choix enregistré** → le réglage du système d'exploitation s'applique, via `prefers-color-scheme`.
2. **`data-theme="dark"`** → thème sombre forcé par l'utilisateur.
3. **`data-theme="light"`** → thème clair forcé, **même si le système est en sombre**.

Si l'utilisateur n'a rien choisi, le site suit **en direct** les changements de
réglage du système, grâce à un écouteur sur `matchMedia`.

### Une difficulté réelle, et sa solution

La variable `--blanc` ne pouvait **pas** être redéfinie : une recherche dans le
CSS montre qu'elle sert **13 fois de couleur de texte** sur les fonds bleu nuit
(titres du pied de page, boutons, bannière) et seulement 10 fois de fond de
carte. La passer au sombre aurait rendu ces 13 textes illisibles.

J'ai donc introduit une variable `--surface`, et remplacé les 10 déclarations
`background: var(--blanc)` par `background: var(--surface)`. En thème clair,
`--surface` vaut `--blanc` : **le rendu est rigoureusement identique à avant.**

### Accessibilité de la fonctionnalité

- C'est un vrai `<button>`, donc utilisable au clavier sans code supplémentaire.
- `aria-pressed` indique l'état au lecteur d'écran.
- Le libellé annonce **l'action à venir** (« Thème sombre »), pas l'état courant.
- Le bouton est `hidden` dans le HTML et révélé par JavaScript : sans JavaScript, il n'apparaît pas, puisqu'il n'aurait aucun effet.
- Contraste vérifié : texte `#e5eaf2` sur fond `#0e1626`.
- Tous les accès à `localStorage` sont dans un `try / catch` : en navigation privée, le thème s'applique quand même, il n'est simplement pas retenu.

### Comment le tester

1. Ouvrez http://localhost/infoleader-tp2ter/ : le bouton « Thème sombre » est à droite du menu.
2. Cliquez : la page passe au sombre, le libellé devient « Thème clair ».
3. Rechargez (F5) : **le thème sombre est conservé, sans flash blanc**.
4. Naviguez vers une autre page : le thème suit.
5. Mettez Windows en thème sombre sans avoir jamais cliqué sur le bouton (videz `localStorage`) : le site s'affiche en sombre tout seul.

**Testé** : bascule dans les deux sens, `aria-pressed` correct, `localStorage`
mis à jour, fond du corps mesuré à `rgb(14, 22, 38)` en sombre et
`rgb(244, 247, 252)` en clair, persistance confirmée après rechargement.

---

## 3. Mesures avant / après

| Indicateur | Site d'origine | Fork TP 2ter |
|---|---|---|
| Cibles tactiles sous 24 px, à 320 px de large | **6 à 8 par page** | **0** |
| Éléments focusables sans indicateur de focus | plusieurs (liens du pied de page, liens de contact) | **0 sur 23** |
| Images avec `width` et `height` | 0 / 10 | **10 / 10** |
| Attributs `style="..."` dans les pages publiques | 8 | **0** |
| Balises `<script>` en ligne | 2 | **0** |
| Pages avec Open Graph | 0 | **7** |
| `robots.txt` / `sitemap.xml` | absents | **présents** |
| Message « aucun résultat » affiché à tort | **oui, en permanence** | non |
| Doublon de message après un F5 | **oui** | non |
| `/products.json` accessible en HTTP | **oui (200)** | non (403) |
| Page 404 révélant « Apache/2.4.65 » | **oui** | non |
| Mentions légales et politique de confidentialité | absentes | **présentes** |
| Consentement avant collecte de données | absent | **obligatoire, validé serveur** |
| Adresse IP enregistrée avec un message | complète | **tronquée** |
| Thème sombre | absent | **présent, avec mémorisation** |
| Erreurs dans la console du navigateur | 0 | **0** |

### Volumétrie

| Fichier | Origine | Fork | Écart |
|---|---|---|---|
| `index.php` | 289 | 230 | −59 |
| `products.php` | 208 | 116 | −92 |
| `contact.php` | 302 | 219 | −83 |
| `style.css` | 1266 | 1658 | +392 |
| `script.js` | 188 | 285 | +97 |

Les trois pages ont **maigri de 234 lignes** grâce au gabarit commun, alors
qu'elles font strictement plus de choses. Les ajouts se concentrent dans la
feuille de style et dans les 9 nouveaux fichiers.

---

## 4. Deux vrais bugs trouvés dans le site d'origine

Ces deux défauts ne viennent pas d'une liste théorique : ils ont été trouvés en
mesurant le site d'origine dans un navigateur.

### Bug 1 — Le catalogue affiche « aucun produit » alors qu'il en affiche six

Mesuré sur http://localhost/infoleader/products.php :

```
nb_produits_affiches : 6
#noResults -> display: "block", visible a l ecran : true
texte : "Aucun produit ne correspond à votre recherche."
```

**Cause** : la classe `.no-results` n'a **aucune** règle `display` dans
`style.css`. Le message était donc visible par défaut, et la fonction de
filtrage, qui le masque, n'était appelée que sur l'événement `input` — jamais au
chargement. La branche « catalogue vide » utilisait, elle, un
`style="display:block"` en ligne, ce qui masquait le problème au premier regard.

**Correction** : attribut `hidden` dans le HTML, et `products.js` le révèle
uniquement quand le filtre ne renvoie rien.

### Bug 2 — Un F5 après l'envoi du formulaire enregistre le message deux fois

`contact.php` traitait le POST puis affichait le résultat dans la **même**
réponse. Le navigateur gardait donc une requête POST rejouable : actualiser la
page la renvoyait.

**Correction** : motif « POST puis redirection ». Le message de succès passe par
`il_flash()` et la page redirige vers elle-même. Les pages du panneau
d'administration appliquaient déjà ce motif : le site public était la seule
exception.

**Note de méthode** : je n'ai pas rejoué ce test sur le site d'origine, pour ne
pas écrire de données dans son dossier. Le défaut est lisible dans son code,
qui ne contient aucune redirection après l'enregistrement.

---

## 5. Fichiers ajoutés et modifiés

### Ajoutés (9)

| Fichier | Lignes | Rôle |
|---|---|---|
| `includes/layout-public.php` | 225 | Gabarit commun : en-tête, balises SEO, navigation, pied de page |
| `confidentialite.php` | 165 | Politique de confidentialité |
| `mentions-legales.php` | 87 | Mentions légales |
| `products.js` | 66 | Filtre de recherche, sorti de `products.php` |
| `sitemap.xml` | 48 | Plan du site pour les moteurs |
| `404.php` | 47 | Page « introuvable » |
| `403.php` | 46 | Page « accès refusé » |
| `theme.js` | 29 | Application du thème avant le premier affichage |
| `robots.txt` | 23 | Règles d'indexation |

### Modifiés (7)

| Fichier | Nature de la modification |
|---|---|
| `index.php` | Passage au gabarit commun, dimensions et chargement différé des images, suppression des styles en ligne |
| `products.php` | Gabarit commun, bouton de devis, correction du message « aucun résultat », externalisation du script, dimensions des images |
| `contact.php` | Redirection après envoi, consentement RGPD, accessibilité du formulaire, objet pré-remplissable, messages uniformisés |
| `style.css` | Blocs 15 et 16 ajoutés : focus, zones tactiles, messages, consentement, thème sombre, pages de contenu |
| `script.js` | Sections 6 et 7 ajoutées : bascule de thème, focus du résumé d'erreurs |
| `includes/store.php` | Troncature de l'adresse IP pour les messages de contact |
| `.htaccess` | Pages d'erreur personnalisées, blocage de `products.json` et des fichiers `.md` |

Aucun fichier n'a été supprimé. Les dossiers `admin/` et `vendor/` n'ont reçu
**aucune** modification.

---

## 6. Ce que je n'ai volontairement pas touché

### Périmètre réservé au TP 3

| Élément | Respecté |
|---|---|
| Transformation en site d'ébénisterie | Non abordé. Le site reste InfoLeader : même nom, même charte, mêmes contenus. |
| Base de données et schéma relationnel | **Aucune base créée.** Ni MySQL, ni SQLite. Le stockage reste en fichiers JSON. |
| Architecture MVC | Non. `includes/layout-public.php` est un simple gabarit d'affichage, du même type que `admin/_layout.php` qui existait déjà. Il n'y a ni routeur, ni contrôleur, ni modèle. |
| Modules du panneau (CRUD articles, maintenance, messages) | **Le dossier `admin/` n'a reçu aucune modification.** |
| Filtre, recherche, tri sur des données de base | Aucune fonctionnalité de ce type ajoutée. Le filtre existant a été **déplacé** dans un fichier, à l'identique, sans changement de comportement. |

### Autres contraintes

- **Aucune nouvelle dépendance** : pas de Composer, pas de npm, pas de framework, pas de CDN. La seule dépendance du projet reste PHPMailer, inchangée.
- **Aucun paiement** : le bouton « Commander » a été renommé précisément parce qu'il promettait une commande inexistante. Il n'y a ni panier, ni passerelle, ni saisie de carte.
- **WAMP uniquement** : aucune configuration de serveur distant, de VPS, de Docker ou de déploiement.

### Actions de `TODO_LIST.md` laissées de côté, et pourquoi

| Action | Raison |
|---|---|
| 1.3 — corriger les appels à `filemtime()` non protégés | Ne concerne que des fichiers de `admin/`, hors périmètre de ce TP. |
| 2.4 — effacement automatique des anciens messages | Se déclenche depuis le module Maintenance, donc dans `admin/`. La page de confidentialité le signale honnêtement comme « prévu mais pas encore en place ». |
| 2.5 — suppression d'un message isolé | Même raison : interface d'administration. |
| 1.1 — limitation du nombre d'envois du formulaire | Reste à faire. C'est de la logique métier plus que de la qualité front-end, et la consigne de ce TP demandait de ne pas toucher à la logique métier. |
| 1.8 — en-tête `Content-Security-Policy` | Son prérequis est désormais rempli (zéro script et zéro style en ligne), mais la poser demande de retester chaque page. À faire dans un second temps. |
| 3.2 — verrou de fichier à l'écriture | Touche le socle de lecture/écriture JSON, utilisé partout, y compris par l'administration. Risque de régression trop élevé pour ce TP. |
| 3.6 — migration vers SQLite | **Interdit par ce TP** (aucune base de données), et réservé au TP 3. |

---

## 7. Comment tout revérifier

Ouvrez les deux sites côte à côte : http://localhost/infoleader/ et
http://localhost/infoleader-tp2ter/.

| Ce qu'on vérifie | Où | Résultat attendu |
|---|---|---|
| Le site d'origine fonctionne toujours | http://localhost/infoleader/ | Les 3 pages s'affichent à l'identique |
| Le fork fonctionne | http://localhost/infoleader-tp2ter/ | 7 pages, aucune erreur dans la console |
| Thème sombre | bouton à droite du menu | Bascule, persiste au F5, pas de flash blanc |
| Message « aucun résultat » | catalogue des deux sites | Visible à tort sur l'original, absent sur le fork |
| Double envoi | formulaire de contact, puis F5 | Un seul message enregistré sur le fork |
| Consentement | formulaire sans cocher la case | Refus du serveur, focus sur le résumé d'erreurs |
| Bouton de devis | une fiche produit | Ouvre le contact avec l'objet pré-rempli |
| Page 404 | `/page-qui-nexiste-pas` sur les deux sites | Page Apache brute sur l'original, page InfoLeader sur le fork |
| `products.json` | `/products.json` sur les deux sites | 200 sur l'original, 403 sur le fork |
| Zones tactiles | mode mobile 320 px | Liens du pied de page hauts de 44 px sur le fork |
| Navigation au clavier | touche Tab sur chaque page | Contour visible sur chaque élément |
| SEO | code source de chaque page | `title`, `description`, `canonical`, `og:` présents et différents |
| Pied de page | toutes les pages | Liens vers mentions légales et confidentialité |

---

*Toutes les affirmations de ce document ont été vérifiées dans un navigateur sur
le fork en fonctionnement, ou mesurées dans le code. Les points non réalisés
sont listés en section 6 plutôt que passés sous silence.*
