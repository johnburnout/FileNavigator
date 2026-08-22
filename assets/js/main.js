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
            this.root = this.container.data('root');
            this.ajaxurl = this.container.data('ajaxurl');
            this.nonce = this.container.data('nonce');

            console.log('File Navigator initialisé avec root:', this.root);

            this.status = $('#WPFN-status');
            this.clipboardIndicator = $('#WPFN-clipboard-indicator');
            this.currentDir = this.getCurrentDir();

            this.bindEvents();
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

        bindEvents: function() {
            const self = this;

            // ---------- UPLOAD ----------
            $('.WPFN-btn-upload').on('click', function() {
                $('#WPFN-upload-input').click();
            });

            $('#WPFN-upload-input').on('change', function() {
                const files = this.files;
                if (files.length === 0) return;

                console.log('Upload de ' + files.length + ' fichier(s)');

                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }
                formData.append('action', 'WPFN_upload');
                formData.append('nonce', self.nonce);
                formData.append('root', self.root);
                formData.append('current_dir', self.currentDir);

                $.ajax({
                    url: self.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            self.setStatus('Upload réussi (' + response.data.files.length + ' fichier(s)).');
                            location.reload();
                        } else {
                            self.setStatus('Erreur upload: ' + response.data.message, true);
                        }
                    },
                    error: function(jqXHR) {
                        console.error('Upload error:', jqXHR);
                        self.setStatus('Erreur de connexion lors de l\'upload.', true);
                    }
                });

                this.value = ''; // réinitialiser
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
                    '&root=' + encodeURIComponent(self.root);
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