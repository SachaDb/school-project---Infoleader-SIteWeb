<?php
/**
 * bootstrap.php - Socle commun du panneau d'administration InfoLeader
 *
 * Chemins du projet, session securisee, utilitaires d'affichage,
 * acces aux fichiers de configuration/donnees, protection CSRF et
 * journalisation.
 *
 * Ce fichier vit dans includes/ qui est interdit en HTTP (includes/.htaccess).
 */

/* ==================================================================
   1. CHEMINS DU PROJET
   ================================================================== */

/** Racine du projet (dossier infoleader/) */
define('IL_ROOT', dirname(__DIR__));

/**
 * Dossier de stockage prive.
 * Peut etre deplace hors de la racine web via la variable d'environnement
 * INFOLEADER_STORAGE (SetEnv dans la configuration Apache).
 */
$ilStorageEnv = getenv('INFOLEADER_STORAGE');
define('IL_STORAGE', ($ilStorageEnv && is_dir($ilStorageEnv))
    ? rtrim($ilStorageEnv, "\\/")
    : IL_ROOT . '/storage');

define('IL_CONFIG_DIR', IL_STORAGE . '/config');
define('IL_LOG_DIR',    IL_STORAGE . '/logs');
define('IL_DATA_DIR',   IL_STORAGE . '/data');
define('IL_TMP_DIR',    IL_STORAGE . '/tmp');

/** Fichiers de configuration et de donnees */
define('IL_ADMIN_FILE',    IL_CONFIG_DIR . '/admin.php');
define('IL_MAIL_FILE',     IL_CONFIG_DIR . '/mail.php');
define('IL_AUTH_LOG',      IL_LOG_DIR . '/auth.json');
define('IL_MAIL_DEBUG_LOG', IL_LOG_DIR . '/mail-debug.log');
define('IL_RATELIMIT_FILE', IL_DATA_DIR . '/ratelimit.json');
define('IL_RESET_FILE',     IL_DATA_DIR . '/resets.json');
define('IL_CONTACT_FILE',   IL_DATA_DIR . '/contact.json');

/** Repertoires necessaires au fonctionnement du site */
function il_required_dirs()
{
    return [
        'storage'        => IL_STORAGE,
        'storage/config' => IL_CONFIG_DIR,
        'storage/logs'   => IL_LOG_DIR,
        'storage/data'   => IL_DATA_DIR,
        'storage/tmp'    => IL_TMP_DIR,
    ];
}

/* Creation des repertoires manquants (sans interrompre la page) */
foreach (il_required_dirs() as $ilDir) {
    if (!is_dir($ilDir)) {
        @mkdir($ilDir, 0775, true);
    }
}
unset($ilDir, $ilStorageEnv);

/* ==================================================================
   2. SESSION SECURISEE
   ================================================================== */

/** Vrai si la requete courante est servie en HTTPS */
function il_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

/**
 * Demarre la session applicative avec des parametres durcis :
 * cookie HttpOnly, SameSite=Lax, use_strict_mode, cookies uniquement.
 */
function il_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => il_is_https(),
        'samesite' => 'Lax',
    ]);

    session_name('INFOLEADERSESSID');
    session_start();
}

/* ==================================================================
   3. UTILITAIRES D'AFFICHAGE
   ================================================================== */

/** Echappement HTML de toute donnee affichee (protection XSS) */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Adresse IP du client, validee */
function il_client_ip()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Duree en secondes formatee en francais */
function il_human_duration($seconds)
{
    $seconds = (int) max(0, $seconds);
    if ($seconds < 60) {
        return $seconds . ' seconde' . ($seconds > 1 ? 's' : '');
    }
    if ($seconds < 3600) {
        $m = (int) ceil($seconds / 60);
        return $m . ' minute' . ($m > 1 ? 's' : '');
    }
    $h = (int) floor($seconds / 3600);
    $m = (int) round(($seconds % 3600) / 60);
    $out = $h . ' heure' . ($h > 1 ? 's' : '');
    if ($m > 0) {
        $out .= ' ' . $m . ' min';
    }
    return $out;
}

/** Taille en octets formatee */
function il_human_size($bytes)
{
    $bytes = (float) $bytes;
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (int) $bytes : number_format($bytes, 1, ',', ' ')) . ' ' . $units[$i];
}

/**
 * URL d'un fichier statique, suffixee par sa date de modification.
 *
 * Sans cela, le navigateur peut servir une ancienne version de
 * style.css ou script.js depuis son cache et afficher le nouveau HTML
 * avec l'ancienne mise en page.
 *
 * @param string $file chemin relatif a la racine du projet
 * @param string $base prefixe d'URL ('' depuis la racine, '../' depuis admin/)
 */
