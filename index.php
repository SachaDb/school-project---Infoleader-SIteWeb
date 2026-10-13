<?php
/**
 * index.php - Page d'accueil InfoLeader
 *
 * Site vitrine d'une entreprise informatique fictive.
 * Les produits mis en avant proviennent de products.json, c'est-à-dire
 * du catalogue réellement géré depuis le panneau d'administration.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$produits = il_products_all();
$phares   = array_slice($produits, 0, 3);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="InfoLeader, société informatique : matériel, conseil et accompagnement pour les particuliers et les entreprises.">
    <title>InfoLeader — Votre partenaire technologique</title>

    <?php /* Posée avant le rendu : sans JavaScript, aucune section n'est masquée */ ?>
    <script>document.documentElement.classList.add("js");</script>

    <!-- Feuille de style partagée par les trois pages du site -->
    <link rel="stylesheet" href="<?php echo e(il_asset('style.css')); ?>">

    <!-- Animations : apparitions au défilement, compteurs, menu mobile -->
    <script src="<?php echo e(il_asset('script.js')); ?>" defer></script>
</head>
<body>

    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <!-- ========================= EN-TÊTE ========================= -->
    <header class="site-header">
        <nav>
            <a href="index.php" class="logo">InfoLeader</a>

            <button class="burger" type="button" aria-expanded="false"
                    aria-controls="menu-principal" aria-label="Ouvrir le menu">
                <span></span><span></span><span></span>
            </button>

            <ul class="menu" id="menu-principal">
                <li><a href="index.php" class="active">Accueil</a></li>
                <li><a href="products.php">Nos produits</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </header>

    <!-- ========================= BANNIÈRE ========================= -->
    <section class="banner" id="banner" aria-labelledby="slogan">
        <span class="trame" aria-hidden="true"></span>

        <div class="banner-inner">
            <p class="eyebrow">
                <span class="pulse" aria-hidden="true"></span>
                Matériel &amp; services informatiques
            </p>

            <h1 class="slogan" id="slogan">
                Votre partenaire <em>technologique</em> de confiance
            </h1>

            <p class="accroche">
                Conseil, matériel sélectionné et accompagnement de proximité :
                nous équipons votre quotidien avec des solutions fiables, du
                premier échange à la mise en service.
            </p>

            <div class="banner-actions">
                <a href="products.php" class="cta" id="cta">Découvrir nos produits</a>
                <a href="contact.php" class="btn-ghost-light">Nous contacter</a>
            </div>
        </div>

        <a class="scroll-cue" href="#contenu" aria-label="Aller au contenu">
            Découvrir
            <span class="chevron" aria-hidden="true"></span>
        </a>
    </section>

    <main id="contenu">

        <!-- ===================== NOS ENGAGEMENTS ===================== -->
        <section class="section">
            <div class="section-head reveal">
                <span class="section-tag">Nos engagements</span>
                <h2>Une approche simple et efficace</h2>
                <p>
                    Nous privilégions la performance, la fiabilité et la simplicité,
                    pour des équipements qui durent et des conseils qui servent.
                </p>
            </div>

            <div class="cards">
                <article class="card-feature reveal" data-delay="1">
                    <div class="icone" aria-hidden="true">&#9881;</div>
                    <h3>Matériel sélectionné</h3>
                    <p>
                        Périphériques et écrans choisis pour leur rapport
                        qualité-prix, testés avant d'entrer au catalogue.
                    </p>
                </article>

                <article class="card-feature reveal" data-delay="2">
                    <div class="icone" aria-hidden="true">&#128172;</div>
                    <h3>Conseil personnalisé</h3>
                    <p>
                        Nous partons de votre usage réel, pas d'une fiche technique :
                        bureautique, création, jeu ou mobilité.
                    </p>
                </article>

                <article class="card-feature reveal" data-delay="3">
                    <div class="icone" aria-hidden="true">&#128736;</div>
                    <h3>Installation et suivi</h3>
                    <p>
                        Mise en service, transfert de données et prise en main :
                        votre poste est opérationnel dès la livraison.
                    </p>
                </article>

                <article class="card-feature reveal" data-delay="4">
                    <div class="icone" aria-hidden="true">&#128274;</div>
                    <h3>Assistance de proximité</h3>
                    <p>
                        Un interlocuteur unique, joignable, qui connaît votre
                        configuration et votre historique.
                    </p>
                </article>
            </div>
        </section>

        <!-- ===================== CHIFFRES ===================== -->
        <section class="stats" aria-label="InfoLeader en chiffres">
            <div class="stats-grid">
                <div class="stat reveal" data-delay="1">
                    <div class="valeur" data-count="<?php echo count($produits); ?>">0</div>
                    <div class="libelle">Références au catalogue</div>
                </div>
                <div class="stat reveal" data-delay="2">
                    <div class="valeur" data-count="12" data-suffix="&nbsp;ans">0</div>
                    <div class="libelle">D'expérience technique</div>
                </div>
                <div class="stat reveal" data-delay="3">
                    <div class="valeur" data-count="24" data-suffix="&nbsp;h">0</div>
                    <div class="libelle">Délai de réponse moyen</div>
                </div>
                <div class="stat reveal" data-delay="4">
                    <div class="valeur" data-count="98" data-suffix="&nbsp;%">0</div>
                    <div class="libelle">Clients satisfaits</div>
                </div>
            </div>
        </section>

        <!-- ===================== QUI SOMMES-NOUS ===================== -->
        <section class="section">
            <div class="split">
                <div class="texte reveal">
                    <span class="section-tag">Qui sommes-nous</span>
                    <h2>Une équipe passionnée, à vos côtés</h2>
                    <p>
                        InfoLeader est une société informatique spécialisée dans les
                        solutions techniques pour les particuliers et les professionnels.
                        Nous accompagnons nos clients dans le choix et la mise en place
                        de leur matériel, en alliant performance, fiabilité et simplicité.
                    </p>
                    <p>
                        Forte d'une équipe expérimentée, notre entreprise propose une
                        large gamme de périphériques et de services destinés à équiper
                        votre quotidien.
                    </p>

                    <ul class="liste-atouts">
                        <li>Devis clair, sans engagement et sans jargon inutile</li>
                        <li>Garantie constructeur suivie par nos soins</li>
                        <li>Reprise et recyclage de votre ancien matériel</li>
                        <li>Interventions sur site ou à distance</li>
                    </ul>

                    <p style="margin-top:26px">
                        <a href="contact.php" class="cta">Demander un conseil</a>
                    </p>
                </div>

                <div class="visuel reveal" data-delay="2">
                    <img src="assets/banner.jpg" alt="L'équipe InfoLeader au travail">
                    <div class="badge-flottant">
                        <strong>Du premier contact à la mise en service</strong>
                        <span>Un interlocuteur unique pour tout votre équipement</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== PRODUITS PHARES ===================== -->
        <section class="section" style="padding-top:0">
            <div class="section-head reveal">
                <span class="section-tag">Sélection</span>
                <h2>Nos produits phares</h2>
                <p>
                    Un extrait de notre catalogue, mis à jour depuis notre espace
                    de gestion.
                </p>
            </div>

            <?php if (!$phares): ?>
                <p class="no-results">Le catalogue est en cours de mise à jour.</p>
            <?php else: ?>
                <div class="products">
                    <?php foreach ($phares as $index => $produit): ?>
                        <article class="product reveal" data-delay="<?php echo $index + 1; ?>">
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
                </div>

                <p style="text-align:center;margin-top:44px">
                    <a href="products.php" class="cta">Voir tout le catalogue</a>
                </p>
            <?php endif; ?>
        </section>

        <!-- ===================== APPEL À L'ACTION ===================== -->
        <section class="cta-band reveal">
            <h2>Un projet d'équipement en tête ?</h2>
            <p>
                Dites-nous ce dont vous avez besoin : nous vous répondons avec une
                proposition claire et adaptée à votre usage.
            </p>
            <a href="contact.php" class="cta">Parler de mon projet</a>
        </section>

    </main>

    <!-- ========================= PIED DE PAGE ========================= -->
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

</body>
</html>
