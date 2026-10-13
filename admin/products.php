<?php
/**
 * products.php - Gestion du catalogue produits
 *
 * Ajout, modification, reordonnancement et suppression des fiches de
 * products.json, visuel compris. Le fichier reste celui que lit la page
 * publique : aucune duplication de donnees.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_layout.php';

il_auth_require();

$errors = [];
$form   = ['id' => '', 'nom' => '', 'prix' => '', 'description' => '', 'image' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    il_csrf_require();
    $action = $_POST['action'] ?? '';
    $id     = (string) ($_POST['id'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $input = [
            'nom'            => $_POST['nom'] ?? '',
            'prix'           => $_POST['prix'] ?? '',
            'description'    => $_POST['description'] ?? '',
            'image_actuelle' => $_POST['image_actuelle'] ?? '',
        ];

        $result = $action === 'create'
            ? il_product_create($input)
            : il_product_update($id, $input);

        if ($result['ok']) {
            il_flash('success', $action === 'create'
                ? 'Produit ajoute au catalogue.'
                : 'Produit mis a jour.');
            il_redirect('products.php');
        }

        $errors = $result['errors'];
        $form = [
            'id'          => $id,
            'nom'         => (string) $input['nom'],
            'prix'        => (string) $input['prix'],
            'description' => (string) $input['description'],
            'image'       => (string) $input['image_actuelle'],
        ];
    }

    if ($action === 'delete') {
        $result = il_product_delete($id);
        if ($result['ok']) {
            il_flash('success', 'Produit supprime (visuel inclus).');
        } else {
            il_flash('error', $result['error']);
        }
        il_redirect('products.php');
    }

    if ($action === 'move-up' || $action === 'move-down') {
        il_product_move($id, $action === 'move-up' ? -1 : 1);
        il_redirect('products.php');
    }
}

/* Mode edition demande par l'URL */
$editing = null;
if (isset($_GET['edit']) && $form['id'] === '') {
    $editing = il_product_find((string) $_GET['edit']);
    if ($editing) {
        $form = [
            'id'          => $editing['id'],
            'nom'         => $editing['nom'],
            'prix'        => (string) $editing['prix'],
            'description' => $editing['description'],
            'image'       => $editing['image'],
        ];
    }
}
if ($form['id'] !== '' && $editing === null) {
    $editing = il_product_find($form['id']);
}

$products   = il_products_all();
$isEditing  = $form['id'] !== '' && $editing !== null;
$imgDirOk   = is_dir(IL_PRODUCTS_IMG_DIR) && is_writable(IL_PRODUCTS_IMG_DIR);
$jsonOk     = is_writable(IL_PRODUCTS_FILE);
$totalValue = array_sum(array_map(function ($p) {
    return (float) ($p['prix'] ?? 0);
}, $products));

il_admin_page_start('Catalogue produits', 'products');
?>

<div class="page-head">
    <h1>Catalogue produits</h1>
    <p>
        Gestion des fiches affichees sur la page publique « Nos Produits ».
        Les modifications sont ecrites dans <span class="mono">products.json</span>
        et visibles immediatement sur le site.
    </p>
</div>

