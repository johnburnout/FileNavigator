=== File Navigator ===
Contributors: johnburnout
Tags: file manager, file browser, upload, download, symfony
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tested up to: 7.1
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
* ⚡ All actions via AJAX (no page reload)

### Shortcode Parameters

| Parameter | Description | Possible values | Default |
|-----------|-------------|-----------------|---------|
| `root` | Directory to browse (relative to `wp-content/uploads` or absolute path) | `downloads`, `/absolute/path` | uploads |
| `extensions` | Allowed file extensions (comma-separated) | `jpg,png,pdf` | (all) |
| `sort` | Sorting criteria | `name`, `size`, `mtime` | `name` |
| `order` | Sort order | `asc`, `desc` | `asc` |
| `show_hidden` | Show hidden files | `true`, `false` | `false` |
| `width` | Browser width (CSS value) | `100%`, `800px` | `100%` |
| `height` | Browser height (scrollbars) | `500px`, `80vh` | `400px` |
| `public_download` | Allow downloads for non-logged-in users | `true`, `false` | `true` |

### Usage Examples

`[filenavigator]`

Browse the `uploads` folder, 100% width, 400px height.  
Date displayed as `DD/MM/YYYY HH:MM`.

`[filenavigator root="downloads" width="800px" height="600px" extensions="jpg,png,pdf"]`

Browse `uploads/downloads`, show only JPG, PNG, and PDF files.

`[filenavigator root="/home/site/public_html/documents" sort="mtime" order="desc"]`

Absolute path, sort by modification date (newest first).

`[filenavigator public_download="false"]`

Downloads restricted to logged-in users only.

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

== Arbitrary Section ==

### Développement

Le plugin est open‑source. Les contributions sont les bienvenues sur GitHub :

[https://github.com/johnburnout/FileNavigator](https://github.com/johnburnout/FileNavigator)

Merci d'utiliser File Navigator ! 🚀