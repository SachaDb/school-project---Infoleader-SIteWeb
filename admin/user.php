<?php
/**
 * user.php - Module « Utilisateur »
 * Modification de l'adresse e-mail du compte administrateur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$admin  = il_auth_user();
$errors = [];
$value  = ['email' => '', 'email_confirm' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();

    $current = (string) ($_POST['current_password'] ?? '');
    $email   = trim((string) ($_POST['email'] ?? ''));
    $confirm = trim((string) ($_POST['email_confirm'] ?? ''));

    $value = ['email' => $email, 'email_confirm' => $confirm];

    /* 1. Mot de passe actuel verifie cote serveur */
    if ($current === '') {
        $errors[] = 'Le mot de passe actuel est requis.';
    } elseif (!password_verify($current, $admin['password_hash'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
        il_log('security_denied', 'Mot de passe incorrect lors d\'un changement d\'adresse e-mail');
    }

    /* 2. Syntaxe, champs non vides et confirmation identique */
    if ($email === '') {
        $errors[] = 'La nouvelle adresse e-mail est requise.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'La nouvelle adresse e-mail n\'est pas valide.';
    } elseif (mb_strlen($email) > 190) {
        $errors[] = 'L\'adresse e-mail est trop longue.';
    }

    if ($confirm === '') {
        $errors[] = 'La confirmation de l\'adresse e-mail est requise.';
    } elseif ($email !== '' && mb_strtolower($email) !== mb_strtolower($confirm)) {
        $errors[] = 'Les deux adresses e-mail ne sont pas identiques.';
    }

    /* 3. Differente de l'adresse actuelle */
    if ($email !== '' && mb_strtolower($email) === mb_strtolower($admin['email'])) {
        $errors[] = 'La nouvelle adresse doit etre differente de l\'adresse actuelle.';
    }

    if (!$errors) {
        $previous       = $admin['email'];
        $admin['email'] = $email;

        if (il_admin_save($admin)) {
            $_SESSION['il_email'] = $email;

            il_log('email_changed', 'Adresse e-mail de l\'administrateur modifiee', [
                'ancienne' => $previous,
                'nouvelle' => $email,
            ]);

            /* Le code de reinitialisation eventuel visait l'ancienne adresse */
            il_reset_clear();

            il_flash('success', 'Adresse e-mail modifiee. Elle est desormais utilisee pour la connexion.');
            il_redirect('dashboard.php');
        }

        $errors[] = 'Enregistrement impossible : verifiez les droits d\'ecriture sur storage/config.';
    }
}

il_admin_page_start('Utilisateur', 'user');
?>

<div class="page-head">
    <h1>Utilisateur</h1>
    <p>Adresse e-mail du compte administrateur : elle sert d'identifiant de
       connexion et de destinataire des codes de reinitialisation.</p>
</div>

<?php if ($errors): ?>
    <div class="msg msg-error" role="alert">
        <strong>L'adresse n'a pas ete modifiee :</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid grid-2">

    <section class="card">
        <h2>Changer l'adresse e-mail</h2>
        <p class="card-hint">
            Adresse actuelle : <strong><?php echo e($admin['email']); ?></strong>
        </p>

        <form method="post" action="user.php" autocomplete="off">
            <?php echo il_csrf_field(); ?>

            <div class="form-group">
                <label for="current_password">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password"
                       required autocomplete="current-password" autofocus>
            </div>

            <hr class="card-sep">

            <div class="form-group">
                <label for="email">Nouvelle adresse e-mail</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo e($value['email']); ?>">
            </div>

            <div class="form-group">
                <label for="email_confirm">Confirmer la nouvelle adresse</label>
                <input type="email" id="email_confirm" name="email_confirm" required
                       value="<?php echo e($value['email_confirm']); ?>">
            </div>

            <button type="submit" class="btn btn-primary" data-loading
                    data-busy-text="Enregistrement...">
                <span class="spinner" aria-hidden="true"></span>
                <span class="btn-label">Enregistrer l'adresse</span>
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Regles appliquees</h2>
        <p class="card-hint">Toutes verifiees cote serveur.</p>
        <ul class="check-list">
            <li><span class="k">Mot de passe actuel exige</span><span class="v"><?php echo il_badge('info', 'Verifie'); ?></span></li>
            <li><span class="k">Syntaxe de l'adresse</span><span class="v mono">filter_var / FILTER_VALIDATE_EMAIL</span></li>
            <li><span class="k">Deux saisies identiques</span><span class="v"><?php echo il_badge('info', 'Verifie'); ?></span></li>
            <li><span class="k">Differente de l'actuelle</span><span class="v"><?php echo il_badge('info', 'Verifie'); ?></span></li>
            <li><span class="k">Champs vides refuses</span><span class="v"><?php echo il_badge('info', 'Verifie'); ?></span></li>
            <li><span class="k">Journalisation du changement</span><span class="v">ancienne et nouvelle adresse</span></li>
        </ul>

        <hr class="card-sep">
        <p class="field-hint">
            Le site ne possede pas de mecanisme de verification d'adresse par
            e-mail : la nouvelle adresse est donc enregistree sans etre marquee
            comme verifiee. Le module Configuration des e-mails permet de
            confirmer qu'une adresse recoit bien les messages.
        </p>
    </section>

</div>

<?php il_admin_page_end(); ?>
