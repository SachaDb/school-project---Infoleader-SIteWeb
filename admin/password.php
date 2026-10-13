<?php
/**
 * password.php - Module « Mot de passe »
 *
 * Modification du mot de passe administrateur. Toutes les regles sont
 * verifiees cote serveur, meme si l'interface les controle en direct.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$admin  = il_auth_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    /* 1. Le mot de passe actuel est verifie cote serveur */
    if ($current === '') {
        $errors[] = 'Le mot de passe actuel est requis.';
    } elseif (!password_verify($current, $admin['password_hash'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
        il_log('security_denied', 'Mot de passe actuel incorrect lors d\'un changement de mot de passe');
    }

    /* 2. Regles de securite du nouveau mot de passe */
    $errors = array_merge($errors, il_password_validate($new));

    /* 3. Confirmation identique */
    if ($new !== $confirm) {
        $errors[] = 'Le nouveau mot de passe et sa confirmation ne correspondent pas.';
    }

    /* 4. Different de l'actuel */
    if ($new !== '' && password_verify($new, $admin['password_hash'])) {
        $errors[] = 'Le nouveau mot de passe doit etre different du mot de passe actuel.';
    }

    if (!$errors) {
        $admin['password_hash'] = il_password_hash($new);

        /* Les autres sessions ouvertes sont invalidees */
        $admin['auth_epoch'] = (int) ($admin['auth_epoch'] ?? 0) + 1;

        if (il_admin_save($admin)) {
            /* La session courante reste valide : on la realigne */
            $_SESSION['il_epoch'] = $admin['auth_epoch'];
            session_regenerate_id(true);

            /* Le mot de passe n'apparait jamais dans le journal */
            il_log('password_changed', 'Mot de passe administrateur modifie', [
                'email' => $admin['email'],
            ]);

            il_flash('success', 'Mot de passe modifie. Les autres sessions ouvertes ont ete invalidees.');
            il_redirect('password.php');
        }

        $errors[] = 'Enregistrement impossible : verifiez les droits d\'ecriture sur storage/config.';
    }
}

$policy = il_password_policy();

il_admin_page_start('Mot de passe', 'password');
?>

<div class="page-head">
    <h1>Mot de passe</h1>
    <p>
        Modification du mot de passe du compte
        <strong><?php echo e($admin['email']); ?></strong>.
        Le mot de passe est conserve sous forme de hash
        (<code>password_hash()</code>) et jamais en clair.
    </p>
</div>

<?php if ($errors): ?>
    <div class="msg msg-error" role="alert">
        <strong>Le mot de passe n'a pas ete modifie :</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid grid-2">

    <section class="card">
        <h2>Changer le mot de passe</h2>
        <p class="card-hint">Les trois champs sont obligatoires.</p>

        <form method="post" action="password.php" autocomplete="off">
            <?php echo il_csrf_field(); ?>

            <div class="form-group">
                <label for="current_password">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password"
                       required autocomplete="current-password" autofocus>
            </div>

            <hr class="card-sep">

            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required
                       data-policy-target="#policy" autocomplete="new-password"
                       aria-describedby="policy">
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary" data-loading
                    data-busy-text="Enregistrement...">
                <span class="spinner" aria-hidden="true"></span>
                <span class="btn-label">Enregistrer le nouveau mot de passe</span>
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Regles a respecter</h2>
        <p class="card-hint">
            Affichees avant validation et verifiees cote serveur a l'enregistrement.
        </p>

        <ul class="policy" id="policy">
            <li data-rule="length"><?php echo e($policy[0]); ?></li>
            <li data-rule="upper"><?php echo e($policy[1]); ?></li>
            <li data-rule="lower"><?php echo e($policy[2]); ?></li>
            <li data-rule="digit"><?php echo e($policy[3]); ?></li>
            <li data-rule="special"><?php echo e($policy[4]); ?></li>
        </ul>

        <ul class="check-list">
            <li>
                <span class="k">Doit differer du mot de passe actuel</span>
                <span class="v"><?php echo il_badge('info', 'Verifie'); ?></span>
            </li>
            <li>
                <span class="k">Confirmation identique obligatoire</span>
                <span class="v"><?php echo il_badge('info', 'Verifie'); ?></span>
            </li>
            <li>
                <span class="k">Algorithme de hachage</span>
                <span class="v mono">password_hash() / PASSWORD_DEFAULT</span>
            </li>
            <li>
                <span class="k">Autres sessions ouvertes</span>
                <span class="v">invalidees apres modification</span>
            </li>
            <li>
                <span class="k">Journalisation</span>
                <span class="v">evenement enregistre, sans le mot de passe</span>
            </li>
        </ul>
    </section>

</div>

<?php il_admin_page_end(); ?>
