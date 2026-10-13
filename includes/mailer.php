<?php
/**
 * mailer.php - Configuration et envoi des e-mails (PHP mail / Brevo SMTP)
 *
 * Utilise PHPMailer (installe via Composer : composer require phpmailer/phpmailer).
 * Charge par includes/bootstrap.php. Interdit en HTTP (includes/.htaccess).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** Chemin de l'autoloader Composer */
define('IL_AUTOLOAD', IL_ROOT . '/vendor/autoload.php');

/** Vrai si PHPMailer est installe et chargeable */
function il_mailer_available()
{
    if (!is_file(IL_AUTOLOAD)) {
        return false;
    }
    require_once IL_AUTOLOAD;
    return class_exists(PHPMailer::class);
}

/* ==================================================================
   1. CONFIGURATION
   ================================================================== */

/**
 * Prereglages des fournisseurs SMTP pris en charge.
 * Gmail est propose par defaut : c'est le seul dont le handshake TLS
 * aboutit depuis ce poste (le trafic vers Brevo est filtre).
 */
function il_mail_providers()
{
    return [
        'gmail' => [
            'label'      => 'Gmail / Google Workspace',
            'host'       => 'smtp.gmail.com',
            'port'       => 587,
            'encryption' => 'tls',
            'auth'       => true,
            'hint'       => 'Identifiant = adresse Gmail complete. Mot de passe = '
                . 'mot de passe d\'application de 16 caracteres (validation en deux '
                . 'etapes requise), jamais le mot de passe du compte. L\'adresse '
                . 'd\'expedition doit etre celle du compte Gmail.',
        ],
        'brevo' => [
            'label'      => 'Brevo (ex-Sendinblue)',
            'host'       => 'smtp-relay.brevo.com',
            'port'       => 587,
            'encryption' => 'tls',
            'auth'       => true,
            'hint'       => 'Identifiant de la forme xxxxxxx@smtp-brevo.com et cle SMTP '
                . 'creee dans SMTP & API > Cles SMTP. L\'adresse d\'expedition doit etre '
                . 'validee dans Expediteurs.',
        ],
    ];
}

/**
 * Valeurs par defaut de la configuration e-mail.
 * Prereglage applique : Gmail en STARTTLS sur le port 587.
 */
function il_mail_defaults()
{
    $gmail = il_mail_providers()['gmail'];

    return [
        'mode'       => 'smtp',                  /* 'smtp' ou 'mail' (PHP mail) */
        'provider'   => 'gmail',                 /* prereglage retenu */
        'from_name'  => 'InfoLeader',
        'from_email' => '',
        'test_email' => '',
        'host'       => $gmail['host'],
        'port'       => $gmail['port'],
        'encryption' => $gmail['encryption'],    /* 'tls' = STARTTLS, 'ssl' = SMTPS, '' = aucune */
        'auth'       => true,
        'username'   => '',
        'password'   => '',                      /* secret : jamais reaffiche */
        'debug'      => false,
        'updated_at' => 0,
    ];
}

/** Prereglage correspondant a l'hote configure */
function il_mail_detect_provider(array $config)
{
    foreach (il_mail_providers() as $key => $provider) {
        if (strcasecmp($config['host'] ?? '', $provider['host']) === 0) {
            return $key;
        }
    }
    return 'custom';
}

/** Configuration e-mail effective (defauts + valeurs enregistrees) */
function il_mail_config()
{
    $stored = il_read_php_config(IL_MAIL_FILE, []);
    $config = array_merge(il_mail_defaults(), is_array($stored) ? $stored : []);
    $config['port']  = (int) $config['port'];
    $config['auth']  = (bool) $config['auth'];
    $config['debug'] = (bool) $config['debug'];
    return $config;
}

/**
 * Enregistre la configuration e-mail.
 * Si 'password' est une chaine vide, le mot de passe existant est conserve
 * (le formulaire ne reaffiche jamais le secret).
 */
