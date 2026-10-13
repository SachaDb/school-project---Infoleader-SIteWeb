<?php
/**
 * contact.php - Formulaire de contact
 *
 * Le formulaire ne simule plus l'envoi : le message est validé côté
 * serveur, enregistré dans storage/data/contact.json (consultable
 * depuis Maintenance > Journaux) et, si la configuration des e-mails
 * est renseignée, notifié par e-mail.
 */

require_once __DIR__ . '/includes/bootstrap.php';

il_session_start();

$success = false;
$errors  = [];
$notice  = '';
$values  = ['nom' => '', 'email' => '', 'objet' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    $values = [
        'nom'     => trim((string) ($_POST['nom'] ?? '')),
        'email'   => trim((string) ($_POST['email'] ?? '')),
        'objet'   => trim((string) ($_POST['objet'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];

    /* Validation côté serveur, indépendante du JavaScript */
    $errors = il_contact_validate($values);

    if (!$errors) {
        $stored = il_contact_save($values);

        if ($stored === null) {
            $errors['global'] = 'Votre message n\'a pas pu être enregistré. Réessayez plus tard.';
        } else {
            $success = true;
            $values  = ['nom' => '', 'email' => '', 'objet' => '', 'message' => ''];

            /* Notification facultative : son échec ne fait pas perdre le message */
            if (il_mail_is_configured()) {
                $sent = il_mail_send_contact_notification($stored);
                if (!$sent['ok']) {
                    $notice = 'Votre message est bien enregistré, mais la notification '
                        . 'interne n\'a pas pu être envoyée.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contactez InfoLeader : conseil, devis et accompagnement sur votre équipement informatique.">
    <title>InfoLeader — Contact</title>

    <script>document.documentElement.classList.add("js");</script>
    <link rel="stylesheet" href="<?php echo e(il_asset('style.css')); ?>">
    <script src="<?php echo e(il_asset('script.js')); ?>" defer></script>
</head>
<body>

    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="site-header">
        <nav>
            <a href="index.php" class="logo">InfoLeader</a>

            <button class="burger" type="button" aria-expanded="false"
                    aria-controls="menu-principal" aria-label="Ouvrir le menu">
                <span></span><span></span><span></span>
            </button>

            <ul class="menu" id="menu-principal">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="products.php">Nos produits</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>
            </ul>
        </nav>
    </header>

    <section class="page-title">
        <h1>Contactez-nous</h1>
        <p>
            Une question sur nos produits, un devis, un conseil avant achat ?
            Notre équipe vous répond rapidement.
        </p>
    </section>

    <main id="contenu">
        <div class="section" style="padding-top:52px">

            <?php if ($success): ?>
                <div class="success-msg" role="status">
                    Merci pour votre message : il a bien été enregistré.
                    Notre équipe vous répondra au plus vite.
                </div>
            <?php endif; ?>

            <?php if ($notice !== ''): ?>
                <div class="success-msg" role="status"
                     style="background:#fffbeb;border-color:#f59e0b;color:#78350f">
                    <?php echo e($notice); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="success-msg" role="alert"
                     style="background:#fef2f2;border-color:#ef4444;color:#7f1d1d">
                    <strong>Votre message n'a pas été envoyé :</strong>
                    <ul style="margin:8px 0 0 20px;text-align:left;font-weight:500">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="contact-grid">

                <div class="info reveal">
                    <h2>Voici comment nous joindre</h2>
                    <p>
                        Par téléphone, par e-mail ou directement via le formulaire :
                        choisissez ce qui vous arrange.
                    </p>

                    <div class="info-item">
                        <div class="icon" aria-hidden="true">&#128205;</div>
                        <p>12 rue de l'Informatique, 75000 Paris</p>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">&#128222;</div>
                        <p><a href="tel:+33123456789" style="color:inherit">01 23 45 67 89</a></p>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">&#9993;</div>
                        <p>
                            <a href="mailto:contact@infoleader.local" style="color:inherit">
                                contact@infoleader.local
                            </a>
                        </p>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">&#128336;</div>
                        <p>Du lundi au vendredi, 9h00 – 18h00</p>
                    </div>
                </div>

                <form id="contactForm" class="contact-form reveal" data-delay="2"
                      action="contact.php" method="post" novalidate>
                    <?php /* Jeton anti-CSRF : le formulaire est traité par le serveur */ ?>
                    <?php echo il_csrf_field(); ?>

                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" maxlength="120"
                               value="<?php echo e($values['nom']); ?>"
                               <?php echo isset($errors['nom']) ? 'aria-invalid="true"' : ''; ?>>
                        <p class="error" data-error="nom">Le nom est requis.</p>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" maxlength="190"
                               value="<?php echo e($values['email']); ?>"
                               <?php echo isset($errors['email']) ? 'aria-invalid="true"' : ''; ?>>
                        <p class="error" data-error="email">
                            Veuillez saisir une adresse e-mail valide.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="objet">
                            Objet
                            <span style="font-weight:400;color:var(--texte-doux)">(facultatif)</span>
                        </label>
                        <input type="text" id="objet" name="objet" maxlength="150"
                               value="<?php echo e($values['objet']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" maxlength="5000"
                                  <?php echo isset($errors['message']) ? 'aria-invalid="true"' : ''; ?>><?php echo e($values['message']); ?></textarea>
                        <p class="error" data-error="message">Le message est requis.</p>
                    </div>

                    <button type="submit" class="submit">Envoyer le message</button>
                </form>

            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <h3>InfoLeader</h3>
                <p>
                    Société informatique : matériel, conseil et accompagnement
                    pour les particuliers et les professionnels.
                </p>
            </div>
            <div>
                <h3>Navigation</h3>
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="products.php">Nos produits</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3>Nous joindre</h3>
                <ul>
                    <li>12 rue de l'Informatique, 75000 Paris</li>
                    <li><a href="tel:+33123456789">01 23 45 67 89</a></li>
                    <li><a href="mailto:contact@infoleader.local">contact@infoleader.local</a></li>
                    <li>Du lundi au vendredi, 9h00 – 18h00</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> InfoLeader. Tous droits réservés.</p>
            <p><a href="admin/">Espace d'administration</a></p>
        </div>
    </footer>

    <script>
        /* Validation côté client : confort de saisie uniquement.
           Le serveur revalide systématiquement tous les champs. */
        (function () {
            var form = document.getElementById('contactForm');
            if (!form) {
                return;
            }

            var nom = document.getElementById('nom');
            var email = document.getElementById('email');
            var message = document.getElementById('message');

            function validerChamp(champ) {
                var erreur = document.querySelector('[data-error="' + champ.id + '"]');
                var valide = champ.value.trim() !== '';

                if (valide && champ.id === 'email') {
                    valide = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(champ.value.trim());
                }

                champ.classList.toggle('invalid', !valide);
                if (valide) {
                    champ.removeAttribute('aria-invalid');
                } else {
                    champ.setAttribute('aria-invalid', 'true');
                }
                if (erreur) {
                    erreur.style.display = valide ? 'none' : 'block';
                }
                return valide;
            }

            /* Correction à la volée des champs déjà signalés */
            [nom, email, message].forEach(function (champ) {
                champ.addEventListener('input', function () {
                    if (champ.classList.contains('invalid')) {
                        validerChamp(champ);
                    }
                });
            });

            form.addEventListener('submit', function (evenement) {
                var valide = [nom, email, message]
                    .map(validerChamp)
                    .every(function (resultat) { return resultat; });

                if (!valide) {
                    evenement.preventDefault();
                    var premier = form.querySelector('.invalid');
                    if (premier) {
                        premier.focus();
                    }
                    return;
                }

                /* État de chargement du bouton */
                var bouton = form.querySelector('.submit');
                if (bouton) {
                    bouton.disabled = true;
                    bouton.textContent = 'Envoi en cours…';
                }
            });
        })();
    </script>

</body>
</html>
