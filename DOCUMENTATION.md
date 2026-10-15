# Documentation technique — InfoLeader

Analyse du site web présent dans `C:\wamp64\www\infoleader`.
Document rédigé à partir du code réellement présent dans le projet.
Chaque affirmation renvoie au fichier qui la justifie.

---

## Table des matières

1. [Présentation générale du site](#1-présentation-générale-du-site)
2. [Stack technique](#2-stack-technique)
3. [Arborescence du projet](#3-arborescence-du-projet)
4. [Points d'entrée et routage](#4-points-dentrée-et-routage)
5. [Stockage des données](#5-stockage-des-données)
6. [Authentification, sessions et sécurité](#6-authentification-sessions-et-sécurité)
7. [Front-end](#7-front-end)
8. [Intégrations externes](#8-intégrations-externes)
9. [Configuration](#9-configuration)
10. [Points forts](#10-points-forts)
11. [Limites connues et risques](#11-limites-connues-et-risques)

---

## 1. Présentation générale du site

InfoLeader est le site vitrine d'une entreprise informatique fictive.
Il présente du matériel informatique (claviers, souris, écrans, casques, webcams, hubs).
Il fonctionne en local sous WAMP. Il n'est pas déployé en ligne.

Le site comporte deux parties distinctes.

**Partie publique** (3 pages) :

| Page | Fichier | Rôle |
|---|---|---|
| Accueil | `index.php` | Bannière animée, engagements, chiffres animés, 3 produits phares, appel à l'action |
| Catalogue | `products.php` | Liste des produits avec filtre de recherche instantané |
| Contact | `contact.php` | Formulaire de contact validé et enregistré côté serveur |

**Partie administration** (`admin/`, 6 modules accessibles après connexion) :

| Module | Fichier | Rôle |
|---|---|---|
| Tableau de bord | `admin/dashboard.php` | État du site, informations du compte, statistiques |
| Produits | `admin/products.php` | Ajout, modification, réordonnancement, suppression des fiches |
| Configuration des e-mails | `admin/email-config.php` | Paramètres SMTP et envoi d'un e-mail de test réel |
| Mot de passe | `admin/password.php` | Changement du mot de passe administrateur |
| Utilisateur | `admin/user.php` | Changement de l'adresse e-mail du compte |
| Maintenance | `admin/maintenance.php` | Contrôles système, journaux, export JSON, purge de `storage/tmp` |

Trois pages d'administration sont accessibles sans être connecté :
`admin/setup.php` (création unique du compte), `admin/login.php` (connexion) et
`admin/reset-password.php` (mot de passe oublié).

**Fonctionnalités absentes** (vérifié : aucun code correspondant) :
pas de panier, pas de commande, pas de paiement, pas de compte client,
pas d'espace membre, pas de recherche côté serveur, pas de pagination.
Le bouton « Commander » de `products.php` (ligne 93) est un simple lien vers `contact.php`.

---

## 2. Stack technique

| Élément | Valeur | Source |
|---|---|---|
| Langage serveur | PHP **8.3.28** | module chargé dans `httpd.conf` : `php8apache2_4.dll` du dossier `php8.3.28` |
| Serveur web | Apache **2.4.65** (WAMP 64 bits, Windows) | `C:\wamp64\bin\apache\apache2.4.65` |
| Base de données | **Aucune** — MySQL n'est pas utilisé | aucun `mysqli`, `PDO` ni requête SQL dans le code |
| Stockage | Fichiers **JSON** et fichiers **PHP de configuration** | `includes/bootstrap.php`, section 4 |
| Framework | **Aucun** | pas de routeur, pas de conteneur, pas de MVC |
| Front-end | HTML5, CSS3, JavaScript natif | `style.css`, `script.js` |
| Gestionnaire de dépendances | Composer, exécuté via le `composer.phar` livré dans le projet | `composer.json`, `composer.phar` |
| Bibliothèque externe | **PHPMailer 7.1.1** (unique dépendance) | `composer.json`, `vendor/phpmailer/phpmailer/VERSION` |
| Extensions PHP utilisées | `openssl`, `mbstring`, `json`, `fileinfo` (finfo), `iconv`, `curl` (vérifiée mais non requise) | `includes/store.php` → `il_sys_environment()` |

Volumétrie du code hors `vendor/` : environ **8 800 lignes**, dont
`style.css` 1 266 lignes, `includes/` 2 150 lignes, `admin/` 2 850 lignes.

Aucune ressource externe n'est chargée par le navigateur : pas de CDN, pas de
police Google Fonts, pas de bibliothèque JavaScript tierce. Les deux seules images
vectorielles de `style.css` sont des `data:` URI en ligne (lignes 628 et 779).
Le site fonctionne donc sans connexion Internet.

---

## 3. Arborescence du projet

```
infoleader/
├── index.php                 Page d'accueil (289 lignes)
├── products.php              Catalogue public (208 lignes)
├── contact.php               Formulaire de contact (302 lignes)
├── style.css                 Feuille de style unique des 3 pages publiques (1266 lignes)
├── script.js                 Animations publiques : défilement, compteurs, menu (188 lignes)
├── products.json             Catalogue produits (6 fiches)
├── DOCUMENTATION.md          Ce document
├── README.md                 Notice d'installation et d'usage
├── .htaccess                 En-têtes de sécurité, blocage composer/README, Options -Indexes
├── .gitignore                Exclut storage/, vendor/, composer.phar
├── composer.json / .lock     Déclaration de la dépendance PHPMailer
├── composer.phar             Composer embarqué (Composer n'est pas installé sur le poste)
│
├── includes/                 Logique privée, interdite en HTTP
│   ├── .htaccess             Require all denied
│   ├── index.php             Renvoie HTTP 403 (défense supplémentaire)
│   ├── bootstrap.php         Socle : chemins, session, CSRF, JSON, journal (510 lignes)
│   ├── security.php          Mots de passe, anti-force-brute, codes de reset (602 lignes)
│   ├── mailer.php            PHPMailer, configuration SMTP, modèles d'e-mail (541 lignes)
│   ├── products.php          Catalogue : validation, envoi d'image, écriture (499 lignes)
│   └── store.php             Messages de contact, contrôles système, export (662 lignes)
│
├── admin/                    Panneau d'administration
│   ├── index.php             Aiguillage selon l'état de session
│   ├── setup.php             Création unique du compte administrateur
│   ├── login.php             Connexion
│   ├── logout.php            Déconnexion (lien porteur d'un jeton CSRF)
│   ├── reset-password.php    Mot de passe oublié, en 3 étapes
│   ├── dashboard.php         Tableau de bord
│   ├── products.php          Gestion du catalogue
│   ├── email-config.php      Configuration et test des e-mails
│   ├── password.php          Changement de mot de passe
│   ├── user.php              Changement d'adresse e-mail
│   ├── maintenance.php       Système, journaux, sauvegarde, purge
│   ├── _layout.php           Gabarit commun (en-tête, navigation, pied)
│   └── assets/
│       ├── admin.css         Charte du panneau (629 lignes)
│       └── admin.js          Messages temporaires, confirmations, compte à rebours (331 lignes)
│
├── storage/                  Données et secrets, interdits en HTTP
│   ├── .htaccess             Require all denied
│   ├── index.php             Renvoie HTTP 403
│   ├── config/
│   │   ├── admin.php         Compte admin : email, password_hash, created_at, auth_epoch, updated_at
│   │   └── mail.php          Configuration SMTP, mot de passe SMTP inclus
│   ├── data/
│   │   ├── contact.json      Messages reçus via le formulaire
│   │   ├── ratelimit.json    Compteurs anti-force-brute
│   │   ├── resets.json       Code de réinitialisation (haché) et son état
│   │   └── http-protection.json  Cache du contrôle « storage/ est-il exposé en HTTP ? »
│   ├── logs/
│   │   ├── auth.json         Journal des événements de sécurité
│   │   └── mail-debug.log    Trace SMTP si le débogage est activé
│   └── tmp/                  Fichiers temporaires, purgeables depuis Maintenance
│
├── assets/
│   ├── .htaccess             Interdit l'exécution de scripts dans ce dossier
│   ├── banner.jpg
│   └── products/             6 visuels produits (jpg)
│
└── vendor/                   PHPMailer installé par Composer
    └── .htaccess             Require all denied
```

---

## 4. Points d'entrée et routage

Le site n'a **ni routeur ni contrôleur frontal**. Le `.htaccess` ne contient aucune
règle `RewriteRule` : il ne fait que de la protection et des en-têtes HTTP.
Le routage est donc assuré directement par Apache, qui fait correspondre une URL
à un fichier `.php` du disque.

Traitement d'une URL, exemple `http://localhost/infoleader/products.php` :

1. Apache reçoit la requête et résout le chemin dans `C:\wamp64\www\infoleader\`.
2. Le `.htaccess` de la racine s'applique (en-têtes de sécurité, `Options -Indexes`).
3. Le module PHP exécute `products.php`.
4. La première instruction du fichier est `require_once __DIR__ . '/includes/bootstrap.php';`
   (ligne 10). Ce socle est commun à **toutes** les pages, publiques comme privées.
5. `bootstrap.php` définit les chemins, crée les répertoires manquants, puis charge
   `security.php`, `mailer.php`, `store.php` et `includes/products.php` (lignes 507-510).
6. La page lit ses données, puis produit directement son HTML.

Table des points d'entrée :

| URL | Fichier exécuté | Accès |
|---|---|---|
| `/infoleader/` ou `/infoleader/index.php` | `index.php` | public |
| `/infoleader/products.php` | `products.php` | public |
| `/infoleader/contact.php` | `contact.php` | public (POST protégé par CSRF) |
| `/infoleader/admin/` | `admin/index.php` | redirige selon l'état |
| `/infoleader/admin/setup.php` | `admin/setup.php` | public, **se désactive** dès qu'un compte existe |
| `/infoleader/admin/login.php` | `admin/login.php` | public |
| `/infoleader/admin/reset-password.php` | `admin/reset-password.php` | public |
| `/infoleader/admin/*.php` (autres) | le fichier correspondant | **authentification requise** (`il_auth_require()`) |
| `/infoleader/includes/*`, `/storage/*`, `/vendor/*` | — | **refusé** par `.htaccess` (`Require all denied`) |

Aiguillage de `admin/index.php` (18 lignes) :

- aucun compte administrateur → redirection vers `setup.php` ;
- session valide → redirection vers `dashboard.php` ;
- sinon → redirection vers `login.php`.

Deux hôtes sont configurés dans Apache (`conf/extra/httpd-vhosts.conf`) :
`http://localhost/infoleader/` (restreint par `Require local`) et
`http://infoleader.local/` (ouvert par `Require all granted`).

La navigation du panneau est centralisée dans `admin/_layout.php`,
fonction `il_admin_nav_items()`.

---

## 5. Stockage des données

**Le site n'utilise aucune base de données.** Aucun appel `mysqli_*`, aucune
instance `PDO`, aucune requête SQL n'existe dans le code. MySQL n'a pas besoin
d'être démarré pour que le site fonctionne (confirmé par `README.md`, section 2).

Les données sont stockées dans des **fichiers plats**, de deux types.

### 5.1 Fichiers JSON de données

Lus et écrits par deux fonctions de `includes/bootstrap.php` :
`il_read_json($path, $default)` et `il_write_json($path, $data)`.
L'écriture utilise `json_encode` avec `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_UNICODE`
et `JSON_UNESCAPED_SLASHES`, puis `file_put_contents(..., LOCK_EX)` (verrou exclusif).
Si un fichier est absent ou invalide, la valeur par défaut est renvoyée : aucune
erreur fatale.

| Fichier | Contenu | Écrit par |
|---|---|---|
| `products.json` (racine) | Tableau de fiches produits | `il_products_write()` dans `includes/products.php` |
| `storage/data/contact.json` | Messages du formulaire de contact | `il_contact_save()` dans `includes/store.php` |
| `storage/data/ratelimit.json` | Compteurs d'échecs de connexion | `il_rl_fail()` / `il_rl_reset()` dans `includes/security.php` |
| `storage/data/resets.json` | Demande de réinitialisation en cours | `il_reset_request()` dans `includes/security.php` |
| `storage/data/http-protection.json` | Cache (5 min) d'un auto-contrôle HTTP | `il_sys_http_protection()` dans `includes/store.php` |
| `storage/logs/auth.json` | Journal des événements de sécurité | `il_log()` dans `includes/bootstrap.php` |

**Structure d'une fiche produit** (`products.json`, 6 fiches actuellement) :

```json
{
    "id": "p412cd524",
    "nom": "Clavier mécanique RGB",
    "prix": 59.99,
    "description": "Clavier mécanique avec switches rouges...",
    "image": "assets/products/clavier.jpg"
}
```

L'`id` est généré par `il_product_new_id()` : `'p' . bin2hex(random_bytes(4))`.
Les fiches dépourvues d'`id` en reçoivent un automatiquement à la première lecture
(`il_products_all()`, migration des données du TP 1).

**Structure d'un message de contact** (`storage/data/contact.json`) :
`id` (12 caractères hexadécimaux), `ts` (timestamp Unix), `date` (format ISO 8601,
produit par `date('c')`), `nom`, `email`, `objet`, `message`, `ip`.
Plafond : 1 000 messages (`IL_CONTACT_MAX`) ; au-delà, les plus anciens sont supprimés.

**Structure d'un événement de journal** (`storage/logs/auth.json`) :
`ts` (timestamp Unix), `date` (ISO 8601), `type`, `category`, `ip`, `message`, `context`.
Plafond : 2 000 événements (`IL_LOG_MAX`). Les types sont classés en 6 catégories
par `il_log_categories()` : connexions réussies, échecs de connexion, blocages,
événements de sécurité, modifications du compte, catalogue produits.

**Structure de `ratelimit.json`** : un dictionnaire dont les clés sont
`ip:<adresse>` ou `acct:<sha256 de l'identifiant>`, et les valeurs
`{fails, first, last, locked_until}` (les trois dernières en timestamps Unix).

**Structure de `resets.json`** : `email`, `code_hash`, `created_at`, `expires_at`,
`used`, `attempts`, `requests[]`.

### 5.2 Fichiers de configuration PHP

Les données sensibles ne sont pas en JSON mais dans des fichiers `.php` qui
retournent un tableau. Lecture et écriture par `il_read_php_config()` et
`il_write_php_config()` (`includes/bootstrap.php`, section 4), via `include` et
`var_export()`. Après écriture, `opcache_invalidate()` est appelé pour que la
relecture dans la même requête renvoie la bonne valeur.

Ce choix est une protection : si la règle `.htaccess` disparaissait, une requête
HTTP directe **exécuterait** le fichier au lieu d'en afficher le contenu.

| Fichier | Clés réellement présentes (valeurs non reproduites ici) |
|---|---|
| `storage/config/admin.php` | `email`, `password_hash`, `created_at`, `auth_epoch`, `updated_at` |
| `storage/config/mail.php` | `mode`, `provider`, `from_name`, `from_email`, `test_email`, `host`, `port`, `encryption`, `auth`, `username`, `password`, `debug`, `updated_at` |

### 5.3 Emplacement du stockage

`includes/bootstrap.php` définit `IL_STORAGE`. Par défaut : `infoleader/storage`.
Si la variable d'environnement `INFOLEADER_STORAGE` est définie (directive `SetEnv`
d'Apache) et pointe vers un répertoire existant, ce chemin est utilisé à la place.
Les cinq répertoires nécessaires sont créés automatiquement s'ils manquent
(`il_required_dirs()` puis `@mkdir`, lignes 45-62).

---

## 6. Authentification, sessions et sécurité

### 6.1 Compte administrateur

Il existe **un seul compte**. Il n'y a ni rôles ni gestion multi-utilisateurs.
Aucun compte n'est livré avec le projet et aucun mot de passe n'est codé en dur.
Le compte est créé par `admin/setup.php`, qui se désactive de lui-même dès que
`storage/config/admin.php` contient un `email` et un `password_hash`
(`il_admin_exists()`).

Le mot de passe est haché par `password_hash($password, PASSWORD_DEFAULT)`
(`il_password_hash()` dans `includes/security.php`). Il n'est jamais stocké en clair.

Politique de mot de passe imposée côté serveur par `il_password_validate()` :
**20 caractères minimum** (`IL_PWD_MIN_LENGTH`), au moins une majuscule, une
minuscule, un chiffre, un caractère spécial, 200 caractères maximum.

### 6.2 Session

`il_session_start()` (`includes/bootstrap.php`) durcit la session avant de la démarrer :

- `session.use_strict_mode = 1` (refuse un identifiant de session non généré par le serveur) ;
- `session.use_only_cookies = 1` ;
- cookie `HttpOnly`, `SameSite=Lax`, et `secure` seulement si la requête est en HTTPS ;
- nom de cookie dédié : `INFOLEADERSESSID`.

`il_auth_check()` (`includes/security.php`) vérifie à chaque requête :

1. la présence du drapeau `il_admin` en session ;
2. l'existence du compte sur le disque ;
3. l'égalité entre `auth_epoch` en session et `auth_epoch` du fichier — c'est le
   mécanisme qui **invalide toutes les autres sessions** après un changement de
   mot de passe ;
4. l'expiration par inactivité : **30 minutes** (`IL_SESSION_IDLE`) ;
5. la stabilité du navigateur : empreinte SHA-256 de `HTTP_USER_AGENT`.

À la connexion, `session_regenerate_id(true)` est appelé (protection contre la
fixation de session) et un nouveau jeton CSRF est généré.

`il_auth_require()` protège les pages : si la session est invalide, l'événement est
journalisé (`security_denied`) et l'utilisateur est redirigé vers `login.php`.
Cet appel est présent en tête de `dashboard.php`, `products.php`, `email-config.php`,
`password.php`, `user.php` et `maintenance.php`.

### 6.3 Limitation des tentatives de connexion

Implémentée dans `includes/security.php`, section 2. Les compteurs sont stockés
**côté serveur** dans `storage/data/ratelimit.json` : recharger la page, supprimer
les cookies ou changer de session ne les remet pas à zéro.

Deux clés sont suivies en parallèle : l'adresse IP et le compte visé (identifiant
haché en SHA-256, donc jamais stocké en clair).

- Délais progressifs entre deux tentatives (`il_rl_delay_for`) : 3 s, 6 s, 12 s, 24 s, puis 60 s.
- Blocages temporaires (`il_rl_lock_for`) : 15 min, 30 min, 1 h, 2 h, 4 h, puis 8 h au maximum.
- Seuil de blocage (`il_rl_lock_threshold`) : 6 échecs pour une IP, 10 pour un compte.
- Remise à zéro après 24 h sans échec (`IL_RL_DECAY`) ou après une connexion réussie.

La limitation est vérifiée **avant** toute comparaison de mot de passe
(`il_auth_attempt`, étape 1).

### 6.4 Non-divulgation d'information

- Le message d'échec est identique dans tous les cas : « Identifiants incorrects. »
  L'existence du compte n'est donc pas révélée.
- Quand aucun compte ne correspond, un `password_verify()` est exécuté sur un hash
  factice afin que le temps de réponse ne trahisse rien (`il_auth_attempt`, étape 2).
- La comparaison des adresses e-mail utilise `hash_equals()`.
- Les pages d'administration portent `<meta name="robots" content="noindex, nofollow">`.

### 6.5 Protection CSRF

`includes/bootstrap.php`, section 6. Le jeton est de 32 octets aléatoires
(`bin2hex(random_bytes(32))`), stocké en session, comparé par `hash_equals()`.

`il_csrf_require()` est appelé au début du traitement POST de **tous** les
formulaires : `contact.php`, `admin/setup.php`, `admin/login.php`,
`admin/reset-password.php`, `admin/products.php`, `admin/email-config.php`,
`admin/password.php`, `admin/user.php`, `admin/maintenance.php`.
En cas d'échec, l'événement est journalisé et la requête est interrompue avec un
code **HTTP 403**.
La déconnexion (`admin/logout.php`) vérifie aussi un jeton passé en paramètre
d'URL : un site tiers ne peut pas déconnecter l'administrateur.

### 6.6 Réinitialisation du mot de passe

Parcours en 3 étapes dans `admin/reset-password.php`, logique dans
`includes/security.php`, section 4.

- Code de **8 chiffres** généré par `random_int()` (générateur cryptographique).
- Stocké **haché** (`password_hash`) dans `storage/data/resets.json` : lire le
  fichier ne permet pas de rejouer le code.
- Validité **5 minutes** (`IL_RESET_TTL`), usage unique, **5 vérifications** maximum
  (`IL_RESET_MAX_ATTEMPTS`), **3 demandes** maximum par **15 minutes**
  (`IL_RESET_MAX_REQUESTS` / `IL_RESET_WINDOW`).
- À l'étape 3, la validité du code est revérifiée côté serveur, et pas seulement
  en session.
- Le nouveau mot de passe doit être différent de l'ancien, et `auth_epoch` est
  incrémenté : toutes les sessions sont invalidées.
- La réponse de l'étape 1 est identique que l'adresse corresponde ou non au compte.

### 6.7 Protection contre les injections

- **XSS** : toute donnée affichée passe par `e()`, alias de
  `htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **SQL** : sans objet, il n'y a pas de base de données.
- **Traversée de répertoire** : aucun chemin de fichier n'est construit à partir
  d'une valeur du navigateur. La purge de `storage/tmp` (`il_tmp_purge`) établit sa
  liste côté serveur, vérifie chaque chemin avec `realpath()`, exige que le fichier
  soit un enfant **direct** de `storage/tmp`, ignore liens symboliques et
  répertoires, et ne supprime jamais récursivement.
  La suppression d'un visuel (`il_product_delete_image`) applique le même contrôle
  sur `assets/products` et épargne une image encore référencée par une autre fiche.

### 6.8 Sécurité de l'envoi de fichiers (visuels produits)

`il_product_handle_upload()` dans `includes/products.php` enchaîne les contrôles :

1. code d'erreur PHP du transfert, puis `is_uploaded_file()` ;
2. taille maximale **3 Mo** (`IL_IMG_MAX_SIZE`) ;
3. extension dans une liste blanche : jpg, jpeg, png, webp, gif ;
4. type MIME réel lu par `finfo`, qui doit **correspondre** à l'extension ;
5. `getimagesize()` : le fichier doit être une image réellement décodable, ce qui
   rejette un script PHP renommé en `.jpg` ;
6. nom de fichier **entièrement régénéré** (`il_slug()` plus 3 octets aléatoires) :
   aucune valeur du navigateur n'est réutilisée comme chemin ;
7. `chmod 0644` après déplacement ;
8. si la validation de la fiche échoue ensuite, le fichier déjà déplacé est
   supprimé : aucune image orpheline.

En défense supplémentaire, `assets/.htaccess` interdit l'exécution de
`.php`, `.phtml`, `.phar`, `.phps`, `.cgi`, `.pl`, `.py`, `.sh` dans ce dossier.

### 6.9 Protections HTTP

`.htaccess` de la racine :

- blocage de `composer.phar`, `composer.json`, `composer.lock`, `.gitignore`, `README.md` ;
- en-têtes `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: same-origin` ;
- `Options -Indexes` (pas de listage de répertoire) ;
- `Cache-Control: no-cache, must-revalidate` sur les `.css`, `.js` et `.json`.

`includes/.htaccess`, `storage/.htaccess` et `vendor/.htaccess` contiennent
`Require all denied`, avec une variante `Order allow,deny` pour Apache 2.2.
En second rideau, `includes/index.php`, `storage/index.php`,
`storage/config/index.php`, `storage/data/index.php`, `storage/logs/index.php` et
`storage/tmp/index.php` renvoient `http_response_code(403); exit;`.

`il_sys_http_protection()` (`includes/store.php`) va plus loin : il émet une
**vraie requête HTTP locale** vers `storage/data/ratelimit.json` et vérifie que le
serveur répond 403 ou 404. Le résultat est mis en cache 5 minutes.

### 6.10 Journalisation

`il_log($type, $message, $context)` ajoute un événement à `storage/logs/auth.json`.
Les appels réels confirment qu'aucun secret n'est journalisé : ni mot de passe, ni
code de réinitialisation, ni identifiant SMTP.
Les messages d'erreur SMTP sont filtrés par `il_mail_scrub()` avant affichage, et
les lignes `AUTH LOGIN/PLAIN/XOAUTH2` du journal de débogage sont masquées par
`il_mail_debug_write()`.

L'export JSON de sauvegarde (`il_backup_payload()`) retire explicitement
`password_hash`, le mot de passe SMTP, `code_hash` et les données de session.

---

## 7. Front-end

### 7.1 Pages publiques

Une **seule** feuille de style, `style.css` (1 266 lignes), sert les trois pages.
Le CSS n'est pas dupliqué en ligne. Les couleurs sont déclarées en variables CSS.
Un seul fichier JavaScript, `script.js` (188 lignes), est chargé en `defer`.

`style.css` et `script.js` sont appelés via `il_asset()`, qui ajoute
`?v=<filemtime>` à l'URL. Le navigateur ne peut donc pas servir une version
périmée depuis son cache après une modification.

`script.js` est écrit en JavaScript natif, sans bibliothèque, et contient quatre
comportements :

| Fonction | Effet |
|---|---|
| `setupHeader()` | L'en-tête se densifie au défilement (écouteur `scroll` en mode `passive`) |
| `setupBurger()` | Menu déployable sur mobile, fermeture au clic extérieur et à la touche Échap |
| `setupReveal()` | Apparition des sections au défilement via `IntersectionObserver` |
| `setupCounters()` | Compteurs animés des chiffres clés (`[data-count]`) |

Le filtre de recherche du catalogue est un script en ligne à la fin de
`products.php` (lignes 150-203). Il masque les cartes déjà rendues par PHP et met à
jour un compteur de résultats. Il n'y a **aucune requête réseau** : pas d'AJAX,
pas d'API.

### 7.2 Dégradation gracieuse et accessibilité

Les fiches produits sont rendues **côté serveur** : le catalogue reste lisible sans
JavaScript. La ligne `<script>document.documentElement.classList.add("js")</script>`
placée dans le `<head>` garantit qu'aucune section n'est masquée si JavaScript est
absent.

Éléments d'accessibilité réellement présents : lien d'évitement
(`<a class="skip-link">`), `aria-expanded` et `aria-controls` sur le bouton du menu,
`aria-labelledby` sur la bannière, `role="status"` et `role="alert"` sur les messages,
`aria-current="page"` dans la navigation du panneau, `loading="lazy"` sur les images,
classe utilitaire `.sr-only`.

Les animations sont désactivées si le visiteur a activé « réduire les animations »
dans son système : `prefers-reduced-motion` est traité à la fois en CSS
(`style.css` ligne 1174) et en JavaScript (`script.js` ligne 19).

### 7.3 Panneau d'administration

Charte distincte dans `admin/assets/admin.css` (629 lignes) : bleu nuit `#0f2b5b`,
bleu `#1e3c72` et `#2a5298`, cyan `#6dd5ed`, fond `#f4f7fc`.
`admin/assets/admin.js` (331 lignes) gère les messages temporaires (disparition
automatique après 5,5 s pour les succès, les erreurs restent affichées), les
confirmations avant action destructrice, les états de chargement des boutons, les
comptes à rebours de blocage et l'indicateur de règles de mot de passe.

### 7.4 Thème clair / sombre

**Absent.** Aucune requête `prefers-color-scheme` liée aux couleurs, aucun attribut
`data-theme`, aucun basculeur de thème dans le code. Le site n'a qu'une seule
apparence, claire. Les occurrences de « dark » dans `admin.css` sont uniquement la
variable `--navy-dark`, une nuance de bleu.

---

## 8. Intégrations externes

Une seule intégration : l'**envoi d'e-mails par SMTP**, via PHPMailer 7.1.1
(`includes/mailer.php`).

Deux préréglages sont définis dans `il_mail_providers()` :

| Préréglage | Serveur | Port | Chiffrement |
|---|---|---|---|
| Gmail / Google Workspace (par défaut) | `smtp.gmail.com` | 587 | STARTTLS |
| Brevo (ex-Sendinblue) | `smtp-relay.brevo.com` | 587 | STARTTLS |

Un mode manuel (« Autre serveur ») et un mode `mail()` de PHP sont également prévus.
Le mode `mail()` est annoncé comme **indisponible** si le `php.ini` ne déclare ni
`sendmail_path` ni `SMTP` — c'est le cas par défaut sous WAMP
(`il_php_mail_available()`).

Trois e-mails sont envoyés par le site :

1. e-mail de test déclenché depuis `admin/email-config.php` (`il_mail_send_test`) ;
2. code de réinitialisation du mot de passe (`il_mail_send_reset_code`) ;
3. notification d'un nouveau message de contact (`il_mail_send_contact_notification`),
   envoyée seulement si `il_mail_is_configured()` est vrai. Son échec n'empêche pas
   l'enregistrement du message (`contact.php`, lignes 40-48).

Aucun envoi n'est simulé : en cas d'échec, `il_mail_send()` retourne une erreur.
`il_mail_diagnose()` ajoute une piste de résolution pour les erreurs les plus
courantes (handshake TLS, connexion refusée, authentification refusée, expéditeur
non validé). Des délais d'attente sont imposés (`Timeout = 10 s`,
`Timelimit = 15 s`) pour qu'un serveur injoignable ne fige pas la page.

**Aucune autre intégration** : pas d'API REST consommée, pas de passerelle de
paiement, pas de service de mesure d'audience, pas de CAPTCHA, pas de CDN, pas de
réseau social, pas de carte.

---

## 9. Configuration

### 9.1 Fichiers de configuration

| Fichier | Rôle | Versionné ? |
|---|---|---|
| `storage/config/admin.php` | Compte administrateur | Non (`.gitignore`) |
| `storage/config/mail.php` | Paramètres SMTP, mot de passe inclus | Non (`.gitignore`) |
| `composer.json` / `composer.lock` | Dépendance PHPMailer | Oui |
| `.htaccess` (×5) | Protections Apache | Oui |
| `.gitignore` | Exclut `storage/config`, `storage/logs`, `storage/data`, `storage/tmp`, `vendor/`, `composer.phar` | Oui |

Les deux fichiers de `storage/config/` sont générés par le code, jamais édités à la
main : ils portent un en-tête « Fichier genere automatiquement ».

### 9.2 Constantes

Toutes les constantes sont définies par `define()`, sans fichier `.env`.

Chemins, dans `includes/bootstrap.php` : `IL_ROOT`, `IL_STORAGE`, `IL_CONFIG_DIR`,
`IL_LOG_DIR`, `IL_DATA_DIR`, `IL_TMP_DIR`, `IL_ADMIN_FILE`, `IL_MAIL_FILE`,
`IL_AUTH_LOG`, `IL_MAIL_DEBUG_LOG`, `IL_RATELIMIT_FILE`, `IL_RESET_FILE`,
`IL_CONTACT_FILE`, `IL_LOG_MAX` (2000).

Sécurité, dans `includes/security.php` : `IL_PWD_MIN_LENGTH` (20),
`IL_RL_DECAY` (86400), `IL_SESSION_IDLE` (1800), `IL_RESET_TTL` (300),
`IL_RESET_MAX_ATTEMPTS` (5), `IL_RESET_MAX_REQUESTS` (3), `IL_RESET_WINDOW` (900).

Contact et catalogue : `IL_CONTACT_MAX` (1000), `IL_CONTACT_MAX_NAME` (120),
`IL_CONTACT_MAX_SUBJECT` (150), `IL_CONTACT_MAX_MESSAGE` (5000),
`IL_PRODUCTS_FILE`, `IL_PRODUCTS_IMG_DIR`, `IL_PRODUCTS_IMG_REL`,
`IL_IMG_MAX_SIZE` (3 Mo), `IL_AUTOLOAD`, `IL_MAIL_DEBUG_MAX` (256 Ko).

### 9.3 Variable d'environnement

`INFOLEADER_STORAGE` : si elle est définie et pointe vers un répertoire existant,
le dossier `storage/` est lu à cet emplacement. Elle permet de sortir les données
de la racine web. Elle n'est pas définie actuellement.

### 9.4 Installation

Décrite dans `README.md`, section 2 : démarrer Apache, exécuter
`php composer.phar install` si `vendor/` est absent, puis ouvrir
`http://localhost/infoleader/`. MySQL n'est pas nécessaire.
Le compte administrateur se crée ensuite via `admin/setup.php`.

---

## 10. Points forts

Ce que le site fait déjà correctement.

1. **Séparation nette des responsabilités.** La logique est isolée dans
   `includes/` (5 modules thématiques), les données dans `storage/`, l'affichage
   dans les pages. Aucun secret ne vit dans un fichier accessible en HTTP.
2. **Protection CSRF réellement généralisée.** Tous les POST du site, y compris le
   formulaire public de contact, et même le lien de déconnexion, sont protégés.
3. **Anti-force-brute solide pour le niveau attendu.** Compteurs côté serveur,
   double suivi IP et compte, délais progressifs puis blocages croissants,
   impossible à contourner en rechargeant la page ou en vidant les cookies.
4. **Aucun secret en clair ni en dur.** Mot de passe haché par `password_hash`,
   code de réinitialisation haché, aucun compte livré par défaut, `setup.php` qui
   se désactive seul.
5. **Échappement systématique en sortie** via la fonction `e()`, appliquée de façon
   homogène dans tout le projet.
6. **Contrôles d'upload sérieux** : extension, type MIME réel, décodabilité de
   l'image, nom régénéré, et interdiction d'exécution dans `assets/`.
7. **Opérations destructrices confinées.** Purge et suppression de fichiers
   vérifiées par `realpath()`, non récursives, jamais pilotées par une valeur du
   navigateur.
8. **Pas de fausse réussite.** Le site ne simule pas un envoi d'e-mail : il annonce
   l'échec et propose un diagnostic. Le mode `mail()` est déclaré indisponible
   plutôt que présenté comme fonctionnel.
9. **Fonctionne sans JavaScript** pour l'essentiel : le catalogue est rendu côté
   serveur et toutes les validations existent côté serveur.
10. **Accessibilité prise en compte** : lien d'évitement, attributs ARIA, rôles de
    messages, respect de `prefers-reduced-motion`.
11. **Aucune dépendance externe au navigateur** : pas de CDN, pas de police
    distante. Le site fonctionne sans connexion Internet.
12. **Problème de cache anticipé** : `il_asset()` versionne CSS et JS par
    `filemtime`, ce qui évite les mises en page cassées après modification.
13. **Code commenté en français**, avec des commentaires qui expliquent les
    décisions et non seulement le fonctionnement.
14. **Auto-diagnostic intégré** : `il_sys_sections()` vérifie réellement la version
    de PHP, les extensions, les droits d'écriture, la validité des fichiers de
    configuration, la joignabilité du port SMTP et l'exposition HTTP de `storage/`.

---

## 11. Limites connues et risques

Faiblesses réelles du code actuel, classées par ordre de gravité décroissante.
Elles servent de base au fichier `TODO_LIST.md`.

### 11.1 Risques de sécurité

**R1 — Le formulaire de contact n'a aucune limitation de débit.**
`contact.php` vérifie le jeton CSRF et valide les champs, mais rien n'empêche
d'enregistrer des centaines de messages à la suite. Aucun délai minimal entre deux
envois, aucun plafond par IP, aucun piège anti-robot. À terme,
`storage/data/contact.json` peut être rempli de spam jusqu'au plafond de 1 000
messages, ce qui **évince les messages légitimes** (les plus anciens sont supprimés).
Un mécanisme réutilisable existe déjà : les fonctions `il_rl_*` de
`includes/security.php`.

**R2 — Le mot de passe SMTP est stocké en clair.**
`storage/config/mail.php` contient la clé `password` en texte lisible. Le fichier
est protégé par `.htaccess`, exclu de Git et porte l'extension `.php`, mais toute
personne ayant accès au disque lit le secret. C'est un compromis difficile à éviter
sans gestionnaire de secrets : PHPMailer a besoin du mot de passe en clair pour
s'authentifier. À documenter plutôt qu'à masquer.

**R3 — Aucun en-tête `Content-Security-Policy`.**
Le `.htaccess` pose `X-Content-Type-Options`, `X-Frame-Options` et
`Referrer-Policy`, mais pas de CSP. Comme `products.php` et les pages
d'administration contiennent des scripts en ligne, une CSP stricte demanderait
d'abord de les externaliser.

**R4 — La réinitialisation de mot de passe dépend entièrement du courriel.**
Si la configuration SMTP est cassée, le parcours « Mot de passe oublié ? » devient
inutilisable et l'accès au panneau est perdu. La seule issue documentée est la
suppression manuelle de `storage/config/admin.php` (`README.md`, section 3).
Aucun message dans l'interface n'indique cette procédure de secours.

**R5 — Le vhost `infoleader.local` est ouvert au réseau local.**
`httpd-vhosts.conf` déclare `Require all granted` pour cet hôte, alors que
`localhost` est restreint par `Require local`. Sur un réseau Wi-Fi partagé, la page
de connexion de l'administration est donc joignable depuis une autre machine.
C'est une configuration Apache, pas un défaut du code du site.

**R6 — Pas d'authentification à deux facteurs, et un seul compte.**
Conséquence : un mot de passe compromis donne un accès complet, et il n'existe
aucune trace permettant de distinguer deux administrateurs.

### 11.2 Fiabilité du stockage en fichiers

**R7 — Accès concurrents : risque d'écrasement.**
`il_write_json()` écrit avec `LOCK_EX`, mais `il_read_json()` lit **sans verrou**.
Le motif utilisé partout est « lire le tableau, le modifier, le réécrire ».
Si deux requêtes se croisent (deux visiteurs envoient un message au même instant,
ou deux tentatives de connexion simultanées), la seconde écriture écrase la
première : un message de contact ou un échec de connexion peut être perdu.
En usage local et pédagogique le risque est faible, mais il est réel.
Fichiers concernés : `storage/data/contact.json`, `storage/data/ratelimit.json`,
`storage/logs/auth.json`, `products.json`.

**R8 — `il_products_all()` écrit pendant une lecture.**
Lignes 53-70 de `includes/products.php` : si une fiche n'a pas d'`id`, la fonction
réécrit `products.json`. Une page publique peut donc déclencher une écriture sur le
disque. La migration est terminée (les 6 fiches ont un `id`), mais le code reste une
écriture non attendue sur un chemin de lecture.

**R9 — Aucune pagination.**
`admin/maintenance.php` charge `storage/logs/auth.json` en entier pour compter les
événements par catégorie, puis affiche 150 entrées et 100 messages de contact.
Avec 2 000 événements et 1 000 messages, la page devient lourde : tout est
désérialisé en mémoire à chaque affichage.

**R10 — Aucune sauvegarde automatique.**
L'export JSON de `admin/maintenance.php` est manuel et volontairement expurgé de
ses secrets : ce n'est pas une sauvegarde restaurable. Il n'existe aucune fonction
de restauration. Une suppression accidentelle de `storage/` est irréversible.

**R11 — `products.json` est servi publiquement.**
Le fichier est à la racine du projet et le `.htaccess` ne le bloque pas (il ne
bloque que `composer.json`, `composer.lock`, `composer.phar`, `.gitignore` et
`README.md`). `http://localhost/infoleader/products.json` renvoie donc le catalogue
brut. Les données ne sont pas confidentielles, mais l'exposition n'est pas
intentionnelle.

### 11.3 Qualité du code et maintenance

**R12 — Aucun test, d'aucune sorte.**
Pas de test unitaire, pas de test fonctionnel, pas de jeu de données de
démonstration, pas de procédure de recette écrite. Chaque modification doit être
revérifiée à la main dans le navigateur, et rien ne garantit qu'une correction n'en
casse pas une autre.

**R13 — Aucune gestion centralisée des erreurs.**
Pas de `set_error_handler`, pas de `set_exception_handler`, pas de page d'erreur
personnalisée, aucune directive `ErrorDocument` dans le `.htaccess`.
Une erreur PHP s'affiche selon la configuration de `php.ini` : sous WAMP, le message
brut apparaît à l'écran, avec le chemin complet des fichiers.

**R14 — Les erreurs silencieuses masquent les pannes.**
`includes/bootstrap.php` crée les répertoires avec `@mkdir`, et plusieurs opérations
de fichier sont préfixées par `@` (`@unlink`, `@filemtime`, `@file_put_contents`,
`@scandir`). Si les droits sont mauvais, la page continue de s'afficher sans rien
signaler. Les contrôles de `il_sys_writable()` compensent en partie, mais seulement
dans l'écran Maintenance.

**R15 — Logique métier mêlée à l'affichage.**
Les pages d'administration traitent le POST, journalisent, redirigent puis
produisent le HTML dans un même fichier (`admin/maintenance.php` : 438 lignes,
`admin/email-config.php` : 536 lignes). La relecture et la modification sont
difficiles.

**R16 — Du balisage reste dupliqué.**
L'en-tête, la navigation et le pied de page des trois pages publiques sont recopiés
dans `index.php`, `products.php` et `contact.php`. Le panneau, lui, a bien factorisé
son gabarit (`admin/_layout.php`). Un changement de menu demande donc trois
modifications au lieu d'une.

**R17 — Le CSS en ligne subsiste par endroits.**
Attributs `style="..."` dans `index.php`, `products.php`, `contact.php`,
`admin/_layout.php`, `admin/dashboard.php` et `admin/maintenance.php`.
Cela complique l'uniformisation visuelle et empêche une CSP stricte.

**R18 — Aucun typage strict, pas de `declare(strict_types=1)`.**
Le code convertit les valeurs à la main (`(int)`, `(string)`, `(float)`), ce qui
fonctionne mais laisse passer des conversions implicites inattendues.

**R19 — `filemtime()` est appelé sans vérifier l'existence du fichier** dans
`admin/_layout.php`, `admin/login.php`, `admin/setup.php` et
`admin/reset-password.php` (`?v=<?php echo filemtime(...)`), contrairement à
`il_asset()` qui, lui, teste `is_file()`. Si un fichier d'assets était supprimé, un
avertissement PHP s'afficherait dans le `<head>`.

### 11.4 Conformité et données personnelles

**R20 — Aucune mention légale, aucune politique de confidentialité.**
Le site collecte nom, adresse e-mail, objet, message **et adresse IP**
(`il_contact_save()`), sans aucune information au visiteur, sans case de
consentement, sans mention de la durée de conservation ni du responsable des
données. C'est le manquement le plus net du projet au regard du RGPD.

**R21 — Conservation illimitée des messages.**
Les messages restent dans `storage/data/contact.json` jusqu'au plafond de 1 000,
sans date d'effacement automatique. La seule suppression possible est une purge
totale depuis Maintenance.

**R22 — L'adresse IP est conservée dans deux fichiers** : `contact.json` et
`auth.json` (chaque événement enregistre `il_client_ip()`). Il s'agit d'une donnée
personnelle, conservée sans durée définie.

### 11.5 Fonctionnalités et expérience utilisateur

**R23 — Pas de thème sombre.** Aucun basculeur, aucune prise en compte de
`prefers-color-scheme` pour les couleurs (voir section 7.4).

**R24 — Le double envoi n'est pas protégé.** Rafraîchir après l'envoi du formulaire
de contact peut renvoyer le POST et enregistrer le message deux fois : `contact.php`
affiche son résultat directement au lieu de rediriger (motif « POST puis
redirection »), contrairement aux pages d'administration qui, elles, appliquent bien
ce motif.

**R25 — Le bouton « Commander » ne commande rien.** Il renvoie vers `contact.php`.
Il n'existe ni panier, ni devis, ni suivi de demande. L'intitulé promet plus que ce
que le site fait.

**R26 — L'empreinte du navigateur peut déconnecter à tort.**
`il_auth_check()` compare un SHA-256 de `HTTP_USER_AGENT`. Une mise à jour du
navigateur change cette chaîne et ferme la session en cours. La protection est
utile, mais l'utilisateur n'a aucune explication : il est simplement redirigé vers
la connexion.

**R27 — Pas de recherche serveur ni de tri du catalogue.**
Le filtre de `products.php` est purement côté navigateur et ne porte que sur les
fiches déjà affichées. Avec un catalogue volumineux, toutes les fiches seraient tout
de même envoyées à chaque chargement.

**R28 — Aucune page 404 personnalisée.** Une URL inexistante affiche la page
d'erreur brute d'Apache, qui révèle le serveur et sa version.

---

*Document rédigé à partir du code du projet, sans extrapolation. Les éléments non
trouvés dans le code sont signalés comme absents.*