<?php if (!$jsonOk || !$imgDirOk): ?>
    <div class="msg msg-error" role="alert">
        <strong>Ecriture impossible :</strong>
        <ul>
            <?php if (!$jsonOk): ?>
                <li>products.json n'est pas accessible en ecriture.</li>
            <?php endif; ?>
            <?php if (!$imgDirOk): ?>
                <li>assets/products n'est pas accessible en ecriture.</li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($errors['global'])): ?>
    <div class="msg msg-error" role="alert"><?php echo e($errors['global']); ?></div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:22px">
    <div class="card" style="margin:0">
        <p class="card-hint" style="margin:0">Produits au catalogue</p>
        <p style="font-size:1.9rem;font-weight:700;color:var(--navy)"><?php echo count($products); ?></p>
    </div>
    <div class="card" style="margin:0">
        <p class="card-hint" style="margin:0">Prix moyen</p>
        <p style="font-size:1.9rem;font-weight:700;color:var(--navy)">
            <?php echo $products ? number_format($totalValue / count($products), 2, ',', ' ') : '0,00'; ?> &euro;
        </p>
    </div>
    <div class="card" style="margin:0">
        <p class="card-hint" style="margin:0">Visuels manquants</p>
        <?php
        $missing = 0;
        foreach ($products as $p) {
            if (!il_product_image_exists($p['image'] ?? '')) {
                $missing++;
            }
        }
        ?>
        <p style="font-size:1.9rem;font-weight:700;color:<?php echo $missing ? 'var(--err)' : 'var(--ok)'; ?>">
            <?php echo (int) $missing; ?>
        </p>
    </div>
    <div class="card" style="margin:0">
        <p class="card-hint" style="margin:0">Page publique</p>
        <p style="margin-top:10px"><a class="btn btn-ghost" href="../products.php" target="_blank"
               rel="noopener">Voir le rendu</a></p>
    </div>
</div>

