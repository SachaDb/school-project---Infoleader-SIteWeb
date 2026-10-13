<?php
/**
 * logout.php - Fermeture de la session d'administration
 *
 * Le lien de deconnexion porte un jeton : une requete forgee par un
 * site tiers ne peut pas deconnecter l'administrateur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

il_session_start();

if (!il_auth_check()) {
    il_redirect('login.php');
}

if (!il_csrf_check($_GET['token'] ?? '')) {
    il_flash('error', 'Lien de deconnexion invalide ou expire. Reessayez depuis le panneau.');
    il_redirect('dashboard.php');
}

il_auth_logout();

il_flash('success', 'Vous etes deconnecte. La session a ete detruite.');
il_redirect('login.php');
