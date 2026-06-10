<?php
/**
 * Bootstrapper principal du plugin.
 *
 * Chef d'orchestre : instancie et connecte tous les modules.
 * Ne contient aucune logique métier.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup(): void {
        throw new \RuntimeException( 'Le singleton Plugin ne peut pas être désérialisé.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
    private function __construct() {}

    /**
     * Démarre tous les modules du plugin.
     * Ajouter un nouveau module = ajouter une ligne ici.
     */
    public function boot(): void {
        Installer::maybe_upgrade();
        ( new \Givoly\Core\AssetsLoader() )->register();
        ( new \Givoly\Admin\AdminMenu() )->register();
        ( new \Givoly\Admin\AdminActions() )->register();
        ( new \Givoly\Admin\Pages\SettingsPage() )->register();
        ( new \Givoly\Admin\Pages\CampaignsPage() )->register();
        ( new \Givoly\Form\ShortcodeManager() )->register();
        ( new \Givoly\Ajax\AjaxHandler() )->register();
    }
}
