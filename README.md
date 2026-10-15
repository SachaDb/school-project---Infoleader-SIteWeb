# InfoLeader — Documentation du projet

Branche **`documentation`** du projet scolaire InfoLeader : site vitrine d'une
entreprise informatique fictive, développé en PHP / HTML / CSS / JavaScript et
fonctionnant en local sous WAMP (Apache 2.4.65, PHP 8.3.28, Windows).

Cette branche ne contient **que la documentation**. Le code vit dans les autres
branches.

## Les cinq documents

| Document | TP | Contenu |
|---|---|---|
| [DOCUMENTATION.md](DOCUMENTATION.md) | 2bis | Analyse technique complète du site : architecture, routage, stockage en fichiers JSON, authentification et sécurité, front-end, intégrations, configuration, points forts, et **28 limites connues** (R1 à R28). |
| [TODO_LIST.md](TODO_LIST.md) | 2bis | Plan de **31 améliorations** par thème, priorité (P0/P1/P2) et complexité, avec critère d'acceptation, « Définition de Terminé » et 7 jalons. Inclut l'**état d'avancement** réel. |
| [AMELIORATIONS.md](AMELIORATIONS.md) | 2ter | Travail de qualité réalisé sur le fork : accessibilité, responsive, performances, SEO, UX, conformité RGPD, et thème clair/sombre. Mesures avant/après et **deux bugs réels** corrigés. |
| [PANIER.md](PANIER.md) | 2quater | Panier et commande simulée, 100 % côté navigateur : parcours, clés `localStorage`, calculs de TVA, accessibilité, tests mesurés et limites assumées. |
| [PROMPTS.md](PROMPTS.md) | les trois | Consignes exactes données à l'agent IA, et ce que la vérification a permis de corriger. |

## Les branches du dépôt

| Branche | Contenu |
|---|---|
| `main` | Le site d'origine, tel qu'issu du TP 2 : site vitrine et panneau d'administration. **Volontairement intact** — c'est la base du TP 3. |
| `tp2ter` | Le fork de travail : qualité (TP 2ter) puis panier et commande simulée (TP 2quater). |
| `documentation` | Cette branche : les cinq documents ci-dessus. |

## Le projet en bref

- **Aucun framework**, aucun routeur : Apache fait correspondre directement une URL à un fichier `.php`.
- **Aucune base de données** : les données vivent dans des fichiers JSON et des fichiers PHP de configuration. Ce choix est assumé et documenté.
- **Dépendance unique** : PHPMailer 7.1.1, installée via Composer.
- **Aucune ressource externe** côté navigateur : pas de CDN, pas de police distante. Le site fonctionne sans connexion Internet.
- Site **non déployé** : il tourne uniquement en local sous WAMP.

## Ce qui n'est pas publié, et pourquoi

Les fichiers de `storage/config/` contiennent le hash du mot de passe
administrateur et le mot de passe SMTP en clair. Ils sont exclus par
`.gitignore` et ne doivent **jamais** être poussés. Le dossier `vendor/` est
exclu lui aussi : il se régénère avec `php composer.phar install`.
