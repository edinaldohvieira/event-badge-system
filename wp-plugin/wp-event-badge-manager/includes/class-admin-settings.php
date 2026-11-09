<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Admin_Settings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_ebm_test_connection', [$this, 'handle_test_connection']);
    }

    public function add_settings_page(): void
    {
        add_options_page(
            'Configurações de Crachás',
            'Gerador de Crachás',
            'manage_options',
            'ebm-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('ebm_settings_group', 'ebm_api_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'http://cracha-php/api/gerar.php',
        ]);

        register_setting('ebm_settings_group', 'ebm_api_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    /**
     * Processa o clique no botão de teste rápido e redireciona com o resultado
     */
    public function handle_test_connection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado.');
        }

        check_admin_referer('ebm_test_nonce');

        $dados_teste = [
            'nome'         => 'Teste Integração WP',
            'cargo'        => 'Administrador do Sistema',
            'empresa'      => get_bloginfo('name') ?: 'WordPress Lab',
            'categoria'    => 'CONEXÃO OK',
            'codigo'       => 'WP-TEST-' . wp_rand(1000, 9999),
            'cor_primaria' => '#38bdf8',
        ];

        $resultado = EBM_Badge_API_Client::gerar_e_salvar_cracha($dados_teste);

        $redirect_url = admin_url('options-general.php?page=ebm-settings');

        if (is_wp_error($resultado)) {
            $redirect_url = add_query_arg([
                'ebm_status' => 'error',
                'ebm_msg'    => urlencode($resultado->get_error_message()),
            ], $redirect_url);
        } else {
            $redirect_url = add_query_arg([
                'ebm_status' => 'success',
                'ebm_img'    => urlencode($resultado),
            ], $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function render_settings_page(): void
    {
        $status = sanitize_text_field($_GET['ebm_status'] ?? '');
        $msg    = sanitize_text_field(wp_unslash($_GET['ebm_msg'] ?? ''));
        $img    = esc_url_raw(wp_unslash($_GET['ebm_img'] ?? ''));
        ?>
        <div class="wrap">
            <h1>Configurações da API de Crachás</h1>

            <?php if ($status === 'success' && $img) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Sucesso!</strong> O WordPress comunicou-se com o container Docker e salvou o crachá.</p>
                </div>
            <?php elseif ($status === 'error') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Falha na conexão:</strong> <?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('ebm_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ebm_api_url">URL do Microserviço</label></th>
                        <td>
                            <input type="url" id="ebm_api_url" name="ebm_api_url"
                                   value="<?php echo esc_attr(get_option('ebm_api_url', 'http://cracha-php/api/gerar.php')); ?>"
                                   class="regular-text" required />
                            <p class="description">No Docker local, utilize: <code>http://cracha-php/api/gerar.php</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ebm_api_token">Bearer Token</label></th>
                        <td>
                            <input type="password" id="ebm_api_token" name="ebm_api_token"
                                   value="<?php echo esc_attr(get_option('ebm_api_token')); ?>"
                                   class="regular-text" required />
                            <p class="description">Deve ser idêntico ao <code>API_SECRET_TOKEN</code> do microserviço.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Salvar Configurações'); ?>
            </form>

            <hr style="margin: 30px 0;" />

            <h2>Teste Rápido de Integração entre Containers</h2>
            <p>Clique no botão abaixo para disparar uma requisição <code>POST</code> simulada para o microserviço e verificar se o arquivo PNG é gerado e salvo no WordPress.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ebm_test_connection" />
                <?php wp_nonce_field('ebm_test_nonce'); ?>
                <?php submit_button('Gerar Crachá de Teste Agora', 'secondary'); ?>
            </form>

            <?php if ($status === 'success' && $img) : ?>
                <div style="margin-top: 25px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; max-width: 340px;">
                    <h3 style="margin-top: 0;">Resultado Gerado:</h3>
                    <img src="<?php echo esc_url($img); ?>" alt="Crachá de Teste" style="width: 100%; height: auto; border: 1px solid #ddd;" />
                    <p style="margin-bottom: 0;">
                        <a href="<?php echo esc_url($img); ?>" target="_blank" class="button button-small">Abrir imagem original (1200x1800px)</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
