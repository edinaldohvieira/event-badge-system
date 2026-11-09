<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Roles_CPTs
{
    public function __construct()
    {
        add_action('init', [$this, 'register_post_types']);
    }

    /**
     * Cria o perfil "Gestor de Eventos" com permissões básicas de upload e leitura
     */
    public static function register_manager_role(): void
    {
        add_role(
            'event_manager',
            'Gestor de Eventos',
            [
                'read'         => true,
                'upload_files' => true,
            ]
        );
    }

    /**
     * Registra os Custom Post Types: Eventos e Participantes
     */
    public function register_post_types(): void
    {
        // 1. CPT: Eventos
        register_post_type('org_evento', [
            'labels' => [
                'name'          => 'Eventos',
                'singular_name' => 'Evento',
            ],
            'public'       => false,
            'show_ui'      => true, // Visível no wp-admin para supervisão do Super Admin
            'menu_icon'    => 'dashicons-calendar-alt',
            'supports'     => ['title', 'author', 'thumbnail'],
        ]);

        // 2. CPT: Participantes
        register_post_type('org_participante', [
            'labels' => [
                'name'          => 'Participantes',
                'singular_name' => 'Participante',
            ],
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-id-alt',
            'supports'     => ['title', 'author'],
        ]);
    }
}
