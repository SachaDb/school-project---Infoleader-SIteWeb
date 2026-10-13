/**
 * admin.js - Comportements du panneau d'administration InfoLeader
 *
 * JavaScript vanilla, comme le reste du site. Tout est pilote par des
 * attributs HTML, pour rester lisible :
 *
 *   data-autodismiss="5500"   message temporaire masque apres N ms
 *   data-confirm="..."        ouvre une confirmation avant de soumettre
 *   data-loading              bouton en etat de chargement a la soumission
 *   data-policy-target="#id"  verifie en direct les regles du mot de passe
 *   data-countdown="12"       compte a rebours avant reactivation du bouton
 *   data-digits="8"           n'accepte que N chiffres
 *
 * IMPORTANT : ces controles sont un confort d'usage. Toutes les regles
 * (validation, limitation des tentatives, autorisations) sont verifiees
 * cote serveur, en PHP.
 */

(function () {
    'use strict';

    /* ==============================================================
       1. Messages temporaires : disparition automatique
       ============================================================== */

    function setupAutoDismiss() {
        document.querySelectorAll('[data-autodismiss]').forEach(function (el) {
            var delay = parseInt(el.getAttribute('data-autodismiss'), 10);
            if (isNaN(delay) || delay <= 0) {
                return;
            }
            window.setTimeout(function () {
                el.style.transition = 'opacity .4s ease';
                el.style.opacity = '0';
                window.setTimeout(function () {
                    el.remove();
                }, 400);
            }, delay);
        });
    }

    /* ==============================================================
       2. Boutons avec etat de chargement
       ============================================================== */

    function markBusy(button) {
        if (!button || button.getAttribute('aria-busy') === 'true') {
            return;
        }
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        var label = button.querySelector('.btn-label');
        var busyText = button.getAttribute('data-busy-text');
        if (label && busyText) {
            label.textContent = busyText;
        }
    }

    function setupLoadingButtons() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                /* Le navigateur a deja valide le formulaire a ce stade */
                form.querySelectorAll('[data-loading]').forEach(markBusy);
            });
        });
    }

    /* ==============================================================
       3. Fenetre de confirmation pour les actions sensibles
       ============================================================== */

    var modal = null;
    var modalPreviousFocus = null;
    var modalPendingForm = null;

    function buildModal() {
        modal = document.createElement('div');
        modal.className = 'modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'il-modal-title');
        modal.hidden = true;
        modal.innerHTML =
            '<div class="modal-box">' +
            '<h2 id="il-modal-title">Confirmer l\'action</h2>' +
            '<p id="il-modal-text"></p>' +
            '<div class="scope" id="il-modal-scope"></div>' +
            '<div class="actions">' +
            '<button type="button" class="btn btn-danger" id="il-modal-ok">Confirmer</button>' +
            '<button type="button" class="btn btn-ghost" id="il-modal-cancel">Annuler</button>' +
            '</div></div>';
        document.body.appendChild(modal);

        modal.querySelector('#il-modal-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        modal.querySelector('#il-modal-ok').addEventListener('click', function () {
            var form = modalPendingForm;
            closeModal();
            if (form) {
                /* confirmed empeche la reouverture de la confirmation */
                form.dataset.confirmed = 'true';
                form.querySelectorAll('[data-loading]').forEach(markBusy);
                form.submit();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.hidden) {
                closeModal();
            }
        });
    }

    function openModal(options, form) {
        if (!modal) {
            buildModal();
        }
        modalPreviousFocus = document.activeElement;
        modalPendingForm = form;

        modal.querySelector('#il-modal-title').textContent = options.title || 'Confirmer l\'action';
        modal.querySelector('#il-modal-text').textContent = options.text || '';

        var scope = modal.querySelector('#il-modal-scope');
        if (options.scope) {
            scope.innerHTML = options.scope;
            scope.hidden = false;
        } else {
            scope.innerHTML = '';
            scope.hidden = true;
        }

        var ok = modal.querySelector('#il-modal-ok');
        ok.textContent = options.confirmLabel || 'Confirmer';

        modal.hidden = false;
        ok.focus();
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modalPendingForm = null;
        if (modalPreviousFocus && typeof modalPreviousFocus.focus === 'function') {
            modalPreviousFocus.focus();
        }
    }

    function setupConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === 'true') {
                    return;
                }
                event.preventDefault();
                openModal({
                    title: form.getAttribute('data-confirm-title') || 'Confirmer l\'action',
                    text: form.getAttribute('data-confirm'),
                    scope: form.getAttribute('data-confirm-scope'),
                    confirmLabel: form.getAttribute('data-confirm-label')
                }, form);
            });
        });
    }

    /* ==============================================================
       4. Regles de mot de passe verifiees en direct
       ============================================================== */

    var policyTests = {
        length: function (value) { return value.length >= 20; },
        upper: function (value) { return /[A-Z]/.test(value); },
        lower: function (value) { return /[a-z]/.test(value); },
        digit: function (value) { return /[0-9]/.test(value); },
        special: function (value) { return /[^A-Za-z0-9]/.test(value); }
    };

    function setupPasswordPolicy() {
        document.querySelectorAll('[data-policy-target]').forEach(function (input) {
            var list = document.querySelector(input.getAttribute('data-policy-target'));
            if (!list) {
                return;
            }
            var items = list.querySelectorAll('[data-rule]');

            input.addEventListener('input', function () {
                var value = input.value;
                items.forEach(function (item) {
                    var rule = item.getAttribute('data-rule');
                    var test = policyTests[rule];
                    if (test) {
                        item.classList.toggle('met', test(value));
                    }
                });
            });
        });
    }

    /* ==============================================================
       5. Champs numeriques (code a 8 chiffres)
       ============================================================== */

    function setupDigitInputs() {
        document.querySelectorAll('[data-digits]').forEach(function (input) {
            var max = parseInt(input.getAttribute('data-digits'), 10) || 8;

            input.addEventListener('input', function () {
                var cleaned = input.value.replace(/\D/g, '').slice(0, max);
                if (cleaned !== input.value) {
                    input.value = cleaned;
                }
            });
        });
    }

    /* ==============================================================
       6. Compte a rebours de la limitation des tentatives
       ==============================================================
       Purement informatif : le serveur refuse de toute facon la
       tentative tant que le delai n'est pas ecoule.
       ============================================================== */

    function formatRemaining(seconds) {
        if (seconds < 60) {
            return seconds + ' s';
        }
        var minutes = Math.floor(seconds / 60);
        var rest = seconds % 60;
        if (minutes < 60) {
            return minutes + ' min ' + (rest < 10 ? '0' : '') + rest + ' s';
        }
        var hours = Math.floor(minutes / 60);
        return hours + ' h ' + (minutes % 60) + ' min';
    }

    function setupCountdowns() {
        document.querySelectorAll('[data-countdown]').forEach(function (holder) {
            var remaining = parseInt(holder.getAttribute('data-countdown'), 10);
            if (isNaN(remaining) || remaining <= 0) {
                return;
            }

            var display = holder.querySelector('.countdown');
            var blocked = document.querySelectorAll('[data-countdown-disable]');
            blocked.forEach(function (el) { el.disabled = true; });

            var timer = window.setInterval(function () {
                remaining -= 1;
                if (display) {
                    display.textContent = formatRemaining(Math.max(0, remaining));
                }
                if (remaining <= 0) {
                    window.clearInterval(timer);
                    blocked.forEach(function (el) { el.disabled = false; });
                    if (holder.hasAttribute('data-countdown-message')) {
                        holder.textContent = holder.getAttribute('data-countdown-message');
                    }
                }
            }, 1000);

            if (display) {
                display.textContent = formatRemaining(remaining);
            }
        });
    }

    /* ==============================================================
       7. Prereglages SMTP (Gmail, Brevo, saisie manuelle)
       ==============================================================
       Simple confort de saisie : le serveur reapplique de toute facon
       les valeurs du prereglage a l'enregistrement.
       ============================================================== */

    function setupSmtpPresets() {
        var select = document.querySelector('[data-smtp-preset]');
        if (!select) {
            return;
        }

        var host = document.getElementById('host');
        var port = document.getElementById('port');
        var encryption = document.getElementById('encryption');
        var auth = document.querySelector('input[name="auth"]');
        var hint = document.getElementById('provider-hint');

        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            var presetHost = option.getAttribute('data-host');

            if (hint) {
                hint.textContent = option.getAttribute('data-hint')
                    || 'Renseignez manuellement le serveur, le port et la securite.';
            }

            /* "Autre serveur" laisse les champs en saisie libre */
            if (!presetHost) {
                [host, port, encryption].forEach(function (el) {
                    if (el) { el.readOnly = false; el.disabled = false; }
                });
                return;
            }

            if (host) { host.value = presetHost; }
            if (port) { port.value = option.getAttribute('data-port'); }
            if (encryption) { encryption.value = option.getAttribute('data-encryption'); }
            if (auth) { auth.checked = true; }
        });
    }

    /* ==============================================================
       8. Initialisation
       ============================================================== */

    document.addEventListener('DOMContentLoaded', function () {
        setupSmtpPresets();
        setupAutoDismiss();
        setupLoadingButtons();
        setupConfirmations();
        setupPasswordPolicy();
        setupDigitInputs();
        setupCountdowns();
    });
})();
