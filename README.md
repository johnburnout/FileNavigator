# File Navigator

<img src="assets/img/icon1024x1024.png" alt="Logo File Navigator" width="200" height="200">

Un navigateur de fichiers pour WordPress, propulsé par **Symfony Finder**, **Twig** et une interface moderne.

---

## ✨ Fonctionnalités

- 📂 Navigation dans les dossiers (arborescence cliquable)  
- 🖼️ Aperçu des images (miniature avec zoom au survol)  
- 📑 Icônes spécifiques pour les PDF  
- 📤 Upload de fichiers (glisser‑déposer ou sélecteur)  
- ⬇️ Téléchargement d’un fichier ou d’un ZIP multiple  
- 📋 Copier / Couper / Coller (presse‑papiers avec localStorage)  
- ✏️ Renommer un fichier ou un dossier  
- 🗑️ Supprimer des fichiers ou dossiers  
- 📁 Créer un nouveau dossier  
- 🔒 Gestion des permissions par rôle WordPress  
- 🔍 Tri dynamique par nom, taille, date (menus déroulants)  
- 📅 Affichage de la date au format **JJ/MM/AAAA HH:MM**  
- 📱 Responsive et paramétrable (largeur/hauteur fixe)  
- ⚡ AJAX pour toutes les actions (pas de rechargement)  
- 🎨 Barre d’outils fixe (sticky) lors du défilement

---

## 📦 Prérequis

- WordPress 5.8 ou supérieur  
- PHP 7.4 ou supérieur  
- Extension PHP `zip` (pour le téléchargement groupé)  
- Composer (pour l’installation en développement)

---

## 📥 Installation

### Via le backend WordPress (recommandé)

1. Téléchargez l’archive ZIP depuis votre dépôt ou la page de release.  
2. Allez dans **Extensions → Ajouter → Téléverser une extension**.  
3. Choisissez le fichier ZIP et cliquez sur **Installer maintenant**.  
4. Activez l’extension **“File Navigator”**.

### Via FTP / SFTP

1. Décompressez l’archive dans le dossier `/wp-content/plugins/`.  
2. Activez l’extension dans le menu **Extensions** de WordPress.

---

## 🧩 Utilisation de la shortcode

Insérez la shortcode `[filenavigator]` dans n’importe quelle page ou article.

### Paramètres disponibles

| Paramètre | Description | Valeurs possibles | Défaut |
|:---|:---|:---|:---|
| `root` | Dossier à explorer (relatif à `wp-content/uploads` ou chemin absolu) | `downloads`, `/chemin/absolu` | uploads |
| `extensions` | Extensions autorisées (séparées par des virgules) | `jpg,png,pdf` | (toutes) |
| `sort` | Critère de tri (modifiable dynamiquement via l’interface) | `name`, `size`, `mtime` | `name` |
| `order` | Ordre de tri (modifiable dynamiquement via l’interface) | `asc`, `desc` | `asc` |
| `show_hidden` | Afficher les fichiers cachés | `true`, `false` | `false` |
| `width` | Largeur du navigateur (CSS) | `100%`, `800px` | `100%` |
| `height` | Hauteur du navigateur (barre de défilement) | `500px`, `80vh` | `400px` |
| `public_download` | Autoriser les téléchargements sans connexion | `true`, `false` | `true` |
| `frame` | Affiche un cadre autour du navigateur | `true`, `false` | `false` |
| `background` | Couleur d’arrière‑plan du conteneur et de la liste | `#f9f9f9`, `lightgray`, etc. | (vide) |
| `toolbar_background` | Couleur d’arrière‑plan de la barre d’outils | `#e3f0fa`, `lightblue`, etc. | `#f1f1f1` (CSS) |
| `breadcrumb_background` | Couleur d’arrière‑plan du fil d’Ariane | `#dce8f0`, `lightgray`, etc. | `#f5f5f5` (CSS) |

### Tri dynamique

Le tri peut être modifié directement depuis l’interface grâce aux menus déroulants :

- **Nom** (A→Z / Z→A)  
- **Taille** (croissant / décroissant)  
- **Date** (plus ancien / plus récent)

Les paramètres sont conservés dans l’URL (`?sort=size&order=desc`) lors de la navigation.

### Exemples d’utilisation

```text
[filenavigator]
```
Navigateur dans le dossier `uploads`, largeur 100%, hauteur 400px.  
Date affichée en `JJ/MM/AAAA HH:MM`.

```text
[filenavigator root="downloads" width="800px" height="600px" extensions="jpg,png,pdf"]
```
Explorer le dossier `uploads/downloads`, afficher uniquement les fichiers JPG, PNG et PDF.

