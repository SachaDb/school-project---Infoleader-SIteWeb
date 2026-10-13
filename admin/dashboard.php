<?php
/**
 * dashboard.php - Tableau de bord du panneau d'administration
 *
 * Presente les quatre outils du panneau et les informations reelles
 * du compte administrateur connecte.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$admin = il_auth_user();

/* Donnees reelles du projet */
$logEvents   = il_read_json(IL_AUTH_LOG, []);
$contactCount = il_contact_count();
$tmp          = il_tmp_status();
$blocks       = il_rl_active_blocks();
$mailConfig   = il_mail_config();
$mailReady    = il_mail_is_configured();

/* Derniere connexion reussie precedant la session courante */
$lastLogins = array_values(array_filter($logEvents, function ($ev) {
    return ($ev['type'] ?? '') === 'login_success';
}));
$previousLogin = count($lastLogins) >= 2 ? $lastLogins[count($lastLogins) - 2] : null;

$failedRecent = count(array_filter($logEvents, function ($ev) {
    return ($ev['type'] ?? '') === 'login_failed'
        && (time() - (int) ($ev['ts'] ?? 0)) < 86400;
}));

$sections = il_sys_sections();
$overall  = il_sys_overall($sections);

il_admin_page_start('Tableau de bord', 'dashboard');
?>

<div class="page-head">
    <h1>Tableau de bord</h1>
    <p>
        Vue d'ensemble du site et acces aux quatre outils d'administration.
        Toutes les valeurs affichees proviennent des fichiers reels du projet.
    </p>
</div>

<div class="status-banner status-<?php echo e($overall['state']); ?>">
    <span class="dot" aria-hidden="true"></span>
    <div>
        <h2>Etat du site : <?php echo e($overall['label']); ?></h2>
        <p>
            <?php echo e($overall['detail']); ?> -
            <a href="maintenance.php#systeme">voir le detail dans Maintenance</a>
        </p>
    </div>
</div>

<!-- ====================== LES QUATRE OUTILS ====================== -->
<h2 class="sr-only">Outils</h2>
<div class="grid grid-4" style="margin-bottom:22px">

    <a class="tool" href="email-config.php">
        <span class="ico" aria-hidden="true">&#9993;</span>
        <h3>Configuration des e-mails</h3>
        <p>Mode d'envoi, serveur SMTP Brevo et e-mail de test reel.</p>
        <?php echo il_badge($mailReady ? 'ok' : 'warn', $mailReady ? 'Configure' : 'A completer'); ?>
    </a>

    <a class="tool" href="password.php">
        <span class="ico" aria-hidden="true">&#128273;</span>
        <h3>Mot de passe</h3>
        <p>Modifier le mot de passe administrateur (<?php echo (int) IL_PWD_MIN_LENGTH; ?> caracteres minimum).</p>
        <?php echo il_badge('info', 'Securite'); ?>
    </a>

    <a class="tool" href="user.php">
        <span class="ico" aria-hidden="true">&#128100;</span>
        <h3>Utilisateur</h3>
        <p>Modifier l'adresse e-mail du compte administrateur.</p>
        <?php echo il_badge('info', 'Compte'); ?>
    </a>

    <a class="tool" href="maintenance.php">
        <span class="ico" aria-hidden="true">&#9881;</span>
        <h3>Maintenance</h3>
        <p>Systeme, journaux, sauvegarde et purge des fichiers temporaires.</p>
        <?php echo il_badge($overall['state'], $overall['state'] === 'ok' ? 'OK' : $overall['label']); ?>
    </a>

</div>

<!-- ============ GESTION DU CONTENU (hors des quatre outils) ============ -->
<section class="card" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <span class="ico" aria-hidden="true"
          style="width:42px;height:42px;border-radius:10px;display:flex;align-items:center;
                 justify-content:center;font-size:1.2rem;color:#fff;
                 background:linear-gradient(135deg,var(--blue),var(--cyan))">&#128230;</span>
    <div style="flex:1 1 260px">
        <h2 style="margin-bottom:2px">Catalogue produits</h2>
        <p class="card-hint" style="margin:0">
            <?php echo (int) il_products_count(); ?> fiche(s) dans products.json :
            nom, prix, description et visuel.
        </p>
    </div>
    <a class="btn btn-primary" href="products.php">Gerer les produits</a>
</section>

