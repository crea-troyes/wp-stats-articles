=== Stats Visites ===
Contributors: GUILLIER Alban
Tags: statistiques, visites, articles, admin
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.2.5
Requires PHP: 7.4 or later
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Statistiques de visites pour articles WordPress, avec suivi des visiteurs actifs et classement par popularité.

== Description ==
Stats Visites est un plugin léger pour suivre les statistiques de vos articles WordPress. 
Il permet de connaître :
* Le nombre de visiteurs actifs sur votre site
* Sur quelle page ils se trouvent
* Le nombre de vues de chaque article (articles uniquement)
* Un classement des articles les plus lus
* Des filtres par période : depuis toujours, 30 derniers jours, 7 derniers jours et hier

Le plugin exclut automatiquement :
* L'administrateur connecté
* Les robots et crawlers

== Installation ==
1. Téléversez le dossier `WP-stats-articles` dans le répertoire `/wp-content/plugins/`
2. Activez le plugin via le menu 'Plugins' dans WordPress
3. Les statistiques apparaîtront dans le menu admin sous "Statistiques"

== Changelog ==
= 1.0.0 =
* Version initiale avec suivi visiteurs actifs et classement des articles
* Filtres par période
* Pagination 50 articles par page
* Interface CSS pour le BackOffice
= 1.1.0 =
* Ajout du filtre des bots
* Ajout du filtre "Aujourd’hui"
* Bouton de réinitialisation des tables
* Bouton de réinitialisation des visiteurs actifs
= 1.2.0 =
* Correction du bug pour Firefox
* Optimisation de l'enregistrement des vues
* Mise à jour des bots
* Optimisation CSS du bouton de filtre
= 1.2.5 =
* Refonte des titres
* Optimisation de l'enregistrement des vues

== Frequently Asked Questions ==
= L'admin est-il comptabilisé ? =
Non, les utilisateurs ayant la capacité 'manage_options' (admin) ne sont jamais comptabilisés.

= Les robots sont-ils exclus ? =
Oui, le plugin filtre automatiquement les robots connus via le User-Agent.

= Les pages et recherches sont-elles comptabilisées ? =
Non, seules les publications de type "post" sont suivies.

