(function($) {
    'use strict';

    const plugin = {
        init: function() {
            this.container = $('.WPFN-filenavigator');
            if (!this.container.length) {
                console.warn('File Navigator: conteneur non trouvé.');
                return;
            }

            // Récupération des données depuis les attributs data
            this.root = this.container.attr('data-root') || '';
            this.ajaxurl = this.container.data('ajaxurl');
            this.nonce = this.container.data('nonce');
            this.rootSig = this.container.attr('data-root-sig') || '';
            this.rootPd = this.container.attr('data-root-pd') || '0';
            const extList = attr => (this.container.attr(attr) || '').toLowerCase().split(',').filter(Boolean);
            this.allowedExt = extList('data-allowed-ext');
            this.blockedExt = extList('data-blocked-ext');
            this.canUpload = String(this.container.attr('data-can-upload')) === '1';
            this.canDelete = String(this.container.attr('data-can-delete')) === '1';
            this.maxUpload = parseInt(this.container.attr('data-max-upload'), 10) || 0;
            this.maxFiles = parseInt(this.container.attr('data-max-files'), 10) || 20;
            this.busy = false;

            console.log('File Navigator initialisé avec root:', this.root);

            this.status = $('#WPFN-status');
            this.clipboardIndicator = $('#WPFN-clipboard-indicator');
            this.currentDir = this.getCurrentDir();

            this.bindEvents();
            if (this.canUpload) {
                this.bindDropZone();
            }
            this.updateClipboardIndicator();
        },

        // Récupère le dossier courant depuis l'URL (paramètre fb_dir)
        getCurrentDir: function() {
            const params = new URLSearchParams(window.location.search);
            return params.get('fb_dir') || '';
        },

        // Met à jour l'affichage du presse-papiers
        updateClipboardIndicator: function() {
            const clipboard = this.getClipboard();
            if (clipboard && clipboard.paths && clipboard.paths.length > 0) {
                this.clipboardIndicator
                    .text(`📋 ${clipboard.mode === 'copy' ? 'Copie' : 'Coupe'} (${clipboard.paths.length} élément(s))`)
                    .show();
            } else {
                this.clipboardIndicator.hide();
            }
        },

        // Lecture du presse-papiers dans localStorage
        getClipboard: function() {
            try {
                return JSON.parse(localStorage.getItem('WPFN_clipboard'));
            } catch(e) {
                return null;
            }
        },

        // Écriture dans le presse-papiers
        setClipboard: function(paths, mode) {
            if (!paths || paths.length === 0) {
                localStorage.removeItem('WPFN_clipboard');
            } else {
                localStorage.setItem('WPFN_clipboard', JSON.stringify({
                    paths: paths,
                    mode: mode,
                    sourceDir: this.currentDir
                }));
            }
            this.updateClipboardIndicator();
        },

        // Récupère les chemins sélectionnés
        getSelectedPaths: function() {
            return this.container.find('.WPFN-item-checkbox:checked').map(function() {
                return $(this).data('path');
            }).get();
        },

        // Affiche un message de statut
        setStatus: function(msg, isError) {
            this.status.text(msg).css('color', isError ? '#b32d2e' : '#46b450');
            clearTimeout(this.statusTimeout);
            this.statusTimeout = setTimeout(() => {
                this.status.text('');
            }, 5000);
        },

        // Appel AJAX générique (sans fichier)
        ajax: function(action, data, success, error) {
            data.action = action;
            data.nonce = this.nonce;
            data.root = this.root;
            data.root_sig = this.rootSig;
            data.root_pd = this.rootPd;

            $.post(this.ajaxurl, data, function(response) {
                if (response.success) {
                    if (success) success(response.data);
                } else {
                    if (error) error(response.data);
                    else plugin.setStatus(
                        'Erreur: ' + (response.data && response.data.message ? response.data.message : 'Erreur inconnue'),
                        true
                    );
                }
            }).fail(function(jqXHR) {
                console.error('AJAX error:', jqXHR);
                plugin.setStatus('Erreur de connexion au serveur.', true);
            });
        },

        // ================================
        // GLISSER-DÉPOSER & UPLOAD
        // ================================

        IGNORED_NAMES: ['.DS_Store', 'Thumbs.db', 'desktop.ini'],

        // Le glisser contient-il des fichiers (et pas du texte ou un lien interne) ?
        hasFiles: function(e) {
            const dt = e.originalEvent && e.originalEvent.dataTransfer;
            return !!dt && Array.prototype.indexOf.call(dt.types || [], 'Files') !== -1;
        },

        bindDropZone: function() {
            const self = this;
            let depth = 0;
            this.container.attr('data-drop-label',
                '⬆️ Déposez ici pour envoyer dans « ' + (this.currentDir || 'Racine') + ' »');

            this.container.on('dragenter', function(e) {
                if (!self.hasFiles(e)) return;
                e.preventDefault();
                depth++;
                self.container.addClass('WPFN-dragover');
            });
            this.container.on('dragover', function(e) {
                if (!self.hasFiles(e)) return;
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'copy';
            });
            this.container.on('dragleave', function(e) {
                if (!self.hasFiles(e)) return;
                depth = Math.max(0, depth - 1);
                if (depth === 0) self.container.removeClass('WPFN-dragover');
            });
            this.container.on('drop', function(e) {
                if (!self.hasFiles(e)) return;
                e.preventDefault();
                depth = 0;
                self.container.removeClass('WPFN-dragover');
                if (self.busy) {
                    self.setStatus('Un envoi est déjà en cours.', true);
                    return;
                }
                // Les entrées doivent être lues immédiatement (le DataTransfer est vidé ensuite)
                self.collectDropped(e.originalEvent.dataTransfer)
                    .then(function(res) { self.startUpload(res.files, res.dirs); })
                    .catch(function(err) {
                        console.error('File Navigator drop:', err);
                        self.setStatus('Impossible de lire les éléments déposés.', true);
                    });
            });
        },

        // Parcourt récursivement fichiers et dossiers déposés
        // Retourne { files: [{file, path}], dirs: [chemins des dossiers vides] }
        collectDropped: function(dt) {
            const self = this;
            const items = dt.items ? Array.from(dt.items).filter(i => i.kind === 'file') : [];
            const entries = items.map(i => (i.webkitGetAsEntry ? i.webkitGetAsEntry() : null));

            if (!entries.length || entries.some(en => !en)) {
                // Navigateur sans API d'entrées : fichiers uniquement
                return Promise.resolve({
                    files: Array.from(dt.files || []).map(f => ({ file: f, path: f.name })),
                    dirs: []
                });
            }

            const files = [];
            const dirs = [];
            const isIgnored = en => en.isFile && self.IGNORED_NAMES.indexOf(en.name) !== -1;

            const walk = function(entry, prefix) {
                const path = prefix + entry.name;
                if (isIgnored(entry)) return Promise.resolve();
                if (entry.isFile) {
                    return new Promise(function(resolve, reject) {
                        entry.file(function(f) { files.push({ file: f, path: path }); resolve(); }, reject);
                    });
                }
                if (entry.isDirectory) {
                    return self.readAllEntries(entry.createReader()).then(function(children) {
                        const kept = children.filter(c => !isIgnored(c));
                        if (!kept.length) dirs.push(path);
                        return Promise.all(kept.map(c => walk(c, path + '/')));
                    });
                }
                return Promise.resolve();
            };

            return Promise.all(entries.map(en => walk(en, ''))).then(() => ({ files: files, dirs: dirs }));
        },

        // readEntries() renvoie les entrées par paquets : on boucle jusqu'à un paquet vide
        readAllEntries: function(reader) {
            return new Promise(function(resolve, reject) {
                const all = [];
                const next = function() {
                    reader.readEntries(function(batch) {
                        if (!batch.length) return resolve(all);
                        Array.prototype.push.apply(all, batch);
                        next();
                    }, reject);
                };
                next();
            });
        },

        // Appel AJAX renvoyant une promesse
        post: function(action, data) {
            const self = this;
            return new Promise(function(resolve, reject) {
                data.action = action;
                data.nonce = self.nonce;
                data.root = self.root;
                data.root_sig = self.rootSig;
                data.root_pd = self.rootPd;
                $.post(self.ajaxurl, data, function(response) {
                    if (response && response.success) resolve(response.data || {});
                    else reject({ message: response && response.data && response.data.message ? response.data.message : 'Erreur inconnue' });
                }).fail(function() {
                    reject({ message: 'Erreur de connexion au serveur.' });
                });
            });
        },

        // Même règle que UploadValidator::checkName() : refus avant tout envoi / remplacement
        checkFileName: function(name) {
            const parts = name.toLowerCase().split('.');
            if (parts.length < 2 || parts[parts.length - 1] === '') return 'fichier sans extension';
            for (let i = 1; i < parts.length; i++) {
                if (this.blockedExt.indexOf(parts[i]) !== -1) return 'type interdit (.' + parts[i] + ')';
            }
            const ext = parts[parts.length - 1];
            if (this.allowedExt.length && this.allowedExt.indexOf(ext) === -1) return 'extension .' + ext + ' non autorisée';
            return null;
        },

        formatSize: function(bytes) {
            const units = ['o', 'Ko', 'Mo', 'Go'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
            return (i ? bytes.toFixed(1) : bytes) + ' ' + units[i];
        },

        setProgress: function(msg) {
            clearTimeout(this.statusTimeout);
            this.status.text(msg).css('color', '#0073aa');
        },

        // Point d'entrée commun (bouton UL et glisser-déposer)
        startUpload: async function(files, dirs) {
            const self = this;
            dirs = dirs || [];
            if (!files.length && !dirs.length) {
                self.setStatus('Aucun fichier à envoyer.', true);
                return;
            }
            if (self.busy) {
                self.setStatus('Un envoi est déjà en cours.', true);
                return;
            }

            // 1. Fichiers refusés d'avance (type non autorisé, trop volumineux),
            //    vérifiés avant toute suppression côté serveur
            const refused = [];
            files = files.filter(function(f) {
                const name = f.path.split('/').pop();
                let reason = self.checkFileName(name);
                if (!reason && self.maxUpload && f.file.size > self.maxUpload) {
                    reason = 'dépasse ' + self.formatSize(self.maxUpload);
                }
                if (reason) refused.push('• ' + f.path + ' : ' + reason);
                return !reason;
            });
            if (refused.length) {
                const list = refused.slice(0, 8).join('\n') + (refused.length > 8 ? '\n…' : '');
                if (!files.length && !dirs.length) {
                    alert('Aucun fichier ne peut être envoyé :\n' + list);
                    return;
                }
                if (!confirm(refused.length + ' fichier(s) seront ignorés :\n' + list + '\n\nContinuer avec les autres ?')) {
                    return;
                }
            }

            // 2. Éléments de premier niveau
            const top = {};
            files.forEach(function(f) {
                const parts = f.path.split('/');
                top[parts[0]] = top[parts[0]] || { name: parts[0], isDir: parts.length > 1 };
            });
            dirs.forEach(function(d) {
                const n = d.split('/')[0];
                top[n] = top[n] || { name: n, isDir: true };
            });
            const topItems = Object.keys(top).map(k => top[k]);

            self.busy = true;
            try {
                self.setProgress('Vérification…');
                const check = await self.post('WPFN_check_exists', {
                    names: JSON.stringify(topItems.map(i => i.name)),
                    current_dir: self.currentDir
                });

                // 3. Choix de l'utilisateur pour chaque conflit
                const decided = await self.askConflicts(topItems, check.existing || []);
                if (!decided) {
                    self.setStatus('Envoi annulé.');
                    return;
                }

                // 4. Application des choix (suppression / renommage) côté serveur
                const prep = await self.post('WPFN_prepare_upload', {
                    items: JSON.stringify(decided),
                    current_dir: self.currentDir
                });
                const mapping = prep.mapping || {};
                const remap = function(path) {
                    const parts = path.split('/');
                    parts[0] = mapping[parts[0]] || parts[0];
                    return parts.join('/');
                };
                files = files.map(f => ({ file: f.file, path: remap(f.path) }));
                dirs = dirs.map(remap);

                // 5. Envoi par lots
                const res = await self.uploadBatches(files, dirs);
                if (res.rejected.length) {
                    alert(res.count + ' fichier(s) envoyé(s), ' + res.rejected.length + ' refusé(s) après vérification du contenu :\n' +
                        res.rejected.slice(0, 8).map(r => '• ' + r.reason).join('\n') + (res.rejected.length > 8 ? '\n…' : ''));
                } else {
                    self.setStatus('Upload réussi (' + res.count + ' fichier(s)).');
                }
                location.reload();
            } catch (err) {
                console.error('File Navigator upload:', err);
                self.setStatus('Erreur upload : ' + (err && err.message ? err.message : 'erreur inconnue'), true);
            } finally {
                self.busy = false;
            }
        },

        // Demande quoi faire pour chaque élément déjà existant. Retourne null si annulé.
        askConflicts: async function(topItems, existing) {
            const existingByName = {};
            existing.forEach(e => { existingByName[e.name] = e; });
            const conflicts = topItems.filter(i => existingByName[i.name]);
            const remembered = {}; // « appliquer à tous » : un choix par type de conflit
            const result = [];

            for (let idx = 0; idx < topItems.length; idx++) {
                const item = topItems[idx];
                const ex = existingByName[item.name];
                if (!ex) {
                    result.push({ name: item.name, isDir: item.isDir, action: 'new' });
                    continue;
                }
                const bothDirs = item.isDir && ex.isDir;
                const kind = bothDirs ? 'dir' : 'file';
                let action = remembered[kind];
                if (!action) {
                    const remaining = conflicts.slice(conflicts.indexOf(item) + 1)
                        .filter(c => (c.isDir && existingByName[c.name].isDir ? 'dir' : 'file') === kind).length;
                    const answer = await this.showConflictDialog(item, ex, bothDirs, remaining);
                    if (!answer) return null;
                    action = answer.action;
                    if (answer.applyAll) remembered[kind] = action;
                }
                result.push({ name: item.name, isDir: item.isDir, action: action });
            }
            return result;
        },

        // Fenêtre de choix : Promise<{action, applyAll}|null>
        showConflictDialog: function(item, existing, bothDirs, remaining) {
            const self = this;
            return new Promise(function(resolve) {
                const what = existing.isDir ? 'Le dossier' : 'Le fichier';
                const where = self.currentDir || 'Racine';
                const options = bothDirs
                    ? [
                        { action: 'merge', label: 'Fusionner', help: 'Ajoute le contenu au dossier existant ; les fichiers de même nom sont écrasés.', needsDelete: true },
                        { action: 'replace', label: 'Remplacer', help: 'Supprime le dossier existant et tout son contenu, puis envoie le nouveau.', needsDelete: true },
                        { action: 'keep', label: 'Garder les deux', help: 'Envoie le nouveau dossier sous un autre nom (ex. « ' + item.name + '_1 »).' }
                    ]
                    : [
                        { action: 'replace', label: 'Remplacer', help: existing.isDir
                            ? 'Supprime le dossier existant et tout son contenu, puis envoie le fichier.'
                            : 'Écrase le fichier existant.', needsDelete: true },
                        { action: 'keep', label: 'Garder les deux', help: 'Envoie le nouvel élément sous un autre nom (suffixe « _1 »).' }
                    ];

                const $backdrop = $('<div class="WPFN-modal-backdrop"></div>');
                const $modal = $('<div class="WPFN-modal" role="dialog" aria-modal="true" aria-labelledby="WPFN-modal-title"></div>').appendTo($backdrop);
                $('<h3 id="WPFN-modal-title"></h3>').text('Élément déjà existant').appendTo($modal);
                $('<p></p>').text(what + ' « ' + item.name + ' » existe déjà dans « ' + where + ' ». Que voulez-vous faire ?').appendTo($modal);

                const $help = $('<ul class="WPFN-modal-help"></ul>').appendTo($modal);
                options.forEach(function(o) {
                    $('<li></li>').append($('<strong></strong>').text(o.label + ' : ')).append(document.createTextNode(o.help)).appendTo($help);
                });
                if (!self.canDelete) {
                    $('<p class="WPFN-modal-note"></p>').text('Vous n\'avez pas le droit de remplacer ou fusionner des éléments existants.').appendTo($modal);
                }

                let $applyAll = null;
                if (remaining > 0) {
                    const $label = $('<label class="WPFN-modal-applyall"></label>').appendTo($modal);
                    $applyAll = $('<input type="checkbox">').appendTo($label);
                    $label.append(document.createTextNode(' Appliquer aux ' + remaining + ' autre(s) conflit(s) du même type'));
                }

                const $actions = $('<div class="WPFN-modal-actions"></div>').appendTo($modal);
                const close = function(value) {
                    $(document).off('keydown.WPFNmodal');
                    $backdrop.remove();
                    resolve(value);
                };
                options.forEach(function(o) {
                    const disabled = o.needsDelete && !self.canDelete;
                    $('<button type="button" class="WPFN-btn WPFN-btn-primary"></button>')
                        .text(o.label).attr('title', o.help).prop('disabled', disabled)
                        .on('click', function() {
                            close({ action: o.action, applyAll: $applyAll ? $applyAll.is(':checked') : false });
                        })
                        .appendTo($actions);
                });
                $('<button type="button" class="WPFN-btn"></button>').text('Annuler')
                    .attr('title', 'Annule tout l\'envoi').on('click', function() { close(null); }).appendTo($actions);

                $(document).on('keydown.WPFNmodal', function(e) {
                    if (e.key === 'Escape') close(null);
                });
                $backdrop.appendTo(document.body);
                $actions.find('button:enabled').first().trigger('focus');
            });
        },

        // Découpe en lots selon max_file_uploads et la taille maximale de requête
        uploadBatches: async function(files, dirs) {
            const maxFiles = this.maxFiles || 20;
            const budget = this.maxUpload ? this.maxUpload * 0.9 : Infinity;
            const batches = [];
            let current = { files: [], dirs: dirs, bytes: 0 };
            files.forEach(function(f) {
                if (current.files.length && (current.files.length >= maxFiles || current.bytes + f.file.size > budget)) {
                    batches.push(current);
                    current = { files: [], dirs: [], bytes: 0 };
                }
                current.files.push(f);
                current.bytes += f.file.size;
            });
            batches.push(current);

            const totalBytes = files.reduce((s, f) => s + f.file.size, 0) || 1;
            let doneBytes = 0;
            let count = 0;
            let rejected = [];
            for (let i = 0; i < batches.length; i++) {
                const res = await this.sendBatch(batches[i], doneBytes, totalBytes, files.length);
                doneBytes += batches[i].bytes;
                count += (res.files || []).length;
                rejected = rejected.concat(res.rejected || []);
            }
            return { count: count, rejected: rejected };
        },

        sendBatch: function(batch, doneBytes, totalBytes, totalFiles) {
            const self = this;
            return new Promise(function(resolve, reject) {
                const formData = new FormData();
                batch.files.forEach(f => formData.append('files[]', f.file, f.file.name));
                formData.append('paths', JSON.stringify(batch.files.map(f => f.path)));
                formData.append('dirs', JSON.stringify(batch.dirs));
                formData.append('action', 'WPFN_upload');
                formData.append('nonce', self.nonce);
                formData.append('root', self.root);
                formData.append('root_sig', self.rootSig);
                formData.append('root_pd', self.rootPd);
                formData.append('current_dir', self.currentDir);

                $.ajax({
                    url: self.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = $.ajaxSettings.xhr();
                        if (xhr.upload) {
                            xhr.upload.addEventListener('progress', function(ev) {
                                if (!ev.lengthComputable) return;
                                const sent = doneBytes + batch.bytes * (ev.loaded / ev.total);
                                self.setProgress('Envoi de ' + totalFiles + ' fichier(s)… ' +
                                    Math.min(100, Math.round(sent / totalBytes * 100)) + ' %');
                            });
                        }
                        return xhr;
                    },
                    success: function(response) {
                        if (response && response.success) resolve(response.data || {});
                        else reject({ message: response && response.data && response.data.message ? response.data.message : 'Erreur inconnue' });
                    },
                    error: function(jqXHR) {
                        reject({ message: jqXHR.status === 413
                            ? 'Requête trop volumineuse pour le serveur.'
                            : 'Erreur de connexion lors de l\'upload.' });
                    }
                });
            });
        },

        bindEvents: function() {
            const self = this;

            // ---------- UPLOAD ----------
            $('.WPFN-btn-upload').on('click', function() {
                $('#WPFN-upload-input').click();
            });

            $('#WPFN-upload-input').on('change', function() {
                const files = Array.from(this.files || []).map(f => ({ file: f, path: f.name }));
                this.value = ''; // réinitialiser
                if (files.length) self.startUpload(files, []);
            });

            // ---------- TÉLÉCHARGER (DL) ----------
            $('.WPFN-btn-download').on('click', function() {
                const selected = self.getSelectedPaths();
                if (selected.length === 0) {
                    self.setStatus('Sélectionnez au moins un élément.', true);
                    return;
                }
                const url = self.ajaxurl +
                    '?action=WPFN_download' +
                    '&paths=' + encodeURIComponent(JSON.stringify(selected)) +
                    '&nonce=' + self.nonce +
                    '&root=' + encodeURIComponent(self.root) +
                    '&root_sig=' + encodeURIComponent(self.rootSig) +
                    '&root_pd=' + encodeURIComponent(self.rootPd);
                window.open(url, '_blank');
                self.setStatus('Téléchargement en cours...');
            });

            // ---------- COPIER ----------
            $('.WPFN-btn-copy').on('click', function() {
                const selected = self.getSelectedPaths();
                if (selected.length === 0) {
                    self.setStatus('Sélectionnez au moins un élément.', true);
                    return;
                }
                self.setClipboard(selected, 'copy');
                self.setStatus(`${selected.length} élément(s) copié(s) dans le presse-papiers.`);
            });

            // ---------- COUPER ----------
            $('.WPFN-btn-cut').on('click', function() {
                const selected = self.getSelectedPaths();
                if (selected.length === 0) {
                    self.setStatus('Sélectionnez au moins un élément.', true);
                    return;
                }
                self.setClipboard(selected, 'cut');
                self.setStatus(`${selected.length} élément(s) coupé(s) dans le presse-papiers.`);
            });

            // ---------- COLLER ----------
            $('.WPFN-btn-paste').on('click', function() {
                const clipboard = self.getClipboard();
                if (!clipboard || !clipboard.paths || clipboard.paths.length === 0) {
                    self.setStatus('Presse-papiers vide.', true);
                    return;
                }

                const data = {
                    paths: JSON.stringify(clipboard.paths),
                    mode: clipboard.mode,
                    source_dir: clipboard.sourceDir || '',
                    // Si currentDir est vide, on utilise '.' pour désigner la racine
                    target_dir: self.currentDir || '.'
                };

                self.ajax('WPFN_paste', data, function(response) {
                    self.setStatus(`Collage terminé (${clipboard.mode === 'copy' ? 'copie' : 'déplacement'}).`);
                    if (clipboard.mode === 'cut') {
                        self.setClipboard([], 'copy');
                    }
                    location.reload();
                });
            });

            // ---------- SUPPRIMER ----------
            $('.WPFN-btn-delete').on('click', function() {
                const selected = self.getSelectedPaths();
                if (selected.length === 0) {
                    self.setStatus('Sélectionnez au moins un élément.', true);
                    return;
                }
                if (!confirm(`Supprimer définitivement ${selected.length} élément(s) ?`)) return;

                self.ajax('WPFN_delete', { paths: JSON.stringify(selected) }, function() {
                    self.setStatus('Suppression réussie.');
                    location.reload();
                });
            });
            
            // ---------- TRI ----------
            $('.WPFN-sort-select, .WPFN-order-select').on('change', function() {
                const sort = $('#WPFN-sort-select').val();
                const order = $('#WPFN-order-select').val();
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('sort', sort);
                currentUrl.searchParams.set('order', order);
                window.location.href = currentUrl.toString();
            });
            
            // ---------- NOUVEAU DOSSIER ----------
            $('.WPFN-btn-mkdir').on('click', function() {
                const dirName = prompt('Nom du nouveau dossier :');
                if (dirName === null) return; // annulé
                const name = dirName.trim();
                if (!name) {
                    self.setStatus('Le nom ne peut pas être vide.', true);
                    return;
                }
                self.ajax('WPFN_mkdir', {
                    new_dir: name,
                    current_dir: self.currentDir || '.'
                }, function(response) {
                    self.setStatus('Dossier créé avec succès.');
                    location.reload();
                });
            });

            // ---------- RENOMMER ----------
            $('.WPFN-btn-rename').on('click', function() {
                const selected = self.getSelectedPaths();
                if (selected.length !== 1) {
                    self.setStatus('Sélectionnez un seul élément pour le renommer.', true);
                    return;
                }

                const $item = self.container.find('.WPFN-item-checkbox:checked').closest('.WPFN-item');
                const $name = $item.find('.WPFN-item-name');
                const $wrapper = $item.find('.WPFN-rename-wrapper');
                const $input = $wrapper.find('.WPFN-rename-input');

                $name.hide();
                $wrapper.show();
                $input.focus().select();
            });

            // Confirmation de renommage
            $(document).on('click', '.WPFN-rename-confirm', function() {
                const $item = $(this).closest('.WPFN-item');
                const oldPath = $item.data('path');
                const $input = $item.find('.WPFN-rename-input');
                const newName = $input.val().trim();

                if (!newName) {
                    self.setStatus('Le nom ne peut pas être vide.', true);
                    return;
                }

                self.ajax('WPFN_rename', {
                    old_path: oldPath,
                    new_name: newName
                }, function() {
                    self.setStatus('Renommage réussi.');
                    location.reload();
                });
            });

            // Annulation du renommage
            $(document).on('click', '.WPFN-rename-cancel', function() {
                const $item = $(this).closest('.WPFN-item');
                $item.find('.WPFN-rename-wrapper').hide();
                $item.find('.WPFN-item-name').show();
            });
        }
    };

    // Initialisation au chargement du DOM
    $(document).ready(function() {
        plugin.init();
    });

})(jQuery);