<?php
/**
 * products.php - Gestion du catalogue produits
 *
 * Le catalogue reste stocke dans products.json, a la racine du projet :
 * c'est le fichier que lit deja la page publique products.php. On y
 * ajoute seulement un identifiant stable (id) pour pouvoir modifier ou
 * supprimer une fiche de maniere fiable.
 *
 * Charge par includes/bootstrap.php. Interdit en HTTP (includes/.htaccess).
 */

/** Fichier du catalogue (deja utilise par la page publique) */
define('IL_PRODUCTS_FILE', IL_ROOT . '/products.json');

/** Dossier des visuels produits */
define('IL_PRODUCTS_IMG_DIR', IL_ROOT . '/assets/products');

/** Chemin relatif utilise dans products.json */
define('IL_PRODUCTS_IMG_REL', 'assets/products');

/** Taille maximale d'un visuel : 3 Mo */
define('IL_IMG_MAX_SIZE', 3 * 1024 * 1024);

/** Formats d'image acceptes : extension => type MIME attendu */
function il_img_allowed()
{
    return [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];
}

/* ==================================================================
   1. LECTURE
   ================================================================== */

/**
 * Catalogue complet.
 * Les fiches sans id en recoivent un a la volee (migration douce des
 * donnees du TP 1, qui n'en avaient pas).
 */
function il_products_all()
{
    $products = il_read_json(IL_PRODUCTS_FILE, []);
    if (!is_array($products)) {
        return [];
    }

    $changed = false;
    foreach ($products as $index => $product) {
        if (empty($product['id'])) {
            $products[$index]['id'] = il_product_new_id();
            $changed = true;
        }
    }

    if ($changed) {
        il_products_write($products);
    }
    return $products;
}

/** Identifiant court et unique */
function il_product_new_id()
{
    return 'p' . bin2hex(random_bytes(4));
}

/** Une fiche par son identifiant, ou null */
function il_product_find($id)
{
    foreach (il_products_all() as $product) {
        if (($product['id'] ?? '') === $id) {
            return $product;
        }
    }
    return null;
}

/** Nombre de produits au catalogue */
function il_products_count()
{
    return count(il_read_json(IL_PRODUCTS_FILE, []));
}

/**
 * Ecrit le catalogue en conservant l'ordre des cles attendu par la
 * page publique (nom, prix, description, image).
 */
function il_products_write(array $products)
{
    $clean = [];
    foreach ($products as $product) {
        $clean[] = [
            'id'          => $product['id'] ?? il_product_new_id(),
            'nom'         => (string) ($product['nom'] ?? ''),
            'prix'        => round((float) ($product['prix'] ?? 0), 2),
            'description' => (string) ($product['description'] ?? ''),
            'image'       => (string) ($product['image'] ?? ''),
        ];
    }
    return il_write_json(IL_PRODUCTS_FILE, $clean);
}

/* ==================================================================
   2. VALIDATION
   ================================================================== */

/**
 * Valide les champs d'une fiche produit.
 * Retourne un tableau champ => message (vide = valide).
 */
function il_product_validate(array $input, $isNew = true)
{
    $errors = [];

    $nom = trim((string) ($input['nom'] ?? ''));
    if ($nom === '') {
        $errors['nom'] = 'Le nom du produit est requis.';
    } elseif (mb_strlen($nom) > 120) {
        $errors['nom'] = 'Le nom ne doit pas depasser 120 caracteres.';
    }

    $prixRaw = str_replace(',', '.', trim((string) ($input['prix'] ?? '')));
    if ($prixRaw === '') {
        $errors['prix'] = 'Le prix est requis.';
    } elseif (!is_numeric($prixRaw)) {
        $errors['prix'] = 'Le prix doit etre un nombre (ex. 59.99).';
    } elseif ((float) $prixRaw < 0) {
        $errors['prix'] = 'Le prix ne peut pas etre negatif.';
    } elseif ((float) $prixRaw > 1000000) {
        $errors['prix'] = 'Le prix est hors limites.';
    }

    $description = trim((string) ($input['description'] ?? ''));
    if ($description === '') {
        $errors['description'] = 'La description est requise.';
    } elseif (mb_strlen($description) > 600) {
        $errors['description'] = 'La description ne doit pas depasser 600 caracteres.';
    }

    /* L'image est obligatoire a la creation, optionnelle ensuite */
    $hasUpload  = !empty($_FILES['image']['name']);
    $keptImage  = trim((string) ($input['image_actuelle'] ?? ''));
    if ($isNew && !$hasUpload && $keptImage === '') {
        $errors['image'] = 'Un visuel est requis pour creer une fiche produit.';
    }

    return $errors;
}

/* ==================================================================
   3. IMAGES
   ================================================================== */

