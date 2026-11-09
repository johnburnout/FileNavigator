# File Navigator

<img src="assets/img/icon1024x1024.png" alt="Logo File Navigator" width="200" height="200">

Un navigateur de fichiers pour WordPress, propulsé par **Symfony Finder**, **Twig** et une interface moderne.

---

## ✨ Fonctionnalités

- 📂 Navigation dans les dossiers (arborescence cliquable)  
- 🖼️ Aperçu des images (miniature avec zoom au survol)  
- 📑 Icônes spécifiques pour les PDF  
- 📤 Upload de fichiers et de dossiers entiers (glisser‑déposer dans la fenêtre ou sélecteur), avec choix en cas de conflit : remplacer, fusionner (dossiers) ou garder les deux  
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
- PHP 8.1 ou supérieur (requis par Symfony 6.4)  
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

## 🔄 Mises à jour automatiques

Le plugin se met à jour depuis les [releases GitHub](https://github.com/johnburnout/FileNavigator/releases), comme une extension du répertoire officiel :

* WordPress vérifie la dernière release (résultat mis en cache 6 h) et affiche la mise à jour dans **Extensions** et **Tableau de bord → Mises à jour** ;
* le lien **Vérifier les mises à jour** (sous le nom du plugin) force une vérification immédiate ;
* les mises à jour s'installent **automatiquement** (tâche planifiée de WordPress, environ deux fois par jour) ; pour revenir au choix manuel, voir `WPFN_FORCE_AUTO_UPDATE` ci-dessous ;
* chaque paquet est **signé** : le plugin vérifie la signature avant toute installation et refuse un paquet non signé ou modifié, même s'il provient du dépôt GitHub officiel (voir « Signature des mises à jour »). Les administrateurs qui préfèrent relire chaque version avant de l'installer peuvent désactiver l'installation automatique.

### Publier une nouvelle version

1. Mettre à jour `Version:` et `WPFN_VERSION` dans `filenavigator.php` et `Stable tag:` dans `readme.txt` (ex. `1.2.0`), ainsi que le changelog.
   Rédiger le descriptif de la release dans `.github/release-notes/vX.Y.Z.md` : il est repris automatiquement par le workflow (suivi de la liste des commits).
2. Committer, puis créer et pousser le tag :
   ```bash
   git tag v1.2.0
   git push origin main v1.2.0
   ```
3. Le workflow GitHub Actions `.github/workflows/release.yml` lance les tests, installe les dépendances Composer, construit `filenavigator.zip` (avec `vendor/`) et publie la release.
4. **Signer la release** depuis la machine qui détient la clé privée (le script attend la fin du workflow) :
   ```bash
   bin/sign-release.sh v1.2.0
   ```
   Il télécharge `filenavigator.zip`, le signe et publie `filenavigator.zip.sig`. **Tant que la signature n'est pas publiée, aucun site ne se met à jour.**

> Seules les releases contenant l'asset `filenavigator.zip` **et sa signature** `filenavigator.zip.sig` sont proposées (l'archive source de GitHub ne contient pas `vendor/`). Les brouillons et pré-versions sont ignorés.

### Signature des mises à jour

Chaque paquet est signé (Ed25519) avec une clé privée qui ne quitte jamais la machine du mainteneur (`~/.config/filenavigator/release-signing.key`, ou `$WPFN_SIGNING_KEY`). Le plugin contient la clé publique (`GitHubUpdater::PUBLIC_KEY`) et refuse d'installer un paquet dont la signature est absente ou invalide : un compte GitHub compromis ne suffit donc pas à diffuser du code sur les sites.

* **Sauvegardez la clé privée** hors de cette machine (gestionnaire de mots de passe, support chiffré). Perdue, elle ne peut pas être recréée : les sites ne pourraient plus être mis à jour automatiquement et devraient installer à la main une version contenant une nouvelle clé publique.
* Nouvelle paire de clés : `php bin/release-keygen.php`, puis recopier la clé publique affichée dans `GitHubUpdater::PUBLIC_KEY`.

