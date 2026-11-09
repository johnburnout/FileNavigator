=== File Navigator ===
Contributors: johnburnout
Tags: file manager, file browser, upload, download, symfony
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful file manager for WordPress, powered by Symfony Finder and Twig.

== Description ==

**File Navigator** is a powerful file manager for WordPress, accessible via the `[filenavigator]` shortcode. It allows you to manage server files directly from your site.

### Features

* 📂 Folder navigation (clickable tree structure)
* 🖼️ Image preview (thumbnail with hover zoom)
* 📑 PDF-specific icons
* 📤 File upload
* ⬇️ Single or batch (ZIP) file download
* 📋 Copy / Cut / Paste (localStorage clipboard)
* ✏️ Rename files and folders
* 🗑️ Delete files and folders
* 📁 Create new folders
* 🔒 Role-based permission management (WordPress capabilities)
* 🔍 Dynamic sorting (name, size, date) with dropdown menus
* 📅 Date display format: **DD/MM/YYYY HH:MM**
* 📱 Responsive and customizable (fixed width/height)
* ⚡ All actions via AJAX (the list refreshes once an action completes)

### Shortcode Parameters

| Parameter | Description | Possible values | Default |
|-----------|-------------|-----------------|---------|
| `root` | Directory to browse, relative to `wp-content/uploads` (an absolute path is accepted only if it is inside `uploads`; see the `WPFN_root_base` filter) | `downloads`, `clients/2026` | uploads |
| `extensions` | Allowed file extensions (comma-separated) | `jpg,png,pdf` | (all) |
| `sort` | Sorting criteria | `name`, `size`, `mtime` | `name` |
| `order` | Sort order | `asc`, `desc` | `asc` |
| `show_hidden` | Show hidden files | `true`, `false` | `false` |
| `width` | Browser width (CSS value) | `100%`, `800px` | `100%` |
| `height` | Browser height (scrollbars) | `500px`, `80vh` | `400px` |
| `public_download` | Allow downloads for non-logged-in users | `true`, `false` | `false` |
| `frame` | Affiche un cadre autour du navigateur | `true`, `false` | `false` |
| `background` | Couleur d’arrière‑plan du conteneur et de la liste | `#f9f9f9`, `lightgray`, etc. | (vide) |
| `toolbar_background` | Couleur d’arrière‑plan de la barre d’outils | `#e3f0fa`, `lightblue`, etc. | `#f1f1f1` (CSS) |
| `breadcrumb_background` | Couleur d’arrière‑plan du fil d’Ariane | `#dce8f0`, `lightgray`, etc. | `#f5f5f5` (CSS) |

### Usage Examples

`[filenavigator]`

Browse the `uploads` folder, 100% width, 400px height.  
Date displayed as `DD/MM/YYYY HH:MM`.

`[filenavigator root="downloads" width="800px" height="600px" extensions="jpg,png,pdf"]`

Browse `uploads/downloads`, show only JPG, PNG, and PDF files.

`[filenavigator root="documents" sort="mtime" order="desc"]`

Folder `uploads/documents`, sort by modification date (newest first).

`[filenavigator public_download="true"]`

Downloads allowed for non-logged-in visitors (by default, downloads are restricted to logged-in users).

`[filenavigator frame="true" background="#f9f9f9" toolbar_background="#e3f0fa" breadcrumb_background="#dce8f0" width="90%" height="500px"]`

Browse the `uploads` folder, 100% width, 400px height. Changed colors.

### Allowed base folder

For security, `root` is always confined to `wp-content/uploads`: paths leaving it (`../..`, `/`, symbolic links…) are refused. To allow another base folder, add to your theme or a mu-plugin:

`add_filter('WPFN_root_base', fn() => '/home/site/documents');`

### Bulk download (ZIP) limits

Downloading several items or a folder is limited to 2000 files and 512 MB by default. Use the `WPFN_zip_limits` filter to change these limits (it also receives whether the visitor is logged in):

`add_filter('WPFN_zip_limits', fn($limits, $loggedIn) => ['max_size' => 100 * MB_IN_BYTES, 'max_files' => 500], 10, 2);`

== Installation ==

1. Téléchargez l'archive ZIP.
2. Allez dans **Extensions → Ajouter → Téléverser une extension**.
3. Sélectionnez le fichier ZIP et cliquez sur **Installer maintenant**.
4. Activez l'extension **"File Navigator"**.
5. Insérez la shortcode `[filenavigator]` dans n'importe quelle page ou article.

== FAQ ==

**Comment personnaliser les permissions ?**

Utilisez le filtre `WPFN_permissions` dans votre `functions.php` :

```php
add_filter('WPFN_permissions', function($perms) {
    $perms['can_delete'] = current_user_can('manage_options');
    return $perms;
});
```

**Comment changer le format de la date ?**

Modifiez le template `filenavigator.twig` et remplacez `'d/m/Y H:i'` par le format souhaité.

**Le téléchargement ne fonctionne pas ?**

Vérifiez que l'extension PHP `zip` est activée sur votre serveur.

**Où sont stockés les fichiers uploadés ?**

Dans le dossier `wp-content/uploads/` ou dans le sous‑dossier spécifié par `root`.

== Screenshots ==

1. Vue principale avec barre d'outils et liste des fichiers.
2. Upload de fichiers.
3. Aperçu des images au survol.
4. Menus de tri dynamique.

== Changelog ==

