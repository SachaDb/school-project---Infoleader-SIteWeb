# InfoLeader

Site vitrine d'une entreprise informatique fictive « InfoLeader », développé en
PHP / HTML / CSS / JavaScript, fonctionnant en local sous WAMP (Apache / PHP).

Le TP 2 ajoute un **panneau d'administration** complet : connexion protégée
contre la force brute, réinitialisation de mot de passe par code à 8 chiffres,
configuration d'envoi d'e-mails via Brevo (PHPMailer), gestion du compte
administrateur et outils de maintenance.

---

## 1. Structure du projet

```
infoleader/
├── index.php              # REFONTE : hero animé, engagements, chiffres, produits phares, CTA
├── products.php           # REFONTE : fiches rendues côté serveur + filtre instantané
├── contact.php            # REFONTE + validation serveur, CSRF, stockage réel, notification
├── style.css              # Feuille unique des 3 pages publiques (mêmes couleurs)
├── script.js              # Animations : scroll reveal, compteurs, menu mobile, header
├── products.json          # Données produits (+ champ `id` pour la gestion admin)
├── .htaccess              # NOUVEAU : blocage composer/README, en-têtes de sécurité
├── .gitignore             # NOUVEAU : exclut storage/, vendor/, composer.phar
├── composer.json/.lock    # NOUVEAU : dépendance PHPMailer
├── composer.phar          # NOUVEAU : Composer local (Composer absent du système)
│
├── admin/                 # NOUVEAU : panneau d'administration
│   ├── index.php          #   point d'entrée (redirige selon l'état de session)
│   ├── setup.php          #   initialisation unique du compte administrateur
│   ├── login.php          #   page de connexion
│   ├── logout.php         #   déconnexion (lien porteur d'un jeton CSRF)
│   ├── reset-password.php #   parcours « Mot de passe oublié ? » en 3 étapes
│   ├── dashboard.php      #   tableau de bord + 4 outils + infos du compte
│   ├── products.php       #   gestion du catalogue (nom, prix, description, visuel)
│   ├── email-config.php   #   configuration des e-mails + test d'envoi réel
│   ├── password.php       #   modification du mot de passe
│   ├── user.php           #   modification de l'adresse e-mail
│   ├── maintenance.php    #   système, journaux, sauvegarde, purge de storage/tmp
│   ├── _layout.php        #   gabarit commun (en-tête, nav, messages, pied)
│   └── assets/
│       ├── admin.css      #   charte du panneau (couleurs du site existant)
│       └── admin.js       #   messages temporaires, confirmations, états de chargement
│
├── includes/              # NOUVEAU : logique privée (interdite en HTTP)
│   ├── .htaccess          #   Require all denied
│   ├── bootstrap.php      #   chemins, session, CSRF, journalisation, utilitaires
│   ├── security.php       #   mots de passe, limitation des tentatives, codes de reset
│   ├── mailer.php         #   PHPMailer : Gmail / Brevo SMTP, modèles d'e-mail
│   ├── products.php       #   catalogue : validation, upload d'image, écriture JSON
│   └── store.php          #   messages de contact, contrôles système, export JSON
│
├── storage/               # NOUVEAU : données et secrets (interdits en HTTP)
│   ├── .htaccess          #   Require all denied
│   ├── config/
│   │   ├── admin.php      #   compte admin : e-mail + hash (créé par setup.php)
│   │   └── mail.php       #   configuration SMTP, mot de passe inclus
│   ├── data/
│   │   ├── ratelimit.json #   compteurs anti-force-brute (côté serveur)
│   │   ├── resets.json    #   code de reset (haché), expiration, usage
│   │   └── contact.json   #   messages reçus via le formulaire de contact
│   ├── logs/
│   │   ├── auth.json      #   journal de connexion et de sécurité
│   │   └── mail-debug.log #   trace SMTP si le débogage est activé
│   └── tmp/               #   fichiers temporaires (purgeables depuis Maintenance)
│
├── vendor/                # NOUVEAU : PHPMailer 7.1.1 (Composer)
└── assets/                # Images (inchangé)
```

Les fichiers de configuration portent l'extension `.php` et retournent un
tableau : même si la protection `.htaccess` disparaissait, une requête HTTP
directe exécuterait le fichier sans afficher aucun secret.

---

## 2. Lancer le site

```
# 1. Démarrer Apache (service WAMP)
net start wampapache64

# 2. Installer les dépendances (uniquement si vendor/ est absent)
cd C:\wamp64\www\infoleader
C:\wamp64\bin\php\php8.3.28\php.exe composer.phar install

# 3. Ouvrir le site
http://localhost/infoleader/          (ou http://infoleader.local/)
```

