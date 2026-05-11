<?php
/**
 * Bootstrapper principal du plugin.
 *
 * Chef d'orchestre : instancie et connecte tous les modules.
 * Ne contient aucune logique métier.
 *
 * @package Givasso\Core
 */

namespace Givasso\Core;

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
        ( new \Givasso\Core\AssetsLoader() )->register();
        ( new \Givasso\Admin\AdminMenu() )->register();
        ( new \Givasso\Admin\AdminActions() )->register();
        ( new \Givasso\Admin\Pages\SettingsPage() )->register();
        ( new \Givasso\Admin\Pages\CampaignsPage() )->register();
        ( new \Givasso\Form\ShortcodeManager() )->register();
        ( new \Givasso\Ajax\AjaxHandler() )->register();
    }
}
