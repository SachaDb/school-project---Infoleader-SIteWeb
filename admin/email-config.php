<?php
/**
 * email-config.php - Module « Configuration des e-mails »
 *
 * Deux modes :
 *   A. PHP mail  - pour un hebergement disposant d'un serveur de
 *                  messagerie. Annonce comme indisponible sous WAMP
 *                  si le php.ini ne declare ni sendmail_path ni SMTP.
 *   B. Brevo SMTP - envoi reel via PHPMailer (mode recommande).
 *
 * Le mot de passe SMTP n'est jamais reaffiche apres enregistrement.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$errors    = [];
$testError = '';
$config    = il_mail_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    $action = $_POST['action'] ?? '';

    /* ---------------------------------------------------------------
       Enregistrement de la configuration
       --------------------------------------------------------------- */
    if ($action === 'save') {
        $mode       = ($_POST['mode'] ?? 'smtp') === 'mail' ? 'mail' : 'smtp';
        $provider   = (string) ($_POST['provider'] ?? 'custom');
        $fromName   = trim((string) ($_POST['from_name'] ?? ''));
        $fromEmail  = trim((string) ($_POST['from_email'] ?? ''));
        $testEmail  = trim((string) ($_POST['test_email'] ?? ''));
        $host       = trim((string) ($_POST['host'] ?? ''));
        $port       = (int) ($_POST['port'] ?? 0);
        $encryption = (string) ($_POST['encryption'] ?? 'tls');
        $auth       = isset($_POST['auth']);
        $username   = trim((string) ($_POST['username'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');
        $debug      = isset($_POST['debug']);

        /* Un prereglage impose ses valeurs de serveur : l'utilisateur
           n'a plus que ses identifiants a saisir. */
        $providers = il_mail_providers();
        if (isset($providers[$provider])) {
            $host       = $providers[$provider]['host'];
            $port       = $providers[$provider]['port'];
            $encryption = $providers[$provider]['encryption'];
            $auth       = true;
        }

        /* Validation serveur */
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse e-mail de l\'expediteur est invalide.';
        }
        if ($testEmail !== '' && !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse e-mail du destinataire de test est invalide.';
        }
        if (mb_strlen($fromName) > 80) {
            $errors[] = 'Le nom de l\'expediteur ne doit pas depasser 80 caracteres.';
        }
        if (!in_array($encryption, ['tls', 'ssl', ''], true)) {
            $errors[] = 'Securite de connexion inconnue.';
        }

        if ($mode === 'smtp') {
            if ($host === '') {
                $errors[] = 'Le serveur SMTP est requis.';
            } elseif (!preg_match('/^[A-Za-z0-9.\-]+$/', $host)) {
                $errors[] = 'Le nom du serveur SMTP contient des caracteres invalides.';
            }
            if ($port < 1 || $port > 65535) {
                $errors[] = 'Le port SMTP doit etre compris entre 1 et 65535.';
            }
            if ($auth) {
                if ($username === '') {
                    $errors[] = 'L\'identifiant SMTP est requis lorsque l\'authentification est activee.';
                }
                /* Un mot de passe vide conserve celui deja enregistre */
                if ($password === '' && !il_mail_has_password()) {
                    $errors[] = 'Le mot de passe SMTP est requis lorsque l\'authentification est activee.';
                }
            }
        }

        if (!$errors) {
            $saved = il_mail_config_save([
                'mode'       => $mode,
                'provider'   => $provider,
                'from_name'  => $fromName,
                'from_email' => $fromEmail,
                'test_email' => $testEmail,
                'host'       => $host,
                'port'       => $port,
                'encryption' => $encryption,
                'auth'       => $auth,
                'username'   => $username,
                'password'   => $password,   /* vide = inchange */
                'debug'      => $debug,
            ]);

            if ($saved) {
                /* Aucun secret dans le journal */
                il_log('mail_config_saved', 'Configuration des e-mails enregistree', [
                    'mode'  => $mode,
                    'hote'  => $mode === 'smtp' ? $host . ':' . $port : 'mail()',
                    'debug' => $debug ? 'actif' : 'inactif',
                ]);
                il_flash('success', 'Configuration enregistree.');
                il_redirect('email-config.php');
            }
            $errors[] = 'Ecriture impossible : verifiez les droits sur storage/config.';
        }

        /* En cas d'erreur, on reaffiche les valeurs soumises
           (jamais le mot de passe) */
        $config = array_merge($config, [
            'mode'       => $mode,
            'provider'   => $provider,
            'from_name'  => $fromName,
            'from_email' => $fromEmail,
            'test_email' => $testEmail,
            'host'       => $host,
            'port'       => $port,
            'encryption' => $encryption,
            'auth'       => $auth,
            'username'   => $username,
            'debug'      => $debug,
        ]);
    }

    /* ---------------------------------------------------------------
       Envoi reel d'un e-mail de test
       --------------------------------------------------------------- */
    if ($action === 'test') {
        $to = $config['test_email'] !== '' ? $config['test_email'] : $config['from_email'];

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            il_flash('error', 'Renseignez d\'abord une adresse de destination valide, puis enregistrez.');
            il_redirect('email-config.php');
        }

        $result = il_mail_send_test($to);

        if ($result['ok']) {
            il_flash('success', 'E-mail de test envoye a ' . $to . '. Verifiez la boite de reception.');
            il_redirect('email-config.php');
        }

        /* L'erreur reste affichee : elle doit pouvoir etre lue et comprise */
        $testError = $result['error'];
    }

    /* ---------------------------------------------------------------
       Purge du journal de debogage SMTP
       --------------------------------------------------------------- */
    if ($action === 'clear-debug') {
        il_mail_debug_clear();
        il_flash('success', 'Journal de debogage SMTP vide.');
        il_redirect('email-config.php');
    }
}

