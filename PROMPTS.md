# Consignes données à l'agent IA — TP 2bis, 2ter et 2quater

Ce fichier conserve la trace de l'usage de l'agent IA pour l'analyse et
l'amélioration du site InfoLeader, conformément au TP 2bis
(« Conservez une trace. La transparence sur l'usage de l'IA fait partie de
l'évaluation »).

- **Projet analysé** : `C:\wamp64\www\infoleader`
- **Environnement** : WAMP local (Apache 2.4.65, PHP 8.3.28, Windows 11)
- **Livrables produits** : `DOCUMENTATION.md`, `TODO_LIST.md`, `AMELIORATIONS.md`, `PANIER.md`, ce fichier

---

## 1. Consigne d'analyse (Partie A)

Consigne donnée à l'agent, dans le dossier racine du site :

```
Tu es développeur web senior. Analyse l'intégralité du site web présent dans le dossier courant et rédige un fichier DOCUMENTATION.md à la racine du projet.

Règles impératives :
  - Base-toi UNIQUEMENT sur le code réellement présent. N'invente rien : si une information manque, indique-le explicitement.
  - Écris en français, au format Markdown, avec une table des matières en haut du document.
  - Utilise un ton professionnel et des phrases courtes.

Plan attendu du document :
  1. Présentation générale du site (à quoi il sert, fonctionnalités principales).
  2. Stack technique (langages, versions, serveur, stockage des données, bibliothèques).
  3. Arborescence du projet (dossiers et fichiers importants, avec leur rôle).
  4. Points d'entrée et routage (comment une URL est traitée par le site).
  5. Stockage des données : indique où et comment les données sont stockées (à ce stade, le plus souvent des fichiers JSON). Si le site utilise une base de données, décris-la (tables, colonnes, relations, requêtes).
  6. Authentification, sessions et sécurité.
  7. Front-end (HTML, CSS, JavaScript, thème clair/sombre...).
  8. Intégrations externes (API, envoi d'e-mails, services tiers...).
  9. Configuration (fichiers de configuration, variables, constantes).
  10. Limites connues et risques : bugs potentiels, failles de sécurité, absence de tests, points fragiles à corriger en priorité.

Écris directement le fichier DOCUMENTATION.md.
```

**Résultat** : `DOCUMENTATION.md`, 823 lignes, 11 sections (les 10 demandées plus
une section « Points forts »), et 28 limites réelles référencées R1 à R28.

---

## 2. Consigne de génération du plan d'améliorations (Partie C)

```
Lis le fichier DOCUMENTATION.md présent à la racine du projet et génère un fichier TODO_LIST.md à la racine.

Contexte : je suis étudiant en BTS SIO 1re année. Les améliorations doivent être à ma portée et réalisables dans le cadre de mes TP.

Environnement imposé (à respecter absolument) :
  - Le site tourne uniquement en local sur WAMP (Apache + PHP + MySQL sous Windows).
  - Ne propose AUCUN serveur distant, VPS, Docker, Nginx, PHP-FPM, déploiement automatisé ni nom de domaine réel.
  - N'impose AUCUNE nouvelle dépendance (Composer, npm, framework) sans le signaler explicitement comme "sous accord du professeur".
  - Aucun paiement réel : toute fonctionnalité de paiement doit être une SIMULATION (aucune passerelle bancaire, aucune donnée de carte).
  - Stockage des données : elles sont actuellement dans des fichiers JSON. L'absence de base de données n'est PAS un défaut ; ne la signale pas comme une erreur.
  - Tu peux proposer, comme évolution optionnelle, un stockage en base SQLite (intégrée à PHP sous WAMP, aucune dépendance à installer).
  - Les actions doivent être adaptées à mon niveau : privilégie le simple et le concret.

Contenu attendu :
  - Organise les actions par thème (sécurité, stockage des données, qualité du code, front-end, conformité/RGPD, documentation, exploitation locale...).
  - Pour chaque action, indique : un titre clair, une priorité (P0 = critique, P1 = important, P2 = optionnel), un niveau de complexité (simple / moyen / complexe) et un critère d'acceptation vérifiable.
  - Ajoute une section "Définition de Terminé" : ce qui doit être vrai pour qu'une tâche soit considérée comme terminée.
  - Ajoute un séquencement par jalons (étapes), du plus urgent au moins urgent.
  - Vouvoie-moi et explique brièvement les termes techniques.

Écris directement le fichier TODO_LIST.md.
```

**Résultat** : `TODO_LIST.md`, 31 actions en 7 thèmes, chacune avec priorité,
complexité, **fichiers concernés** et critère d'acceptation, plus la « Définition de
Terminé » et 7 jalons.

La colonne « Fichiers concernés », proposée en correction facultative par le TP, a
été intégrée directement à la première génération.

---

## 3. Consigne d'évolution du site (Partie E)

```
Lis le fichier TODO_LIST.md et fais évoluer le site pour mettre en œuvre les tâches, en commençant par les priorités P0.

Règles :
  - Traite les tâches UNE PAR UNE, de la plus prioritaire à la moins prioritaire.
  - Avant chaque tâche : explique ce que tu vas faire et quels fichiers tu vas modifier.
  - Après chaque tâche : indique précisément comment je peux la tester dans mon navigateur (WAMP local).
  - Le site doit rester fonctionnel à tout moment.
  - Contraintes : environnement WAMP local ; aucun paiement réel (simulation uniquement) ; aucune nouvelle dépendance sans mon accord ; modifications adaptées à mon niveau.
  - Ne passe à la tâche suivante qu'après ma validation.

Commence par la première tâche P0.
```

**État** : l'agent a présenté la première tâche P0 (action 3.1 — rediriger après
l'envoi du formulaire de contact) et attend validation avant toute modification de
code. Aucun fichier du site n'a été modifié à ce stade : seuls des fichiers de
documentation ont été créés.

---

## 4. Vérifications effectuées sur le travail de l'agent

L'agent IA peut se tromper ou inventer. Les points suivants ont été contrôlés dans
le code avant d'accepter la documentation.

| Vérification | Méthode | Conclusion |
|---|---|---|
| Absence de base de données | recherche de `PDO`, `mysqli` et de requêtes SQL dans tout le projet | Confirmée : aucun résultat. Le stockage est bien en fichiers JSON et PHP. |
| Versions annoncées | lecture de `httpd.conf` (module PHP chargé) et de `vendor/phpmailer/phpmailer/VERSION` | Confirmées : PHP 8.3.28, Apache 2.4.65, PHPMailer 7.1.1. |
| Thème clair / sombre | recherche de `prefers-color-scheme` et `data-theme` dans les deux feuilles de style | **Absent** du site. Signalé comme absent au lieu d'être inventé. |
| Dépendances externes du navigateur | recherche des URL `http(s)://` dans les pages, le CSS et le JS | Aucun CDN, aucune police distante. Seules deux images `data:` URI en ligne. |
| Protection des dossiers privés | lecture des cinq fichiers `.htaccess` et des `index.php` de garde | Confirmée : `Require all denied` plus un second rideau en PHP. |
| Exposition de `products.json` | lecture des règles `FilesMatch` du `.htaccess` racine | Le fichier **n'est pas** bloqué : limite réelle, consignée en R11. |
| Ouverture réseau du vhost | lecture de `conf/extra/httpd-vhosts.conf` | `infoleader.local` est en `Require all granted`, contrairement à `localhost` : limite réelle, consignée en R5. |

Aucun secret n'a été recopié dans les livrables : `DOCUMENTATION.md` ne liste que
les **noms** des clés de `storage/config/admin.php` et `storage/config/mail.php`,
jamais leurs valeurs (ni adresse e-mail, ni hash de mot de passe, ni identifiant ou
mot de passe SMTP).

---

## 5. Points de vigilance retenus

- Les deux fichiers de `storage/config/` contiennent des secrets et sont exclus par
  `.gitignore` : ils ne doivent **jamais** être envoyés sur GitHub.
- Le `README.md` du projet datait du TP 2 et décrivait en partie des intentions ; la
  documentation produite ici part du code réellement exécuté, ce qui explique
  quelques écarts entre les deux documents.
- Les limites les plus graves relevées ne sont pas techniques mais
  **réglementaires** (absence de mentions légales et de politique de
  confidentialité, alors que le site enregistre nom, e-mail, message et adresse IP) :
  elles constituent le jalon 2 du plan.

---

## 6. TP 2ter — Qualité du fork

Le TP 2ter impose de travailler sur une **copie** du site (un « fork ») pour ne
pas risquer le site d'origine, réservé au TP 3.

### 6.1 Mise en place du fork

Demande faite à l'agent : dupliquer `C:\wamp64\www\infoleader` en
`C:\wamp64\www\infoleader-tp2ter`, vérifier que la copie répond sous WAMP, et
**ne plus jamais écrire dans le dossier d'origine**.

Contrôle exigé après chaque étape : aucun fichier de code de l'original modifié.

### 6.2 Consigne qualité front-end (Partie C.6 du TP)

```
Améliore la qualité front-end de cette copie de site (dossier courant) sans changer la logique métier.

Règles :
  - Ne crée AUCUNE base de données, ne restructure PAS en MVC, n'ajoute AUCUN module d'administration.
  - Garde le site fonctionnel et respecte le style existant.
  - Aucune nouvelle dépendance (framework, CDN) sans mon accord.

Objectifs, à traiter un par un (explique avant chaque modification) :
1. Accessibilité : attributs alt, labels de formulaires, contraste, navigation au clavier, focus visible.
2. Responsive : corriger l'affichage sur mobile.
3. Performances : alléger les images et le CSS/JS.
4. SEO : titres et descriptions par page, Open Graph, robots.txt, sitemap.

Pour chaque modification, indique le fichier concerné et comment la tester dans le navigateur.
```

### 6.3 Consigne fonctionnalité (Partie D du TP)

```
Ajoute la fonctionnalité suivante à cette copie de site (dossier courant) : mode sombre.

Contraintes :
  - Aucune base de données, aucun module d'administration, aucune architecture MVC.
  - Code simple et lisible, dans le style du site existant.
  - Accessible (navigation au clavier, contraste) et responsive.
  - Aucune nouvelle dépendance sans mon accord.

Explique ce que tu ajoutes, où, puis comment le tester dans le navigateur.
```

**Résultat** : 31 améliorations, documentées dans
[AMELIORATIONS.md](AMELIORATIONS.md), et un thème clair/sombre mémorisé dans
`localStorage`.

### 6.4 Ce que la vérification a révélé

L'agent n'a pas travaillé sur une liste théorique : il a **mesuré** le site
d'origine dans un navigateur avant de corriger. Deux défauts réels en sont
sortis, qui n'étaient pas dans le plan initial :

| Défaut trouvé par la mesure | Comment |
|---|---|
| Le catalogue affichait « Aucun produit ne correspond à votre recherche » **sous les 6 produits** | Lecture du style calculé de `#noResults` : `display: block`, visible, alors que 6 fiches étaient affichées |
| Les liens du pied de page faisaient **18 à 21 px de haut** sur mobile, sous le minimum WCAG de 24 px | Mesure de `getBoundingClientRect()` sur tous les éléments interactifs à 320 px de large |

C'est la différence entre « l'IA applique une liste » et « on vérifie ce que le
site fait vraiment ».

---

## 7. TP 2quater — Panier et commande simulée

### 7.1 Consigne panier (12.1 du TP)

```
Ajoute un panier 100 % côté navigateur à cette copie de site (dossier courant).

Contraintes :
  - Aucune base de données (ni SQLite ni MySQL), aucun stockage côté serveur : utilise uniquement localStorage.
  - Aucune architecture MVC, aucun module d'administration.
  - JavaScript natif (vanilla), aucune bibliothèque.
  - Garde le reste du site fonctionnel.

À réaliser :
  1. Un bouton « Ajouter au panier » sur chaque produit (informations via des attributs data-* : id, nom, prix).
  2. Une icône panier dans l'en-tête avec un badge indiquant le nombre total d'articles.
  3. Une page « Panier » : lignes (nom, prix unitaire, quantité modifiable, sous-total), suppression d'une ligne, total HT, TVA 20 %, total TTC, état vide.
  4. La persistance du panier (localStorage) : le panier survit au rechargement.

Explique chaque fichier touché et comment tester dans le navigateur.
```

### 7.2 Consigne commande simulée (12.2 du TP)

```
Ajoute un tunnel de commande SIMULÉ à la page Panier.

Contraintes :
  - Aucun paiement réel : aucune passerelle bancaire, aucune donnée de carte. C'est une simulation.
  - Aucune base de données : la commande n'est pas stockée côté serveur.
  - JavaScript natif, aucune dépendance.

À réaliser :
  1. Un formulaire de commande (nom, e-mail, adresse, code postal, ville) avec validation côté client et messages d'erreur.
  2. Un récapitulatif de la commande avant validation.
  3. Après validation : un écran de confirmation affichant un numéro de commande généré et le détail, puis vidage du panier.
  4. Un bouton « Imprimer le récapitulatif » (impression navigateur, via @media print).
```

### 7.3 Consigne finitions (12.3 du TP)

```
Vérifie et améliore l'accessibilité et le responsive du panier et du tunnel de commande :
  - navigation au clavier, focus visible, libellés de formulaires, aria-live pour le badge du panier ;
  - affichage mobile correct (contenu du panier lisible) ;
  - messages d'état cohérents (panier vide, erreurs, succès).
Explique chaque modification et comment la tester.
```

**Résultat** : panier persistant, tunnel de commande en trois états et
récapitulatif imprimable, détaillés dans [PANIER.md](PANIER.md).

### 7.4 Ce que la vérification a révélé

| Problème | Détecté comment | Correction |
|---|---|---|
| L'adresse IP tronquée donnait `::1::`, valeur **invalide** | Lecture du fichier réellement écrit après un envoi de test | Une IPv6 compressée ne peut pas être découpée sur les deux-points : masquage binaire des 80 derniers bits |
| Le lien « Modifier le panier » faisait 115 × 21 px sur mobile | Mesure des cibles tactiles à 320 px | Porté à 44 px de haut |
| Première version de `commande.js` incomplète et avec une faute de syntaxe | Relecture avant exécution | Fichier réécrit entièrement |

### 7.5 Limites que l'agent a signalées de lui-même

Deux limites n'étaient pas demandées par le TP, mais sont documentées en
section 7 de [PANIER.md](PANIER.md) :

1. **Les prix viennent du navigateur.** `data-prix` et le `localStorage` sont
   modifiables par le visiteur. Un vrai site de vente doit recalculer tous les
   montants côté serveur. Ici aucun paiement n'existe, donc aucune conséquence.
2. **Le numéro de commande est propre à chaque navigateur.** Deux visiteurs
   obtiendraient tous les deux `CMD-2026-0001`. Sur un vrai site, la numérotation
   est tenue par le serveur.

---

## 8. Méthode de travail retenue

Ce qui a fonctionné, sur les trois TP :

1. **Travailler sur une copie.** Le site d'origine n'a pas été touché une seule
   fois, et c'était vérifiable à tout moment par la date de modification des
   fichiers.
2. **Mesurer avant de corriger.** Les améliorations les plus utiles sont venues
   de mesures dans le navigateur, pas d'une liste de bonnes pratiques.
3. **Une tâche à la fois, testée.** Chaque modification a été suivie d'un contrôle
   de syntaxe PHP et d'une requête HTTP sur les pages concernées.
4. **Écrire ce qui n'a pas été fait.** Les sections « limites » et « volontairement
   hors périmètre » valent autant que la liste des réussites : elles montrent où
   s'arrête ce qui a été vérifié.
