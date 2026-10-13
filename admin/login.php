<?php
/**
 * login.php - Page de connexion au panneau d'administration
 *
 * - message d'erreur identique quelle que soit la cause (l'existence
 *   du compte n'est jamais revelee) ;
 * - limitation progressive des tentatives, appliquee cote serveur
 *   (voir includes/security.php) ;
 * - session regeneree apres authentification.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

il_session_start();

/* Deja connecte : inutile de repasser par le formulaire */
if (il_auth_check()) {
    il_redirect('dashboard.php');
}

$adminExists = il_admin_exists();
$error       = '';
$wait        = 0;
$identifier  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminExists) {
    il_csrf_require();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        /* Champs vides : on n'incremente pas le compteur d'echecs */
        $error = 'Veuillez renseigner votre adresse e-mail et votre mot de passe.';
    } else {
        $result = il_auth_attempt($identifier, $password);

        if ($result['ok']) {
            il_flash('success', 'Connexion reussie. Bienvenue dans le panneau d\'administration.');
            il_redirect('dashboard.php');
        }

        $error = $result['error'];
        $wait  = (int) $result['wait'];
    }
}

/* Sur simple affichage, on indique le delai restant eventuel : il ne
   peut pas etre contourne en rechargeant la page, puisqu'il est
   conserve cote serveur. */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = il_rl_status('');
    if (!$status['allowed']) {
        $wait = (int) $status['wait'];
        $error = $status['reason'] === 'lock'
            ? 'Trop de tentatives depuis cette adresse. Acces temporairement bloque.'
            : 'Une tentative recente a echoue. Patientez avant de reessayer.';
    }
}

$messages = il_take_flash();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>InfoLeader - Connexion a l'administration</title>
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body class="login-page">

    <div class="login-shell">
        <div class="login-logo">
            <div class="name">InfoLeader</div>
            <p>Espace d'administration</p>
        </div>

        <div class="login-card">
            <h1>Connexion</h1>
            <p class="sub">Acces reserve a l'administrateur du site.</p>

            <?php il_render_messages($messages); ?>

            <?php if (!$adminExists): ?>

                <div class="msg msg-warning" role="status">
                    Aucun compte administrateur n'existe encore. Lancez d'abord
                    l'initialisation : elle n'est possible qu'une seule fois.
                </div>
                <a class="btn btn-primary btn-block" href="setup.php">
                    Initialiser le compte administrateur
                </a>

            <?php else: ?>

                <?php if ($error !== ''): ?>
                    <div class="msg msg-error" role="alert">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($wait > 0): ?>
                    <div class="msg msg-warning" role="status"
                         data-countdown="<?php echo (int) $wait; ?>"
                         data-countdown-message="Vous pouvez reessayer.">
                        Nouvelle tentative possible dans
                        <span class="countdown"><?php echo e(il_human_duration($wait)); ?></span>.
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" autocomplete="on">
                    <?php echo il_csrf_field(); ?>

                    <div class="form-group">
                        <label for="identifier">Adresse e-mail</label>
                        <input type="email" id="identifier" name="identifier" required
                               value="<?php echo e($identifier); ?>"
                               autocomplete="username" autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" data-loading
                            data-busy-text="Connexion..."
                            <?php echo $wait > 0 ? 'data-countdown-disable disabled' : ''; ?>>
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Se connecter</span>
                    </button>
                </form>

                <p class="login-foot">
                    <a href="reset-password.php">Mot de passe oublie ?</a>
                </p>

            <?php endif; ?>
        </div>

        <div class="back-site">
            <a href="../index.php">Retour au site InfoLeader</a>
        </div>
    </div>

</body>
</html>