/* Prereglage correspondant a la configuration courante */
$currentProvider = $config['provider'] ?? il_mail_detect_provider($config);
if ($currentProvider !== 'custom' && !isset(il_mail_providers()[$currentProvider])) {
    $currentProvider = il_mail_detect_provider($config);
}

/* Prerequis techniques, reellement verifies */
$prereq = [];
$prereq[] = il_check('Extension OpenSSL', extension_loaded('openssl') ? 'activee' : 'desactivee',
    extension_loaded('openssl') ? 'ok' : 'error',
    extension_loaded('openssl') ? '' : 'Activez extension=openssl dans le php.ini utilise par Apache.');

$mailerOk = il_mailer_available();
$prereq[] = il_check('PHPMailer', $mailerOk ? 'installe' : 'absent', $mailerOk ? 'ok' : 'error',
    $mailerOk ? '' : 'Executez : composer require phpmailer/phpmailer');

if ($config['mode'] === 'smtp') {
    $probe = il_sys_tcp_probe($config['host'], $config['port'], 5);
    $prereq[] = il_check(
        'Acces sortant ' . $config['host'] . ':' . $config['port'],
        $probe['ok'] ? 'joignable' : 'injoignable',
        $probe['ok'] ? 'ok' : 'error',
        $probe['ok'] ? '' : 'Verifiez le reseau ou le pare-feu : ' . $probe['error']
    );
} else {
    $available = il_php_mail_available();
    $prereq[] = il_check('Serveur de messagerie local', $available ? 'configure' : 'absent',
        $available ? 'ok' : 'warn',
        $available ? '' : 'Ni sendmail_path ni SMTP ne sont renseignes dans le php.ini : le mode PHP mail ne peut pas fonctionner ici.');
}

$prereq[] = il_check('Mot de passe SMTP enregistre', il_mail_has_password() ? 'oui' : 'non',
    il_mail_has_password() ? 'ok' : 'warn');

