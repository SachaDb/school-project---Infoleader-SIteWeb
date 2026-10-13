<?php
/**
 * store.php - Messages de contact, verifications systeme et export JSON
 *
 * Charge par includes/bootstrap.php. Interdit en HTTP (includes/.htaccess).
 */

/* ==================================================================
   1. MESSAGES DE CONTACT
   ==================================================================

   Le formulaire de contact du site vitrine n'enregistrait rien
   (simulation d'envoi). Les messages sont desormais stockes dans
   storage/data/contact.json et consultables depuis la section
   Maintenance > Journaux > Messages de contact.
   ================================================================== */

/** Nombre maximal de messages conserves */
define('IL_CONTACT_MAX', 1000);

/** Longueurs maximales acceptees */
define('IL_CONTACT_MAX_NAME', 120);
define('IL_CONTACT_MAX_SUBJECT', 150);
define('IL_CONTACT_MAX_MESSAGE', 5000);

/**
 * Valide un message de contact cote serveur.
 * Retourne un tableau champ => message d'erreur (vide = valide).
 */
function il_contact_validate(array $input)
{
    $errors = [];

    $nom = trim((string) ($input['nom'] ?? ''));
    if ($nom === '') {
        $errors['nom'] = 'Le nom est requis.';
    } elseif (mb_strlen($nom) > IL_CONTACT_MAX_NAME) {
        $errors['nom'] = 'Le nom ne doit pas depasser ' . IL_CONTACT_MAX_NAME . ' caracteres.';
    }

    $email = trim((string) ($input['email'] ?? ''));
    if ($email === '') {
        $errors['email'] = 'L\'adresse e-mail est requise.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Veuillez saisir une adresse e-mail valide.';
    }

    $objet = trim((string) ($input['objet'] ?? ''));
    if (mb_strlen($objet) > IL_CONTACT_MAX_SUBJECT) {
        $errors['objet'] = 'L\'objet ne doit pas depasser ' . IL_CONTACT_MAX_SUBJECT . ' caracteres.';
    }

    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') {
        $errors['message'] = 'Le message est requis.';
    } elseif (mb_strlen($message) > IL_CONTACT_MAX_MESSAGE) {
        $errors['message'] = 'Le message ne doit pas depasser ' . IL_CONTACT_MAX_MESSAGE . ' caracteres.';
    }

    return $errors;
}

/**
 * Enregistre un message de contact valide.
 * Retourne le message stocke, ou null en cas d'echec d'ecriture.
 */
function il_contact_save(array $input)
{
    $messages = il_read_json(IL_CONTACT_FILE, []);

    $objet = trim((string) ($input['objet'] ?? ''));
    $entry = [
        'id'      => bin2hex(random_bytes(6)),
        'ts'      => time(),
        'date'    => date('c'),
        'nom'     => trim((string) $input['nom']),
        'email'   => trim((string) $input['email']),
        'objet'   => $objet !== '' ? $objet : 'Sans objet',
        'message' => trim((string) $input['message']),
        'ip'      => il_client_ip(),
    ];

    $messages[] = $entry;
    if (count($messages) > IL_CONTACT_MAX) {
        $messages = array_slice($messages, -IL_CONTACT_MAX);
    }

    return il_write_json(IL_CONTACT_FILE, $messages) ? $entry : null;
}

/** Messages de contact, du plus recent au plus ancien */
function il_contact_all($limit = 0)
{
    $messages = array_reverse(il_read_json(IL_CONTACT_FILE, []));
    if ($limit > 0) {
        $messages = array_slice($messages, 0, $limit);
    }
    return $messages;
}

/** Nombre de messages stockes */
function il_contact_count()
{
    return count(il_read_json(IL_CONTACT_FILE, []));
}

/** Supprime tous les messages de contact */
function il_contact_purge()
{
    return il_write_json(IL_CONTACT_FILE, []);
}

/* ==================================================================
   2. FICHIERS TEMPORAIRES (storage/tmp)
   ================================================================== */

/** Fichiers de protection a ne jamais lister ni supprimer */
function il_tmp_protected()
{
    return ['index.php', '.htaccess', '.gitignore'];
}

/**
 * Etat du repertoire des fichiers temporaires.
 *
 * Retourne : exists, writable, path_label, files (nom, taille, date),
 *            total_size, count
 */
