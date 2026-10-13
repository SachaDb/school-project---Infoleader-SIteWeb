<?php
/**
 * security.php - Authentification, limitation des tentatives et
 *                reinitialisation du mot de passe.
 *
 * Charge par includes/bootstrap.php. Interdit en HTTP (includes/.htaccess).
 */

/* ==================================================================
   1. POLITIQUE DE MOT DE PASSE
   ================================================================== */

/** Longueur minimale imposee au mot de passe administrateur */
define('IL_PWD_MIN_LENGTH', 20);

/** Regles affichees dans l'interface avant validation */
function il_password_policy()
{
    return [
        'Au moins ' . IL_PWD_MIN_LENGTH . ' caracteres',
        'Au moins une lettre majuscule (A-Z)',
        'Au moins une lettre minuscule (a-z)',
        'Au moins un chiffre (0-9)',
        'Au moins un caractere special (!, ?, @, #, -, _, etc.)',
    ];
}

/**
 * Valide un mot de passe cote serveur.
 * Retourne un tableau (vide = valide) de messages d'erreur.
 */
function il_password_validate($password)
{
    $errors = [];
    $password = (string) $password;

    if (mb_strlen($password) < IL_PWD_MIN_LENGTH) {
        $errors[] = 'Le mot de passe doit contenir au moins ' . IL_PWD_MIN_LENGTH . ' caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une lettre minuscule.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un caractere special.';
    }
    if (mb_strlen($password) > 200) {
        $errors[] = 'Le mot de passe ne doit pas depasser 200 caracteres.';
    }

    return $errors;
}

/** Hachage du mot de passe (algorithme par defaut de PHP) */
function il_password_hash($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/* ==================================================================
   2. LIMITATION DES TENTATIVES (anti force brute)
   ==================================================================

   Le compteur est stocke cote serveur dans storage/data/ratelimit.json :
   actualiser la page, vider les cookies ou recreer une session ne le
   remet pas a zero.

   Deux cles sont suivies en parallele :
   - "ip:<adresse>"  : cible l'attaquant ;
   - "acct:<hash>"   : cible le compte vise (identifiant hache, afin de
                       ne pas stocker les saisies arbitraires en clair),
                       avec un seuil de blocage plus eleve pour ne pas
                       verrouiller facilement un utilisateur legitime.
   ================================================================== */

/** Delai impose avant la tentative suivante, selon le nombre d'echecs */
function il_rl_delay_for($fails)
{
    $schedule = [1 => 3, 2 => 6, 3 => 12, 4 => 24, 5 => 60];
    if (isset($schedule[$fails])) {
        return $schedule[$fails];
    }
    return $fails >= 1 ? 60 : 0;
}

/** Duree de blocage temporaire, selon le nombre d'echecs */
function il_rl_lock_for($fails)
{
    $schedule = [
        6  => 15 * 60,
        7  => 30 * 60,
        8  => 60 * 60,
        9  => 2 * 3600,
        10 => 4 * 3600,
    ];
    if (isset($schedule[$fails])) {
        return $schedule[$fails];
    }
    return $fails > 10 ? 8 * 3600 : 0;
}

/** Seuil d'echecs declenchant un blocage, par type de cle */
function il_rl_lock_threshold($keyType)
{
    /* Le compte est plus tolerant que l'IP : un utilisateur legitime
       ne doit pas etre verrouille par les echecs d'un tiers. */
    return $keyType === 'acct' ? 10 : 6;
}

/** Les compteurs retombent a zero apres cette duree sans echec */
define('IL_RL_DECAY', 24 * 3600);

/** Cles de limitation pour un identifiant soumis */
function il_rl_keys($identifier)
{
    $keys = ['ip:' . il_client_ip()];
    $identifier = trim((string) $identifier);
    if ($identifier !== '') {
        $keys[] = 'acct:' . hash('sha256', mb_strtolower($identifier));
    }
    return $keys;
}

/** Charge le fichier de limitation en purgeant les entrees expirees */
function il_rl_load()
{
    $store = il_read_json(IL_RATELIMIT_FILE, []);
    $now   = time();
    $clean = [];

    foreach ($store as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $last        = (int) ($entry['last'] ?? 0);
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);

        /* On conserve l'entree si elle est encore bloquee ou recente */
        if ($lockedUntil > $now || ($now - $last) < IL_RL_DECAY) {
            $clean[$key] = $entry;
        }
    }
    return $clean;
}

/**
 * Etat de la limitation pour un identifiant.
 *
 * Retourne :
 *   allowed => bool    une tentative est-elle autorisee maintenant
 *   wait    => int     secondes restantes a attendre
 *   reason  => string  'lock' (blocage temporaire) | 'delay' | ''
 *   fails   => int     nombre d'echecs cumules (max des deux cles)
 */