#### Rotation de la clé

Les sites installés ne font confiance qu'à la clé publique de leur version. Changer de clé passe donc par une **version de transition** : elle contient la nouvelle clé publique, mais elle est signée avec l'ancienne.

1. Générer la nouvelle paire **sans écraser l'ancienne** :
   ```bash
   php bin/release-keygen.php ~/.config/filenavigator/release-signing-2.key
   ```
2. Recopier la nouvelle clé publique dans `GitHubUpdater::PUBLIC_KEY`, publier la version (tag), puis la signer avec **l'ancienne** clé :
   ```bash
   WPFN_KEY_ROTATION=1 bin/sign-release.sh vX.Y.Z
   ```
3. Une fois les sites passés à cette version, signer les suivantes avec la nouvelle clé (`WPFN_SIGNING_KEY=~/.config/filenavigator/release-signing-2.key`, ou la renommer en `release-signing.key`), puis supprimer l'ancienne.

**Clé privée compromise :** la personne qui la détient peut signer des paquets acceptés par les sites. Il faut faire la rotation au plus vite : la version de transition retire sa confiance à l'ancienne clé. Comme une signature ne protège plus rien dans ce cas, il faut aussi sécuriser le compte GitHub (mot de passe, double authentification, jetons). Prévenez les administrateurs : ils peuvent suspendre les mises à jour avec `define('WPFN_FORCE_AUTO_UPDATE', false);` puis installer la version de transition à la main.

**Clé privée perdue :** aucune version signée ne peut plus être produite. Les sites doivent installer à la main une version qui contient une nouvelle clé publique.

### Options (`wp-config.php`)

```php
define('WPFN_GITHUB_TOKEN', 'ghp_xxx');    // dépôt privé ou limite de l'API GitHub (60 req/h sans jeton)
define('WPFN_FORCE_AUTO_UPDATE', false);   // désactiver l'installation automatique (activée par défaut)
```

## 🧩 Utilisation de la shortcode

Insérez la shortcode `[filenavigator]` dans n’importe quelle page ou article.

### Paramètres disponibles

| Paramètre | Description | Valeurs possibles | Défaut |
|:---|:---|:---|:---|
| `root` | Dossier à explorer, relatif à `wp-content/uploads` (un chemin absolu n’est accepté que s’il se trouve dans `uploads` ; voir le filtre `WPFN_root_base`) | `downloads`, `clients/2026` | uploads |
| `extensions` | Extensions autorisées (séparées par des virgules) | `jpg,png,pdf` | (toutes) |
| `sort` | Critère de tri (modifiable dynamiquement via l’interface) | `name`, `size`, `mtime` | `name` |
| `order` | Ordre de tri (modifiable dynamiquement via l’interface) | `asc`, `desc` | `asc` |
| `show_hidden` | Afficher les fichiers cachés | `true`, `false` | `false` |
| `width` | Largeur du navigateur (CSS) | `100%`, `800px` | `100%` |
| `height` | Hauteur du navigateur (barre de défilement) | `500px`, `80vh` | `400px` |
| `public_download` | Autoriser les téléchargements sans connexion | `true`, `false` | `false` |
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
[filenavigator root="documents" sort="mtime" order="desc"]
```
Dossier `uploads/documents`, tri par date de modification du plus récent au plus ancien.

```text
[filenavigator public_download="true"]
```
Téléchargement autorisé aux visiteurs non connectés (par défaut, il est réservé aux utilisateurs connectés).

```text
[filenavigator frame="true" background="#f9f9f9" toolbar_background="#e3f0fa" breadcrumb_background="#dce8f0" width="90%" height="500px"]
```
Navigateur dans le dossier `uploads`, largeur 100%, hauteur 400px. Modification des couleurs.

### Dossier de base autorisé

Pour des raisons de sécurité, `root` est toujours confiné à `wp-content/uploads` : un chemin qui en sort (`../..`, `/`, lien symbolique…) est refusé. Pour autoriser une autre base, ajoutez dans votre thème ou un mu-plugin :

```php
add_filter('WPFN_root_base', fn() => '/home/site/documents');
```

### Limites du téléchargement groupé (ZIP)

Le téléchargement de plusieurs éléments ou d’un dossier est limité par défaut à 2000 fichiers et 512 Mo, pour éviter qu’un visiteur ne surcharge le serveur. Le filtre `WPFN_zip_limits` permet de les ajuster ; il reçoit aussi un booléen indiquant si le visiteur est connecté :

```php
add_filter('WPFN_zip_limits', function (array $limits, bool $loggedIn) {
    return $loggedIn
        ? ['max_size' => 2 * GB_IN_BYTES, 'max_files' => 10000]
        : ['max_size' => 100 * MB_IN_BYTES, 'max_files' => 500];
}, 10, 2);
```

### Gros fichiers : envoi par le serveur web

Par défaut, PHP transmet lui-même les fichiers téléchargés, sans les charger en mémoire. Pour de très gros fichiers, le serveur web peut s'en charger et libérer PHP immédiatement. À n'activer que si le serveur est configuré, sinon les fichiers téléchargés seraient vides :

```php
// Apache (mod_xsendfile, avec XSendFilePath autorisant le dossier uploads) ou LiteSpeed
define('WPFN_SENDFILE', 'x-sendfile');