/**
 * Traite le fichier envoye et retourne le chemin relatif a stocker.
 *
 * Retourne : ok => bool, path => string, error => string
 *
 * Verifications : code d'erreur PHP, taille, extension, type MIME reel
 * (finfo) et dimensions lisibles (getimagesize). Le nom de fichier est
 * regenere : aucune valeur fournie par le navigateur n'est reutilisee
 * comme chemin.
 */
function il_product_handle_upload(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => '', 'error' => ''];
    }

    $phpErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Le fichier depasse la taille autorisee par le serveur.',
        UPLOAD_ERR_FORM_SIZE  => 'Le fichier depasse la taille autorisee par le formulaire.',
        UPLOAD_ERR_PARTIAL    => 'Le transfert du fichier a ete interrompu.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire introuvable sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Ecriture du fichier impossible sur le serveur.',
        UPLOAD_ERR_EXTENSION  => 'Transfert bloque par une extension PHP.',
    ];
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return [
            'ok'    => false,
            'path'  => '',
            'error' => $phpErrors[$file['error']] ?? 'Transfert du fichier impossible.',
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'path' => '', 'error' => 'Fichier invalide.'];
    }

    if ((int) $file['size'] > IL_IMG_MAX_SIZE) {
        return [
            'ok'    => false,
            'path'  => '',
            'error' => 'Image trop lourde (' . il_human_size($file['size'])
                . '). Maximum : ' . il_human_size(IL_IMG_MAX_SIZE) . '.',
        ];
    }

    $allowed   = il_img_allowed();
    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

    if (!isset($allowed[$extension])) {
        return [
            'ok'    => false,
            'path'  => '',
            'error' => 'Format non accepte. Formats autorises : '
                . implode(', ', array_unique(array_keys($allowed))) . '.',
        ];
    }

    /* Le type reel doit correspondre a l'extension annoncee */
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    if ($mime !== '' && $mime !== $allowed[$extension]) {
        return [
            'ok'    => false,
            'path'  => '',
            'error' => 'Le contenu du fichier ne correspond pas a son extension.',
        ];
    }

    /* Le fichier doit etre une image reellement decodable */
    $size = @getimagesize($file['tmp_name']);
    if ($size === false) {
        return ['ok' => false, 'path' => '', 'error' => 'Ce fichier n\'est pas une image valide.'];
    }

    if (!is_dir(IL_PRODUCTS_IMG_DIR) && !@mkdir(IL_PRODUCTS_IMG_DIR, 0775, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Dossier des visuels inaccessible.'];
    }
    if (!is_writable(IL_PRODUCTS_IMG_DIR)) {
        return [
            'ok'    => false,
            'path'  => '',
            'error' => 'Le dossier assets/products n\'est pas accessible en ecriture.',
        ];
    }

    /* Nom de fichier entierement regenere */
    $name   = il_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME));
    $final  = ($name !== '' ? $name : 'produit') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
    $target = IL_PRODUCTS_IMG_DIR . DIRECTORY_SEPARATOR . $final;

    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'path' => '', 'error' => 'Enregistrement de l\'image impossible.'];
    }
    @chmod($target, 0644);

    return ['ok' => true, 'path' => IL_PRODUCTS_IMG_REL . '/' . $final, 'error' => ''];
}

/** Transforme un texte en identifiant de fichier sur (a-z, 0-9, tirets) */
function il_slug($text)
{
    $text = (string) $text;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

/**
 * Supprime un visuel devenu inutile.
 * Ne touche qu'aux fichiers situes dans assets/products et jamais a un
 * visuel encore reference par une autre fiche.
 */
function il_product_delete_image($relativePath, array $products)
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || strpos($relativePath, IL_PRODUCTS_IMG_REL . '/') !== 0) {
        return false;
    }

    foreach ($products as $product) {
        if (($product['image'] ?? '') === $relativePath) {
            return false;   /* encore utilise */
        }
    }

    $base = realpath(IL_PRODUCTS_IMG_DIR);
    $real = realpath(IL_ROOT . '/' . $relativePath);

    if ($base === false || $real === false || !is_file($real)) {
        return false;
    }
    /* Le fichier doit bien se trouver dans assets/products */
    if (dirname($real) !== $base) {
        return false;
    }
    return @unlink($real);
}

/* ==================================================================
   4. ECRITURE : CREATION, MODIFICATION, SUPPRESSION
   ================================================================== */

/**
 * Cree une fiche produit.
 * Retourne : ok => bool, id => string, errors => array
 */