function il_tmp_status()
{
    $status = [
        'exists'     => is_dir(IL_TMP_DIR),
        'writable'   => is_dir(IL_TMP_DIR) && is_writable(IL_TMP_DIR),
        'path_label' => 'storage/tmp',   /* jamais le chemin systeme complet */
        'files'      => [],
        'total_size' => 0,
        'count'      => 0,
    ];

    if (!$status['exists']) {
        return $status;
    }

    $entries = @scandir(IL_TMP_DIR);
    if ($entries === false) {
        $status['readable'] = false;
        return $status;
    }
    $status['readable'] = true;

    foreach ($entries as $name) {
        if ($name === '.' || $name === '..' || in_array($name, il_tmp_protected(), true)) {
            continue;
        }
        $full = IL_TMP_DIR . DIRECTORY_SEPARATOR . $name;

        /* Les liens symboliques ne sont ni suivis ni proposes a la suppression */
        if (is_link($full)) {
            $status['files'][] = [
                'name'     => $name,
                'size'     => 0,
                'modified' => @filemtime($full) ?: 0,
                'type'     => 'lien symbolique (ignore)',
                'removable' => false,
            ];
            continue;
        }

        if (is_dir($full)) {
            $status['files'][] = [
                'name'      => $name,
                'size'      => 0,
                'modified'  => @filemtime($full) ?: 0,
                'type'      => 'repertoire (ignore)',
                'removable' => false,
            ];
            continue;
        }

        $size = @filesize($full) ?: 0;
        $status['files'][] = [
            'name'      => $name,
            'size'      => $size,
            'modified'  => @filemtime($full) ?: 0,
            'type'      => 'fichier',
            'removable' => true,
        ];
        $status['total_size'] += $size;
        $status['count']++;
    }

    /* Du plus recent au plus ancien */
    usort($status['files'], function ($a, $b) {
        return $b['modified'] <=> $a['modified'];
    });

    return $status;
}

/**
 * Supprime les fichiers temporaires de storage/tmp.
 *
 * Securite :
 * - la liste est etablie cote serveur (aucun chemin venant du navigateur) ;
 * - chaque chemin est verifie par realpath() et doit etre un enfant direct
 *   de storage/tmp ;
 * - les liens symboliques et les repertoires ne sont pas touches ;
 * - aucune suppression recursive.
 *
 * Retourne : deleted, failed, freed
 */
function il_tmp_purge()
{
    $result = ['deleted' => 0, 'failed' => 0, 'freed' => 0];

    $base = realpath(IL_TMP_DIR);
    if ($base === false) {
        return $result;
    }

    $status = il_tmp_status();
    foreach ($status['files'] as $file) {
        if (empty($file['removable'])) {
            continue;
        }

        $full = $base . DIRECTORY_SEPARATOR . $file['name'];
        $real = realpath($full);

        /* Le fichier doit se trouver directement dans storage/tmp */
        if ($real === false
            || is_link($full)
            || !is_file($real)
            || dirname($real) !== $base
        ) {
            $result['failed']++;
            continue;
        }

        $size = @filesize($real) ?: 0;
        if (@unlink($real)) {
            $result['deleted']++;
            $result['freed'] += $size;
        } else {
            $result['failed']++;
        }
    }

    return $result;
}

/* ==================================================================
   3. VERIFICATIONS SYSTEME
   ==================================================================
   Chaque verification retourne :
     label  => intitule
     value  => valeur affichable
     state  => 'ok' | 'warn' | 'error' | 'info'
     hint   => conseil eventuel
   ================================================================== */

/** Construit une ligne de verification */
function il_check($label, $value, $state = 'info', $hint = '')
{
    return ['label' => $label, 'value' => $value, 'state' => $state, 'hint' => $hint];
}