MySQL n'est pas nécessaire : le projet n'utilise pas de base de données
(stockage en fichiers JSON dans `storage/`).

---

## 3. Créer le compte administrateur (à faire une seule fois)

Aucun compte n'est livré et aucun mot de passe n'est codé en dur.

1. Ouvrir `http://localhost/infoleader/admin/` → redirection automatique vers
   `setup.php`.
2. Saisir l'adresse e-mail (deux fois) et le mot de passe (deux fois).
3. Le mot de passe doit respecter : **20 caractères minimum**, une majuscule,
   une minuscule, un chiffre, un caractère spécial.
4. Valider : le compte est écrit dans `storage/config/admin.php` (e-mail +
   hash `password_hash()`), puis **`setup.php` se désactive de lui-même**.

Pour recréer un compte perdu : supprimer `storage/config/admin.php`, puis
relancer `setup.php`.

---

## 4. Configurer l'envoi d'e-mails

Le module propose deux préréglages : **Gmail** (retenu par défaut, le seul dont
le handshake TLS aboutit depuis ce poste) et **Brevo**. « Autre serveur » permet
une saisie manuelle.

### Gmail / Google Workspace (préréglage par défaut)

1. Activer la **validation en deux étapes** sur le compte Google, puis créer un
   **mot de passe d'application** sur https://myaccount.google.com/apppasswords
   (16 caractères). Le mot de passe habituel du compte est refusé par le SMTP.
2. Panneau d'administration → **Configuration des e-mails** :

   | Champ | Valeur |
   |---|---|
   | Mode d'envoi | SMTP authentifié |
   | Préréglage | Gmail / Google Workspace |
   | Serveur SMTP | `smtp.gmail.com` |
   | Port | `587` (STARTTLS) ou `465` (SSL/TLS) |
   | Sécurité | STARTTLS |
   | Authentification | activée |
   | Identifiant SMTP | l'adresse Gmail complète |
   | Mot de passe SMTP | le mot de passe d'application (sans espaces) |
   | Adresse de l'expéditeur | **la même adresse Gmail** (Gmail refuse tout autre expéditeur non déclaré comme alias) |
   | Destinataire de test | l'adresse de réception souhaitée |
   | Journaux de débogage | à activer pour diagnostiquer |

   Un contrôle dédié signale si l'expéditeur diffère de l'identifiant Gmail.

### Brevo (alternative)

Préréglage **Brevo** : serveur `smtp-relay.brevo.com`, port `587`, STARTTLS,
identifiant de la forme `xxxxxxx@smtp-brevo.com`, mot de passe = clé créée dans
**SMTP & API > Clés SMTP**, adresse d'expédition validée dans **Expéditeurs**.

3. **Enregistrer**, puis **Tester l'envoi**. Le mot de passe n'est jamais
   réaffiché : laisser le champ vide conserve la valeur enregistrée.
4. Aucune réussite n'est simulée : en cas d'échec, le message d'erreur indique
   la cause probable (pare-feu, clé refusée, expéditeur non validé).

### Mode « PHP mail »

Les champs sont proposés pour un hébergement disposant d'un serveur de
messagerie. Sous WAMP, `php.ini` ne déclare ni `sendmail_path` ni `SMTP` : le
mode est donc annoncé comme **indisponible** plutôt que simulé.

### Prérequis vérifiés automatiquement

Extension `openssl`, présence de PHPMailer, joignabilité du port SMTP sortant,
droits d'écriture, présence et validité des fichiers de configuration.

---

## 5. Sécurité mise en place

- Authentification obligatoire sur toutes les pages du panneau.
- Session durcie : `HttpOnly`, `SameSite=Lax`, `use_strict_mode`, identifiant
  régénéré à la connexion, expiration après 30 min d'inactivité, liaison au
  navigateur.
- Message de connexion identique quelle que soit la cause de l'échec :
  l'existence d'un compte n'est jamais révélée.
- **Limitation des tentatives, stockée côté serveur** (ni cookie ni session,
  donc incontournable par rechargement) :
  - délais : 3 s, 6 s, 12 s, 24 s, 60 s ;
  - blocages : 15 min, 30 min, 1 h, 2 h, 4 h, 8 h (plafond) ;
  - suivi par IP (blocage dès 6 échecs) **et** par compte (seuil plus élevé,
    10 échecs, pour ne pas verrouiller un utilisateur légitime) ;
  - remise à zéro après 24 h sans échec, ou après une connexion réussie.
- Code de réinitialisation : 8 chiffres, `random_int()`, **haché**
  (`password_hash`), valable 5 min, usage unique, 5 essais maximum,
  3 demandes maximum par quart d'heure.
