# Consignes données à l'agent IA — TP 2bis

Ce fichier conserve la trace de l'usage de l'agent IA pour l'analyse et
l'amélioration du site InfoLeader, conformément au TP 2bis
(« Conservez une trace. La transparence sur l'usage de l'IA fait partie de
l'évaluation »).

- **Projet analysé** : `C:\wamp64\www\infoleader`
- **Environnement** : WAMP local (Apache 2.4.65, PHP 8.3.28, Windows 11)
- **Livrables produits** : `DOCUMENTATION.md`, `TODO_LIST.md`, ce fichier

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