/** Environnement technique (PHP, extensions, dependances) */
function il_sys_environment()
{
    $checks = [];

    $checks[] = il_check('Version de PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'warn');
    $checks[] = il_check('Serveur', $_SERVER['SERVER_SOFTWARE'] ?? 'inconnu', 'info');

    foreach (['openssl' => true, 'mbstring' => true, 'json' => true, 'fileinfo' => false, 'curl' => false] as $ext => $required) {
        $loaded = extension_loaded($ext);
        $checks[] = il_check(
            'Extension ' . $ext,
            $loaded ? 'activee' : 'desactivee',
            $loaded ? 'ok' : ($required ? 'error' : 'warn'),
            $loaded ? '' : 'Activez extension=' . $ext . ' dans le php.ini utilise par Apache.'
        );
    }

    $mailer = il_mailer_available();
    $version = $mailer && defined('PHPMailer\\PHPMailer\\PHPMailer::VERSION')
        ? constant('PHPMailer\\PHPMailer\\PHPMailer::VERSION')
        : '';
    $checks[] = il_check(
        'PHPMailer',
        $mailer ? 'charge' . ($version !== '' ? ' (v' . $version . ')' : '') : 'absent',
        $mailer ? 'ok' : 'error',
        $mailer ? '' : 'Executez : composer require phpmailer/phpmailer'
    );

    /* Mode d'envoi des e-mails reellement utilisable */
    $mailConfig = il_mail_config();
    if ($mailConfig['mode'] === 'mail') {
        $available = il_php_mail_available();
        $checks[] = il_check(
            'Mode d\'envoi',
            'PHP mail' . ($available ? '' : ' (indisponible sur cet hote)'),
            $available ? 'ok' : 'warn',
            $available ? '' : 'Aucun serveur de messagerie n\'est configure dans le php.ini. Preferez le mode Brevo SMTP.'
        );
    } else {
        $configured = il_mail_is_configured();
        $checks[] = il_check(
            'Mode d\'envoi',
            'SMTP ' . $mailConfig['host'] . ':' . $mailConfig['port']
                . ' (' . ($mailConfig['encryption'] === 'tls' ? 'STARTTLS' : ($mailConfig['encryption'] === 'ssl' ? 'SSL' : 'non chiffre')) . ')',
            $configured ? 'ok' : 'warn',
            $configured ? '' : 'Configuration des e-mails incomplete.'
        );

        /* Accessibilite reelle du port SMTP sortant */
        $reachable = il_sys_tcp_probe($mailConfig['host'], $mailConfig['port'], 4);
        $checks[] = il_check(
            'Acces sortant SMTP',
            $reachable['ok']
                ? 'port ' . $mailConfig['port'] . ' joignable'
                : 'port ' . $mailConfig['port'] . ' injoignable',
            $reachable['ok'] ? 'ok' : 'error',
            $reachable['ok'] ? '' : 'Verifiez la connexion reseau ou le pare-feu : ' . $reachable['error']
        );
    }

    return $checks;
}

/** Test d'ouverture d'un port TCP */
function il_sys_tcp_probe($host, $port, $timeout = 4)
{
    if ($host === '' || (int) $port <= 0) {
        return ['ok' => false, 'error' => 'hote ou port non renseigne'];
    }
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, (int) $port, $errno, $errstr, $timeout);
    if ($socket) {
        fclose($socket);
        return ['ok' => true, 'error' => ''];
    }
    return ['ok' => false, 'error' => $errstr !== '' ? $errstr : 'connexion refusee'];
}

/** Droits d'ecriture des repertoires necessaires */
function il_sys_writable()
{
    $checks = [];
    foreach (il_required_dirs() as $label => $dir) {
        if (!is_dir($dir)) {
            $checks[] = il_check($label, 'repertoire absent', 'error', 'Le repertoire sera recree au prochain chargement.');
            continue;
        }
        $writable = is_writable($dir);
        $checks[] = il_check(
            $label,
            $writable ? 'accessible en ecriture' : 'lecture seule',
            $writable ? 'ok' : 'error',
            $writable ? '' : 'Donnez les droits d\'ecriture a ce repertoire.'
        );
    }
    return $checks;
}

/** Presence, validite et protection des fichiers de configuration */
function il_sys_config_files()
{
    $checks = [];

    /* Compte administrateur */
    $admin = il_admin_get();
    $checks[] = il_check(
        'storage/config/admin.php',
        $admin ? 'present et valide' : 'absent ou incomplet',
        $admin ? 'ok' : 'error',
        $admin ? '' : 'Executez admin/setup.php pour creer le compte administrateur.'
    );

    /* Configuration e-mail */
    $mailExists = is_file(IL_MAIL_FILE);
    $mailValid  = $mailExists && is_array(il_read_php_config(IL_MAIL_FILE, null));
    $configured = il_mail_is_configured();
    $checks[] = il_check(
        'storage/config/mail.php',
        !$mailExists ? 'absent' : ($mailValid ? ($configured ? 'present et complet' : 'present mais incomplet') : 'illisible'),
        !$mailExists ? 'warn' : ($mailValid ? ($configured ? 'ok' : 'warn') : 'error'),
        $configured ? '' : 'Renseignez la configuration dans Configuration des e-mails.'
    );

    /* Dependances Composer */
    $checks[] = il_check(
        'vendor/autoload.php',
        is_file(IL_AUTOLOAD) ? 'present' : 'absent',
        is_file(IL_AUTOLOAD) ? 'ok' : 'error',
        is_file(IL_AUTOLOAD) ? '' : 'Executez : php composer.phar install'
    );

    /* Protection .htaccess des repertoires prives */
    foreach (['storage' => IL_ROOT . '/storage/.htaccess', 'includes' => IL_ROOT . '/includes/.htaccess'] as $name => $file) {
        $present = is_file($file);
        $checks[] = il_check(
            'Protection ' . $name . '/.htaccess',
            $present ? 'presente' : 'absente',
            $present ? 'ok' : 'error',
            $present ? '' : 'Ce repertoire doit etre interdit en HTTP.'
        );
    }

    /* Verification reelle : le stockage prive repond-il en HTTP ? */
    $http = il_sys_http_protection();
    $checks[] = il_check(
        'Acces HTTP au stockage prive',
        $http['value'],
        $http['state'],
        $http['hint']
    );

    return $checks;
}

