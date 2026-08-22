# WP FileBrowser

![Logo WP FileBrowser](assets/img/icon1024x1024.png)

Un navigateur de fichiers pour WordPress, propulsé par Symfony Finder et Twig.

## Fonctionnalités

- Navigation dans les dossiers via shortcode `[filebrowser]`
- Paramètres : `root`, `extensions`, `sort`, `order`, `show_hidden`
- Templates Twig pour un affichage propre et sécurisé
- Gestion des permissions par `SecurityChecker` (empêche les remontées de dossier)
- Tri par nom, taille, date de modification

## Installation

1. Placez le plugin dans `/wp-content/plugins/wp-filebrowser/`
2. Exécutez `composer install` pour installer les dépendances (Symfony Finder, Twig)
3. Activez le plugin dans WordPress
4. Utilisez la shortcode : `[filebrowser root="downloads" extensions="jpg,png,pdf"]`

## Exemples d'utilisation

- `[filebrowser]` – explore le dossier uploads par défaut
- `[filebrowser root="downloads"]` – explore le sous‑dossier downloads de uploads
- `[filebrowser root="/chemin/absolu" sort="mtime" order="desc"]` – tri par date modifiée, plus récent en premier

## Développement

- Les classes PHP sont dans `src/`
- Les templates Twig sont dans `templates/`
- Les assets (CSS) sont dans `assets/`

## Auteur

Jean Roussie