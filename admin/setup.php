<?php
/**
 * setup.php - Initialisation unique du compte administrateur
 *
 * Aucun compte par defaut n'est cree et aucun mot de passe n'est code
 * en dur : le compte est saisi ici, une seule fois. Des qu'un compte
 * existe, cette page se desactive d'elle-meme.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

il_session_start();

/* ------------------------------------------------------------------
   Desactivation apres la premiere utilisation
   ------------------------------------------------------------------ */
$alreadyInitialised = il_admin_exists();

$errors  = [];
$values  = ['email' => '', 'email_confirm' => ''];
$done    = false;

if (!$alreadyInitialised && $_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    $email        = trim((string) ($_POST['email'] ?? ''));
    $emailConfirm = trim((string) ($_POST['email_confirm'] ?? ''));
    $password     = (string) ($_POST['password'] ?? '');
    $passwordConf = (string) ($_POST['password_confirm'] ?? '');

    $values['email']         = $email;
    $values['email_confirm'] = $emailConfirm;

    /* Validation serveur */
    if ($email === '') {
        $errors[] = 'L\'adresse e-mail est requise.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse e-mail saisie n\'est pas valide.';
    } elseif (mb_strtolower($email) !== mb_strtolower($emailConfirm)) {
        $errors[] = 'Les deux adresses e-mail ne sont pas identiques.';
    }

    $errors = array_merge($errors, il_password_validate($password));

    if ($password !== $passwordConf) {
        $errors[] = 'Le mot de passe et sa confirmation ne correspondent pas.';
    }

    if (!$errors) {
        $saved = il_admin_save([
            'email'         => $email,
            'password_hash' => il_password_hash($password),
            'created_at'    => time(),
            'auth_epoch'    => 1,
        ]);

        if ($saved) {
            /* Le mot de passe n'est jamais journalise */
            il_log('setup_completed', 'Compte administrateur initialise', ['email' => $email]);
            il_flash('success', 'Compte administrateur cree. Vous pouvez maintenant vous connecter.');
            il_redirect('login.php');
        }

        $errors[] = 'Impossible d\'ecrire le fichier de configuration. '
            . 'Verifiez les droits d\'ecriture sur storage/config.';
    }
}

$policy = il_password_policy();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>InfoLeader - Initialisation du compte administrateur</title>
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body class="login-page">

    <div class="login-shell">
        <div class="login-logo">
            <div class="name">InfoLeader</div>
            <p>Initialisation du panneau d'administration</p>
        </div>

        <div class="login-card">
            <?php if ($alreadyInitialised): ?>

                <h1>Initialisation deja effectuee</h1>
                <p class="sub">Un compte administrateur existe deja.</p>

                <div class="msg msg-warning" role="status">
                    Cette page est desactivee : elle ne peut servir qu'une seule fois,
                    afin qu'un visiteur ne puisse pas remplacer le compte existant.
                    Pour changer le mot de passe, utilisez « Mot de passe oublie ? »
                    sur la page de connexion, ou le module Mot de passe apres connexion.
                </div>

                <a class="btn btn-primary btn-block" href="login.php">Aller a la connexion</a>

            <?php else: ?>

                <h1>Creer le compte administrateur</h1>
                <p class="sub">
                    Cette etape n'est proposee qu'une fois : aucun compte n'existe
                    actuellement. Choisissez vous-meme l'adresse et le mot de passe.
                </p>

                <?php if ($errors): ?>
                    <div class="msg msg-error" role="alert">
                        <strong>Creation impossible :</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="setup.php" autocomplete="off">
                    <?php echo il_csrf_field(); ?>

                    <div class="form-group">
                        <label for="email">Adresse e-mail de l'administrateur</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo e($values['email']); ?>"
                               autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="email_confirm">Confirmer l'adresse e-mail</label>
                        <input type="email" id="email_confirm" name="email_confirm" required
                               value="<?php echo e($values['email_confirm']); ?>"
                               autocomplete="off">
                    </div>

                    <hr class="card-sep">

                    <p class="card-hint">Regles a respecter pour le mot de passe :</p>
                    <ul class="policy" id="policy">
                        <li data-rule="length"><?php echo e($policy[0]); ?></li>
                        <li data-rule="upper"><?php echo e($policy[1]); ?></li>
                        <li data-rule="lower"><?php echo e($policy[2]); ?></li>
                        <li data-rule="digit"><?php echo e($policy[3]); ?></li>
                        <li data-rule="special"><?php echo e($policy[4]); ?></li>
                    </ul>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required
                               data-policy-target="#policy" autocomplete="new-password">
                        <p class="field-hint">
                            Les regles sont verifiees cote serveur a l'enregistrement.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmer le mot de passe</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" data-loading
                            data-busy-text="Creation...">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Creer le compte</span>
                    </button>
                </form>

            <?php endif; ?>
        </div>

        <div class="back-site">
            <a href="../index.php">Retour au site InfoLeader</a>
        </div>
    </div>

</body>
</html>