```text
[filenavigator root="/home/site/public_html/documents" sort="mtime" order="desc"]
```
Dossier absolu, tri par date de modification du plus récent au plus ancien.

```text
[filenavigator public_download="false"]
```
Téléchargement uniquement autorisé aux utilisateurs connectés.

```text
[filenavigator frame="true" background="#f9f9f9" toolbar_background="#e3f0fa" breadcrumb_background="#dce8f0" width="90%" height="500px"]
```
Navigateur dans le dossier `uploads`, largeur 100%, hauteur 400px. Modification des couleurs.

---

## 🔐 Permissions (capabilités)

| Action | Capacité WordPress | Rôle minimal |
|:---|:---|:---|
| Voir le navigateur | `read` | Tous (abonné) |
| Télécharger | `read` ou `public_download=true` | Tous |
| Uploader | `upload_files` | Contributeur |
| Copier / Couper | `edit_posts` | Auteur |
| Coller | `edit_posts` | Auteur |
| Renommer | `edit_posts` | Auteur |
| Supprimer | `delete_posts` | Auteur |
| Créer un dossier | `edit_posts` | Auteur |

**Personnalisation** : utilisez le filtre `WPFN_permissions` dans votre `functions.php` pour modifier les droits.

```php
add_filter('WPFN_permissions', function($perms) {
    $perms['can_delete'] = current_user_can('manage_options'); // admins seulement
    return $perms;
});
```

---

## 🛠️ Développement

### Installation locale

```bash
git clone https://github.com/johnburnout/FileNavigator.git
cd FileNavigator
composer install
```

### Structure du projet

```
filenavigator/
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── src/
│   ├── FileNavigator/
│   │   ├── FileNavigatorService.php
│   │   ├── FileOperationService.php
│   │   └── SecurityChecker.php
│   └── Shortcode/
│       └── FileNavigatorShortcode.php
├── templates/
│   └── filenavigator.twig
├── vendor/                (généré par Composer)
├── composer.json
├── composer.lock
├── filenavigator.php
├── README.md
└── readme.txt
```

### Tests

Activez le mode debug dans `wp-config.php` :

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
```

Les erreurs apparaîtront dans `wp-content/debug.log`.

---

## 📝 Changelog

### 1.1.2 – 23 août 2026
- Ajout des paramètres `frame`, `background`, `toolbar_background` et `breadcrumb_background` pour personnaliser l’apparence
- Possibilité de désactiver le cadre ou de modifier les couleurs de fond
- 
### 1.1.1 – 23 août 2026
- Téléchargement direct en cliquant sur le nom du fichier
- Lien de téléchargement sur chaque fichier (images, PDF, etc.)
- Ajout du lien vers le profil GitHub de l'auteur dans l'en-tête du plugin

### 1.1.0 – 22 août 2026
- Ajout des paramètres `width` et `height` avec barre de défilement  
- Barre d’outils fixe (sticky)  
- Aperçu des images au survol avec zoom  
- Icônes spécifiques pour les PDF  
- Gestion des permissions par rôle WordPress  
- Filtre `WPFN_permissions` pour personnalisation  
- Correction de la détection des dossiers (`is_dir`)  
- Liens cliquables pour les dossiers  
- **Tri dynamique** par nom, taille, date (menus déroulants)  
- **Affichage de la date au format JJ/MM/AAAA HH:MM**  
- Amélioration de la sécurité (SecurityChecker)

### 1.0.0 – 20 août 2026
- Version initiale : navigateur de fichiers, shortcode, Symfony Finder, Twig, barre d’outils AJAX (upload, download, copier, couper, coller, renommer, supprimer, nouveau dossier)


---

## 👤 Auteur

**Jean Roussie** – [GitHub](https://github.com/johnburnout)

---

## 📄 Licence

Ce projet est sous licence **GPL-2.0+** – voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 🤝 Contribution

Les contributions sont les bienvenues !  
1. Forkez le projet  
2. Créez votre branche (`git checkout -b feature/ma-fonctionnalite`)  
3. Commitez (`git commit -m 'Ajout de ma fonctionnalité'`)  
4. Pushez (`git push origin feature/ma-fonctionnalite`)  
5. Ouvrez une Pull Request

---

## 🐛 Signaler un bug

Utilisez l’onglet [Issues](https://github.com/johnburnout/FileNavigator/issues) de GitHub.

---

**Profitez d’une gestion de fichiers simple et puissante directement dans WordPress !** 🚀