function il_product_create(array $input)
{
    $errors = il_product_validate($input, true);

    $imagePath = trim((string) ($input['image_actuelle'] ?? ''));
    if (!empty($_FILES['image']['name'])) {
        $upload = il_product_handle_upload($_FILES['image']);
        if (!$upload['ok'] && $upload['error'] !== '') {
            $errors['image'] = $upload['error'];
        } elseif ($upload['ok']) {
            $imagePath = $upload['path'];
        }
    }

    if ($errors) {
        /* Le fichier venait d'etre deplace dans assets/products alors que
           la fiche est refusee : on le retire pour ne pas laisser
           d'image orpheline. */
        if ($imagePath !== '' && $imagePath !== trim((string) ($input['image_actuelle'] ?? ''))) {
            il_product_delete_image($imagePath, il_products_all());
        }
        return ['ok' => false, 'id' => '', 'errors' => $errors];
    }

    $products = il_products_all();
    $id       = il_product_new_id();

    $products[] = [
        'id'          => $id,
        'nom'         => trim((string) $input['nom']),
        'prix'        => (float) str_replace(',', '.', (string) $input['prix']),
        'description' => trim((string) $input['description']),
        'image'       => $imagePath,
    ];

    if (!il_products_write($products)) {
        return [
            'ok'     => false,
            'id'     => '',
            'errors' => ['global' => 'Ecriture de products.json impossible : verifiez les droits.'],
        ];
    }

    il_log('product_created', 'Produit ajoute au catalogue', [
        'nom'  => trim((string) $input['nom']),
        'prix' => (float) str_replace(',', '.', (string) $input['prix']),
    ]);

    return ['ok' => true, 'id' => $id, 'errors' => []];
}

/**
 * Modifie une fiche existante.
 * Retourne : ok => bool, errors => array
 */
function il_product_update($id, array $input)
{
    $products = il_products_all();
    $index    = null;

    foreach ($products as $position => $product) {
        if (($product['id'] ?? '') === $id) {
            $index = $position;
            break;
        }
    }
    if ($index === null) {
        return ['ok' => false, 'errors' => ['global' => 'Produit introuvable.']];
    }

    $errors    = il_product_validate($input, false);
    $current   = $products[$index];
    $imagePath = $current['image'] ?? '';
    $oldImage  = $imagePath;

    if (!empty($_FILES['image']['name'])) {
        $upload = il_product_handle_upload($_FILES['image']);
        if (!$upload['ok'] && $upload['error'] !== '') {
            $errors['image'] = $upload['error'];
        } elseif ($upload['ok']) {
            $imagePath = $upload['path'];
        }
    }

    if ($errors) {
        /* Nouveau visuel deja deplace mais fiche refusee : on le retire
           et l'ancien visuel reste intact. */
        if ($imagePath !== $oldImage) {
            il_product_delete_image($imagePath, $products);
        }
        return ['ok' => false, 'errors' => $errors];
    }

    $products[$index] = [
        'id'          => $id,
        'nom'         => trim((string) $input['nom']),
        'prix'        => (float) str_replace(',', '.', (string) $input['prix']),
        'description' => trim((string) $input['description']),
        'image'       => $imagePath,
    ];

    if (!il_products_write($products)) {
        return ['ok' => false, 'errors' => ['global' => 'Ecriture de products.json impossible.']];
    }

    /* L'ancien visuel n'est supprime qu'apres une ecriture reussie */
    if ($imagePath !== $oldImage) {
        il_product_delete_image($oldImage, $products);
    }

    il_log('product_updated', 'Produit modifie', ['nom' => trim((string) $input['nom'])]);

    return ['ok' => true, 'errors' => []];
}

/** Supprime une fiche et son visuel */
function il_product_delete($id)
{
    $products = il_products_all();
    $removed  = null;
    $kept     = [];

    foreach ($products as $product) {
        if (($product['id'] ?? '') === $id) {
            $removed = $product;
            continue;
        }
        $kept[] = $product;
    }

    if ($removed === null) {
        return ['ok' => false, 'error' => 'Produit introuvable.'];
    }

    if (!il_products_write($kept)) {
        return ['ok' => false, 'error' => 'Ecriture de products.json impossible.'];
    }

    il_product_delete_image($removed['image'] ?? '', $kept);

    il_log('product_deleted', 'Produit supprime du catalogue', [
        'nom' => $removed['nom'] ?? '',
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * Deplace une fiche dans l'ordre d'affichage.
 * $direction : -1 (vers le haut) ou 1 (vers le bas).
 */
function il_product_move($id, $direction)
{
    $products = il_products_all();
    $index    = null;

    foreach ($products as $position => $product) {
        if (($product['id'] ?? '') === $id) {
            $index = $position;
            break;
        }
    }

    $target = $index === null ? null : $index + ($direction < 0 ? -1 : 1);
    if ($index === null || $target < 0 || $target >= count($products)) {
        return false;
    }

    $swap              = $products[$target];
    $products[$target] = $products[$index];
    $products[$index]  = $swap;

    return il_products_write($products);
}

/** Verifie qu'un visuel reference existe bien sur le disque */
function il_product_image_exists($relativePath)
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
        return false;
    }
    return is_file(IL_ROOT . '/' . ltrim($relativePath, '/'));
}