<div class="grid grid-2">

    <!-- ====================== COMPTE CONNECTE ====================== -->
    <section class="card">
        <h2>Compte administrateur</h2>
        <p class="card-hint">Informations du compte actuellement connecte.</p>

        <ul class="check-list">
            <li>
                <span class="k">Adresse e-mail</span>
                <span class="v"><strong><?php echo e($admin['email']); ?></strong></span>
            </li>
            <li>
                <span class="k">Compte cree le</span>
                <span class="v"><?php echo e(isset($admin['created_at']) ? il_human_date($admin['created_at']) : 'inconnu'); ?></span>
            </li>
            <li>
                <span class="k">Derniere modification du compte</span>
                <span class="v"><?php echo e(isset($admin['updated_at']) ? il_human_date($admin['updated_at']) : 'inconnu'); ?></span>
            </li>
            <li>
                <span class="k">Session ouverte depuis</span>
                <span class="v"><?php echo e(il_human_date($_SESSION['il_login_at'] ?? time())); ?></span>
            </li>
            <li>
                <span class="k">Connexion precedente</span>
                <span class="v">
                    <?php echo e($previousLogin ? il_human_date($previousLogin['ts']) . ' (' . ($previousLogin['ip'] ?? '?') . ')' : 'premiere connexion'); ?>
                </span>
            </li>
            <li>
                <span class="k">Votre adresse IP</span>
                <span class="v mono"><?php echo e(il_client_ip()); ?></span>
            </li>
            <li>
                <span class="k">Expiration par inactivite</span>
                <span class="v"><?php echo e(il_human_duration(IL_SESSION_IDLE)); ?></span>
            </li>
        </ul>

        <hr class="card-sep">
        <div class="actions">
            <a class="btn btn-ghost" href="password.php">Changer le mot de passe</a>
            <a class="btn btn-ghost" href="user.php">Changer l'adresse e-mail</a>
        </div>
    </section>

    <!-- ====================== ETAT DU SITE ====================== -->
    <section class="card">
        <h2>Donnees du site</h2>
        <p class="card-hint">Compteurs lus directement dans storage/.</p>

        <ul class="check-list">
            <li>
                <span class="k">Evenements journalises</span>
                <span class="v"><?php echo count($logEvents); ?></span>
            </li>
            <li>
                <span class="k">Echecs de connexion (24 h)</span>
                <span class="v">
                    <?php echo (int) $failedRecent; ?>
                    <?php echo il_badge($failedRecent > 0 ? 'warn' : 'ok', $failedRecent > 0 ? 'A surveiller' : 'Aucun'); ?>
                </span>
            </li>
            <li>
                <span class="k">Blocages actifs</span>
                <span class="v">
                    <?php echo count($blocks); ?>
                    <?php echo il_badge(count($blocks) > 0 ? 'warn' : 'ok', count($blocks) > 0 ? 'En cours' : 'Aucun'); ?>
                </span>
            </li>
            <li>
                <span class="k">Messages de contact</span>
                <span class="v"><?php echo (int) $contactCount; ?></span>
            </li>
            <li>
                <span class="k">Produits au catalogue</span>
                <span class="v">
                    <?php echo (int) il_products_count(); ?>
                    <a href="products.php">gerer</a>
                </span>
            </li>
            <li>
                <span class="k">Fichiers temporaires</span>
                <span class="v">
                    <?php echo (int) $tmp['count']; ?>
                    <?php if ($tmp['count'] > 0): ?>
                        (<?php echo e(il_human_size($tmp['total_size'])); ?>)
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <span class="k">Mode d'envoi des e-mails</span>
                <span class="v">
                    <?php echo e($mailConfig['mode'] === 'smtp' ? 'SMTP (Brevo)' : 'PHP mail'); ?>
                    <?php echo il_badge($mailReady ? 'ok' : 'warn', $mailReady ? 'Pret' : 'Incomplet'); ?>
                </span>
            </li>
            <li>
                <span class="k">Version de PHP</span>
                <span class="v mono"><?php echo e(PHP_VERSION); ?></span>
            </li>
        </ul>

        <hr class="card-sep">
        <div class="actions">
            <a class="btn btn-ghost" href="maintenance.php#journaux">Consulter les journaux</a>
            <a class="btn btn-ghost" href="maintenance.php#sauvegarde">Exporter la configuration</a>
        </div>
    </section>

</div>

<?php il_admin_page_end(); ?>
