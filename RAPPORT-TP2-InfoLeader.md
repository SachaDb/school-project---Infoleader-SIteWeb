# TP 2 — Amélioration du site web professionnel « InfoLeader »

> Panneau d'administration sécurisé, envoi d'e-mails SMTP, gestion du catalogue
> et refonte visuelle d'un site vitrine PHP existant.

**Environnement** : Windows 11 · WAMP · Apache 2.4.65 · PHP 8.3.28 · sans base de données
**Dépendance** : PHPMailer 7.1.1 (Composer)
**Volume ajouté** : ~2 800 lignes (logique privée) + ~3 700 lignes (panneau admin) + ~2 250 lignes (site public)

---

## Sommaire

1. [Point de départ](#1-point-de-départ)
2. [Architecture retenue](#2-architecture-retenue)
3. [Module Connexion et anti-force-brute](#3-module-connexion-et-anti-force-brute)
4. [Module Mot de passe oublié](#4-module-mot-de-passe-oublié)
5. [Module Configuration des e-mails](#5-module-configuration-des-e-mails)
6. [Modules Mot de passe et Utilisateur](#6-modules-mot-de-passe-et-utilisateur)
7. [Module Maintenance](#7-module-maintenance)
8. [Gestion du catalogue produits](#8-gestion-du-catalogue-produits)
9. [Refonte visuelle du site vitrine](#9-refonte-visuelle-du-site-vitrine)
10. [Sécurité : synthèse](#10-sécurité--synthèse)
11. [Plan de tests et résultats](#11-plan-de-tests-et-résultats)
12. [Difficultés rencontrées](#12-difficultés-rencontrées)
13. [Installation et configuration](#13-installation-et-configuration)
14. [Bilan et reste à faire](#14-bilan-et-reste-à-faire)

---

## 1. Point de départ

Le TP 1 avait produit un site vitrine fonctionnel mais très simple.

| Élément | État initial |
|---|---|
| Pages | `index.php`, `products.php`, `contact.php` |
| Technologies | PHP, HTML5, CSS, JavaScript — **aucun framework** |
| Données | `products.json` (fichier plat), **aucune base de données** |
| Authentification | **aucune** : pas de session, pas de compte, pas de dossier `admin/` |
| Formulaire de contact | validation JavaScript puis `$success = true` → **envoi simulé, aucun stockage** |
| Sécurité | aucune protection CSRF, aucun échappement du POST, aucune journalisation |

Deux incohérences relevées lors de l'analyse :

- `index.php` utilisait `style.css` (bleu `#0056b3`, vert `#4caf50`) alors que
  `products.php` et `contact.php` embarquaient **leur propre CSS en ligne** avec
  une autre palette (`#1e3c72` → `#6dd5ed`) — donc trois pages, deux chartes et
  du CSS dupliqué.
- Apache était **arrêté** au démarrage du TP ; il a fallu le lancer
  (`net start wampapache64`) avant toute vérification.

**Vérifications d'environnement effectuées avant d'écrire la moindre ligne :**
extensions `openssl`, `curl`, `mbstring`, `fileinfo`, `sockets` actives dans le
`phpForApache.ini` ; `AllowOverride All` actif sur les deux vhosts (donc
`.htaccess` fonctionnel) ; Composer absent du système ; MySQL arrêté et non requis.

---

## 2. Architecture retenue

Le TP impose de respecter l'architecture existante plutôt que d'imposer une
technologie. Le projet n'utilisant **pas de base de données**, les données sont
stockées en fichiers, dans un dossier privé.

```
infoleader/
├── index.php              Accueil (refonte)
├── products.php           Catalogue (refonte, rendu serveur)
├── contact.php            Contact (refonte + traitement serveur réel)
├── style.css              Feuille unique des 3 pages publiques
├── script.js              Animations (scroll reveal, compteurs, menu mobile)
├── products.json          Catalogue produits (+ champ `id`)
├── .htaccess              Blocage composer/README, en-têtes de sécurité, cache
├── .gitignore             Exclut storage/, vendor/, composer.phar
│
├── admin/                 Panneau d'administration
│   ├── index.php          Point d'entrée (redirige selon l'état de session)
│   ├── setup.php          Initialisation unique du compte administrateur
│   ├── login.php          Connexion
│   ├── logout.php         Déconnexion (lien porteur d'un jeton CSRF)
│   ├── reset-password.php Parcours « Mot de passe oublié ? » en 3 étapes
│   ├── dashboard.php      Tableau de bord + 4 outils + infos du compte
│   ├── products.php       Gestion du catalogue (CRUD + upload d'image)
│   ├── email-config.php   Configuration SMTP + test d'envoi réel
│   ├── password.php       Modification du mot de passe
│   ├── user.php           Modification de l'adresse e-mail
│   ├── maintenance.php    Système, journaux, sauvegarde, purge tmp
│   ├── _layout.php        Gabarit commun
│   └── assets/            admin.css, admin.js
│
├── includes/              Logique privée — interdite en HTTP
│   ├── .htaccess          Require all denied
│   ├── bootstrap.php      Chemins, session, CSRF, journalisation, utilitaires
│   ├── security.php       Mots de passe, limitation des tentatives, codes
│   ├── mailer.php         PHPMailer : Gmail / Brevo, modèles d'e-mail
│   ├── products.php       Catalogue : validation, upload, écriture JSON
│   └── store.php          Messages de contact, contrôles système, export JSON
│
├── storage/               Données et secrets — interdits en HTTP
│   ├── .htaccess          Require all denied
│   ├── config/            admin.php (e-mail + hash), mail.php (SMTP)
│   ├── data/              ratelimit.json, resets.json, contact.json
│   ├── logs/              auth.json, mail-debug.log
│   └── tmp/               Fichiers temporaires purgeables
│
├── vendor/                PHPMailer (Composer)
└── assets/                Images (+ .htaccess interdisant tout script)
```

### Choix de conception notables

**Les fichiers de configuration sont des `.php` qui retournent un tableau.**
Même si la protection `.htaccess` disparaissait, une requête HTTP directe
*exécuterait* le fichier et n'afficherait aucun secret. Les fichiers de données
restent en `.json` et sont protégés par `.htaccess` — protection **vérifiée par
une vraie requête HTTP** (403 obtenu), pas supposée.

**Le stockage est déplaçable hors de la racine web** via la variable
d'environnement `INFOLEADER_STORAGE` (directive `SetEnv` d'Apache).

---

## 3. Module Connexion et anti-force-brute

### Création du compte

Aucun compte n'est livré et **aucun mot de passe n'est codé en dur**.
`admin/setup.php` ne fonctionne que tant qu'aucun compte n'existe, puis se
désactive de lui-même (message « Initialisation déjà effectuée »).

### Connexion

- Message d'erreur **identique quelle que soit la cause** : l'existence d'un
  compte n'est jamais révélée.
- `password_verify()` est exécuté même sans compte correspondant (hash factice),
  afin de ne pas trahir le compte par le temps de réponse.
- Session durcie : `HttpOnly`, `SameSite=Lax`, `use_strict_mode`,
  **identifiant régénéré** à la connexion, expiration après 30 min d'inactivité,
  liaison à l'empreinte du navigateur.

### Limitation des tentatives

Le compteur est stocké **côté serveur** dans `storage/data/ratelimit.json` :
recharger la page, vider ses cookies ou recréer une session ne le remet pas à zéro.

| Échecs | Conséquence |
|---|---|
| 1 | attente de 3 s |
| 2 | 6 s |
| 3 | 12 s |
| 4 | 24 s |
| 5 | 60 s |
| 6 | **blocage 15 min** |
| 7 | 30 min |
| 8 | 1 h |
| 9 | 2 h |
| 10 | 4 h |
| 11 et + | 8 h (plafond) |

Deux clés sont suivies en parallèle : l'**adresse IP** (blocage dès 6 échecs,
elle vise l'attaquant) et le **compte** visé, dont l'identifiant est haché en
SHA-256 et dont le seuil est volontairement plus élevé (10 échecs) pour ne pas
verrouiller facilement un utilisateur légitime depuis une autre adresse.
Les compteurs retombent à zéro après 24 h sans échec, ou immédiatement après une
connexion réussie.

### Tableau de bord

Affiche l'état global du site, les **quatre outils** exigés (Configuration des
e-mails, Mot de passe, Utilisateur, Maintenance) et les informations réelles du
compte : adresse e-mail, date de création, connexion précédente avec son IP,
adresse IP courante, délai d'expiration de session.

---

## 4. Module Mot de passe oublié

Parcours complet en trois étapes, avec l'état conservé en session.

| Exigence | Mise en œuvre |
|---|---|
| Code de 8 chiffres | `random_int(0, 99999999)` complété à gauche par des zéros |
| Généré côté serveur | oui, jamais côté client |
| Expiration 5 minutes | `expires_at = time() + 300` |
| Usage unique | marqué `used` puis `code_hash` vidé après succès |
| Stocké sous forme protégée | **haché avec `password_hash()`** — jamais en clair |
| Invalidé si remplacé | une nouvelle demande écrase le code précédent |
| Demandes limitées | 3 maximum par fenêtre de 15 minutes |
| Essais limités | 5 vérifications maximum par code |
| Message générique | « Si ce compte est éligible, un code vient de lui être envoyé » |

Après succès : le mot de passe est rehaché, `auth_epoch` est incrémenté — ce qui
**invalide toutes les sessions ouvertes** — l'événement est journalisé, et ni le
mot de passe ni le code n'apparaissent dans les journaux.

---

## 5. Module Configuration des e-mails

Deux préréglages sont proposés, plus une saisie manuelle.

### Gmail / Google Workspace (préréglage par défaut)

| Champ | Valeur |
|---|---|
| Serveur | `smtp.gmail.com` |
| Port | 587 (STARTTLS) ou 465 (SSL/TLS) |
| Identifiant | adresse Gmail complète |
| Mot de passe | **mot de passe d'application** de 16 caractères (validation en deux étapes requise) |
| Expéditeur | obligatoirement l'adresse du compte authentifié |

Un contrôle dédié signale si l'expéditeur diffère de l'identifiant Gmail.

### Brevo

`smtp-relay.brevo.com:587`, STARTTLS, identifiant `xxxxxxx@smtp-brevo.com`,
clé SMTP créée dans *SMTP & API → Clés SMTP*, expéditeur validé dans *Expéditeurs*.

### Mode PHP mail

Les champs sont présents pour un hébergement compatible, mais le mode est
**annoncé comme indisponible** si le `php.ini` ne déclare ni `sendmail_path` ni
`SMTP` — ce qui est le cas sous WAMP. Conformément à la consigne, rien n'est simulé.

### Sécurité des secrets

- Le mot de passe SMTP **n'est jamais réaffiché** après enregistrement : le champ
  reste vide et porte la mention « enregistré — laisser vide pour le conserver ».
- Les identifiants sont **masqués** dans le journal de débogage SMTP
  (les échanges `AUTH LOGIN/PLAIN` en base64 sont remplacés par `[masqué]`).
- Ils sont **retirés des messages d'erreur** avant affichage.
- Le fichier `storage/config/mail.php` est hors accès HTTP et exclu du dépôt Git.

### Bouton « Tester l'envoi »

Envoie un e-mail réel au destinataire configuré. Aucune réussite n'est affichée
si le serveur refuse. En cas d'échec, le message est enrichi d'une **piste de
résolution** selon le code SMTP : 535/534 → mot de passe d'application,
550/553 → expéditeur non autorisé, échec de handshake → pare-feu, etc.

---

## 6. Modules Mot de passe et Utilisateur

### Mot de passe

Trois champs : actuel, nouveau, confirmation. Règles affichées **avant**
validation et vérifiées côté serveur, indépendamment du JavaScript :

- **20 caractères minimum**
- au moins une majuscule, une minuscule, un chiffre, un caractère spécial
- confirmation identique
- différent du mot de passe actuel

Hachage par `password_hash()` / `PASSWORD_DEFAULT`. Après modification, les
autres sessions sont invalidées et l'événement est journalisé **sans le mot de passe**.

### Utilisateur

Modification de l'adresse e-mail après vérification du mot de passe actuel.
Contrôles serveur : syntaxe (`FILTER_VALIDATE_EMAIL`), deux saisies identiques,
adresse différente de l'actuelle, champs non vides. Le tableau de bord affiche
immédiatement la nouvelle adresse, qui devient l'identifiant de connexion.

Le site ne disposant d'aucun mécanisme de vérification d'adresse, la nouvelle
adresse **n'est pas marquée comme vérifiée** : l'interface le précise explicitement.

---

## 7. Module Maintenance

### A. Système

Indicateur global **calculé à partir des contrôles réellement exécutés** : un
seul échec critique suffit à sortir de l'état vert. Quatre sous-sections :

1. **Fichiers temporaires** — existence, droits, liste réelle des fichiers avec
   nom, taille et date. Les chemins système complets ne sont jamais exposés.
2. **Environnement** — version de PHP, état des extensions, PHPMailer, mode
   d'envoi, et **test réel d'accès sortant** vers le port SMTP.
3. **Droits d'écriture** — chaque répertoire nécessaire.
4. **Fichiers de configuration** — présence, validité, protection `.htaccess`, et
   **vérification par une vraie requête HTTP locale** que `storage/` répond bien 403.

### B. Journaux

Journal de connexion filtrable par catégorie : connexions réussies, échecs,
blocages, événements de sécurité, modifications du compte, catalogue produits.
Les filtres agissent réellement sur les données affichées.

Les **messages de contact** sont consultables (date, nom, e-mail, objet, message) :
le formulaire public les enregistre désormais réellement.

Les deux purges passent par une fenêtre de confirmation précisant les données
concernées, et sont protégées contre les requêtes forgées.

### C. Sauvegarde

Export JSON généré côté serveur, avec confirmation préalable et mention explicite
que les données restent locales. Contenu : configuration de l'application, compte
administrateur (non sensible), configuration e-mail **sans secret**, paramètres de
sécurité et de limitation, catalogue produits, statistiques.

**Exclus de l'export** (vérifié) : hash du mot de passe administrateur, mot de
passe SMTP, codes de réinitialisation, données de session.

### D. Purge de storage/tmp

- Liste établie **côté serveur** : aucun chemin ne vient du navigateur.
- Chaque chemin est validé par `realpath()` et doit être un **enfant direct** de
  `storage/tmp`.
- Liens symboliques et sous-répertoires ignorés, **aucune suppression récursive**.
- Fichiers de protection (`index.php`, `.htaccess`) conservés.

---

## 8. Gestion du catalogue produits

Ajout hors cahier des charges initial, demandé en cours de projet.

Page **Admin → Produits** : ajout, modification, réordonnancement (l'ordre
d'affichage public), suppression avec confirmation, et quatre indicateurs
(nombre de fiches, prix moyen, visuels manquants, lien vers le rendu public).

Le catalogue reste `products.json` — **aucune duplication de données** : c'est le
fichier que lisait déjà la page publique. Seul un champ `id` a été ajouté, avec
migration automatique des fiches existantes.

### Sécurité de l'upload d'images

| Contrôle | Détail |
|---|---|
| Extension | JPG, JPEG, PNG, WEBP, GIF uniquement |
| Type MIME réel | lu par `finfo` et **devant concorder** avec l'extension |
| Image décodable | `getimagesize()` — un fichier PHP renommé en `.jpg` est refusé |
| Nom de fichier | **entièrement régénéré** : aucune valeur du navigateur n'est réutilisée comme chemin |
| Taille | 3 Mo maximum |
| Exécution | `assets/.htaccess` interdit tout script dans le dossier des visuels |
| Suppression | confinée à `assets/products` par `realpath()`, et épargne une image encore utilisée par une autre fiche |

---

## 9. Refonte visuelle du site vitrine

Contrainte respectée : **ni les couleurs ni le nom n'ont changé**. Seules les
valeurs déjà présentes dans le projet sont utilisées (`#0056b3`, `#1e3c72`,
`#2a5298`, `#4a90d9`, `#6dd5ed`, `#4caf50`, `#2d2d2d`, `#f4f7fc`).

- **Une seule feuille de style** pour les trois pages : le CSS dupliqué en ligne
  dans `products.php` et `contact.php` a été supprimé.
- **Accueil** : bannière plein écran en dégradé avec trame fine et halos animés,
  badge pulsé, entrée en cascade du titre, bandeau de chiffres à compteurs animés,
  quatre cartes « engagements », section de présentation en deux colonnes,
  produits phares **issus du catalogue réel**, bandeau d'appel à l'action,
  pied de page en trois colonnes.
- **Produits** : fiches **rendues côté serveur** — la page fonctionne sans
  JavaScript — filtre instantané avec compteur de résultats, zoom au survol.
- **Animations** : apparitions au défilement (`IntersectionObserver`), en-tête qui
  se densifie, menu déployable sur mobile, reflet sur les boutons. Tout est
  neutralisé si le système demande une réduction des animations.

### Quatre défauts visuels identifiés en observant le rendu réel

| Défaut | Cause | Correction |
|---|---|---|
| La 4ᵉ carte passait seule à la ligne | 4 × 270 px + gouttières > conteneur 1140 px | colonnes ramenées à 248 px |
| Texte fantôme derrière le titre | `banner.jpg` contient elle-même du texte incrusté | bannière en dégradé plein ; l'image reste plus bas |
| Titres masqués par l'en-tête fixe | pas de `scroll-padding-top` | marge de défilement de 92 px |
| Sections potentiellement invisibles | `.reveal` posait `opacity:0` même si le JS échouait | masquage conditionné à une classe `js` |

### Mise en cache des fichiers statiques

Apache ne renvoyait **aucun en-tête `Cache-Control`** pour les fichiers `.css` et
`.js`. Le navigateur pouvait donc servir une ancienne feuille de style avec le
nouveau HTML, ce qui cassait complètement la mise en page — un symptôme trompeur,
puisqu'il n'apparaissait que sur les postes ayant déjà visité le site.

Deux corrections : les pages référencent `style.css?v=<date de modification>`
(l'URL change automatiquement à chaque édition) et le `.htaccess` impose
`Cache-Control: no-cache, must-revalidate`.

---

## 10. Sécurité : synthèse

| Domaine | Mise en œuvre |
|---|---|
| Authentification | obligatoire sur toutes les pages du panneau |
| Sessions | HttpOnly, SameSite=Lax, `use_strict_mode`, régénération, expiration, liaison navigateur |
| CSRF | jeton sur **tous** les formulaires, y compris le contact public ; requête sans jeton → **HTTP 403** |
| XSS | échappement systématique par `htmlspecialchars()` à l'affichage |
| Injection SQL | sans objet : aucune base de données |
| Mots de passe | `password_hash()` / `password_verify()`, 20 caractères minimum |
| Force brute | délais progressifs puis blocages, stockés côté serveur |
| Secrets | hors racine HTTP, hors Git, masqués dans les journaux et les erreurs |
| Chemins de fichiers | `realpath()` et confinement au répertoire autorisé |
| Uploads | extension + MIME réel + décodage, nom régénéré, exécution interdite |
| Journalisation | événements sensibles tracés, **jamais** de mot de passe ni de code |

---

## 11. Plan de tests et résultats

Tous les tests ci-dessous ont été **exécutés** (requêtes HTTP réelles ou appels
PHP), pas seulement relus.

### Connexion

| Test | Résultat |
|---|---|
| Création du compte puis désactivation de `setup.php` | ✅ |
| Connexion valide + régénération de l'identifiant de session | ✅ identifiant modifié |
| Refus d'un mauvais mot de passe | ✅ |
| Délai de 3 s appliqué après un échec | ✅ la tentative suivante est refusée |
| Escalade 3 → 6 → 12 → 24 → 60 s | ✅ vérifiée sur 12 échecs successifs |
| Blocages 15 min → 8 h | ✅ |
| Message identique pour un e-mail inexistant | ✅ |
| Limitation incontournable (cookies vidés) | ✅ compteur côté serveur |
| Accès au panneau sans session | ✅ redirection vers `login.php` |
| Déconnexion | ✅ session détruite |

### Mot de passe oublié

| Test | Résultat |
|---|---|
| Code de 8 chiffres, haché, valable 300 s | ✅ |
| Refus d'un code incorrect (essais décomptés) | ✅ |
| Refus d'un code expiré | ✅ |
| Refus d'un code déjà utilisé | ✅ |
| Changement effectif + reconnexion | ✅ |
| Invalidation des sessions antérieures | ✅ 2 sessions sur 2 |
| Réception réelle de l'e-mail | ⚠️ **non vérifiée** (voir §12) |

### Mot de passe et utilisateur

| Test | Résultat |
|---|---|
| Refus d'un mot de passe actuel incorrect | ✅ |
| Refus d'un nouveau mot de passe trop court | ✅ |
| Refus d'une confirmation différente | ✅ |
| Refus d'un mot de passe identique à l'actuel | ✅ |
| Acceptation d'un mot de passe conforme | ✅ reconnexion réussie |
| Refus d'une adresse invalide / identique / non confirmée / vide | ✅ 4 cas sur 4 |
| Mise à jour du tableau de bord | ✅ |

### Maintenance

| Test | Résultat |
|---|---|
| État système cohérent | ✅ |
| Affichage des fichiers temporaires | ✅ |
| Purge de `storage/tmp` | ✅ 2 fichiers supprimés, `index.php` et sous-dossier préservés |
| Filtres des journaux | ✅ 18 / 3 / 2 / 13 / 2 lignes selon la catégorie |
| Confirmation avant purge | ✅ |
| Export JSON valide (7 sections) | ✅ |
| Absence de secrets dans l'export | ✅ mot de passe SMTP → `[retiré de l'export]`, aucun hash |

### Catalogue produits

| Test | Résultat |
|---|---|
| Création avec upload réel | ✅ fichier renommé, prix `19,90` → `19.9` |
| Refus : nom vide / prix non numérique / sans visuel | ✅ |
| **Fichier PHP renommé en `.jpg`** | ✅ refusé, rien écrit sur le disque |
| Modification (visuel conservé) | ✅ |
| Réordonnancement | ✅ |
| Répercussion sur la page publique | ✅ |
| Suppression fiche + visuel | ✅ images d'origine intactes |

### Vérifications techniques

| Test | Résultat |
|---|---|
| `php -l` sur l'ensemble des fichiers | ✅ 0 erreur |
| `node --check` sur les fichiers JavaScript | ✅ |
| Erreurs PHP sur les pages admin et publiques | ✅ 0 |
| Erreurs console navigateur | ✅ 0 |
| `storage/`, `includes/`, `vendor/`, `composer.phar` en HTTP | ✅ 403 |
| Non-régression des pages vitrine | ✅ HTTP 200 |
| Encodage UTF-8 | ✅ valide sur tous les fichiers |

---

## 12. Difficultés rencontrées

### Envoi SMTP vers Brevo impossible depuis le poste

Le diagnostic a montré que la connexion TCP aboutit (`220 smtp-relay.brevo.com
ESMTP Service Ready`) mais que le chiffrement échoue :

```
CLIENT -> SERVER: STARTTLS
SERVER -> CLIENT: 220 2.0.0 Ready to start TLS
stream_socket_enable_crypto(): SSL: Handshake timed out
```

Les ports 587 (STARTTLS) **et** 465 (SSL direct) échouent tous deux, alors
qu'OpenSSL est actif : le trafic SMTP sortant est filtré sur ce poste.
Le même test vers `smtp.gmail.com:587` **réussit** (handshake en 0,6 s), ce qui a
conduit à faire de Gmail le préréglage par défaut.

Conséquence assumée : l'intégration est complète et testée jusqu'au dernier
maillon, mais **aucun e-mail n'a été réellement reçu** faute de mot de passe
d'application. Ce point est documenté plutôt que masqué par une fausse réussite.

### Deux bugs découverts par les tests

1. **Images orphelines** : une validation refusée déplaçait quand même le fichier
   téléversé dans `assets/products`. Corrigé — l'upload est annulé en cas de
   refus, revérifié (6 fichiers avant comme après un échec).
2. **Blocage de la page pendant l'envoi** : sans borne de temps, un handshake TLS
   filtré figeait la page plus de deux minutes. Corrigé par `Timeout` et
   `Timelimit` (cette dernière étant une propriété de l'objet `SMTP`, pas de
   `PHPMailer` — une première version créait une propriété dynamique signalée
   comme dépréciée en PHP 8.3).

### Code HTTP non standard

`http_response_code(419)` provoquait une **erreur 500** côté Apache. Remplacé par
un **403**, correctement relayé.

---

## 13. Installation et configuration

```bash
# 1. Démarrer Apache
net start wampapache64

# 2. Installer les dépendances (si vendor/ est absent)
cd C:\wamp64\www\infoleader
C:\wamp64\bin\php\php8.3.28\php.exe composer.phar install

# 3. Ouvrir le site
http://localhost/infoleader/
```

MySQL n'est pas nécessaire.

### Créer le compte administrateur (une seule fois)

1. Ouvrir `http://localhost/infoleader/admin/` → redirection vers `setup.php`.
2. Saisir l'adresse e-mail (deux fois) et le mot de passe (deux fois).
3. Respecter : 20 caractères minimum, majuscule, minuscule, chiffre, caractère spécial.
4. Valider : `setup.php` se désactive automatiquement.

Pour repartir de zéro : supprimer `storage/config/admin.php`, puis relancer `setup.php`.

### Configurer l'envoi d'e-mails (Gmail)

1. Activer la validation en deux étapes sur le compte Google.
2. Créer un mot de passe d'application sur `myaccount.google.com/apppasswords`.
3. Dans **Configuration des e-mails** : préréglage Gmail, identifiant = adresse
   Gmail complète, mot de passe = les 16 caractères **sans espaces**, expéditeur =
   la même adresse Gmail.
4. Enregistrer, puis **Tester l'envoi**.

---

## 14. Bilan et reste à faire

### Terminé et vérifié

Connexion et anti-force-brute · tableau de bord · mot de passe oublié (3 étapes) ·
configuration des e-mails (Gmail / Brevo / PHP mail) · module Mot de passe ·
module Utilisateur · Maintenance (système, journaux, sauvegarde, purge) ·
gestion du catalogue produits · refonte visuelle du site vitrine.

### Partiellement terminé

**Envoi d'e-mail réel** : code complet et testé jusqu'à la couche SMTP, mais la
réception n'a pas pu être confirmée (trafic SMTP filtré vers Brevo, et mot de
passe d'application Gmail non fourni au moment de la rédaction).

### Reste à faire

- Saisir le mot de passe d'application Gmail, puis retester le parcours complet
  « Mot de passe oublié ».
- Remplacer les **visuels produits**, qui sont les images de substitution du TP 1
  (aplats colorés portant le nom du produit) — remplaçables en quelques secondes
  via *Admin → Produits → Modifier → Visuel*.
- Harmoniser les libellés du panneau d'administration, encore écrits sans accents.

### Écarts assumés avec l'énoncé

| Écart | Justification |
|---|---|
| Pas de base de données | le projet n'en utilisait aucune ; l'énoncé autorise à adapter à l'architecture réelle |
| `storage/` dans la racine web | protégé par `.htaccess` (403 vérifié) **et** configurations en `.php` ; déplaçable via `INFOLEADER_STORAGE` |
| Page dédiée plutôt qu'une fenêtre pour le mot de passe oublié | l'énoncé laisse le choix |
| CSRF refusé en 403 au lieu de 419 | Apache renvoie une erreur 500 sur un code non standard |
| Gmail proposé avant Brevo | seul fournisseur joignable depuis le poste de développement |
