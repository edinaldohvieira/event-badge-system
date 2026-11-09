<?php
/**
 * Plugin Name: Event Badge Manager
 * Description: Gestão multi-organização de eventos e geração de crachás via microserviço Docker.
 * Version: 0.1.0
 * Author: Edinaldo H Vieira
 * Text Domain: wp-event-badge-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EBM_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once EBM_PLUGIN_DIR . 'includes/class-roles-cpts.php';
require_once EBM_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once EBM_PLUGIN_DIR . 'includes/class-badge-api-client.php';
require_once EBM_PLUGIN_DIR . 'includes/class-front-portal.php';
require_once EBM_PLUGIN_DIR . 'includes/class-checkin-portal.php';

// Inicializa classes
add_action('plugins_loaded', function () {
    new EBM_Roles_CPTs();
    new EBM_Admin_Settings();
    new EBM_Front_Portal();
    new EBM_Checkin_Portal();
});

// Hook de ativação: cria a role customizada e atualiza regras de URL
register_activation_hook(__FILE__, function () {
    EBM_Roles_CPTs::register_manager_role();
    flush_rewrite_rules();
});

// Hook de desativação
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
