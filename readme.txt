=== File Navigator ===
Contributors: johnburnout
Tags: file manager, file browser, upload, download, symfony, twig, files, media
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Un navigateur de fichiers pour WordPress, propulsé par Symfony Finder et Twig. Upload, download, copier, coller, supprimer, renommer, créer des dossiers, aperçu des images.

== Description ==

**File Navigator** est un navigateur de fichiers intégré à WordPress, accessible via une shortcode `[filenavigator]`. Il permet de gérer les fichiers du serveur directement depuis votre site.

### Fonctionnalités

* 📂 Navigation dans les dossiers (arborescence cliquable)
* 🖼️ Aperçu des images (miniature avec zoom au survol)
* 📑 Icônes spécifiques pour les PDF
* 📤 Upload de fichiers
* ⬇️ Téléchargement d'un fichier ou d'un ZIP multiple
* 📋 Copier / Couper / Coller (presse‑papiers avec localStorage)
* ✏️ Renommer un fichier ou un dossier
* 🗑️ Supprimer des fichiers ou dossiers
* 📁 Créer un nouveau dossier
* 🔒 Gestion des permissions par rôle WordPress
* 📱 Responsive et paramétrable (largeur/hauteur fixe)
* ⚡ AJAX pour toutes les actions
* 🔍 Tri dynamique par nom, taille, date

### Paramètres de la shortcode

| Paramètre | Description | Valeurs possibles | Défaut |
|-----------|-------------|-------------------|--------|
| `root` | Dossier à explorer (relatif à `wp-content/uploads` ou absolu) | `downloads`, `/chemin/absolu` | uploads |
| `extensions` | Extensions autorisées | `jpg,png,pdf` | (toutes) |
| `sort` | Critère de tri | `name`, `size`, `mtime` | `name` |
| `order` | Ordre de tri | `asc`, `desc` | `asc` |
| `show_hidden` | Afficher les fichiers cachés | `true`, `false` | `false` |
| `width` | Largeur du navigateur | `100%`, `800px` | `100%` |
| `height` | Hauteur du navigateur | `500px`, `80vh` | `400px` |
| `public_download` | Téléchargement public | `true`, `false` | `true` |

### Exemples d'utilisation

`[filenavigator]`

Navigateur dans le dossier `uploads`, largeur 100%, hauteur 400px.

`[filenavigator root="downloads" width="800px" height="600px" extensions="jpg,png,pdf"]`

Explorer le dossier `uploads/downloads`, afficher uniquement JPG, PNG et PDF.

`[filenavigator root="/home/site/public_html/documents" sort="mtime" order="desc"]`

Dossier absolu, tri par date de modification du plus récent au plus ancien.

`[filenavigator public_download="false"]`

Téléchargement uniquement autorisé aux utilisateurs connectés.

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

== Arbitrary Section ==

### Développement

Le plugin est open‑source. Les contributions sont les bienvenues sur GitHub :

[https://github.com/johnburnout/WP-FileNavigator](https://github.com/johnburnout/WP-FileNavigator)

Merci d'utiliser File Navigator ! 🚀