/* Controles propres a Gmail : l'expediteur doit correspondre au compte
   authentifie, et le mot de passe d'application fait 16 caracteres. */
if ($currentProvider === 'gmail' && $config['mode'] === 'smtp') {
    $same = $config['from_email'] !== ''
        && strcasecmp($config['from_email'], $config['username']) === 0;
    $prereq[] = il_check(
        'Expediteur = identifiant Gmail',
        $config['from_email'] === '' || $config['username'] === ''
            ? 'a completer'
            : ($same ? 'identiques' : 'differents'),
        $same ? 'ok' : 'warn',
        $same ? '' : 'Gmail n\'accepte comme expediteur que l\'adresse du compte '
            . 'authentifie (ou un alias declare dans Gmail).'
    );
}

$ready     = il_mail_is_configured();
$debugLines = $config['debug'] ? il_mail_debug_tail(60) : [];

il_admin_page_start('Configuration des e-mails', 'email-config');
?>

<div class="page-head">
    <h1>Configuration des e-mails</h1>
    <p>
        Parametrage de l'envoi des e-mails du site : code de reinitialisation du
        mot de passe et notification des messages de contact.
    </p>
</div>

<?php if ($errors): ?>
    <div class="msg msg-error" role="alert">
        <strong>Configuration non enregistree :</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($testError !== ''): ?>
    <div class="msg msg-error" role="alert">
        <strong>L'envoi a echoue.</strong><br>
        <?php echo e($testError); ?>
        <?php if (!$config['debug']): ?>
            <br>Activez les journaux de debogage ci-dessous pour obtenir le detail de l'echange SMTP.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="status-banner status-<?php echo $ready ? 'ok' : 'warn'; ?>">
    <span class="dot" aria-hidden="true"></span>
    <div>
        <h2><?php echo $ready ? 'Configuration complete' : 'Configuration incomplete'; ?></h2>
        <p>
            <?php if ($ready): ?>
                Mode <?php echo e($config['mode'] === 'smtp' ? 'Brevo SMTP' : 'PHP mail'); ?>.
                Utilisez « Tester l'envoi » pour confirmer la reception reelle.
            <?php else: ?>
                Completez les champs requis puis enregistrez. Aucun envoi n'est simule :
                tant que la configuration est incomplete, les e-mails ne partent pas.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid grid-2">

    <!-- ====================== FORMULAIRE ====================== -->
    <section class="card">
        <h2>Parametres d'envoi</h2>
        <p class="card-hint">
            Les secrets sont stockes dans storage/config/mail.php, hors acces HTTP
            et exclus du depot Git.
        </p>

        <form method="post" action="email-config.php" autocomplete="off">
            <?php echo il_csrf_field(); ?>
            <input type="hidden" name="action" value="save">

            <fieldset>
                <legend>Mode d'envoi</legend>
                <div class="radio-row">
                    <label>
                        <input type="radio" name="mode" value="smtp"
                               <?php echo $config['mode'] === 'smtp' ? 'checked' : ''; ?>>
                        SMTP authentifie (recommande)
                    </label>
                    <label>
                        <input type="radio" name="mode" value="mail"
                               <?php echo $config['mode'] === 'mail' ? 'checked' : ''; ?>>
                        PHP mail
                    </label>
                </div>
                <?php if (!il_php_mail_available()): ?>
                    <p class="field-hint">
                        Le mode PHP mail necessite un serveur de messagerie sur l'hote.
                        Il n'est pas disponible dans cette installation WAMP : les champs
                        restent proposes pour un hebergement compatible.
                    </p>
                <?php endif; ?>
            </fieldset>

            <fieldset>
                <legend>Fournisseur SMTP</legend>
                <div class="form-group">
                    <label for="provider">Prereglage</label>
                    <select id="provider" name="provider" data-smtp-preset>
                        <?php foreach (il_mail_providers() as $key => $provider): ?>
                            <option value="<?php echo e($key); ?>"
                                    data-host="<?php echo e($provider['host']); ?>"
                                    data-port="<?php echo (int) $provider['port']; ?>"
                                    data-encryption="<?php echo e($provider['encryption']); ?>"
                                    data-hint="<?php echo e($provider['hint']); ?>"
                                    <?php echo $currentProvider === $key ? 'selected' : ''; ?>>
                                <?php echo e($provider['label']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom" <?php echo $currentProvider === 'custom' ? 'selected' : ''; ?>>
                            Autre serveur (saisie manuelle)
                        </option>
                    </select>
                    <p class="field-hint" id="provider-hint">
                        <?php echo e(il_mail_providers()[$currentProvider]['hint']
                            ?? 'Renseignez manuellement le serveur, le port et la securite.'); ?>
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>Expediteur et destinataire</legend>

                <div class="form-group">
                    <label for="from_name">Nom de l'expediteur</label>
                    <input type="text" id="from_name" name="from_name" maxlength="80"
                           value="<?php echo e($config['from_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="from_email">Adresse e-mail de l'expediteur</label>
                    <input type="email" id="from_email" name="from_email" required
                           value="<?php echo e($config['from_email']); ?>"
                           aria-describedby="from-hint">
                    <p class="field-hint" id="from-hint">
                        Avec Gmail, cette adresse doit etre celle du compte
                        authentifie (ou un alias declare dans Gmail). Avec Brevo,
                        elle doit etre validee dans Expediteurs.
                    </p>
                </div>

                <div class="form-group">
                    <label for="test_email">Adresse e-mail du destinataire de test</label>
                    <input type="email" id="test_email" name="test_email"
                           value="<?php echo e($config['test_email']); ?>">
                    <p class="field-hint">
                        Sert aussi de destinataire des notifications de contact.
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>Serveur SMTP</legend>

                <div class="form-group">
                    <label for="host">Serveur SMTP</label>
                    <input type="text" id="host" name="host"
                           value="<?php echo e($config['host']); ?>">
                    <p class="field-hint">
                        Gmail : smtp.gmail.com &middot; Brevo : smtp-relay.brevo.com
                    </p>
                </div>

                <div class="form-group">
                    <label for="port">Port SMTP</label>
                    <input type="number" id="port" name="port" min="1" max="65535"
                           value="<?php echo (int) $config['port']; ?>">
                    <p class="field-hint">587 avec STARTTLS, ou 465 avec SSL/TLS.</p>
                </div>

                <div class="form-group">
                    <label for="encryption">Securite de connexion</label>
                    <select id="encryption" name="encryption">
                        <option value="tls" <?php echo $config['encryption'] === 'tls' ? 'selected' : ''; ?>>
                            STARTTLS (port 587)
                        </option>
                        <option value="ssl" <?php echo $config['encryption'] === 'ssl' ? 'selected' : ''; ?>>
                            SSL / TLS (port 465)
                        </option>
                        <option value="" <?php echo $config['encryption'] === '' ? 'selected' : ''; ?>>
                            Aucune (deconseille)
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="check-label">
                        <input type="checkbox" name="auth" value="1"
                               <?php echo $config['auth'] ? 'checked' : ''; ?>>
                        Authentification SMTP activee
                    </label>
                </div>

                <div class="form-group">
                    <label for="username">Identifiant SMTP</label>
                    <input type="text" id="username" name="username"
                           value="<?php echo e($config['username']); ?>"
                           autocomplete="off">
                    <p class="field-hint">
                        Gmail : votre adresse Gmail complete.
                        Brevo : identifiant de la forme xxxxxxx@smtp-brevo.com
                    </p>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe SMTP</label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password"
                           placeholder="<?php echo il_mail_has_password()
                               ? 'enregistre - laisser vide pour le conserver'
                               : ($currentProvider === 'gmail'
                                   ? 'mot de passe d\'application (16 caracteres)'
                                   : 'cle ou mot de passe SMTP'); ?>">
                    <p class="field-hint">
                        <?php if (il_mail_has_password()): ?>
                            Un mot de passe est enregistre. Il n'est jamais reaffiche :
                            laissez le champ vide pour le conserver.
                        <?php else: ?>
                            Aucun mot de passe enregistre pour le moment.
                        <?php endif; ?>
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>Debogage</legend>
                <label class="check-label">
                    <input type="checkbox" name="debug" value="1"
                           <?php echo $config['debug'] ? 'checked' : ''; ?>>
                    Enregistrer les journaux de debogage SMTP
                </label>
                <p class="field-hint">
                    Ecrit l'echange avec le serveur dans storage/logs/mail-debug.log
                    (hors acces HTTP). Les identifiants y sont masques.
                </p>
            </fieldset>

            <div class="actions">
                <button type="submit" class="btn btn-primary" data-loading
                        data-busy-text="Enregistrement...">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="btn-label">Enregistrer</span>
                </button>
                <a class="btn btn-ghost" href="email-config.php">Annuler</a>
            </div>
        </form>
    </section>

    <div>
        <!-- ====================== PREREQUIS ====================== -->
        <section class="card">
            <h2>Verification des prerequis</h2>
            <p class="card-hint">Controles executes a l'affichage de cette page.</p>
            <?php il_check_list($prereq); ?>
        </section>

        <!-- ====================== TEST D'ENVOI ====================== -->
        <section class="card">
            <h2>Tester l'envoi</h2>
            <p class="card-hint">
                Un e-mail reel est envoye a
                <strong><?php echo e($config['test_email'] !== '' ? $config['test_email'] : ($config['from_email'] !== '' ? $config['from_email'] : 'aucune adresse configuree')); ?></strong>.
                Aucune reussite n'est affichee si le serveur refuse l'envoi.
            </p>

            <form method="post" action="email-config.php">
                <?php echo il_csrf_field(); ?>
                <input type="hidden" name="action" value="test">
                <button type="submit" class="btn btn-primary" data-loading
                        data-busy-text="Envoi en cours..."
                        <?php echo $ready ? '' : 'disabled'; ?>>
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="btn-label">Envoyer l'e-mail de test</span>
                </button>
            </form>

            <?php if (!$ready): ?>
                <p class="field-hint">
                    Le bouton s'active une fois la configuration enregistree et complete.
                </p>
            <?php endif; ?>
        </section>

        <!-- ====================== JOURNAL DE DEBOGAGE ====================== -->
        <section class="card">
            <h2>Journal de debogage SMTP</h2>
            <?php if (!$config['debug']): ?>
                <p class="card-hint">
                    Le debogage est desactive. Activez-le dans le formulaire pour
                    enregistrer l'echange avec le serveur SMTP.
                </p>
            <?php elseif (!$debugLines): ?>
                <div class="empty">
                    <strong>Aucune ligne enregistree</strong>
                    Le journal se remplira au prochain envoi.
                </div>
            <?php else: ?>
                <p class="card-hint">
                    <?php echo count($debugLines); ?> derniere(s) ligne(s). Les identifiants
                    sont masques.
                </p>
                <div class="table-wrap">
                    <pre class="mono" style="font-size:.78rem;line-height:1.5;max-height:260px;overflow:auto;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid var(--border)"><?php
                        foreach ($debugLines as $line) {
                            echo e($line) . "\n";
                        }
                    ?></pre>
                </div>
                <form method="post" action="email-config.php" style="margin-top:14px"
                      data-confirm="Le journal de debogage SMTP va etre supprime."
                      data-confirm-title="Vider le journal de debogage"
                      data-confirm-label="Vider">
                    <?php echo il_csrf_field(); ?>
                    <input type="hidden" name="action" value="clear-debug">
                    <button type="submit" class="btn btn-ghost" data-loading>
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Vider le journal</span>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>

</div>

<?php il_admin_page_end(); ?>