// Nginx : correspondance chemin disque => location interne
define('WPFN_SENDFILE', 'x-accel-redirect');
define('WPFN_ACCEL_MAP', ['/var/www/site/wp-content/uploads/' => '/wpfn-uploads/']);
// location /wpfn-uploads/ { internal; alias /var/www/site/wp-content/uploads/; }
```

Les archives ZIP sont toujours envoyées par PHP.

---

## 🔐 Permissions (capabilités)

| Action | Capacité WordPress | Rôle minimal |
|:---|:---|:---|
| Voir le navigateur | `read` | Tous (abonné) |
| Télécharger | `read` ou `public_download=true` | Tous |
| Uploader (bouton ou glisser‑déposer) | `upload_files` | Auteur |
| Remplacer / fusionner un élément existant lors d’un upload | `upload_files` + `delete_posts` | Auteur |
| Copier / Couper | `edit_posts` | Auteur |
| Coller | `edit_posts` | Auteur |
| Renommer | `edit_posts` | Auteur |
| Supprimer | `delete_posts` | Auteur |
| Créer un dossier | `edit_posts` | Auteur |

### Types de fichiers autorisés à l’upload

Par défaut : les types autorisés par WordPress (`get_allowed_mime_types()`) **+ WebP**. Le contenu réel de chaque fichier est vérifié (`wp_check_filetype_and_ext()`), et les fichiers dont le contenu ne correspond pas à l’extension sont refusés.

Toujours refusés, même en double extension : scripts serveur (`php`, `phtml`, `phar`, `cgi`, `pl`, `py`, `sh`, `asp`, `jsp`…), exécutables (`exe`, `bat`, `msi`…), fichiers de configuration (`.htaccess`, `.user.ini`…) et contenus actifs dans le navigateur (`html`, `js`, `svg`). Les fichiers sans extension sont refusés.

```php
add_filter('WPFN_allowed_mimes', function($mimes) {
    $mimes['csv'] = 'text/csv';   // ajouter un type
    unset($mimes['mp3|m4a|m4b']); // en retirer un
    return $mimes;
});
```

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
│   ├── Ajax/AjaxController.php
│   ├── FileNavigator/
│   │   ├── FileNavigatorService.php
│   │   ├── FileOperationService.php
│   │   ├── RootSignature.php
│   │   ├── SecurityChecker.php
│   │   └── UploadValidator.php
│   ├── Shortcode/
│   │   └── FileNavigatorShortcode.php
│   └── Updater/GitHubUpdater.php
├── bin/                   (scripts de signature des releases, hors archive)
├── templates/
│   └── filenavigator.twig
├── tests/                 (tests PHPUnit, hors archive)
├── vendor/                (généré par Composer)
├── composer.json
├── composer.lock
├── filenavigator.php
├── README.md
└── readme.txt
```