function il_rl_status($identifier)
{
    $store = il_rl_load();
    $now   = time();

    $wait   = 0;
    $reason = '';
    $fails  = 0;

    foreach (il_rl_keys($identifier) as $key) {
        $entry = $store[$key] ?? null;
        if (!$entry) {
            continue;
        }

        $entryFails  = (int) ($entry['fails'] ?? 0);
        $last        = (int) ($entry['last'] ?? 0);
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        $fails       = max($fails, $entryFails);

        /* Blocage temporaire en cours */
        if ($lockedUntil > $now && ($lockedUntil - $now) > $wait) {
            $wait   = $lockedUntil - $now;
            $reason = 'lock';
        }

        /* Delai progressif entre deux tentatives */
        if ($lockedUntil <= $now && $entryFails > 0) {
            $required = il_rl_delay_for($entryFails);
            $elapsed  = $now - $last;
            if ($elapsed < $required && ($required - $elapsed) > $wait) {
                $wait   = $required - $elapsed;
                $reason = $reason === 'lock' ? 'lock' : 'delay';
            }
        }
    }

    return [
        'allowed' => $wait <= 0,
        'wait'    => $wait,
        'reason'  => $reason,
        'fails'   => $fails,
    ];
}

/**
 * Enregistre un echec et retourne l'etat resultant.
 * Journalise le passage en blocage temporaire.
 */
function il_rl_fail($identifier)
{
    $store = il_rl_load();
    $now   = time();

    foreach (il_rl_keys($identifier) as $key) {
        $type  = explode(':', $key, 2)[0];
        $entry = $store[$key] ?? ['fails' => 0, 'first' => $now, 'last' => 0, 'locked_until' => 0];

        $entry['fails'] = (int) ($entry['fails'] ?? 0) + 1;
        $entry['last']  = $now;
        if (empty($entry['first'])) {
            $entry['first'] = $now;
        }

        /* Au-dela du seuil, blocage temporaire progressif */
        if ($entry['fails'] >= il_rl_lock_threshold($type)) {
            $lock = il_rl_lock_for($entry['fails']);
            if ($lock > 0) {
                $entry['locked_until'] = $now + $lock;
                il_log('login_locked', 'Blocage temporaire apres ' . $entry['fails']
                    . ' echecs (' . il_human_duration($lock) . ')', ['scope' => $type]);
            }
        }

        $store[$key] = $entry;
    }

    il_write_json(IL_RATELIMIT_FILE, $store);
    return il_rl_status($identifier);
}

/** Remet a zero les compteurs apres une authentification reussie */
function il_rl_reset($identifier)
{
    $store = il_rl_load();
    foreach (il_rl_keys($identifier) as $key) {
        unset($store[$key]);
    }
    return il_write_json(IL_RATELIMIT_FILE, $store);
}

/** Entrees actuellement bloquees (pour la section Maintenance) */
function il_rl_active_blocks()
{
    $now = time();
    $out = [];
    foreach (il_rl_load() as $key => $entry) {
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            $parts = array_pad(explode(':', $key, 2), 2, '');
            $out[] = [
                'scope'        => $parts[0] === 'ip' ? 'Adresse IP' : 'Compte',
                'value'        => $parts[0] === 'ip' ? $parts[1] : 'identifiant masque',
                'fails'        => (int) ($entry['fails'] ?? 0),
                'locked_until' => $lockedUntil,
                'remaining'    => $lockedUntil - $now,
            ];
        }
    }
    return $out;
}

/* ==================================================================
   3. AUTHENTIFICATION
   ================================================================== */

/** Duree d'inactivite au bout de laquelle la session expire */
define('IL_SESSION_IDLE', 30 * 60);

/**
 * Tente une authentification.
 *
 * Retourne :
 *   ok    => bool
 *   error => string  message generique destine a l'utilisateur
 *   wait  => int     secondes a attendre si la limitation s'applique
 */
