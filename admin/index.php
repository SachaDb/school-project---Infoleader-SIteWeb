<?php
/**
 * index.php - Point d'entree du panneau d'administration
 *
 * Aucune page n'est accessible sans authentification : on redirige
 * vers le tableau de bord si la session est valide, vers la connexion
 * sinon (ou vers l'initialisation si aucun compte n'existe).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

il_session_start();

if (!il_admin_exists()) {
    il_redirect('setup.php');
}

il_redirect(il_auth_check() ? 'dashboard.php' : 'login.php');