### Tests

Les tests automatisés (PHPUnit) couvrent les composants de sécurité : confinement des chemins, signature du dossier racine, validation des uploads, opérations sur les fichiers, signature des mises à jour. Ils ne nécessitent pas WordPress :

```bash
composer test
```

Ils sont lancés par GitHub Actions à chaque push sur `main`, sur chaque pull request et avant chaque release (PHP 8.1, 8.3 et 8.4), avec `composer audit`.

### Débogage

Activez le mode debug dans `wp-config.php` :

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
```

Les erreurs apparaîtront dans `wp-content/debug.log`.

---

## 📝 Changelog

### 1.3.8 – 9 novembre 2026
- Sans droit de téléchargement, le nom des fichiers s'affiche sans lien (plus d'erreur 403 au clic) et les miniatures non publiques sont remplacées par une icône
- Gros fichiers : envoi sans chargement en mémoire (`Content-Length`, tampons vidés) ; option X-Sendfile / X-Accel-Redirect via `WPFN_SENDFILE` dans `wp-config.php`
- Dossier courant normalisé proprement (les noms contenant « .. », comme « v1..2 », ne sont plus déformés)
- Correction : « Renommer » masque aussi le nom des fichiers pendant la saisie
- Documentation : signature des mises à jour et procédure de rotation de la clé (`WPFN_KEY_ROTATION`)

### 1.3.7 – 9 novembre 2026
- Correction : une mise à jour pouvait échouer (« paquet de mise à jour inconnu ») si l'API GitHub était momentanément indisponible ; la signature est désormais trouvée sans appel à l'API
- Correction : le cache des releases n'est plus vidé lors de la mise à jour d'une autre extension
- Messages d'erreur de mise à jour plus précis (signature introuvable, non téléchargeable ou invalide)

### 1.3.6 – 9 novembre 2026
- Nouveau : bouton 🔗 à côté de chaque fichier pour copier son lien de téléchargement (visible seulement si le téléchargement est autorisé)

### 1.3.5 – 9 novembre 2026
- **Mises à jour signées** : chaque paquet est signé (Ed25519) hors de GitHub ; le plugin refuse d'installer une mise à jour dont la signature est absente ou invalide
- **Changement incompatible** : `public_download` vaut désormais `false` par défaut (téléchargement réservé aux utilisateurs connectés) ; ajouter `public_download="true"` pour l'ouvrir aux visiteurs
- Sécurité : le chemin absolu du serveur n'apparaît plus dans la page (racine transmise relativement à `uploads`)
- Sécurité : le dossier racine ne peut plus être supprimé, renommé ni déplacé
- Sécurité : `symfony/filesystem` mis à jour en 6.4.50 (faille de sortie du dossier de base via `Path`)
- Sécurité : téléchargement ZIP limité (2000 fichiers, 512 Mo par défaut, filtre `WPFN_zip_limits`), noms d'entrées assainis, liens symboliques sortants ignorés
- Sécurité : nonce retiré des liens de téléchargement ; couleurs et dimensions du shortcode validées ; paramètres de tri limités à une liste blanche
- Nouveau : plusieurs navigateurs sur une même page (paramètres d'URL `fb_dir_2`, `sort_2`… pour les suivants)
- Correction : coller un dossier dans lui-même ou dans un sous-dossier affiche un message clair (la copie bouclait)
- Correction : plus d'erreur fatale sur des données JSON mal formées
- Tests automatisés (PHPUnit) lancés par GitHub Actions à chaque push et avant chaque release

### 1.3.4 – 9 novembre 2026
- **Sécurité** : l’attribut `root` est désormais confiné à `wp-content/uploads` (chemins absolus hors de ce dossier, `../` et liens symboliques refusés). **Changement incompatible** : pour un autre dossier, utiliser le filtre `WPFN_root_base` (voir « Dossier de base autorisé »)
- Sécurité : correction d’une faille XSS dans les messages d’erreur du téléchargement
- Sécurité : nom de fichier assaini dans l’en-tête `Content-Disposition` (prise en charge des noms UTF-8)
- Correction : plus d’erreur 500 lorsqu’un paramètre est envoyé sous forme de tableau
- Correction : message clair si l’extension PHP `zip` est absente

### 1.3.3 – 7 novembre 2026
- Mises à jour automatiques **activées par défaut** : les nouvelles versions s'installent sans intervention. `define('WPFN_FORCE_AUTO_UPDATE', false);` dans `wp-config.php` rétablit le choix manuel

### 1.3.2 – 7 novembre 2026
- Correction : l’option `show_hidden="true"` affiche désormais les fichiers et dossiers cachés (les dossiers de versionnage `.git`, `.svn`… restent masqués)
- Correction : le renommage des fichiers dont le nom contient une apostrophe (ex. `l'été.jpg`) échouait avec « Chemin non autorisé »
- Coller : en cas de doublon, les dossiers et fichiers cachés sont renommés comme à l’upload (`dossier.v2_1` au lieu de `dossier_1.v2`, `.htaccess_1` au lieu de `_1.htaccess`)
- Refactorisation interne : endpoints AJAX regroupés dans `WPFN\Ajax\AjaxController`, signature du dossier racine dans `WPFN\FileNavigator\RootSignature`, résolution des chemins mutualisée