function il_auth_attempt($identifier, $password)
{
    $identifier = trim((string) $identifier);

    /* 1. La limitation est verifiee avant toute comparaison */
    $status = il_rl_status($identifier);
    if (!$status['allowed']) {
        $type = $status['reason'] === 'lock' ? 'login_locked' : 'login_throttled';
        il_log($type, 'Tentative refusee par la limitation ('
            . il_human_duration($status['wait']) . ' restantes)');

        $message = $status['reason'] === 'lock'
            ? 'Trop de tentatives. Acces temporairement bloque. Reessayez dans '
                . il_human_duration($status['wait']) . '.'
            : 'Veuillez patienter ' . il_human_duration($status['wait'])
                . ' avant une nouvelle tentative.';

        return ['ok' => false, 'error' => $message, 'wait' => $status['wait']];
    }

    $admin = il_admin_get();

    /* 2. Comparaison a temps sensiblement constant : meme sans compte
          correspondant, on execute un password_verify factice afin de
          ne pas reveler l'existence du compte par le temps de reponse. */
    $hash = $admin['password_hash']
        ?? '$2y$10$usesomesillystringfoeahhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh';

    $emailOk = $admin !== null
        && hash_equals(mb_strtolower($admin['email']), mb_strtolower($identifier));
    $pwdOk = password_verify((string) $password, $hash);

    if (!$emailOk || !$pwdOk) {
        $after = il_rl_fail($identifier);

        /* Message volontairement identique dans les deux cas : il ne
           revele pas si c'est l'adresse ou le mot de passe qui est faux. */
        $message = 'Identifiants incorrects.';
        if ($after['wait'] > 0) {
            $message .= ' Nouvelle tentative possible dans '
                . il_human_duration($after['wait']) . '.';
        }

        il_log('login_failed', 'Echec de connexion (tentative ' . $after['fails'] . ')');
        return ['ok' => false, 'error' => $message, 'wait' => $after['wait']];
    }

    /* 3. Succes : session securisee */
    il_rl_reset($identifier);
    il_auth_establish_session($admin);
    il_log('login_success', 'Connexion reussie', ['email' => $admin['email']]);

    return ['ok' => true, 'error' => '', 'wait' => 0];
}

/** Ouvre une session authentifiee pour le compte fourni */
function il_auth_establish_session(array $admin)
{
    il_session_start();

    /* Regeneration de l'identifiant de session (anti fixation de session) */
    session_regenerate_id(true);

    $_SESSION['il_admin']     = true;
    $_SESSION['il_email']     = $admin['email'];
    $_SESSION['il_epoch']     = (int) ($admin['auth_epoch'] ?? 0);
    $_SESSION['il_login_at']  = time();
    $_SESSION['il_last_seen'] = time();
    $_SESSION['il_ua']        = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    /* Nouveau jeton CSRF pour la session authentifiee */
    unset($_SESSION['il_csrf']);
    il_csrf_token();
}

/**
 * Vrai si la requete courante est authentifiee.
 * Verifie aussi l'expiration par inactivite, le changement de
 * navigateur et l'invalidation globale des sessions (auth_epoch).
 */
function il_auth_check()
{
    il_session_start();

    if (empty($_SESSION['il_admin'])) {
        return false;
    }

    $admin = il_admin_get();
    if (!$admin) {
        il_auth_logout(false);
        return false;
    }

    /* Invalidation des sessions anterieures (changement de mot de passe) */
    if ((int) ($_SESSION['il_epoch'] ?? -1) !== (int) ($admin['auth_epoch'] ?? 0)) {
        il_auth_logout(false);
        return false;
    }

    /* Expiration par inactivite */
    if (time() - (int) ($_SESSION['il_last_seen'] ?? 0) > IL_SESSION_IDLE) {
        il_auth_logout(false);
        return false;
    }

    /* Le cookie de session ne doit pas changer de navigateur */
    if (!hash_equals(
        (string) ($_SESSION['il_ua'] ?? ''),
        hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')
    )) {
        il_auth_logout(false);
        return false;
    }

    $_SESSION['il_last_seen'] = time();
    return true;
}

/**
 * Exige une session authentifiee.
 * Redirige vers la page de connexion sinon.
 */
