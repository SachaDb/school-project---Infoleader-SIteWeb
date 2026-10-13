<?php
/**
 * maintenance.php - Module « Maintenance »
 *
 * Quatre outils : Systeme, Journaux, Sauvegarde, Purger storage/tmp.
 * Toutes les operations destructrices passent par une confirmation et
 * sont protegees contre les requetes forgees (CSRF).
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$admin = il_auth_user();

/* ==================================================================
   Actions (traitees avant tout affichage : l'export envoie un fichier)
   ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'export') {
        $payload = il_backup_payload();
        $json    = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            il_flash('error', 'Export impossible : les donnees n\'ont pas pu etre encodees en JSON.');
            il_redirect('maintenance.php#sauvegarde');
        }

        il_log('backup_exported', 'Export JSON de la configuration genere', [
            'taille' => strlen($json) . ' octets',
        ]);

        $filename = 'infoleader-sauvegarde-' . date('Y-m-d-His') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-store');
        echo $json;
        exit;
    }

    if ($action === 'purge-logs') {
        $count = count(il_read_json(IL_AUTH_LOG, []));
        il_log_purge();
        il_log('logs_purged', 'Journal de connexion vide (' . $count . ' evenements supprimes)', [
            'par' => $admin['email'],
        ]);
        il_flash('success', $count . ' evenement(s) supprime(s) du journal de connexion.');
        il_redirect('maintenance.php#journaux');
    }

    if ($action === 'purge-contact') {
        $count = il_contact_count();
        il_contact_purge();
        il_log('logs_purged', 'Messages de contact supprimes (' . $count . ' message(s))', [
            'par' => $admin['email'],
        ]);
        il_flash('success', $count . ' message(s) de contact supprime(s).');
        il_redirect('maintenance.php#journaux');
    }

    if ($action === 'purge-tmp') {
        $result = il_tmp_purge();
        il_log('tmp_purged', 'Purge de storage/tmp : ' . $result['deleted'] . ' fichier(s) supprime(s)', [
            'echecs' => $result['failed'],
            'espace' => il_human_size($result['freed']),
        ]);

        if ($result['deleted'] === 0 && $result['failed'] === 0) {
            il_flash('info', 'Aucun fichier temporaire a supprimer.');
        } elseif ($result['failed'] > 0) {
            il_flash('warning', $result['deleted'] . ' fichier(s) supprime(s), '
                . $result['failed'] . ' non supprimable(s) (ignores par securite).');
        } else {
            il_flash('success', $result['deleted'] . ' fichier(s) supprime(s), '
                . il_human_size($result['freed']) . ' liberes.');
        }
        il_redirect('maintenance.php#tmp');
    }
}

/* ==================================================================
   Donnees affichees
   ================================================================== */
$sections   = il_sys_sections();
$overall    = il_sys_overall($sections);
$tmp        = il_tmp_status();
$blocks     = il_rl_active_blocks();
$categories = il_log_categories();

$filter = (string) ($_GET['cat'] ?? 'all');
if ($filter !== 'all' && !isset($categories[$filter])) {
    $filter = 'all';
}

$events    = il_log_read($filter, 150);
$allEvents = il_read_json(IL_AUTH_LOG, []);
$messages  = il_contact_all(100);

/* Compteurs par categorie, pour les filtres */
$counts = ['all' => count($allEvents)];
foreach ($categories as $key => $cat) {
    $counts[$key] = count(array_filter($allEvents, function ($ev) use ($key) {
        return ($ev['category'] ?? '') === $key;
    }));
}

il_admin_page_start('Maintenance', 'maintenance');
?>

<div class="page-head">
    <h1>Maintenance</h1>
    <p>Etat du site, journaux, sauvegarde et nettoyage des fichiers temporaires.
       Toutes les valeurs proviennent de controles reellement executes.</p>
</div>

<div class="status-banner status-<?php echo e($overall['state']); ?>">
    <span class="dot" aria-hidden="true"></span>
    <div>
        <h2>Etat global : <?php echo e($overall['label']); ?></h2>
        <p><?php echo e($overall['detail']); ?></p>
    </div>
</div>