### 1.3.1 – 4 novembre 2026
- Glisser‑déposer sur un dossier de la liste : les fichiers et dossiers sont envoyés dans ce dossier, et non dans le dossier ouvert (le dossier visé est surligné et indiqué dans le bandeau)

### 1.3.0 – 23 octobre 2026
- Upload par glisser‑déposer de fichiers **et de dossiers** (sous‑dossiers et dossiers vides inclus) dans la fenêtre du navigateur, pour les utilisateurs connectés ayant le droit `upload_files`
- Fenêtre de conflit si un élément existe déjà : **Remplacer** ou **Garder les deux** pour un fichier ; **Fusionner**, **Remplacer** ou **Garder les deux** pour un dossier, avec option « Appliquer à tous »
- Remplacer / fusionner nécessite en plus le droit `delete_posts`
- Le bouton ⬆️ UL utilise la même fenêtre de conflit (auparavant renommage automatique)
- Envoi par lots respectant `max_file_uploads` et la taille maximale d’upload, avec progression
- Nouveaux endpoints AJAX `WPFN_check_exists` et `WPFN_prepare_upload`
- **Sécurité** : le dossier racine transmis par le navigateur est signé (HMAC) et vérifié par le serveur pour toutes les actions, y compris le téléchargement public
- **Sécurité** : `public_download="false"` est désormais appliqué côté serveur (téléchargement réservé aux utilisateurs connectés)
- **Sécurité** : contrôle des fichiers envoyés — extensions autorisées par WordPress + WebP, extensions dangereuses toujours refusées (même en double extension, ex. `image.php.jpg`), vérification du contenu réel (signature du fichier comparée à l’extension) et détection de code PHP caché dans les images
- **Sécurité** : le renommage ne peut plus changer l’extension vers un type interdit ni sortir du dossier
- Remplacer un fichier par un fichier conserve l’ancien si le nouveau est refusé

### 1.2.0 – 22 octobre 2026
- Mises à jour automatiques depuis les releases GitHub
- Lien « Vérifier les mises à jour » dans la liste des extensions
- PHP 8.1 minimum (requis par Symfony 6.4)

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