function il_auth_require()
{
    if (il_auth_check()) {
        return;
    }

    il_log('security_denied', 'Acces refuse a une page protegee', [
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    il_flash('error', 'Veuillez vous connecter pour acceder au panneau d\'administration.');
    il_redirect('login.php');
}

/** Compte connecte, ou null */
function il_auth_user()
{
    if (!il_auth_check()) {
        return null;
    }
    return il_admin_get();
}

/** Ferme la session (et journalise si demande) */
function il_auth_logout($log = true)
{
    il_session_start();

    if ($log && !empty($_SESSION['il_admin'])) {
        il_log('logout', 'Deconnexion', ['email' => $_SESSION['il_email'] ?? '']);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'httponly' => true,
            'secure'   => il_is_https(),
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * Invalide toutes les sessions existantes en incrementant auth_epoch.
 * Utilise apres un changement de mot de passe.
 */
function il_auth_invalidate_sessions(array $admin)
{
    $admin['auth_epoch'] = (int) ($admin['auth_epoch'] ?? 0) + 1;
    il_admin_save($admin);
    return $admin;
}

/* ==================================================================
   4. REINITIALISATION DU MOT DE PASSE (code a 8 chiffres)
   ================================================================== */

/** Duree de validite du code */
define('IL_RESET_TTL', 300);          /* 5 minutes */
/** Nombre maximal de verifications d'un meme code */
define('IL_RESET_MAX_ATTEMPTS', 5);
/** Nombre maximal de demandes dans la fenetre ci-dessous */
define('IL_RESET_MAX_REQUESTS', 3);
define('IL_RESET_WINDOW', 15 * 60);

/** Genere un code de 8 chiffres cryptographiquement aleatoire */
function il_reset_generate_code()
{
    return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

/** Etat courant de la demande de reinitialisation */
function il_reset_load()
{
    return il_read_json(IL_RESET_FILE, []);
}

/**
 * Cree un code de reinitialisation.
 *
 * Retourne :
 *   ok        => bool
 *   code      => string|null  code en clair, a transmettre par e-mail
 *                             uniquement (jamais stocke, jamais journalise)
 *   throttled => bool
 *   wait      => int
 */
function il_reset_request($email)
{
    $state = il_reset_load();
    $now   = time();

    /* Limitation des demandes repetees */
    $requests = array_values(array_filter(
        (array) ($state['requests'] ?? []),
        function ($ts) use ($now) {
            return ($now - (int) $ts) < IL_RESET_WINDOW;
        }
    ));

    if (count($requests) >= IL_RESET_MAX_REQUESTS) {
        $oldest = min($requests);
        $wait   = IL_RESET_WINDOW - ($now - (int) $oldest);
        il_log('reset_throttled', 'Demandes de reinitialisation trop nombreuses');
        return ['ok' => false, 'code' => null, 'throttled' => true, 'wait' => max(1, $wait)];
    }

    $code = il_reset_generate_code();
    $requests[] = $now;

    /* Le code est stocke hache : un acces au fichier ne permet pas de
       le rejouer. Tout code precedent est remplace, donc invalide. */
    il_write_json(IL_RESET_FILE, [
        'email'      => $email,
        'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
        'created_at' => $now,
        'expires_at' => $now + IL_RESET_TTL,
        'used'       => false,
        'attempts'   => 0,
        'requests'   => $requests,
    ]);

    il_log('reset_requested', 'Code de reinitialisation genere (valable '
        . il_human_duration(IL_RESET_TTL) . ')');

    return ['ok' => true, 'code' => $code, 'throttled' => false, 'wait' => 0];
}

/**
 * Verifie un code de reinitialisation.
 * Retourne : ok => bool, error => string
 */
function il_reset_verify($code)
{
    $state = il_reset_load();
    $now   = time();
    $code  = preg_replace('/\D/', '', (string) $code);

    if (empty($state['code_hash'])) {
        return ['ok' => false, 'error' => 'Aucune demande de reinitialisation en cours. Demandez un nouveau code.'];
    }
    if (!empty($state['used'])) {
        il_log('reset_code_invalid', 'Code deja utilise');
        return ['ok' => false, 'error' => 'Ce code a deja ete utilise. Demandez un nouveau code.'];
    }
    if ($now > (int) ($state['expires_at'] ?? 0)) {
        il_log('reset_expired', 'Code expire');
        return ['ok' => false, 'error' => 'Ce code a expire (validite 5 minutes). Demandez un nouveau code.'];
    }
    if ((int) ($state['attempts'] ?? 0) >= IL_RESET_MAX_ATTEMPTS) {
        il_log('reset_code_invalid', 'Trop de verifications du code');
        return ['ok' => false, 'error' => 'Trop d\'essais sur ce code. Demandez un nouveau code.'];
    }
    if (strlen($code) !== 8) {
        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        il_write_json(IL_RESET_FILE, $state);
        return ['ok' => false, 'error' => 'Le code doit comporter exactement 8 chiffres.'];
    }

    if (!password_verify($code, $state['code_hash'])) {
        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        il_write_json(IL_RESET_FILE, $state);
        il_log('reset_code_invalid', 'Code incorrect (essai '
            . $state['attempts'] . '/' . IL_RESET_MAX_ATTEMPTS . ')');

        $left = IL_RESET_MAX_ATTEMPTS - (int) $state['attempts'];
        return [
            'ok'    => false,
            'error' => 'Code incorrect.' . ($left > 0 ? ' Essais restants : ' . $left . '.' : ''),
        ];
    }

    return ['ok' => true, 'error' => ''];
}

/** Marque le code comme utilise (usage unique) */
function il_reset_consume()
{
    $state = il_reset_load();
    $state['used']      = true;
    $state['used_at']   = time();
    $state['code_hash'] = '';   /* le code n'est plus verifiable */
    return il_write_json(IL_RESET_FILE, $state);
}

/** Supprime toute demande en cours */
function il_reset_clear()
{
    return il_write_json(IL_RESET_FILE, []);
}