- Protection CSRF sur tous les formulaires (y compris le contact public) ;
  requête sans jeton valide → **HTTP 403**.
- Échappement systématique à l'affichage (`htmlspecialchars`).
- Validation serveur de tous les formulaires, indépendamment du JavaScript.
- Secrets hors du dépôt Git et hors accès HTTP ; identifiants SMTP masqués dans
  les journaux de débogage et retirés des messages d'erreur.
- Journalisation des événements sensibles **sans jamais enregistrer** de mot de
  passe ni de code de réinitialisation.
- Purge de `storage/tmp` : liste établie côté serveur, chemins vérifiés par
  `realpath()`, liens symboliques et sous-répertoires ignorés, aucune
  suppression récursive.
- Export JSON expurgé : ni hash du mot de passe, ni mot de passe SMTP, ni code
  de réinitialisation, ni donnée de session.

---

## 6. Charte graphique

Le panneau reprend la palette des pages les plus récentes du site
(`products.php`, `contact.php`) :

- bleu nuit `#0f2b5b` / `#1e3c72`
- bleu `#2a5298` / `#4a90d9`
- cyan `#6dd5ed`
- fond `#f4f7fc`, texte `#1e293b`

La page d'accueil conserve sa charte d'origine (`style.css` : `#0056b3`,
`#2d2d2d`, `#4caf50`).

---

## 7. Fonctionnalités

### Site vitrine (refondu, couleurs et nom conservés)

- **Accueil** : bannière plein écran avec dégradés animés, badge pulsé, entrée
  en cascade du titre, bandeau de chiffres à compteurs animés, cartes
  « engagements », section de présentation en deux colonnes, produits phares
  issus du catalogue réel, bandeau d'appel à l'action, pied de page en colonnes.
- **Produits** : fiches **rendues côté serveur** (la page fonctionne sans
  JavaScript), filtre instantané avec compteur de résultats, zoom au survol.
- **Animations** : apparitions au défilement (`IntersectionObserver`), en-tête
  qui se densifie, menu déployable sur mobile, reflet sur les boutons. Tout est
  neutralisé si le visiteur a activé « réduire les animations » dans son système.
- **Une seule feuille de style** pour les trois pages : le CSS n'est plus
  dupliqué en ligne dans `products.php` et `contact.php`.
- **Contact** : validation JavaScript **et** serveur ; les messages sont
  désormais **réellement enregistrés** (`storage/data/contact.json`),
  consultables dans Maintenance, et notifiés par e-mail si Brevo est configuré.

### Panneau d'administration (ajouté)

- **Connexion** : limitation progressive, messages non révélateurs,
  « Mot de passe oublié ? ».
- **Tableau de bord** : état global du site, 4 outils, informations réelles du
  compte (adresse e-mail, création, connexion précédente, IP, expiration).
- **Configuration des e-mails** : PHP mail / Brevo SMTP, test d'envoi réel,
  journaux de débogage protégés.
- **Mot de passe** : vérification de l'ancien, règles affichées et contrôlées
  côté serveur, invalidation des autres sessions.
- **Utilisateur** : modification de l'adresse e-mail après vérification du mot
  de passe.
- **Maintenance** : système (4 sous-sections), journaux filtrables + messages de
  contact + purges confirmées, export JSON, purge de `storage/tmp`.
- **Catalogue produits** : ajout, modification, réordonnancement et suppression
  des fiches (nom, prix, description, visuel), écrits dans `products.json`.

#### Sécurité de l'upload des visuels

- Extension **et** type MIME réel (`finfo`) vérifiés et devant concorder.
- Image réellement décodable (`getimagesize`) : un fichier PHP renommé en `.jpg`
  est refusé.
- Nom de fichier entièrement régénéré : aucune valeur du navigateur n'est
  réutilisée comme chemin.
- Taille limitée à 3 Mo, formats JPG / PNG / WEBP / GIF.
- `assets/.htaccess` interdit l'exécution de tout script dans les visuels.
- Une validation refusée ne laisse aucun fichier orphelin sur le disque.
- La suppression d'un visuel est confinée à `assets/products` (`realpath`) et
  épargne une image encore utilisée par une autre fiche.

---

## 8. Notes techniques

- PHP 8.3.28 (Apache 2.4.65 / WAMP), aucun framework.
- Seule dépendance : `phpmailer/phpmailer` (7.1.1), installée via Composer.
- Aucune base de données : stockage en fichiers JSON et PHP.
- `storage/` peut être déplacé hors de la racine web en définissant la variable
  d'environnement `INFOLEADER_STORAGE` (directive `SetEnv` d'Apache).
