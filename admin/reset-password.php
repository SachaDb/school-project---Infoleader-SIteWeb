<?php
/**
 * reset-password.php - Parcours complet « Mot de passe oublie ? »
 *
 * Etape 1 : demande d'un code a 8 chiffres, envoye par e-mail.
 * Etape 2 : saisie et verification du code (cote serveur).
 * Etape 3 : saisie du nouveau mot de passe.
 *
 * Le code est aleatoire, stocke hache, valable 5 minutes, a usage
 * unique, et invalide des qu'un nouveau code est demande.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

il_session_start();

/* Deja connecte : le module Mot de passe est plus adapte */
if (il_auth_check()) {
    il_redirect('password.php');
}

if (!il_admin_exists()) {
    il_flash('warning', 'Aucun compte administrateur n\'existe encore.');
    il_redirect('login.php');
}

/* Etape courante, conservee en session */
$step = $_SESSION['il_reset_step'] ?? 'request';
if (!in_array($step, ['request', 'code', 'password'], true)) {
    $step = 'request';
}

$errors  = [];
$notices = [];
$action  = $_POST['action'] ?? '';

/* Retour volontaire a l'etape 1 (lien « demander un nouveau code ») */
if (($_GET['new'] ?? '') === '1') {
    $step = 'request';
    unset($_SESSION['il_reset_step'], $_SESSION['il_reset_ok']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    /* ---------------------------------------------------------------
       Etape 1 : demande d'un code
       --------------------------------------------------------------- */
    if ($action === 'request') {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Veuillez saisir une adresse e-mail valide.';
        } elseif (!il_mail_is_configured()) {
            /* Ce message porte sur la configuration du site, pas sur
               l'existence du compte : il ne revele donc rien. */
            $errors[] = 'L\'envoi d\'e-mails n\'est pas configure sur ce site. '
                . 'Un administrateur doit renseigner la configuration des e-mails '
                . '(module Configuration des e-mails) avant d\'utiliser cette fonction.';
        } else {
            $admin = il_admin_get();
            $matches = $admin && hash_equals(
                mb_strtolower($admin['email']),
                mb_strtolower($email)
            );

            if ($matches) {
                $request = il_reset_request($admin['email']);

                if (!$request['ok'] && !empty($request['throttled'])) {
                    $errors[] = 'Trop de demandes de reinitialisation. Reessayez dans '
                        . il_human_duration($request['wait']) . '.';
                } else {
                    $sent = il_mail_send_reset_code($admin['email'], $request['code']);

                    /* L'echec d'envoi est journalise sans le code */
                    if (!$sent['ok']) {
                        il_log('mail_test_failed', 'Echec d\'envoi du code de reinitialisation', [
                            'erreur' => $sent['error'],
                        ]);
                    }
                }
            }

            /* Reponse volontairement identique dans tous les cas */
            if (!$errors) {
                $_SESSION['il_reset_step'] = 'code';
                $step = 'code';
                il_flash('info', 'Si ce compte est eligible, un code de reinitialisation '
                    . 'vient de lui etre envoye par e-mail. Il est valable 5 minutes.');
                il_redirect('reset-password.php');
            }
        }
    }

    /* ---------------------------------------------------------------
       Etape 2 : verification du code
       --------------------------------------------------------------- */
    if ($action === 'code') {
        $code   = (string) ($_POST['code'] ?? '');
        $result = il_reset_verify($code);

        if ($result['ok']) {
            $_SESSION['il_reset_step'] = 'password';
            $_SESSION['il_reset_ok']   = time();
            il_flash('success', 'Code accepte. Choisissez votre nouveau mot de passe.');
            il_redirect('reset-password.php');
        }

        $errors[] = $result['error'];
        $step = 'code';
    }

    /* ---------------------------------------------------------------
       Etape 3 : nouveau mot de passe
       --------------------------------------------------------------- */
    if ($action === 'password') {
        /* Le code doit avoir ete valide dans cette session */
        if (empty($_SESSION['il_reset_ok'])) {
            il_flash('error', 'Veuillez d\'abord saisir le code de reinitialisation.');
            unset($_SESSION['il_reset_step']);
            il_redirect('reset-password.php');
        }

        /* Et il doit toujours etre valide cote serveur */
        $state = il_reset_load();
        if (empty($state['code_hash']) || !empty($state['used'])
            || time() > (int) ($state['expires_at'] ?? 0)
        ) {
            unset($_SESSION['il_reset_step'], $_SESSION['il_reset_ok']);
            il_flash('error', 'Le code a expire ou a deja ete utilise. Demandez un nouveau code.');
            il_redirect('reset-password.php');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        $errors = il_password_validate($password);

        if ($password !== $confirm) {
            $errors[] = 'Le mot de passe et sa confirmation ne correspondent pas.';
        }

        $admin = il_admin_get();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $errors[] = 'Le nouveau mot de passe doit etre different de l\'ancien.';
        }

        if (!$errors && $admin) {
            $admin['password_hash'] = il_password_hash($password);
            $admin['auth_epoch']    = (int) ($admin['auth_epoch'] ?? 0) + 1; /* invalide les sessions */

            if (il_admin_save($admin)) {
                il_reset_consume();   /* usage unique */

                /* Ni le mot de passe ni le code ne sont journalises */
                il_log('reset_success', 'Mot de passe reinitialise via un code', [
                    'email' => $admin['email'],
                ]);

                unset($_SESSION['il_reset_step'], $_SESSION['il_reset_ok']);
                il_flash('success', 'Mot de passe modifie. Vous pouvez vous connecter.');
                il_redirect('login.php');
            }

            $errors[] = 'Enregistrement impossible : verifiez les droits d\'ecriture '
                . 'sur storage/config.';
        }

        $step = 'password';
    }
}