/**
 * Verifie par une vraie requete HTTP locale que storage/ n'est pas
 * servi publiquement. Le resultat est mis en cache 5 minutes pour ne
 * pas interroger le serveur a chaque affichage.
 */
function il_sys_http_protection()
{
    $cacheFile = IL_DATA_DIR . '/http-protection.json';
    $cached = il_read_json($cacheFile, []);
    if (!empty($cached['ts']) && (time() - (int) $cached['ts']) < 300) {
        return $cached['result'];
    }

    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }

    /* Chemin HTTP du projet, deduit du script courant */
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php'))), '/');
    $target   = $basePath . '/storage/data/ratelimit.json';

    $result = ['value' => 'verification impossible', 'state' => 'warn', 'hint' => 'Le serveur n\'a pas pu etre interroge localement.'];

    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 3);
    if ($socket) {
        $request = "GET " . $target . " HTTP/1.1\r\n"
            . "Host: " . $host . "\r\n"
            . "Connection: close\r\n"
            . "User-Agent: InfoLeader-SelfCheck\r\n\r\n";
        fwrite($socket, $request);
        $statusLine = (string) fgets($socket, 128);
        fclose($socket);

        if (preg_match('#HTTP/1\.[01]\s+(\d{3})#', $statusLine, $m)) {
            $code = (int) $m[1];
            if ($code === 403 || $code === 404) {
                $result = [
                    'value' => 'bloque (HTTP ' . $code . ')',
                    'state' => 'ok',
                    'hint'  => '',
                ];
            } else {
                $result = [
                    'value' => 'ACCESSIBLE (HTTP ' . $code . ')',
                    'state' => 'error',
                    'hint'  => 'storage/ est servi publiquement. Verifiez AllowOverride All et la presence de storage/.htaccess.',
                ];
            }
        }
    }

    il_write_json($cacheFile, ['ts' => time(), 'result' => $result]);
    return $result;
}

/** Les quatre sous-sections de l'etat systeme */
function il_sys_sections()
{
    $tmp = il_tmp_status();

    $tmpChecks = [
        il_check(
            'Repertoire storage/tmp',
            $tmp['exists'] ? 'present' : 'absent',
            $tmp['exists'] ? 'ok' : 'error',
            $tmp['exists'] ? '' : 'Il sera recree au prochain chargement d\'une page admin.'
        ),
        il_check(
            'Droits d\'acces',
            $tmp['writable'] ? 'lecture / ecriture' : 'ecriture impossible',
            $tmp['writable'] ? 'ok' : 'error'
        ),
        il_check(
            'Fichiers temporaires',
            $tmp['count'] . ' fichier' . ($tmp['count'] > 1 ? 's' : '')
                . ($tmp['count'] > 0 ? ' (' . il_human_size($tmp['total_size']) . ')' : ''),
            'info'
        ),
    ];

    return [
        'tmp'     => ['title' => 'Fichiers temporaires', 'checks' => $tmpChecks],
        'env'     => ['title' => 'Environnement',        'checks' => il_sys_environment()],
        'write'   => ['title' => 'Droits d\'ecriture',   'checks' => il_sys_writable()],
        'config'  => ['title' => 'Fichiers de configuration', 'checks' => il_sys_config_files()],
    ];
}

/**
 * Etat global du site, calcule a partir des verifications reellement
 * effectuees : un seul echec critique suffit a sortir de l'etat vert.
 */