= 1.3.5 – 9 novembre 2026 =
* Mises à jour signées : chaque paquet est signé (Ed25519) hors de GitHub ; le plugin refuse d'installer une mise à jour dont la signature est absente ou invalide
* Changement incompatible : `public_download` vaut désormais `false` par défaut (téléchargement réservé aux utilisateurs connectés) ; ajouter `public_download="true"` pour l'ouvrir aux visiteurs
* Sécurité : le chemin absolu du serveur n'apparaît plus dans la page (racine transmise relativement à `uploads`)
* Sécurité : le dossier racine ne peut plus être supprimé, renommé ni déplacé
* Sécurité : `symfony/filesystem` mis à jour en 6.4.50 (faille de sortie du dossier de base via `Path`)
* Sécurité : téléchargement ZIP limité (2000 fichiers, 512 Mo par défaut, filtre `WPFN_zip_limits`), noms d'entrées assainis, liens symboliques sortants ignorés
* Sécurité : nonce retiré des liens de téléchargement ; couleurs et dimensions du shortcode validées ; paramètres de tri limités à une liste blanche
* Nouveau : plusieurs navigateurs sur une même page (paramètres d'URL `fb_dir_2`, `sort_2`… pour les suivants)
* Correction : coller un dossier dans lui-même ou dans un sous-dossier affiche un message clair (la copie bouclait)
* Correction : plus d'erreur fatale sur des données JSON mal formées
* Tests automatisés (PHPUnit) lancés par GitHub Actions à chaque push et avant chaque release

= 1.3.4 – 9 novembre 2026 =
* Sécurité : l'attribut `root` est désormais confiné à `wp-content/uploads` (chemins absolus hors de ce dossier, `../` et liens symboliques refusés). **Changement incompatible** : pour un autre dossier, utiliser le filtre `WPFN_root_base`
* Sécurité : correction d'une faille XSS dans les messages d'erreur du téléchargement
* Sécurité : nom de fichier assaini dans l'en-tête `Content-Disposition` (prise en charge des noms UTF-8)
* Correction : plus d'erreur 500 lorsqu'un paramètre est envoyé sous forme de tableau
* Correction : message clair si l'extension PHP `zip` est absente

= 1.3.3 – 7 novembre 2026 =
* Mises à jour automatiques activées par défaut ; `define('WPFN_FORCE_AUTO_UPDATE', false);` dans `wp-config.php` pour les désactiver

= 1.3.2 – 7 novembre 2026 =
* Correction : l'option `show_hidden="true"` affiche désormais les fichiers et dossiers cachés (les dossiers `.git`, `.svn`… restent masqués)
* Correction : renommage des fichiers dont le nom contient une apostrophe
* Coller : en cas de doublon, les dossiers et fichiers cachés sont renommés comme à l'upload (`dossier.v2_1`, `.htaccess_1`)
* Refactorisation interne du code PHP (endpoints AJAX regroupés dans une classe)

= 1.3.1 – 4 novembre 2026 =
* Glisser-déposer sur un dossier de la liste : les fichiers et dossiers sont envoyés dans ce dossier (surbrillance du dossier visé)

= 1.3.0 – 23 octobre 2026 =
* Upload par glisser-déposer de fichiers et de dossiers dans la fenêtre du navigateur (utilisateurs connectés avec `upload_files`)
* Fenêtre de conflit : Remplacer / Garder les deux (fichiers), Fusionner / Remplacer / Garder les deux (dossiers), option « Appliquer à tous »
* Remplacer ou fusionner nécessite aussi `delete_posts`
* Envoi par lots (respect de `max_file_uploads` et de la taille maximale) avec progression
* Sécurité : dossier racine signé et vérifié côté serveur pour toutes les actions
* Sécurité : `public_download="false"` appliqué côté serveur
* Sécurité : contrôle des fichiers envoyés (types WordPress + WebP, extensions dangereuses refusées même en double extension, vérification du contenu réel, détection de code PHP dans les images) ; filtre `WPFN_allowed_mimes`
* Sécurité : le renommage ne peut plus produire une extension interdite ni sortir du dossier

= 1.1.0 – 22 août 2026 =
* Ajout des paramètres `width` et `height` avec barre de défilement
* Barre d'outils fixe (sticky)
* Aperçu des images au survol avec zoom
* Icônes spécifiques pour les PDF
* Gestion des permissions par rôle WordPress
* Filtre `WPFN_permissions` pour personnalisation
* Correction de la détection des dossiers (`is_dir`)
* Liens cliquables pour les dossiers
* Tri dynamique par nom, taille et date
* Affichage de la date au format JJ/MM/AAAA HH:MM
* Amélioration de la sécurité (SecurityChecker)

= 1.0.0 – 20 août 2026 =
* Version initiale : navigateur de fichiers, shortcode, Symfony Finder, Twig, barre d'outils AJAX (upload, download, copier, couper, coller, renommer, supprimer, nouveau dossier)

== Upgrade Notice ==

= 1.1.0 =
Mise à jour majeure avec tri dynamique, aperçu des images, barre d'outils fixe et gestion des permissions.

= 1.1.1 – 23 août 2026 =
* Téléchargement direct en cliquant sur le nom du fichier
* Lien de téléchargement sur chaque fichier (images, PDF, etc.)
* Ajout du lien vers le profil GitHub de l'auteur dans l'en-tête du plugin (Author URI)

= 1.1.2 – 23 août 2026 =
* Ajout des paramètres `frame`, `background`, `toolbar_background` et `breadcrumb_background`
* Personnalisation complète de l'apparence du navigateur (cadre, couleurs)

== Arbitrary Section ==

### Développement

Le plugin est open‑source. Les contributions sont les bienvenues sur GitHub :

[https://github.com/johnburnout/FileNavigator](https://github.com/johnburnout/FileNavigator)

Merci d'utiliser File Navigator ! 🚀

= 1.2.0 – 22 octobre 2026 =
* Mises à jour automatiques depuis les releases GitHub
* Lien « Vérifier les mises à jour » dans la liste des extensions
* PHP 8.1 minimum (requis par Symfony 6.4)
