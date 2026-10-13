<?php
/**
 * _layout.php - Gabarit commun des pages authentifiees du panneau
 *
 * Fournit l'en-tete, la navigation, l'affichage des messages et le
 * pied de page, pour que tous les modules restent homogenes.
 */

/** Entrees de la navigation du panneau */
function il_admin_nav_items()
{
    return [
        'dashboard'    => ['label' => 'Tableau de bord', 'href' => 'dashboard.php'],
        'products'     => ['label' => 'Produits', 'href' => 'products.php'],
        'email-config' => ['label' => 'Configuration des e-mails', 'href' => 'email-config.php'],
        'password'     => ['label' => 'Mot de passe', 'href' => 'password.php'],
        'user'         => ['label' => 'Utilisateur', 'href' => 'user.php'],
        'maintenance'  => ['label' => 'Maintenance', 'href' => 'maintenance.php'],
    ];
}

/* L'affichage des messages est fourni par includes/bootstrap.php
   (il_render_messages), car les pages non authentifiees - connexion,
   initialisation, reinitialisation - en ont egalement besoin. */

/** Badge d'etat colore (ok / warn / error / info) */
function il_badge($state, $label = '')
{
    $map = [
        'ok'    => ['badge-ok', 'OK'],
        'warn'  => ['badge-warn', 'A verifier'],
        'error' => ['badge-error', 'Erreur'],
        'info'  => ['badge-info', 'Info'],
    ];
    [$class, $default] = $map[$state] ?? $map['info'];
    return '<span class="badge ' . $class . '">' . e($label !== '' ? $label : $default) . '</span>';
}

/** Affiche une liste de verifications produite par il_check() */
function il_check_list(array $checks)
{
    echo '<ul class="check-list">';
    foreach ($checks as $check) {
        echo '<li>';
        echo '<span class="k">' . e($check['label']) . '</span>';
        echo '<span class="v">' . e($check['value']) . ' ' . il_badge($check['state']) . '</span>';
        if (!empty($check['hint'])) {
            echo '<span class="check-hint">' . e($check['hint']) . '</span>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Debut de page : en-tete HTML, barre superieure, navigation et
 * messages temporaires.
 */
function il_admin_page_start($title, $current = '')
{
    $admin = il_auth_user();
    $email = $admin['email'] ?? '';
    $messages = il_take_flash();

    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>InfoLeader - <?php echo e($title); ?></title>
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/admin.css'); ?>">
    <script src="assets/admin.js?v=<?php echo filemtime(__DIR__ . '/assets/admin.js'); ?>" defer></script>
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="admin-top">
        <div class="inner">
            <a class="admin-brand" href="dashboard.php">
                <span class="name">InfoLeader</span>
                <span class="tag">Administration</span>
            </a>
            <div class="session">
                <span>Connecte : <strong><?php echo e($email); ?></strong></span>
                <?php /* Le jeton protege la deconnexion d'une requete forgee */ ?>
                <a class="btn btn-ghost" style="color:#ffffff;border-color:rgba(255,255,255,.35)"
                   href="logout.php?token=<?php echo e(il_csrf_token()); ?>">Deconnexion</a>
            </div>
        </div>
    </header>

    <nav class="admin-nav" aria-label="Navigation du panneau">
        <ul>
            <?php foreach (il_admin_nav_items() as $key => $item): ?>
                <li>
                    <a href="<?php echo e($item['href']); ?>"
                       <?php echo $key === $current ? 'aria-current="page"' : ''; ?>>
                        <?php echo e($item['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li><a href="../index.php">Voir le site</a></li>
        </ul>
    </nav>

    <main class="admin-main" id="contenu">
    <?php

    il_render_messages($messages, true);
}

/** Fin de page */
function il_admin_page_end()
{
    ?>
    </main>

    <footer class="admin-foot">
        <p>
            &copy; <?php echo date('Y'); ?> InfoLeader - Panneau d'administration.
            <a href="../index.php">Retour au site</a>
        </p>
    </footer>
</body>
</html>
    <?php
}