/* Duree restante du code, pour information */
$state     = il_reset_load();
$remaining = 0;
if (!empty($state['expires_at']) && empty($state['used'])) {
    $remaining = max(0, (int) $state['expires_at'] - time());
}

$messages = il_take_flash();
$policy   = il_password_policy();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>InfoLeader - Mot de passe oublie</title>
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body class="login-page">

    <div class="login-shell">
        <div class="login-logo">
            <div class="name">InfoLeader</div>
            <p>Reinitialisation du mot de passe</p>
        </div>

        <div class="login-card">

            <?php il_render_messages($messages); ?>

            <?php if ($errors): ?>
                <div class="msg msg-error" role="alert">
                    <?php if (count($errors) === 1): ?>
                        <?php echo e($errors[0]); ?>
                    <?php else: ?>
                        <strong>Veuillez corriger :</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>

                <h1>Etape 1 - Demander un code</h1>
                <p class="sub">
                    Saisissez l'adresse e-mail du compte administrateur. Un code a
                    8 chiffres, valable 5 minutes, vous sera envoye.
                </p>

                <form method="post" action="reset-password.php">
                    <?php echo il_csrf_field(); ?>
                    <input type="hidden" name="action" value="request">

                    <div class="form-group">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" required
                               autocomplete="username" autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" data-loading
                            data-busy-text="Envoi en cours...">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Envoyer le code</span>
                    </button>
                </form>

            <?php elseif ($step === 'code'): ?>

                <h1>Etape 2 - Saisir le code</h1>
                <p class="sub">
                    Entrez le code a 8 chiffres recu par e-mail.
                    <?php if ($remaining > 0): ?>
                        Il expire dans
                        <span class="countdown"><?php echo e(il_human_duration($remaining)); ?></span>.
                    <?php endif; ?>
                </p>

                <?php if ($remaining > 0): ?>
                    <div class="msg msg-info" role="status"
                         data-countdown="<?php echo (int) $remaining; ?>"
                         data-countdown-message="Le code a expire. Demandez-en un nouveau.">
                        Temps restant : <span class="countdown"></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="reset-password.php">
                    <?php echo il_csrf_field(); ?>
                    <input type="hidden" name="action" value="code">

                    <div class="form-group">
                        <label for="code">Code de reinitialisation</label>
                        <input type="text" id="code" name="code" class="code-input"
                               inputmode="numeric" pattern="[0-9]{8}" maxlength="8"
                               data-digits="8" required autocomplete="one-time-code"
                               autofocus aria-describedby="code-hint">
                        <p class="field-hint" id="code-hint">8 chiffres, sans espace.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" data-loading
                            data-busy-text="Verification...">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Verifier le code</span>
                    </button>
                </form>

                <p class="login-foot">
                    <a href="reset-password.php?new=1">Demander un nouveau code</a>
                </p>

            <?php else: ?>

                <h1>Etape 3 - Nouveau mot de passe</h1>
                <p class="sub">Le code a ete accepte. Definissez votre nouveau mot de passe.</p>

                <ul class="policy" id="policy">
                    <li data-rule="length"><?php echo e($policy[0]); ?></li>
                    <li data-rule="upper"><?php echo e($policy[1]); ?></li>
                    <li data-rule="lower"><?php echo e($policy[2]); ?></li>
                    <li data-rule="digit"><?php echo e($policy[3]); ?></li>
                    <li data-rule="special"><?php echo e($policy[4]); ?></li>
                </ul>

                <form method="post" action="reset-password.php">
                    <?php echo il_csrf_field(); ?>
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" required
                               data-policy-target="#policy" autocomplete="new-password" autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" data-loading
                            data-busy-text="Enregistrement...">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Enregistrer le mot de passe</span>
                    </button>
                </form>

            <?php endif; ?>

            <p class="login-foot">
                <a href="login.php">Retour a la connexion</a>
            </p>
        </div>

        <div class="back-site">
            <a href="../index.php">Retour au site InfoLeader</a>
        </div>
    </div>

</body>
</html>