function il_asset($file, $base = '')
{
    $full = IL_ROOT . '/' . ltrim($file, '/');
    $version = is_file($full) ? filemtime($full) : time();
    return $base . $file . '?v=' . $version;
}

/** Horodatage lisible a partir d'un timestamp Unix */
function il_human_date($timestamp)
{
    return date('d/m/Y H:i:s', (int) $timestamp);
}

/* ==================================================================
   4. FICHIERS DE DONNEES ET DE CONFIGURATION
   ================================================================== */

/** Lit un fichier JSON ; retourne $default si absent ou invalide */
function il_read_json($path, $default = [])
{
    if (!is_file($path) || !is_readable($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** Ecrit un tableau en JSON avec verrou exclusif */
function il_write_json($path, array $data)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Lit un fichier de configuration PHP (format : <?php return [...];).
 *
 * L'extension .php est volontaire : meme si la protection .htaccess
 * tombait, une requete HTTP directe executerait le fichier sans
 * afficher le moindre secret.
 */
function il_read_php_config($path, $default = [])
{
    if (!is_file($path) || !is_readable($path)) {
        return $default;
    }
    $data = @include $path;
    return is_array($data) ? $data : $default;
}

/** Ecrit un fichier de configuration PHP depuis un tableau */
function il_write_php_config($path, array $data)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $code = "<?php\n"
        . "/* Fichier genere automatiquement - ne pas editer a la main. */\n"
        . "/* Peut contenir des secrets : ne jamais versionner (voir .gitignore). */\n"
        . 'return ' . var_export($data, true) . ";\n";

    $ok = file_put_contents($path, $code, LOCK_EX) !== false;

    /* Le cache d'opcode doit etre invalide, sinon la relecture
       dans la meme requete renverrait l'ancienne valeur. */
    if ($ok && function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return $ok;
}

/* ==================================================================
   5. COMPTE ADMINISTRATEUR
   ================================================================== */

/**
 * Retourne le compte administrateur, ou null si aucun compte n'existe.
 * Structure : email, password_hash, created_at, updated_at, auth_epoch
 */
function il_admin_get()
{
    $admin = il_read_php_config(IL_ADMIN_FILE, []);
    if (empty($admin['email']) || empty($admin['password_hash'])) {
        return null;
    }
    return $admin;
}

/** Vrai si le compte administrateur a deja ete initialise */
function il_admin_exists()
{
    return il_admin_get() !== null;
}

/** Enregistre (ou met a jour) le compte administrateur */
function il_admin_save(array $admin)
{
    $admin['updated_at'] = time();
    return il_write_php_config(IL_ADMIN_FILE, $admin);
}

/* ==================================================================
   6. PROTECTION CSRF
   ================================================================== */

/** Retourne le jeton CSRF de la session (le cree si besoin) */
function il_csrf_token()
{
    il_session_start();
    if (empty($_SESSION['il_csrf'])) {
        $_SESSION['il_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['il_csrf'];
}

/** Champ cache a inserer dans chaque formulaire */
function il_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(il_csrf_token()) . '">';
}

/** Verifie un jeton CSRF en temps constant */
function il_csrf_check($token)
{
    il_session_start();
    $expected = $_SESSION['il_csrf'] ?? '';
    return $expected !== ''
        && is_string($token)
        && hash_equals($expected, $token);
}

/**
 * Verifie le jeton CSRF du POST courant.
 * Interrompt la requete (419) si le jeton est absent ou invalide.
 */
function il_csrf_require()
{
    if (!il_csrf_check($_POST['csrf_token'] ?? '')) {
        il_log('security_csrf', 'Jeton CSRF invalide ou expire', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        /* 403 : code standard, correctement relaye par Apache
           (un code non standard comme 419 provoque une erreur 500). */
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
            . '<title>Requete refusee</title></head><body style="font-family:Segoe UI,Arial,sans-serif;'
            . 'padding:40px;color:#1e293b"><h1 style="color:#b91c1c;font-size:1.2rem">Requete refusee</h1>'
            . '<p>Votre session a expire ou la requete n\'a pas pu etre authentifiee. '
            . 'Revenez a la page precedente, rechargez-la et recommencez.</p></body></html>');
    }
}

/* ==================================================================
   7. JOURNALISATION
   ================================================================== */

/** Nombre maximal d'evenements conserves dans le journal */
define('IL_LOG_MAX', 2000);

/**
 * Categories de journal, utilisees par les filtres de la section
 * Maintenance > Journaux.
 */
function il_log_categories()
{
    return [
        'success'  => ['label' => 'Connexions reussies', 'types' => ['login_success']],
        'failure'  => ['label' => 'Echecs de connexion', 'types' => ['login_failed']],
        'block'    => ['label' => 'Blocages',            'types' => ['login_throttled', 'login_locked', 'reset_throttled']],
        'security' => ['label' => 'Evenements de securite', 'types' => ['security_csrf', 'security_denied', 'reset_requested', 'reset_code_invalid', 'reset_expired', 'logs_purged', 'tmp_purged', 'backup_exported', 'mail_test_ok', 'mail_test_failed']],
        'account'  => ['label' => 'Modifications du compte', 'types' => ['password_changed', 'email_changed', 'setup_completed', 'mail_config_saved', 'reset_success', 'logout']],
        'catalog'  => ['label' => 'Catalogue produits', 'types' => ['product_created', 'product_updated', 'product_deleted']],
    ];
}

/** Retourne la categorie d'un type d'evenement */
function il_log_category_of($type)
{
    foreach (il_log_categories() as $key => $cat) {
        if (in_array($type, $cat['types'], true)) {
            return $key;
        }
    }
    return 'security';
}

/**
 * Enregistre un evenement dans le journal de connexion.
 *
 * Ne jamais passer de secret (mot de passe, code de reinitialisation,
 * identifiants SMTP) dans $message ou $context.
 */
function il_log($type, $message, array $context = [])
{
    $events = il_read_json(IL_AUTH_LOG, []);

    $events[] = [
        'ts'       => time(),
        'date'     => date('c'),
        'type'     => $type,
        'category' => il_log_category_of($type),
        'ip'       => il_client_ip(),
        'message'  => $message,
        'context'  => $context,
    ];

    if (count($events) > IL_LOG_MAX) {
        $events = array_slice($events, -IL_LOG_MAX);
    }

    return il_write_json(IL_AUTH_LOG, $events);
}

/** Lit le journal, de l'evenement le plus recent au plus ancien */
function il_log_read($category = 'all', $limit = 200)
{
    $events = il_read_json(IL_AUTH_LOG, []);
    $events = array_reverse($events);

    if ($category !== 'all' && $category !== '') {
        $events = array_values(array_filter($events, function ($ev) use ($category) {
            return ($ev['category'] ?? '') === $category;
        }));
    }

    if ($limit > 0) {
        $events = array_slice($events, 0, $limit);
    }
    return $events;
}

/** Vide le journal de connexion */
function il_log_purge()
{
    return il_write_json(IL_AUTH_LOG, []);
}

/* ==================================================================
   8. MESSAGES TEMPORAIRES ET REDIRECTIONS
   ================================================================== */

/**
 * Enregistre un message a afficher apres redirection.
 * $type : success | error | info | warning
 */
function il_flash($type, $message)
{
    il_session_start();
    $_SESSION['il_flash'][] = ['type' => $type, 'message' => $message];
}

/** Recupere puis vide les messages temporaires */
function il_take_flash()
{
    il_session_start();
    $messages = $_SESSION['il_flash'] ?? [];
    unset($_SESSION['il_flash']);
    return is_array($messages) ? $messages : [];
}

/**
 * Affiche une liste de messages.
 *
 * $toast = true : messages flottants, qui disparaissent apres 5,5 s
 *                 (exigence : disparition en 5 a 6 secondes).
 * Les messages d'erreur, eux, restent affiches : une erreur de
 * validation doit pouvoir etre relue.
 */
function il_render_messages(array $messages, $toast = false)
{
    if (!$messages) {
        return;
    }

    if ($toast) {
        echo '<div class="toast-stack" aria-live="polite" aria-atomic="false">';
    }

    $classes = [
        'success' => 'msg-success',
        'error'   => 'msg-error',
        'warning' => 'msg-warning',
        'info'    => 'msg-info',
    ];

    foreach ($messages as $message) {
        $type  = $message['type'] ?? 'info';
        $class = $classes[$type] ?? 'msg-info';

        /* Seuls les messages de succes et d'information s'effacent seuls */
        $dismiss = ($toast && in_array($type, ['success', 'info'], true))
            ? ' data-autodismiss="5500"'
            : '';

        $role = $type === 'error' ? 'alert' : 'status';

        echo '<div class="msg ' . $class . '" role="' . $role . '"' . $dismiss . '>'
            . e($message['message']) . '</div>';
    }

    if ($toast) {
        echo '</div>';
    }
}

/** Redirige puis arrete le script */
function il_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** Repond en JSON et arrete le script */
function il_json_response(array $payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==================================================================
   9. CHARGEMENT DES AUTRES MODULES
   ================================================================== */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/products.php';