<div class="grid grid-2">

    <!-- ====================== FORMULAIRE ====================== -->
    <section class="card">
        <h2><?php echo $isEditing ? 'Modifier un produit' : 'Ajouter un produit'; ?></h2>
        <p class="card-hint">
            <?php echo $isEditing
                ? 'Laissez le champ visuel vide pour conserver l\'image actuelle.'
                : 'Les quatre champs sont requis, visuel compris.'; ?>
        </p>

        <form method="post" action="products.php" enctype="multipart/form-data">
            <?php echo il_csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
            <input type="hidden" name="id" value="<?php echo e($form['id']); ?>">
            <input type="hidden" name="image_actuelle" value="<?php echo e($form['image']); ?>">

            <div class="form-group">
                <label for="nom">Nom du produit</label>
                <input type="text" id="nom" name="nom" maxlength="120" required
                       value="<?php echo e($form['nom']); ?>"
                       <?php echo isset($errors['nom']) ? 'aria-invalid="true"' : ''; ?>>
                <?php if (isset($errors['nom'])): ?>
                    <p class="field-error"><?php echo e($errors['nom']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="prix">Prix en euros</label>
                <input type="text" id="prix" name="prix" inputmode="decimal" required
                       placeholder="59.99" value="<?php echo e($form['prix']); ?>"
                       <?php echo isset($errors['prix']) ? 'aria-invalid="true"' : ''; ?>>
                <?php if (isset($errors['prix'])): ?>
                    <p class="field-error"><?php echo e($errors['prix']); ?></p>
                <?php else: ?>
                    <p class="field-hint">Point ou virgule accepte.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="600" required
                          <?php echo isset($errors['description']) ? 'aria-invalid="true"' : ''; ?>><?php echo e($form['description']); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <p class="field-error"><?php echo e($errors['description']); ?></p>
                <?php else: ?>
                    <p class="field-hint">600 caracteres maximum.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="image">Visuel du produit</label>

                <?php if ($form['image'] !== ''): ?>
                    <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px">
                        <?php if (il_product_image_exists($form['image'])): ?>
                            <img src="../<?php echo e($form['image']); ?>" alt=""
                                 style="width:70px;height:70px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                        <?php else: ?>
                            <span class="badge badge-error">Fichier absent</span>
                        <?php endif; ?>
                        <span class="mono" style="font-size:.8rem;color:var(--muted)">
                            <?php echo e($form['image']); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <input type="file" id="image" name="image"
                       accept=".jpg,.jpeg,.png,.webp,.gif"
                       <?php echo isset($errors['image']) ? 'aria-invalid="true"' : ''; ?>>
                <?php if (isset($errors['image'])): ?>
                    <p class="field-error"><?php echo e($errors['image']); ?></p>
                <?php else: ?>
                    <p class="field-hint">
                        JPG, PNG, WEBP ou GIF, <?php echo e(il_human_size(IL_IMG_MAX_SIZE)); ?> maximum.
                        Le fichier est renomme et son contenu verifie a l'envoi.
                    </p>
                <?php endif; ?>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary" data-loading
                        data-busy-text="Enregistrement...">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="btn-label"><?php echo $isEditing ? 'Enregistrer les modifications' : 'Ajouter le produit'; ?></span>
                </button>
                <?php if ($isEditing): ?>
                    <a class="btn btn-ghost" href="products.php">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- ====================== LISTE ====================== -->
    <section class="card">
        <h2>Fiches existantes</h2>
        <p class="card-hint">
            L'ordre ci-dessous est celui de la page publique.
        </p>

        <?php if (!$products): ?>
            <div class="empty">
                <strong>Catalogue vide</strong>
                Ajoutez un premier produit avec le formulaire ci-contre.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data" style="min-width:480px">
                    <caption class="sr-only">Produits du catalogue</caption>
                    <thead>
                        <tr><th>Visuel</th><th>Produit</th><th>Prix</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $position => $product): ?>
                            <tr<?php echo $form['id'] === $product['id'] ? ' style="background:#eff6ff"' : ''; ?>>
                                <td>
                                    <?php if (il_product_image_exists($product['image'] ?? '')): ?>
                                        <img src="../<?php echo e($product['image']); ?>" alt=""
                                             loading="lazy"
                                             style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                                    <?php else: ?>
                                        <span class="badge badge-error">absent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo e($product['nom']); ?></strong><br>
                                    <span class="field-hint">
                                        <?php echo e(mb_strimwidth($product['description'], 0, 70, '...')); ?>
                                    </span>
                                </td>
                                <td class="mono">
                                    <?php echo e(number_format((float) $product['prix'], 2, ',', ' ')); ?> &euro;
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <a class="btn btn-ghost" style="padding:6px 12px;font-size:.82rem"
                                           href="products.php?edit=<?php echo e($product['id']); ?>">Modifier</a>

                                        <?php if ($position > 0): ?>
                                            <form method="post" action="products.php">
                                                <?php echo il_csrf_field(); ?>
                                                <input type="hidden" name="action" value="move-up">
                                                <input type="hidden" name="id" value="<?php echo e($product['id']); ?>">
                                                <button type="submit" class="btn btn-ghost"
                                                        style="padding:6px 10px;font-size:.82rem"
                                                        title="Monter" aria-label="Monter <?php echo e($product['nom']); ?>">&uarr;</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($position < count($products) - 1): ?>
                                            <form method="post" action="products.php">
                                                <?php echo il_csrf_field(); ?>
                                                <input type="hidden" name="action" value="move-down">
                                                <input type="hidden" name="id" value="<?php echo e($product['id']); ?>">
                                                <button type="submit" class="btn btn-ghost"
                                                        style="padding:6px 10px;font-size:.82rem"
                                                        title="Descendre" aria-label="Descendre <?php echo e($product['nom']); ?>">&darr;</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" action="products.php"
                                              data-confirm="La fiche produit et son visuel vont etre definitivement supprimes."
                                              data-confirm-title="Supprimer ce produit"
                                              data-confirm-label="Supprimer"
                                              data-confirm-scope="<strong>Produit concerne :</strong><ul><li><?php echo e($product['nom']); ?> (<?php echo e(number_format((float) $product['prix'], 2, ',', ' ')); ?> euros)</li></ul>Le visuel n'est supprime que s'il n'est utilise par aucune autre fiche. Action irreversible.">
                                            <?php echo il_csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo e($product['id']); ?>">
                                            <button type="submit" class="btn btn-danger"
                                                    style="padding:6px 12px;font-size:.82rem" data-loading>
                                                <span class="spinner" aria-hidden="true"></span>
                                                <span class="btn-label">Supprimer</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php il_admin_page_end(); ?>