<nav class="filters" aria-label="Sections de maintenance">
    <a href="#systeme">1. Systeme</a>
    <a href="#journaux">2. Journaux</a>
    <a href="#sauvegarde">3. Sauvegarde</a>
    <a href="#tmp">4. Purger storage/tmp</a>
</nav>

<!-- =================== A. SYSTEME =================== -->
<h2 id="systeme" style="color:var(--navy);margin:26px 0 14px">1. Systeme</h2>

<div class="grid grid-2">
    <?php foreach ($sections as $key => $section): ?>
        <section class="card">
            <h2><?php echo e($section['title']); ?></h2>
            <p class="card-hint">
                <?php
                $hints = [
                    'tmp'    => 'Repertoire storage/tmp : existence, droits et contenu reel.',
                    'env'    => 'Version de PHP, extensions et dependances controlables.',
                    'write'  => 'Droits d\'ecriture des repertoires necessaires.',
                    'config' => 'Presence, validite et protection des fichiers de configuration.',
                ];
                echo e($hints[$key] ?? '');
                ?>
            </p>
            <?php il_check_list($section['checks']); ?>

            <?php if ($key === 'tmp'): ?>
                <hr class="card-sep">
                <?php if (!$tmp['files']): ?>
                    <div class="empty">
                        <strong>Aucun fichier temporaire</strong>
                        Le repertoire storage/tmp est vide.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data">
                            <caption class="sr-only">Fichiers presents dans storage/tmp</caption>
                            <thead>
                                <tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Modifie le</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tmp['files'] as $file): ?>
                                    <tr>
                                        <td class="mono"><?php echo e($file['name']); ?></td>
                                        <td><?php echo e($file['type']); ?></td>
                                        <td><?php echo e($file['size'] > 0 ? il_human_size($file['size']) : '-'); ?></td>
                                        <td><?php echo e($file['modified'] ? il_human_date($file['modified']) : '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="field-hint">
                        Seul le nom des fichiers est affiche : les chemins systeme
                        complets ne sont pas exposes.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php if ($blocks): ?>
    <section class="card">
        <h2>Blocages en cours</h2>
        <p class="card-hint">Limitation des tentatives de connexion active.</p>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Portee</th><th>Valeur</th><th>Echecs</th><th>Fin du blocage</th></tr></thead>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <tr>
                            <td><?php echo e($block['scope']); ?></td>
                            <td class="mono"><?php echo e($block['value']); ?></td>
                            <td><?php echo (int) $block['fails']; ?></td>
                            <td><?php echo e(il_human_date($block['locked_until'])); ?>
                                (dans <?php echo e(il_human_duration($block['remaining'])); ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<!-- =================== B. JOURNAUX =================== -->
<h2 id="journaux" style="color:var(--navy);margin:26px 0 14px">2. Journaux</h2>

<section class="card">
    <h2>Journal de connexion</h2>
    <p class="card-hint">
        <?php echo (int) $counts['all']; ?> evenement(s) enregistre(s).
        Les filtres agissent sur les donnees affichees.
    </p>

    <nav class="filters" aria-label="Filtrer le journal">
        <a href="?cat=all#journaux" <?php echo $filter === 'all' ? 'aria-current="true"' : ''; ?>>
            Tous (<?php echo (int) $counts['all']; ?>)
        </a>
        <?php foreach ($categories as $key => $cat): ?>
            <a href="?cat=<?php echo e($key); ?>#journaux"
               <?php echo $filter === $key ? 'aria-current="true"' : ''; ?>>
                <?php echo e($cat['label']); ?> (<?php echo (int) $counts[$key]; ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$events): ?>
        <div class="empty">
            <strong>Aucun evenement dans cette categorie</strong>
            Changez de filtre ou attendez de nouveaux evenements.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <caption class="sr-only">Evenements du journal de connexion</caption>
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Categorie</th><th>Adresse IP</th><th>Detail</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td class="mono"><?php echo e(il_human_date($event['ts'] ?? 0)); ?></td>
                            <td class="mono"><?php echo e($event['type'] ?? ''); ?></td>
                            <td><?php echo e($categories[$event['category'] ?? '']['label'] ?? '-'); ?></td>
                            <td class="mono"><?php echo e($event['ip'] ?? ''); ?></td>
                            <td>
                                <?php echo e($event['message'] ?? ''); ?>
                                <?php if (!empty($event['context'])): ?>
                                    <br><span class="field-hint"><?php
                                        $parts = [];
                                        foreach ($event['context'] as $k => $v) {
                                            $parts[] = $k . ' : ' . (is_scalar($v) ? $v : json_encode($v));
                                        }
                                        echo e(implode(' | ', $parts));
                                    ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint">150 evenements les plus recents. Aucun secret n'est journalise.</p>
    <?php endif; ?>

    <hr class="card-sep">
    <form method="post" action="maintenance.php"
          data-confirm="Tous les evenements du journal de connexion vont etre definitivement supprimes."
          data-confirm-title="Vider le journal de connexion"
          data-confirm-label="Supprimer definitivement"
          data-confirm-scope="<strong>Donnees concernees :</strong><ul><li>Connexions reussies et echecs</li><li>Blocages et evenements de securite</li><li>Modifications du compte administrateur</li></ul>Les messages de contact ne sont pas touches. Cette action est irreversible.">
        <?php echo il_csrf_field(); ?>
        <input type="hidden" name="action" value="purge-logs">
        <button type="submit" class="btn btn-danger" data-loading
                <?php echo $counts['all'] ? '' : 'disabled'; ?>>
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label">Vider le journal de connexion</span>
        </button>
    </form>
</section>

<section class="card">
    <h2>Messages de contact</h2>
    <p class="card-hint">
        Messages recus via le formulaire de contact du site
        (<?php echo (int) il_contact_count(); ?> au total), stockes dans
        storage/data/contact.json.
    </p>

    <?php if (!$messages): ?>
        <div class="empty">
            <strong>Aucun message recu</strong>
            Les messages envoyes depuis contact.php apparaitront ici.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <caption class="sr-only">Messages recus via le formulaire de contact</caption>
                <thead>
                    <tr><th>Date</th><th>Nom</th><th>E-mail</th><th>Objet</th><th>Message</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td class="mono"><?php echo e(il_human_date($message['ts'] ?? 0)); ?></td>
                            <td><?php echo e($message['nom'] ?? ''); ?></td>
                            <td><a href="mailto:<?php echo e($message['email'] ?? ''); ?>"><?php echo e($message['email'] ?? ''); ?></a></td>
                            <td><?php echo e($message['objet'] ?? ''); ?></td>
                            <td class="msg-body"><?php echo e($message['message'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <hr class="card-sep">
    <form method="post" action="maintenance.php"
          data-confirm="Tous les messages de contact recus vont etre definitivement supprimes."
          data-confirm-title="Supprimer les messages de contact"
          data-confirm-label="Supprimer definitivement"
          data-confirm-scope="<strong>Donnees concernees :</strong><ul><li>Date, nom, adresse e-mail, objet et message</li></ul>Le journal de connexion n'est pas touche. Cette action est irreversible.">
        <?php echo il_csrf_field(); ?>
        <input type="hidden" name="action" value="purge-contact">
        <button type="submit" class="btn btn-danger" data-loading
                <?php echo il_contact_count() ? '' : 'disabled'; ?>>
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label">Supprimer les messages</span>
        </button>
    </form>
</section>

<!-- =================== C. SAUVEGARDE =================== -->
<h2 id="sauvegarde" style="color:var(--navy);margin:26px 0 14px">3. Sauvegarde</h2>

<section class="card">
    <h2>Export JSON de la configuration</h2>
    <p class="card-hint">
        Fichier genere cote serveur et telecharge directement. Les donnees
        restent locales : rien n'est transmis a un service tiers.
    </p>

    <div class="grid grid-2">
        <div>
            <p style="font-size:.92rem;margin-bottom:8px"><strong>Contenu de l'export</strong></p>
            <ul class="check-list">
                <li><span class="k">Configuration de l'application</span><span class="v"><?php echo il_badge('ok', 'Inclus'); ?></span></li>
                <li><span class="k">Compte administrateur (non sensible)</span><span class="v"><?php echo il_badge('ok', 'Inclus'); ?></span></li>
                <li><span class="k">Configuration des e-mails</span><span class="v"><?php echo il_badge('ok', 'Sans secret'); ?></span></li>
                <li><span class="k">Parametres de securite et de limitation</span><span class="v"><?php echo il_badge('ok', 'Inclus'); ?></span></li>
                <li><span class="k">Catalogue produits</span><span class="v"><?php echo il_badge('ok', 'Inclus'); ?></span></li>
            </ul>
        </div>
        <div>
            <p style="font-size:.92rem;margin-bottom:8px"><strong>Volontairement exclu</strong></p>
            <ul class="check-list">
                <?php foreach (il_backup_excluded_fields() as $field): ?>
                    <li><span class="k"><?php echo e($field); ?></span><span class="v"><?php echo il_badge('error', 'Retire'); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <hr class="card-sep">
    <form method="post" action="maintenance.php"
          data-confirm="Un fichier JSON contenant la configuration du site va etre genere et telecharge sur cet ordinateur."
          data-confirm-title="Exporter la configuration"
          data-confirm-label="Generer et telecharger"
          data-confirm-scope="<strong>A savoir :</strong><ul><li>Export local uniquement, aucun envoi vers un service tiers</li><li>Tous les secrets sont retires (hash du mot de passe, mot de passe SMTP, codes de reinitialisation)</li></ul>">
        <?php echo il_csrf_field(); ?>
        <input type="hidden" name="action" value="export">
        <button type="submit" class="btn btn-primary" data-loading data-busy-text="Generation...">
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label">Exporter en JSON</span>
        </button>
    </form>
</section>

<!-- =================== D. PURGER storage/tmp =================== -->
<h2 id="tmp" style="color:var(--navy);margin:26px 0 14px">4. Purger storage/tmp</h2>

<section class="card">
    <h2>Nettoyage des fichiers temporaires</h2>
    <p class="card-hint">
        <?php echo (int) $tmp['count']; ?> fichier(s) supprimable(s)
        <?php if ($tmp['count'] > 0): ?>
            pour <?php echo e(il_human_size($tmp['total_size'])); ?>
        <?php endif; ?>
        dans <span class="mono">storage/tmp</span>.
    </p>

    <ul class="check-list">
        <li><span class="k">Liste etablie</span><span class="v">cote serveur, aucun chemin transmis par le navigateur</span></li>
        <li><span class="k">Verification des chemins</span><span class="v mono">realpath() + parent = storage/tmp</span></li>
        <li><span class="k">Liens symboliques</span><span class="v">ignores</span></li>
        <li><span class="k">Sous-repertoires</span><span class="v">ignores (aucune suppression recursive)</span></li>
        <li><span class="k">Fichiers de protection</span><span class="v">index.php et .htaccess conserves</span></li>
    </ul>

    <hr class="card-sep">
    <form method="post" action="maintenance.php"
          data-confirm="Les fichiers temporaires de storage/tmp vont etre supprimes."
          data-confirm-title="Purger storage/tmp"
          data-confirm-label="Supprimer les fichiers"
          data-confirm-scope="<strong>Fichiers concernes :</strong><ul><?php
              foreach (array_slice($tmp['files'], 0, 12) as $file) {
                  if (!empty($file['removable'])) {
                      echo '<li>' . e($file['name']) . ' (' . e(il_human_size($file['size'])) . ')</li>';
                  }
              }
              if ($tmp['count'] > 12) {
                  echo '<li>et ' . ((int) $tmp['count'] - 12) . ' autre(s)</li>';
              }
              if ($tmp['count'] === 0) {
                  echo '<li>aucun fichier a supprimer</li>';
              }
          ?></ul>Cette action est irreversible.">
        <?php echo il_csrf_field(); ?>
        <input type="hidden" name="action" value="purge-tmp">
        <button type="submit" class="btn btn-danger" data-loading
                <?php echo $tmp['count'] ? '' : 'disabled'; ?>>
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label">Purger les fichiers temporaires</span>
        </button>
    </form>
</section>

<?php il_admin_page_end(); ?>
