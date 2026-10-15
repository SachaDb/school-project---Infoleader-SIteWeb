# InfoLeader — Analyse du site et plan d'améliorations

Livrables du **TP 2bis** (BTS SIO 1re année) portant sur le site vitrine
« InfoLeader », développé en PHP / HTML / CSS / JavaScript et fonctionnant en
local sous WAMP (Apache 2.4.65, PHP 8.3.28, Windows).

Ce dépôt ne contient **que la documentation**. Le code du site n'y est pas publié.

| Fichier | Contenu |
|---|---|
| [DOCUMENTATION.md](DOCUMENTATION.md) | Analyse technique complète : architecture, routage, stockage des données (fichiers JSON), authentification et sécurité, front-end, intégrations, configuration, points forts, et 28 limites connues (R1 à R28). |
| [TODO_LIST.md](TODO_LIST.md) | Plan de 31 améliorations classées par thème, priorité (P0/P1/P2) et complexité, avec critère d'acceptation, « Définition de Terminé » et séquencement en 7 jalons. |
| [PROMPTS.md](PROMPTS.md) | Consignes exactes données à l'agent IA et vérifications effectuées sur son travail. |

## Contexte technique du site analysé

- Aucun framework, aucun routeur : Apache fait correspondre directement une URL à un fichier `.php`.
- **Aucune base de données** : les données sont stockées dans des fichiers JSON et des fichiers PHP de configuration.
- Dépendance unique : PHPMailer 7.1.1, installée via Composer.
- Site non déployé : il fonctionne uniquement en local sous WAMP.

## Remarque

Les fichiers de configuration contenant des secrets (`storage/config/`) ne sont
pas et ne doivent pas être publiés : ils contiennent le hash du mot de passe
administrateur et le mot de passe SMTP.
