# Plan d'améliorations — InfoLeader

Ce document découle de la section « Limites connues et risques » de
[`DOCUMENTATION.md`](DOCUMENTATION.md). Chaque action porte la référence du risque
qu'elle corrige (R1, R2, …).

Les actions sont dimensionnées pour un étudiant de **BTS SIO 1re année** et pour un
site qui tourne **uniquement en local sous WAMP**.

---

## Table des matières

- [Comment lire ce document](#comment-lire-ce-document)
- [Contraintes à respecter](#contraintes-à-respecter)
- [Définition de Terminé](#définition-de-terminé)
- [1. Sécurité](#1-sécurité)
- [2. Conformité et données personnelles (RGPD)](#2-conformité-et-données-personnelles-rgpd)
- [3. Stockage des données](#3-stockage-des-données)
- [4. Qualité du code](#4-qualité-du-code)
- [5. Front-end](#5-front-end)
- [6. Exploitation locale](#6-exploitation-locale)
- [7. Documentation et recette](#7-documentation-et-recette)
- [Séquencement par jalons](#séquencement-par-jalons)
- [Volontairement hors périmètre](#volontairement-hors-périmètre)

---

## Comment lire ce document

**Priorité**

| Niveau | Signification |
|---|---|
| **P0** | Critique. À traiter en premier : faille, non-conformité ou bug qui peut perdre des données. |
| **P1** | Important. Améliore nettement la fiabilité ou la maintenance, sans urgence. |
| **P2** | Optionnel. Confort, finition, ou évolution à faire si vous avez le temps. |

**Complexité** : estimation du travail pour votre niveau.

| Niveau | Signification |
|---|---|
| **simple** | Une seule page ou une seule fonction à modifier. Moins d'une heure. |
| **moyen** | Plusieurs fichiers, ou une nouvelle fonction à écrire. Une à trois heures. |
| **complexe** | Modification de structure. À faire sur plusieurs séances, en sauvegardant avant. |

**Critère d'acceptation** : le test concret à faire dans votre navigateur pour
prouver que l'action est réussie. Si le test échoue, l'action n'est pas terminée.

**Petit lexique** (les termes reviennent dans les tableaux)

- **CSRF** (*Cross-Site Request Forgery*) : attaque où un site piégé fait envoyer un
  formulaire sur votre site à votre insu. Votre site s'en protège déjà avec un jeton.
- **Limitation de débit** (*rate limiting*) : empêcher qu'une même personne répète
  une action trop souvent (par exemple envoyer 200 messages en une minute).
- **POST puis redirection** (*POST / Redirect / GET*) : après l'envoi d'un
  formulaire, on redirige le visiteur vers une autre URL. Ainsi, s'il actualise la
  page, le formulaire n'est pas renvoyé une deuxième fois.
- **Verrou de fichier** (`flock`) : réservation d'un fichier pendant qu'on le
  modifie, pour que deux visiteurs simultanés ne s'écrasent pas l'un l'autre.
- **SQLite** : base de données contenue dans un seul fichier, déjà incluse dans PHP.
  Aucune installation, aucun serveur à démarrer.
- **CSP** (*Content-Security-Policy*) : en-tête HTTP qui indique au navigateur
  quels scripts il a le droit d'exécuter.

---

## Contraintes à respecter

Ces règles s'appliquent à **toutes** les actions de ce document.

1. **WAMP local uniquement.** Apache + PHP sous Windows, dans
   `C:\wamp64\www\infoleader`. Aucune action ne suppose un serveur distant, un VPS,
   Docker, Nginx, PHP-FPM, un nom de domaine réel ou un déploiement automatisé.
2. **Aucune nouvelle dépendance** (Composer, npm, framework, bibliothèque externe)
   n'est imposée. Les rares actions qui en évoqueraient une sont marquées
   **« sous accord du professeur »**. La seule dépendance actuelle, PHPMailer, reste
   inchangée.
3. **Aucun paiement réel.** Le site n'a aujourd'hui aucune fonction de paiement, et
   ce document n'en ajoute aucune. Si une telle fonction était demandée plus tard,
   elle devrait rester une **simulation** : aucune passerelle bancaire (Stripe,
   PayPal…), aucune clé d'API bancaire, aucune saisie de numéro de carte.
4. **Le stockage en fichiers JSON n'est pas un défaut.** C'est le choix actuel du
   projet et il est pleinement accepté. Les actions de la section 3 visent à le
   rendre plus **fiable**, pas à le remplacer. La migration vers SQLite est une
   évolution **optionnelle** (action 3.6).
5. **Le site doit rester fonctionnel** après chaque action, prise une par une.

---

## Définition de Terminé

Une action n'est considérée comme terminée que si **les six points** suivants sont
vrais.

1. **Le critère d'acceptation du tableau est vérifié** dans votre navigateur, sur
   `http://localhost/infoleader/`, et vous avez vu le résultat de vos yeux.
2. **Les trois pages publiques s'affichent encore sans erreur** : `index.php`,
   `products.php`, `contact.php`. Aucun message `Warning`, `Notice`, `Fatal error`
   ou `Deprecated` n'apparaît à l'écran.
3. **Le panneau d'administration fonctionne encore** : vous pouvez vous connecter,
   ouvrir les six modules, et vous déconnecter.
4. **Le tableau de bord n'affiche pas de nouvelle anomalie.** Le bandeau d'état
   (`admin/dashboard.php`) et la section « 1. Système » de `admin/maintenance.php`
   ne comptent pas plus de contrôles en échec qu'avant votre modification.
5. **Aucun secret ne s'est retrouvé au mauvais endroit** : vous avez vérifié
   qu'aucun mot de passe, hash ou identifiant SMTP n'apparaît dans une page, dans un
   message d'erreur, dans `storage/logs/auth.json`, ni dans un fichier versionné.
6. **La ligne est cochée dans ce document** (`- [x]`) et vous savez expliquer en une
   phrase ce que vous avez changé et pourquoi.

> **Avant chaque action**, copiez le dossier `infoleader` ailleurs sur le disque
> (par exemple sur le Bureau). C'est votre seul moyen de revenir en arrière tant que
> l'action 6.3 (Git en local) n'est pas faite.

---

## 1. Sécurité

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 1.1 | **Limiter le nombre d'envois du formulaire de contact** (R1). Réutilisez les fonctions `il_rl_*` qui existent déjà pour la connexion : refusez un envoi si la même adresse IP a déjà envoyé, par exemple, 3 messages en 15 minutes, et affichez un message clair indiquant le temps à attendre. | **P0** | moyen | `contact.php`, `includes/store.php` | Envoyez 4 messages d'affilée : le 4e est refusé avec un message qui indique la durée d'attente. Le compteur résiste à l'actualisation de la page et à la suppression des cookies. `storage/data/contact.json` ne contient que 3 nouveaux messages. |
| 1.2 | **Ajouter un piège anti-robot simple** (R1). Un champ de formulaire caché en CSS (« pot de miel ») que seul un robot remplira, plus un contrôle de délai : un formulaire renvoyé en moins de 2 secondes est rejeté. Aucun CAPTCHA, donc aucun service externe. | P1 | simple | `contact.php`, `style.css` | Un envoi normal passe. Si vous remplissez le champ caché avec les outils de développement du navigateur, le message est rejeté et rien n'est écrit dans `contact.json`. |
| 1.3 | **Corriger les appels à `filemtime()` non protégés** (R19). Remplacez `filemtime(__DIR__ . '/assets/admin.css')` par un appel à une fonction qui teste d'abord `is_file()`, sur le modèle de `il_asset()` qui le fait déjà correctement. | **P0** | simple | `admin/_layout.php`, `admin/login.php`, `admin/setup.php`, `admin/reset-password.php` | Renommez temporairement `admin/assets/admin.css`, rechargez la page de connexion : aucun avertissement PHP ne s'affiche. Remettez le nom d'origine, la page est de nouveau stylée. |
| 1.4 | **Empêcher l'accès direct à `products.json`** (R11). Ajoutez une règle `FilesMatch` dans le `.htaccess` de la racine, comme celle qui bloque déjà `composer.json`. Les pages PHP lisent le fichier sur le disque : elles continueront de fonctionner. | P1 | simple | `.htaccess` | `http://localhost/infoleader/products.json` renvoie une erreur 403. `http://localhost/infoleader/products.php` affiche toujours les 6 produits. |
| 1.5 | **Restreindre le vhost `infoleader.local` au poste local** (R5). Remplacez `Require all granted` par `Require local`, comme c'est déjà le cas pour `localhost`. C'est une modification de configuration WAMP, pas de code. | P1 | simple | `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf` | Après redémarrage d'Apache, `http://infoleader.local/` fonctionne depuis votre poste. Depuis un téléphone sur le même Wi-Fi, l'accès est refusé. |
| 1.6 | **Documenter et sécuriser la procédure de secours du compte administrateur** (R4). Affichez, sur la page « Mot de passe oublié ? », un encadré expliquant quoi faire si l'envoi d'e-mails est en panne, et ajoutez cette procédure dans le `README.md`. | **P0** | simple | `admin/reset-password.php`, `README.md` | Ouvrez `admin/reset-password.php` alors que la configuration SMTP est vide : un encadré explique la procédure de secours. Suivez-la : vous retrouvez l'accès au panneau. |
| 1.7 | **Documenter le stockage en clair du mot de passe SMTP** (R2). Cette limite ne peut pas être supprimée : PHPMailer a besoin du mot de passe pour s'authentifier. Signalez-la explicitement dans `admin/email-config.php` (message d'information) et dans la documentation, avec le conseil d'utiliser un mot de passe d'application dédié, jamais le mot de passe principal du compte de messagerie. | P1 | simple | `admin/email-config.php`, `DOCUMENTATION.md` | La page de configuration des e-mails affiche l'avertissement. Vous savez expliquer à l'oral pourquoi ce secret ne peut pas être haché. |
| 1.8 | **Ajouter un en-tête `Content-Security-Policy`** (R3). À faire **après** l'action 5.2 (sortir les scripts en ligne), sinon la CSP casserait le filtre du catalogue et le panneau. Commencez par une CSP permissive, puis durcissez-la progressivement. | P2 | complexe | `.htaccess`, `products.php`, `admin/_layout.php` | L'en-tête est visible dans l'onglet « Réseau » des outils de développement. La console du navigateur n'affiche aucune erreur CSP sur les trois pages publiques ni dans le panneau. |

---

## 2. Conformité et données personnelles (RGPD)

C'est le point le plus faible du site aujourd'hui : il collecte des données
personnelles (nom, e-mail, message, **adresse IP**) sans rien en dire au visiteur.

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 2.1 | **Créer une page « Mentions légales »** (R20). Éditeur du site, hébergeur (ici : WAMP en local, site non publié, à titre pédagogique), contact. Réutilisez le gabarit des pages publiques. | **P0** | simple | nouvelle page `mentions-legales.php`, pied de page des 3 pages publiques | La page s'affiche avec la même charte que le reste du site, et un lien y mène depuis le pied de page des trois pages publiques. |
| 2.2 | **Créer une page « Politique de confidentialité »** (R20, R21, R22). Indiquez : quelles données sont collectées (nom, e-mail, objet, message, adresse IP), pourquoi, combien de temps elles sont conservées, et comment demander leur suppression. | **P0** | simple | nouvelle page `confidentialite.php`, pied de page | La page liste les champs réellement enregistrés par `il_contact_save()` — ni plus, ni moins — et annonce une durée de conservation chiffrée. |
| 2.3 | **Ajouter une case de consentement au formulaire de contact** (R20). Case à cocher obligatoire, non cochée par défaut, avec un lien vers la politique de confidentialité. La validation doit être faite **côté serveur**, pas seulement par l'attribut HTML `required`. | **P0** | simple | `contact.php`, `includes/store.php` | Le formulaire est refusé si la case n'est pas cochée, même en supprimant l'attribut `required` avec les outils de développement du navigateur. |
| 2.4 | **Effacer automatiquement les anciens messages** (R21). Écrivez une fonction qui supprime les messages de contact de plus de N jours (par exemple 365), appelée à l'ouverture du module Maintenance. Affichez la durée retenue dans l'interface. | P1 | moyen | `includes/store.php`, `admin/maintenance.php` | Modifiez à la main le champ `ts` (timestamp Unix) d'un message de test pour le rendre très ancien, ouvrez Maintenance : le message a disparu de `contact.json`, les messages récents sont intacts. |
| 2.5 | **Permettre la suppression d'un seul message** (R21). Aujourd'hui, seule une purge totale est possible. Ajoutez un bouton « Supprimer ce message » par ligne, avec confirmation et jeton CSRF, comme le fait déjà la suppression d'un produit. | P1 | moyen | `admin/maintenance.php`, `includes/store.php` | Vous supprimez un message précis : lui seul disparaît de `contact.json`, les autres restent. L'action est tracée dans le journal. |
| 2.6 | **Réduire la conservation des adresses IP** (R22). Enregistrez l'IP sous forme tronquée (par exemple `192.168.1.0` au lieu de `192.168.1.42`) pour les messages de contact. Gardez l'IP complète dans le journal de sécurité, qui en a besoin pour le blocage anti-force-brute, mais annoncez-le dans la politique de confidentialité. | P1 | simple | `includes/store.php`, `confidentialite.php` | Un nouveau message enregistré dans `contact.json` contient une IP tronquée. Le blocage après 6 échecs de connexion fonctionne toujours. |

---

## 3. Stockage des données

Rappel : **le stockage en fichiers JSON est le choix du projet et il est accepté.**
Les actions ci-dessous le rendent plus fiable. Elles n'imposent aucune base de données.

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 3.1 | **Rediriger après l'envoi du formulaire de contact** (R24). Appliquez le motif « POST puis redirection » : enregistrez le message, mettez le message de succès en session avec `il_flash()`, puis redirigez vers `contact.php`. Les pages du panneau font déjà exactement cela. | **P0** | simple | `contact.php` | Envoyez un message, puis actualisez la page avec F5 : aucune boîte « renvoyer les informations », et `contact.json` ne contient qu'**un** message, pas deux. |
| 3.2 | **Écrire une fonction de modification de fichier JSON avec verrou** (R7). Créez `il_json_update($chemin, $callback)` qui ouvre le fichier une seule fois avec `flock(LOCK_EX)`, lit, applique la modification, puis réécrit. Remplacez ensuite les motifs « lire / modifier / réécrire » par cette fonction, **un fichier à la fois** en testant à chaque étape. | P1 | moyen | `includes/bootstrap.php`, puis `includes/store.php`, `includes/security.php`, `includes/products.php` | Les fonctionnalités concernées marchent toujours : envoi d'un message, échec de connexion compté, ajout d'un produit. Le code ne contient plus de lecture suivie d'une écriture sans verrou sur ces fichiers. |
| 3.3 | **Supprimer l'écriture déclenchée par une lecture** (R8). Dans `il_products_all()`, la génération d'un `id` manquant réécrit `products.json` depuis une page publique. Déplacez cette migration dans une fonction appelée uniquement depuis le panneau d'administration. | P1 | simple | `includes/products.php`, `admin/products.php` | Retirez l'`id` d'une fiche à la main, ouvrez `products.php` : la page s'affiche, `products.json` n'est **pas** modifié. Ouvrez le module Produits du panneau : l'`id` est alors régénéré. |
| 3.4 | **Paginer les journaux et les messages de contact** (R9). Affichez 25 lignes par page avec des liens « précédent / suivant », au lieu de 150 événements et 100 messages d'un coup. | P2 | moyen | `admin/maintenance.php`, `includes/bootstrap.php` | Avec plus de 25 événements au journal, la page affiche 25 lignes et une navigation fonctionnelle. Les filtres par catégorie continuent de fonctionner avec la pagination. |
| 3.5 | **Ajouter une sauvegarde locale complète et sa restauration** (R10). L'export JSON actuel retire volontairement les secrets : il ne permet pas de restaurer. Ajoutez un bouton qui copie `storage/` et `products.json` dans un dossier horodaté sur le disque local, et une page expliquant comment restaurer à la main. | P2 | moyen | `admin/maintenance.php`, `includes/store.php` | Vous déclenchez une sauvegarde : un dossier horodaté contenant les fichiers apparaît. Vous supprimez un produit, restaurez d'après la sauvegarde, et le produit est de retour. |
| 3.6 | **(Optionnel) Migrer le catalogue produits vers SQLite.** SQLite est une base de données contenue dans un seul fichier, déjà fournie avec PHP sous WAMP (extension `pdo_sqlite`) : **aucune installation, aucune dépendance, tout reste local**. Commencez par le seul catalogue produits, en gardant `products.json` comme export de secours. **À valider avec votre professeur avant de commencer**, et à ne lancer qu'une fois les jalons 1 et 2 terminés. | P2 | complexe | nouveau `includes/db.php`, `includes/products.php`, `admin/products.php`, nouveau `storage/data/infoleader.sqlite` | `admin/maintenance.php` confirme que `pdo_sqlite` est activée. Les 6 produits sont lus depuis la base, `products.php` et `index.php` les affichent à l'identique, et l'ajout, la modification et la suppression fonctionnent depuis le panneau. |

---

## 4. Qualité du code

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 4.1 | **Factoriser l'en-tête et le pied de page des pages publiques** (R16). Le même balisage est recopié trois fois. Créez `includes/layout-public.php` avec deux fonctions, sur le modèle de `admin/_layout.php` qui le fait déjà bien. | P1 | moyen | nouveau `includes/layout-public.php`, `index.php`, `products.php`, `contact.php` | Vous ajoutez une entrée au menu en modifiant **un seul** fichier, et elle apparaît sur les trois pages. L'apparence des pages est inchangée. |
| 4.2 | **Mettre en place une gestion centralisée des erreurs** (R13). Ajoutez un `set_exception_handler` et un `set_error_handler` dans `includes/bootstrap.php` : en local, affichez le détail ; sinon, affichez une page neutre. Écrivez les erreurs dans un fichier `storage/logs/php-errors.log`. | P1 | moyen | `includes/bootstrap.php`, `.gitignore` | Provoquez volontairement une erreur dans une copie de page : une page d'erreur lisible s'affiche, sans chemin de fichier complet, et l'erreur est enregistrée dans le journal. Supprimez ensuite l'erreur de test. |
| 4.3 | **Rendre visibles les échecs d'écriture silencieux** (R14). Les appels `@mkdir`, `@unlink` et `@file_put_contents` masquent les problèmes de droits. Faites-les retourner un état, et affichez un message dans le panneau quand une écriture échoue. | P1 | moyen | `includes/bootstrap.php`, `includes/store.php` | Mettez `storage/data` en lecture seule, essayez d'envoyer un message : un message d'erreur explicite s'affiche au lieu d'un faux succès. Rétablissez les droits ensuite. |
| 4.4 | **Alléger `admin/maintenance.php` et `admin/email-config.php`** (R15). Ces fichiers font 438 et 536 lignes, et mélangent traitement et affichage. Déplacez le traitement des actions POST dans des fonctions placées dans `includes/`. Faites-le **un fichier à la fois**. | P2 | complexe | `admin/maintenance.php`, `admin/email-config.php`, `includes/store.php`, `includes/mailer.php` | Les quatre outils de Maintenance et le test d'envoi d'e-mail fonctionnent exactement comme avant. Chaque page d'administration fait moins de 300 lignes. |
| 4.5 | **Activer le typage strict** (R18). Ajoutez `declare(strict_types=1);` en première ligne, **un fichier à la fois** en testant après chacun : cette directive peut révéler des conversions implicites et provoquer des erreurs. Commencez par `includes/security.php`. | P2 | moyen | tous les fichiers `.php`, un par un | Après chaque fichier traité, le site et le panneau fonctionnent sans aucune erreur `TypeError`. |

---

## 5. Front-end

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 5.1 | **Corriger l'intitulé du bouton « Commander »** (R25). Il ne commande rien : il renvoie vers le formulaire de contact. Remplacez-le par « Demander un devis » ou « Nous contacter à ce sujet », et pré-remplissez l'objet du message avec le nom du produit. Aucun paiement n'est ajouté : il n'y a ni panier ni transaction. | P1 | simple | `products.php`, `contact.php` | Un clic depuis une fiche produit ouvre `contact.php` avec le champ « objet » déjà rempli avec le nom du produit. Aucun paiement, aucun panier n'apparaît sur le site. |
| 5.2 | **Sortir les scripts en ligne dans des fichiers** (R17, prérequis de 1.8). Le filtre du catalogue est écrit directement dans `products.php` (lignes 150-203). Déplacez-le dans `script.js` ou dans un fichier `products.js`, et remplacez les attributs `style="..."` par des classes CSS. | P1 | moyen | `products.php`, `script.js`, `style.css`, `index.php`, `contact.php` | Le filtre de recherche fonctionne toujours, le compteur de résultats se met à jour, et plus aucun `<script>` ni `style="..."` ne subsiste dans les trois pages publiques. L'apparence est inchangée. |
| 5.3 | **Expliquer la déconnexion automatique** (R26). Quand la session est fermée pour inactivité ou pour changement de navigateur, l'administrateur est redirigé sans explication. Affichez un message distinct selon la cause. | P2 | simple | `includes/security.php`, `admin/login.php` | Réduisez temporairement `IL_SESSION_IDLE`, attendez l'expiration, rechargez une page du panneau : le message indique que la session a expiré pour inactivité. Remettez la valeur d'origine. |
| 5.4 | **Ajouter un thème sombre** (R23). Définissez les couleurs en variables CSS (c'est déjà en partie le cas), ajoutez un bloc `@media (prefers-color-scheme: dark)`, puis un bouton de bascule qui mémorise le choix dans `localStorage`. Traitez d'abord le site public, le panneau ensuite. | P2 | moyen | `style.css`, `script.js`, `admin/assets/admin.css` | En basculant votre système Windows en thème sombre, le site s'affiche en sombre. Le bouton de bascule fonctionne et le choix est conservé après rechargement. Les textes restent lisibles dans les deux thèmes. |
| 5.5 | **Ajouter le tri du catalogue** (R27). Tri par prix croissant, prix décroissant et nom, fait côté serveur avec un paramètre d'URL (`?tri=prix-asc`), pour que la page fonctionne sans JavaScript. | P2 | moyen | `products.php`, `includes/products.php` | `products.php?tri=prix-asc` affiche les produits du moins cher au plus cher, même avec JavaScript désactivé dans le navigateur. Le filtre de recherche continue de fonctionner. |

---

## 6. Exploitation locale

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 6.1 | **Ajouter des pages d'erreur 403 et 404 personnalisées** (R28). Une URL inexistante affiche aujourd'hui la page brute d'Apache, qui révèle sa version. Ajoutez deux directives `ErrorDocument` dans le `.htaccess` et deux pages aux couleurs du site. | P1 | simple | `.htaccess`, nouvelles pages `404.php` et `403.php` | `http://localhost/infoleader/page-inexistante` affiche votre page 404 avec le menu du site. `http://localhost/infoleader/storage/` affiche votre page 403. |
| 6.2 | **Vérifier et documenter l'état des extensions PHP requises** (prévention). Le module Maintenance contrôle déjà `openssl`, `mbstring`, `json`, `fileinfo` et `curl`. Ajoutez `pdo_sqlite` à la liste contrôlée, pour préparer l'action 3.6, et notez dans le `README.md` comment activer une extension depuis le menu de WAMP. | P2 | simple | `includes/store.php`, `README.md` | La section « Environnement » de Maintenance affiche une ligne `pdo_sqlite` avec son état réel. Le `README.md` décrit la manipulation dans WAMP. |
| 6.3 | **Mettre le projet sous Git en local** (prévention, et prérequis du dépôt GitHub du TP). `git init`, le `.gitignore` est déjà présent et correct, puis un commit par action terminée. Cela remplace la copie manuelle du dossier avant chaque modification. | P1 | simple | racine du projet, `.gitignore` (déjà prêt) | `git status` ne liste ni `storage/`, ni `vendor/`, ni `composer.phar`. Vous pouvez annuler une modification avec `git checkout` et retrouver le site en état de marche. |

---

## 7. Documentation et recette

| # | Action | Priorité | Complexité | Fichiers concernés | Critère d'acceptation |
|---|---|---|---|---|---|
| 7.1 | **Écrire une procédure de recette manuelle** (R12). Le site n'a aucun test. Écrivez la liste des 15 à 20 vérifications à faire après chaque modification : les 3 pages publiques, l'envoi d'un message, la connexion, un mauvais mot de passe, l'ajout d'un produit, la déconnexion… C'est l'action la plus rentable de cette section. | P1 | simple | nouveau `RECETTE.md` | La liste existe, chaque ligne est une action concrète avec son résultat attendu, et vous l'avez déroulée une fois en entier sans trouver d'anomalie. |
| 7.2 | **Conserver les consignes données à l'agent IA** (demandé en bonus par le TP). Recopiez dans un fichier les instructions exactes que vous avez données, et ce que vous avez fait corriger. | P1 | simple | nouveau `PROMPTS.md` | Le fichier contient les consignes d'analyse, de génération du plan et d'évolution, ainsi que vos demandes de correction. |
| 7.3 | **Tenir `DOCUMENTATION.md` à jour** (R12). Après chaque action terminée, corrigez la section concernée et retirez le risque de la liste des limites connues. Une documentation fausse est plus dangereuse qu'une documentation absente. | P1 | simple | `DOCUMENTATION.md`, `TODO_LIST.md` | Pour chaque case cochée de ce document, la section correspondante de `DOCUMENTATION.md` décrit le nouveau comportement. |
| 7.4 | **(Optionnel) Écrire quelques tests en PHP pur** (R12). Un simple fichier `tests/verifs.php`, exécuté en ligne de commande, qui vérifie une dizaine de fonctions de validation (`il_password_validate`, `il_contact_validate`, `il_product_validate`) avec des `if` et des `echo`. **Sans PHPUnit** : aucune dépendance à installer. | P2 | moyen | nouveau dossier `tests/`, `.htaccess` | `php tests/verifs.php` affiche la liste des vérifications et « 0 échec ». Introduisez volontairement une faute dans une règle de validation : le test la signale. |

---

## Séquencement par jalons

Traitez les jalons dans l'ordre. **Une action à la fois**, testée avant de passer à
la suivante.

### Jalon 1 — Corriger ce qui peut faire perdre des données ou des messages

Le plus urgent. Quatre actions courtes, toutes réalisables en une séance.

- [ ] 3.1 — Rediriger après l'envoi du formulaire de contact (**P0**, simple)
- [ ] 1.3 — Corriger les appels à `filemtime()` non protégés (**P0**, simple)
- [ ] 1.1 — Limiter le nombre d'envois du formulaire de contact (**P0**, moyen)
- [ ] 1.6 — Documenter la procédure de secours du compte administrateur (**P0**, simple)

### Jalon 2 — Se mettre en conformité (RGPD)

La plus grosse lacune du site, et la plus facile à combler : ce sont surtout des
pages de texte.

- [ ] 2.1 — Page « Mentions légales » (**P0**, simple)
- [ ] 2.2 — Page « Politique de confidentialité » (**P0**, simple)
- [ ] 2.3 — Case de consentement au formulaire de contact (**P0**, simple)

### Jalon 3 — Sécuriser le reste et se donner un filet de sécurité

- [ ] 6.3 — Mettre le projet sous Git en local (P1, simple)
- [ ] 7.1 — Écrire la procédure de recette manuelle (P1, simple)
- [ ] 1.4 — Empêcher l'accès direct à `products.json` (P1, simple)
- [ ] 1.5 — Restreindre le vhost `infoleader.local` (P1, simple)
- [ ] 1.2 — Ajouter un piège anti-robot simple (P1, simple)
- [ ] 1.7 — Documenter le stockage en clair du mot de passe SMTP (P1, simple)
- [ ] 6.1 — Pages d'erreur 403 et 404 personnalisées (P1, simple)

### Jalon 4 — Fiabiliser le stockage en fichiers

- [ ] 3.3 — Supprimer l'écriture déclenchée par une lecture (P1, simple)
- [ ] 2.6 — Réduire la conservation des adresses IP (P1, simple)
- [ ] 2.4 — Effacer automatiquement les anciens messages (P1, moyen)
- [ ] 2.5 — Permettre la suppression d'un seul message (P1, moyen)
- [ ] 3.2 — Fonction de modification de fichier JSON avec verrou (P1, moyen)

### Jalon 5 — Rendre le code plus facile à maintenir

- [ ] 5.1 — Corriger l'intitulé du bouton « Commander » (P1, simple)
- [ ] 4.1 — Factoriser l'en-tête et le pied de page publics (P1, moyen)
- [ ] 4.2 — Gestion centralisée des erreurs (P1, moyen)
- [ ] 4.3 — Rendre visibles les échecs d'écriture silencieux (P1, moyen)
- [ ] 5.2 — Sortir les scripts en ligne dans des fichiers (P1, moyen)
- [ ] 7.3 — Tenir `DOCUMENTATION.md` à jour (P1, simple, en continu)
- [ ] 7.2 — Conserver les consignes données à l'agent IA (P1, simple, en continu)

### Jalon 6 — Confort et finition (optionnel)

- [ ] 5.3 — Expliquer la déconnexion automatique (P2, simple)
- [ ] 6.2 — Contrôler et documenter `pdo_sqlite` (P2, simple)
- [ ] 5.4 — Thème sombre (P2, moyen)
- [ ] 5.5 — Tri du catalogue (P2, moyen)
- [ ] 3.4 — Paginer les journaux et les messages (P2, moyen)
- [ ] 3.5 — Sauvegarde locale complète et restauration (P2, moyen)
- [ ] 7.4 — Quelques tests en PHP pur (P2, moyen)
- [ ] 4.5 — Activer le typage strict (P2, moyen)

### Jalon 7 — Évolutions de structure (à ne lancer qu'ensuite)

À faire seulement si tout le reste est terminé et testé. Sauvegardez avant.

- [ ] 4.4 — Alléger `maintenance.php` et `email-config.php` (P2, complexe)
- [ ] 1.8 — En-tête `Content-Security-Policy` (P2, complexe, après 5.2)
- [ ] 3.6 — Migration du catalogue vers SQLite (P2, complexe, **à valider avec votre professeur**)

---

## Volontairement hors périmètre

Ces sujets sont écartés, et pour des raisons précises.

| Sujet | Pourquoi il est écarté |
|---|---|
| Paiement en ligne | Interdit dans le cadre du TP. Un étudiant ne doit pas utiliser le système bancaire réel. Si un paiement était demandé plus tard, il devrait rester une **simulation** : aucune passerelle, aucune clé d'API bancaire, aucune saisie de carte. |
| Panier et commandes | Fonctionnalité lourde, sans intérêt pour un site vitrine, et porte d'entrée vers le point précédent. |
| Mise en ligne, VPS, nom de domaine | Hors contrainte : le site tourne uniquement en local sous WAMP. |
| Docker, Nginx, PHP-FPM | Hors contrainte : WAMP fournit déjà Apache et PHP. |
| Déploiement automatisé, intégration continue | Sans objet : il n'y a pas de serveur de destination. |
| Framework (Laravel, Symfony) et npm | Nouvelle dépendance lourde, et réécriture complète du site. **Sous accord du professeur uniquement**, et hors de portée de ce TP. |
| PHPUnit | Nouvelle dépendance Composer. L'action 7.4 atteint le même objectif avec du PHP pur, sans rien installer. |
| MySQL | Non nécessaire : le stockage en fichiers JSON convient, et SQLite (action 3.6) suffit si une base devient souhaitable. |
| HTTPS | Sans objet en local sur `http://localhost`. Le code est déjà prêt : `il_is_https()` active le cookie `secure` automatiquement si le site passe un jour en HTTPS. |
| Service externe de CAPTCHA | Dépendance à un tiers et envoi de données du visiteur à ce tiers. L'action 1.2 obtient un résultat suffisant sans aucun service externe. |
