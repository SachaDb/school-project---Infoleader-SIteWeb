<?php
/**
 * products.php - Catalogue produits
 *
 * Les fiches sont rendues côté serveur depuis products.json : la page
 * fonctionne sans JavaScript, et la recherche en temps réel se contente
 * de filtrer les cartes déjà présentes.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$produits = il_products_all();
$nombre   = count($produits);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catalogue InfoLeader : claviers, souris, écrans, casques, webcams et accessoires informatiques sélectionnés.">
    <title>InfoLeader — Nos produits</title>

    <script>document.documentElement.classList.add("js");</script>
    <link rel="stylesheet" href="<?php echo e(il_asset('style.css')); ?>">
    <script src="<?php echo e(il_asset('script.js')); ?>" defer></script>
</head>
<body>

    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="site-header">
        <nav>
            <a href="index.php" class="logo">InfoLeader</a>

            <button class="burger" type="button" aria-expanded="false"
                    aria-controls="menu-principal" aria-label="Ouvrir le menu">
                <span></span><span></span><span></span>
            </button>

            <ul class="menu" id="menu-principal">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="products.php" class="active">Nos produits</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </header>

    <section class="page-title">
        <h1>Nos produits</h1>
        <p>
            Une sélection de périphériques et d'écrans testés par notre équipe.
            Utilisez la recherche pour trouver rapidement votre matériel.
        </p>
    </section>

    <main id="contenu">
        <div class="section" style="padding-top:52px">

            <div class="search-bar reveal">
                <label class="sr-only" for="searchInput">Rechercher un produit</label>
                <input type="search" id="searchInput" placeholder="Rechercher un produit…"
                       autocomplete="off" aria-describedby="searchCount">
            </div>

            <p class="search-count" id="searchCount" role="status">
                <?php echo $nombre; ?> produit<?php echo $nombre > 1 ? 's' : ''; ?> au catalogue
            </p>

            <?php if (!$produits): ?>

                <p class="no-results" style="display:block">
                    Le catalogue est en cours de mise à jour. Revenez très bientôt.
                </p>

            <?php else: ?>

                <section class="products" id="productGrid">
                    <?php foreach ($produits as $index => $produit): ?>
                        <article class="product reveal" data-delay="<?php echo ($index % 3) + 1; ?>"
                                 data-nom="<?php echo e(mb_strtolower($produit['nom'])); ?>"
                                 data-description="<?php echo e(mb_strtolower($produit['description'])); ?>">
                            <div class="thumb">
                                <img src="<?php echo e($produit['image']); ?>"
                                     alt="<?php echo e($produit['nom']); ?>" loading="lazy">
                            </div>
                            <div class="body">
                                <h3><?php echo e($produit['nom']); ?></h3>
                                <p><?php echo e($produit['description']); ?></p>
                                <div class="price">
                                    <span class="montant">
                                        <?php echo e(number_format((float) $produit['prix'], 2, ',', ' ')); ?>&nbsp;&euro;
                                    </span>
                                    <a href="contact.php" class="buy">Commander</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <p class="no-results" id="noResults">
                    Aucun produit ne correspond à votre recherche.
                </p>

            <?php endif; ?>
        </div>

        <section class="cta-band reveal">
            <h2>Besoin d'un conseil avant d'acheter ?</h2>
            <p>
                Décrivez-nous votre usage : nous vous orientons vers le matériel
                qui correspond vraiment à votre besoin.
            </p>
            <a href="contact.php" class="cta">Demander un conseil</a>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <h3>InfoLeader</h3>
                <p>
                    Société informatique : matériel, conseil et accompagnement
                    pour les particuliers et les professionnels.
                </p>
            </div>
            <div>
                <h3>Navigation</h3>
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="products.php">Nos produits</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3>Nous joindre</h3>
                <ul>
                    <li>12 rue de l'Informatique, 75000 Paris</li>
                    <li><a href="tel:+33123456789">01 23 45 67 89</a></li>
                    <li><a href="mailto:contact@infoleader.local">contact@infoleader.local</a></li>
                    <li>Du lundi au vendredi, 9h00 – 18h00</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> InfoLeader. Tous droits réservés.</p>
            <p><a href="admin/">Espace d'administration</a></p>
        </div>
    </footer>

    <script>
        /* Recherche en temps réel : filtre les cartes déjà rendues par PHP,
           sans rechargement ni requête réseau. */
        (function () {
            var champ = document.getElementById('searchInput');
            var grille = document.getElementById('productGrid');
            var vide = document.getElementById('noResults');
            var compteur = document.getElementById('searchCount');

            if (!champ || !grille) {
                return;
            }

            var cartes = Array.prototype.slice.call(grille.querySelectorAll('.product'));
            var total = cartes.length;

            function libelle(nombre, recherche) {
                if (recherche === '') {
                    return total + ' produit' + (total > 1 ? 's' : '') + ' au catalogue';
                }
                if (nombre === 0) {
                    return 'Aucun résultat pour « ' + recherche + ' »';
                }
                return nombre + ' produit' + (nombre > 1 ? 's' : '')
                    + ' sur ' + total + ' pour « ' + recherche + ' »';
            }

            function filtrer() {
                var recherche = champ.value.trim().toLowerCase();
                var visibles = 0;

                cartes.forEach(function (carte) {
                    var correspond = recherche === ''
                        || carte.dataset.nom.indexOf(recherche) !== -1
                        || carte.dataset.description.indexOf(recherche) !== -1;

                    carte.hidden = !correspond;
                    if (correspond) {
                        visibles++;
                        /* Une carte filtrée puis réaffichée reste visible */
                        carte.classList.add('visible');
                    }
                });

                if (vide) {
                    vide.style.display = visibles === 0 ? 'block' : 'none';
                }
                if (compteur) {
                    compteur.textContent = libelle(visibles, champ.value.trim());
                }
            }

            champ.addEventListener('input', filtrer);
        })();
    </script>

</body>
</html>