function il_sys_overall(array $sections = null)
{
    $sections = $sections ?? il_sys_sections();

    $errors = 0;
    $warnings = 0;
    foreach ($sections as $section) {
        foreach ($section['checks'] as $check) {
            if ($check['state'] === 'error') {
                $errors++;
            } elseif ($check['state'] === 'warn') {
                $warnings++;
            }
        }
    }

    if ($errors > 0) {
        return [
            'state'    => 'error',
            'label'    => 'Anomalie detectee',
            'detail'   => $errors . ' controle' . ($errors > 1 ? 's' : '') . ' en echec'
                . ($warnings > 0 ? ', ' . $warnings . ' avertissement' . ($warnings > 1 ? 's' : '') : ''),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }
    if ($warnings > 0) {
        return [
            'state'    => 'warn',
            'label'    => 'Fonctionnel avec reserves',
            'detail'   => $warnings . ' avertissement' . ($warnings > 1 ? 's' : ''),
            'errors'   => 0,
            'warnings' => $warnings,
        ];
    }
    return [
        'state'    => 'ok',
        'label'    => 'Site operationnel',
        'detail'   => 'Tous les controles sont passes',
        'errors'   => 0,
        'warnings' => 0,
    ];
}

/* ==================================================================
   4. EXPORT JSON (sauvegarde)
   ==================================================================
   Aucun secret ne doit figurer dans l'export : ni hash du mot de passe
   administrateur, ni mot de passe SMTP, ni code de reinitialisation,
   ni donnee de session.
   ================================================================== */

/** Champs volontairement exclus de l'export */
function il_backup_excluded_fields()
{
    return [
        'password_hash (compte administrateur)',
        'password (SMTP)',
        'code_hash (code de reinitialisation)',
        'donnees de session',
    ];
}

/** Construit le contenu de l'export JSON, secrets retires */
function il_backup_payload()
{
    $admin = il_admin_get();
    $mail  = il_mail_config();

    /* Configuration e-mail sans le secret */
    unset($mail['password']);
    $mail['password'] = '[retire de l\'export]';

    $sections = il_sys_sections();
    $overall  = il_sys_overall($sections);

    return [
        'export' => [
            'genere_le'   => date('c'),
            'genere_par'  => 'Panneau d\'administration InfoLeader',
            'portee'      => 'Export local. Aucune donnee n\'est transmise a un service tiers.',
            'secrets'     => 'Retires : ' . implode(', ', il_backup_excluded_fields()),
        ],
        'application' => [
            'nom'              => 'InfoLeader',
            'version_php'      => PHP_VERSION,
            'serveur'          => $_SERVER['SERVER_SOFTWARE'] ?? 'inconnu',
            'stockage'         => 'storage/ (hors acces HTTP)',
            'etat_global'      => $overall['state'],
            'etat_libelle'     => $overall['label'],
        ],
        'compte_administrateur' => $admin ? [
            'email'      => $admin['email'],
            'cree_le'    => isset($admin['created_at']) ? date('c', (int) $admin['created_at']) : null,
            'modifie_le' => isset($admin['updated_at']) ? date('c', (int) $admin['updated_at']) : null,
            /* password_hash volontairement absent */
        ] : null,
        'configuration_email' => $mail,
        'parametres_securite' => [
            'mot_de_passe' => [
                'longueur_minimale' => IL_PWD_MIN_LENGTH,
                'regles'            => il_password_policy(),
            ],
            'session' => [
                'inactivite_secondes' => IL_SESSION_IDLE,
                'cookie_httponly'     => true,
                'cookie_samesite'     => 'Lax',
            ],
            'limitation_tentatives' => [
                'delais_progressifs_secondes' => [
                    '1 echec'  => il_rl_delay_for(1),
                    '2 echecs' => il_rl_delay_for(2),
                    '3 echecs' => il_rl_delay_for(3),
                    '4 echecs' => il_rl_delay_for(4),
                    '5 echecs' => il_rl_delay_for(5),
                ],
                'blocages_secondes' => [
                    '6 echecs'  => il_rl_lock_for(6),
                    '8 echecs'  => il_rl_lock_for(8),
                    '10 echecs' => il_rl_lock_for(10),
                    '11+ echecs' => il_rl_lock_for(11),
                ],
                'seuil_blocage_ip'     => il_rl_lock_threshold('ip'),
                'seuil_blocage_compte' => il_rl_lock_threshold('acct'),
                'remise_a_zero_secondes' => IL_RL_DECAY,
            ],
            'reinitialisation' => [
                'longueur_code'        => 8,
                'validite_secondes'    => IL_RESET_TTL,
                'essais_maximum'       => IL_RESET_MAX_ATTEMPTS,
                'demandes_maximum'     => IL_RESET_MAX_REQUESTS,
                'fenetre_demandes_sec' => IL_RESET_WINDOW,
            ],
        ],
        'statistiques' => [
            'evenements_journalises'  => count(il_read_json(IL_AUTH_LOG, [])),
            'messages_de_contact'     => il_contact_count(),
            'fichiers_temporaires'    => il_tmp_status()['count'],
            'blocages_actifs'         => count(il_rl_active_blocks()),
        ],
        'produits' => il_read_json(IL_ROOT . '/products.json', []),
    ];
}