function il_mail_config_save(array $input)
{
    $current = il_mail_config();
    $config  = array_merge($current, $input);

    if (!isset($input['password']) || $input['password'] === '') {
        $config['password'] = $current['password'];
    }

    $config['updated_at'] = time();
    return il_write_php_config(IL_MAIL_FILE, $config);
}

/** Vrai si un mot de passe SMTP est enregistre (sans le reveler) */
function il_mail_has_password()
{
    $config = il_mail_config();
    return $config['password'] !== '';
}

/** Vrai si la configuration permet un envoi reel */
function il_mail_is_configured()
{
    $config = il_mail_config();

    if ($config['from_email'] === '' || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if ($config['mode'] === 'mail') {
        return il_php_mail_available();
    }
    if ($config['host'] === '' || $config['port'] <= 0) {
        return false;
    }
    if ($config['auth'] && ($config['username'] === '' || $config['password'] === '')) {
        return false;
    }
    return true;
}

/**
 * Vrai si la fonction mail() de PHP a une chance de fonctionner.
 *
 * Sous WAMP en local, aucun serveur de messagerie n'est fourni : si ni
 * SMTP ni sendmail_path ne sont renseignes dans le php.ini, le mode
 * "PHP mail" est annonce comme indisponible plutot que simule.
 */
function il_php_mail_available()
{
    if (!function_exists('mail')) {
        return false;
    }
    $sendmail = trim((string) ini_get('sendmail_path'));
    $smtpHost = trim((string) ini_get('SMTP'));

    if ($sendmail !== '') {
        return true;
    }
    /* La valeur par defaut 'localhost' de WAMP ne correspond a aucun
       serveur reellement installe : on ne la considere pas valide. */
    return $smtpHost !== '' && strtolower($smtpHost) !== 'localhost';
}

/* ==================================================================
   2. JOURNAL DE DEBOGAGE SMTP
   ================================================================== */

/** Taille maximale du journal de debogage avant rotation (256 Ko) */
define('IL_MAIL_DEBUG_MAX', 262144);

/**
 * Ecrit une ligne dans le journal de debogage SMTP.
 * Les identifiants eventuellement presents dans la trace sont masques.
 */
function il_mail_debug_write($line)
{
    $line = rtrim((string) $line);
    if ($line === '') {
        return;
    }

    /* Masquage des echanges d'authentification SMTP : AUTH LOGIN/PLAIN
       transmet identifiant et mot de passe encodes en base64. */
    $line = preg_replace('/(AUTH\s+(?:LOGIN|PLAIN|XOAUTH2))\s+\S+/i', '$1 [masque]', $line);
    $line = preg_replace('/(CLIENT -> SERVER:\s*)[A-Za-z0-9+\/=]{16,}$/m', '$1[masque]', $line);

    if (is_file(IL_MAIL_DEBUG_LOG) && filesize(IL_MAIL_DEBUG_LOG) > IL_MAIL_DEBUG_MAX) {
        @unlink(IL_MAIL_DEBUG_LOG);
    }

    @file_put_contents(
        IL_MAIL_DEBUG_LOG,
        '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/** Dernieres lignes du journal de debogage SMTP */
function il_mail_debug_tail($lines = 120)
{
    if (!is_file(IL_MAIL_DEBUG_LOG)) {
        return [];
    }
    $content = file(IL_MAIL_DEBUG_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($content)) {
        return [];
    }
    return array_slice($content, -$lines);
}

/** Vide le journal de debogage SMTP */
function il_mail_debug_clear()
{
    if (is_file(IL_MAIL_DEBUG_LOG)) {
        return @unlink(IL_MAIL_DEBUG_LOG);
    }
    return true;
}

/* ==================================================================
   3. ENVOI
   ================================================================== */

/**
 * Envoie un e-mail reel.
 *
 * Retourne :
 *   ok    => bool
 *   error => string  message comprehensible, sans identifiants SMTP
 *
 * Aucun envoi n'est simule : si le serveur est indisponible, la
 * fonction retourne une erreur.
 */
function il_mail_send($to, $subject, $htmlBody, $textBody = '')
{
    $config = il_mail_config();

    if (!il_mailer_available()) {
        return [
            'ok'    => false,
            'error' => 'PHPMailer n\'est pas installe. Executez : composer require phpmailer/phpmailer',
        ];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Adresse du destinataire invalide.'];
    }
    if (!filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'ok'    => false,
            'error' => 'Adresse de l\'expediteur absente ou invalide. Completez la configuration des e-mails.',
        ];
    }
    if ($config['mode'] === 'mail' && !il_php_mail_available()) {
        return [
            'ok'    => false,
            'error' => 'Le mode PHP mail n\'est pas utilisable : aucun serveur de messagerie '
                . 'n\'est configure dans le php.ini (sendmail_path / SMTP). Utilisez le mode Brevo SMTP.',
        ];
    }
    if ($config['mode'] === 'smtp') {
        if ($config['host'] === '') {
            return ['ok' => false, 'error' => 'Serveur SMTP non renseigne.'];
        }
        if ($config['auth'] && ($config['username'] === '' || $config['password'] === '')) {
            return ['ok' => false, 'error' => 'Identifiant ou mot de passe SMTP manquant.'];
        }
        if ($config['encryption'] !== '' && !extension_loaded('openssl')) {
            return [
                'ok'    => false,
                'error' => 'L\'extension OpenSSL de PHP est desactivee : la connexion chiffree '
                    . 'est impossible. Activez extension=openssl dans le php.ini.',
            ];
        }
    }

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        /* Bornes de temps : sans elles, un serveur SMTP injoignable ou
           un handshake TLS bloque par un pare-feu figerait la page
           pendant plusieurs minutes.
           Timeout   : ouverture de la connexion et handshake.
           Timelimit : attente de la reponse a chaque commande SMTP
                       (propriete de l'objet SMTP, pas de PHPMailer). */
        $mail->Timeout = 10;
        if (method_exists($mail, 'getSMTPInstance')) {
            $mail->getSMTPInstance()->Timelimit = 15;
        }

        if ($config['mode'] === 'smtp') {
            $mail->isSMTP();
            $mail->Host     = $config['host'];
            $mail->Port     = $config['port'];
            $mail->SMTPAuth = $config['auth'];

            if ($config['auth']) {
                $mail->Username = $config['username'];
                $mail->Password = $config['password'];
            }

            if ($config['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            }

            if (!empty($config['debug'])) {
                $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    il_mail_debug_write('[' . $level . '] ' . $str);
                };
            }
        } else {
            $mail->isMail();
        }

        $fromName = $config['from_name'] !== '' ? $config['from_name'] : 'InfoLeader';
        $mail->setFrom($config['from_email'], $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        $mail->send();

        if (!empty($config['debug'])) {
            il_mail_debug_write('Envoi reussi vers ' . $to . ' (sujet : ' . $subject . ')');
        }
        return ['ok' => true, 'error' => ''];

    } catch (PHPMailerException $e) {
        /* ErrorInfo peut contenir la reponse du serveur, mais jamais le
           mot de passe : il est malgre tout filtre par securite. */
        $message = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        $message = il_mail_scrub($message, $config);
        $message = il_mail_diagnose($message, $config);

        if (!empty($config['debug'])) {
            il_mail_debug_write('ECHEC vers ' . $to . ' : ' . $message);
        }
        return ['ok' => false, 'error' => $message];

    } catch (\Throwable $e) {
        $message = il_mail_scrub($e->getMessage(), $config);
        if (!empty($config['debug'])) {
            il_mail_debug_write('ECHEC technique : ' . $message);
        }
        return ['ok' => false, 'error' => $message];
    }
}

/**
 * Ajoute une piste de resolution aux erreurs SMTP les plus courantes.
 * Ne divulgue aucune information sensible.
 */
function il_mail_diagnose($message, array $config)
{
    $lower = mb_strtolower($message);

    if (strpos($lower, 'handshake') !== false || strpos($lower, 'enable_crypto') !== false) {
        return $message . ' | Piste : la connexion TCP aboutit mais le chiffrement '
            . 'echoue. C\'est le symptome typique d\'un pare-feu, d\'un antivirus ou '
            . 'd\'un proxy qui intercepte le trafic SMTP sortant. Verifiez aussi que '
            . 'extension=openssl est actif, puis essayez le port 465 avec SSL/TLS.';
    }
    if (strpos($lower, 'could not connect') !== false || strpos($lower, 'connection refused') !== false) {
        return $message . ' | Piste : le port ' . (int) $config['port'] . ' de '
            . $config['host'] . ' n\'est pas joignable depuis ce poste. '
            . 'Verifiez la connexion reseau et les regles sortantes du pare-feu.';
    }
    $isGmail = stripos($config['host'] ?? '', 'gmail') !== false;

    if (strpos($lower, 'authenticate') !== false || strpos($lower, '535') !== false
        || strpos($lower, '534') !== false
    ) {
        return $message . ' | Piste : ' . ($isGmail
            ? 'Gmail refuse le mot de passe du compte. Generez un mot de passe '
                . 'd\'application de 16 caracteres sur myaccount.google.com/apppasswords '
                . '(validation en deux etapes obligatoire) et saisissez-le sans espaces. '
                . 'L\'identifiant doit etre l\'adresse Gmail complete.'
            : 'identifiant ou cle SMTP refuses par le serveur. Pour Brevo, utilisez '
                . 'la cle SMTP (SMTP & API > Cles SMTP), pas le mot de passe du compte.');
    }
    if (strpos($lower, 'sender') !== false || strpos($lower, '550') !== false
        || strpos($lower, '553') !== false
    ) {
        return $message . ' | Piste : ' . ($isGmail
            ? 'Gmail n\'autorise comme expediteur que l\'adresse du compte '
                . 'authentifie (ou un alias declare dans Gmail). Mettez la meme '
                . 'adresse dans « expediteur » et « identifiant SMTP ».'
            : 'l\'adresse d\'expedition doit etre validee comme expediteur dans '
                . 'votre compte Brevo.');
    }
    return $message;
}

/**
 * Retire de tout message d'erreur les valeurs sensibles qui pourraient
 * y figurer (identifiant et mot de passe SMTP).
 */
function il_mail_scrub($message, array $config)
{
    $message = (string) $message;
    foreach (['password', 'username'] as $field) {
        if (!empty($config[$field]) && strlen((string) $config[$field]) > 2) {
            $message = str_replace($config[$field], '[masque]', $message);
        }
    }
    return trim($message);
}

/* ==================================================================
   4. MODELES D'E-MAIL
   ================================================================== */

/** Gabarit HTML commun, aux couleurs du site */
function il_mail_template($title, $contentHtml)
{
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:24px;background:#f4f7fc;'
        . 'font-family:Segoe UI,Arial,sans-serif;color:#1e293b;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;'
        . 'border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">'
        . '<div style="background:#1e3c72;padding:20px 24px;color:#ffffff;'
        . 'font-size:18px;font-weight:700;">InfoLeader</div>'
        . '<div style="padding:24px;">'
        . '<h1 style="margin:0 0 16px;font-size:18px;color:#1e3c72;">' . e($title) . '</h1>'
        . $contentHtml
        . '</div>'
        . '<div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;">'
        . 'Message automatique envoye par le panneau d\'administration InfoLeader.'
        . '</div></div></body></html>';
}

/** E-mail de test declenche depuis la configuration des e-mails */
function il_mail_send_test($to)
{
    $config = il_mail_config();

    $serveur = $config['mode'] === 'smtp'
        ? $config['host'] . ':' . $config['port']
        : 'fonction mail() de PHP';

    $securite = $config['encryption'] === 'tls'
        ? 'STARTTLS'
        : ($config['encryption'] === 'ssl' ? 'SSL/TLS' : 'aucune');

    $html = il_mail_template(
        'E-mail de test',
        '<p style="margin:0 0 12px;line-height:1.6;">Cet e-mail confirme que la '
        . 'configuration d\'envoi du panneau d\'administration InfoLeader fonctionne.</p>'
        . '<ul style="margin:0 0 12px;padding-left:20px;line-height:1.8;color:#475569;">'
        . '<li>Mode : ' . e($config['mode'] === 'smtp' ? 'SMTP (Brevo)' : 'PHP mail') . '</li>'
        . '<li>Serveur : ' . e($serveur) . '</li>'
        . '<li>Securite : ' . e($securite) . '</li>'
        . '<li>Date : ' . e(date('d/m/Y H:i:s')) . '</li>'
        . '</ul>'
        . '<p style="margin:0;color:#64748b;font-size:13px;">Aucun identifiant SMTP '
        . 'n\'est inclus dans ce message.</p>'
    );

    $result = il_mail_send($to, 'InfoLeader - E-mail de test', $html);

    if ($result['ok']) {
        il_log('mail_test_ok', 'E-mail de test envoye', ['to' => $to, 'mode' => $config['mode']]);
    } else {
        il_log('mail_test_failed', 'Echec de l\'e-mail de test', [
            'to'     => $to,
            'mode'   => $config['mode'],
            'erreur' => $result['error'],
        ]);
    }
    return $result;
}

/** E-mail contenant le code de reinitialisation a 8 chiffres */
function il_mail_send_reset_code($to, $code)
{
    $html = il_mail_template(
        'Reinitialisation de votre mot de passe',
        '<p style="margin:0 0 16px;line-height:1.6;">Voici votre code de '
        . 'reinitialisation. Il est valable <strong>5 minutes</strong> et ne peut '
        . 'etre utilise qu\'une seule fois.</p>'
        . '<p style="margin:0 0 16px;text-align:center;font-size:30px;font-weight:700;'
        . 'letter-spacing:7px;color:#1e3c72;background:#f1f5fb;border-radius:10px;'
        . 'padding:16px 10px;">' . e($code) . '</p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">Si vous '
        . 'n\'etes pas a l\'origine de cette demande, ignorez ce message : votre mot '
        . 'de passe reste inchange.</p>'
    );

    /* Le code n'est jamais journalise : seul l'envoi est trace. */
    return il_mail_send($to, 'InfoLeader - Code de reinitialisation', $html);
}

/** Notification d'un nouveau message recu via le formulaire de contact */
function il_mail_send_contact_notification(array $message)
{
    $config = il_mail_config();
    $to = $config['test_email'] !== '' ? $config['test_email'] : $config['from_email'];

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Aucun destinataire de notification configure.'];
    }

    $html = il_mail_template(
        'Nouveau message de contact',
        '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#64748b;width:90px;">Nom</td>'
        . '<td style="padding:6px 0;">' . e($message['nom']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b;">E-mail</td>'
        . '<td style="padding:6px 0;">' . e($message['email']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b;">Objet</td>'
        . '<td style="padding:6px 0;">' . e($message['objet']) . '</td></tr>'
        . '</table>'
        . '<p style="margin:16px 0 0;padding:14px;background:#f8fafc;border-radius:8px;'
        . 'line-height:1.6;white-space:pre-wrap;">' . e($message['message']) . '</p>'
    );

    return il_mail_send($to, 'InfoLeader - Message de contact : ' . $message['objet'], $html);
}
