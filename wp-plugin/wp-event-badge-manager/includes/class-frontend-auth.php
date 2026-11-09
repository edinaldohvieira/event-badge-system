<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Frontend_Auth
{
    public function __construct()
    {
        add_shortcode('ebm_cadastro_gestor', [$this, 'render_cadastro_form']);
        add_action('init', [$this, 'processar_cadastro']);
    }

    public function processar_cadastro(): void
    {
        if (!isset($_POST['ebm_action']) || $_POST['ebm_action'] !== 'registrar_gestor') {
            return;
        }

        if (!isset($_POST['ebm_reg_nonce']) || !wp_verify_nonce($_POST['ebm_reg_nonce'], 'ebm_registrar_gestor')) {
            wp_die('Falha de segurança na requisição.');
        }

        $nome         = sanitize_text_field($_POST['nome'] ?? '');
        $organizacao  = sanitize_text_field($_POST['organizacao'] ?? '');
        $email        = sanitize_email($_POST['email'] ?? '');
        $senha        = $_POST['senha'] ?? '';

        if (empty($nome) || empty($organizacao) || !is_email($email) || strlen($senha) < 6) {
            wp_safe_redirect(add_query_arg('ebm_reg_erro', 'dados_invalidos', wp_get_referer()));
            exit;
        }

        if (email_exists($email)) {
            wp_safe_redirect(add_query_arg('ebm_reg_erro', 'email_existente', wp_get_referer()));
            exit;
        }

        // Cria o usuário com o perfil restrito "event_manager"
        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $senha,
            'display_name' => $nome,
            'role'         => 'event_manager',
        ]);

        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('ebm_reg_erro', 'erro_criacao', wp_get_referer()));
            exit;
        }

        update_user_meta($user_id, '_ebm_organizacao', $organizacao);

        // Faz login automático após o cadastro
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_safe_redirect(remove_query_arg('ebm_reg_erro', wp_get_referer()));
        exit;
    }

    public function render_cadastro_form(): string
    {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return '<div style="padding:15px; background:#f0fdf4; border:1px solid #86efac; border-radius:6px;">
                Você já está conectado como <strong>' . esc_html($user->display_name) . '</strong>. 
                <a href="' . esc_url(wp_logout_url(get_permalink())) . '">Sair</a>
            </div>';
        }

        $erro = sanitize_text_field($_GET['ebm_reg_erro'] ?? '');
        $mensagens = [
            'dados_invalidos' => 'Preencha todos os campos corretamente (senha mínima de 6 caracteres).',
            'email_existente' => 'Este e-mail já está cadastrado no sistema.',
            'erro_criacao'    => 'Não foi possível concluir o cadastro. Tente novamente.',
        ];

        ob_start();
        ?>
        <div style="max-width: 450px; margin: 20px auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;">
            <h3 style="margin-top:0;">Cadastro de Gestor de Eventos</h3>

            <?php if ($erro && isset($mensagens[$erro])) : ?>
                <p style="color: #dc2626; background: #fef2f2; padding: 10px; border-radius: 4px;">
                    <?php echo esc_html($mensagens[$erro]); ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="ebm_action" value="registrar_gestor" />
                <?php wp_nonce_field('ebm_registrar_gestor', 'ebm_reg_nonce'); ?>

                <p>
                    <label>Seu Nome Completo *</label><br>
                    <input type="text" name="nome" required style="width:100%; padding:8px;" />
                </p>
                <p>
                    <label>Nome da Organização / Empresa *</label><br>
                    <input type="text" name="organizacao" required style="width:100%; padding:8px;" />
                </p>
                <p>
                    <label>E-mail de Acesso *</label><br>
                    <input type="email" name="email" required style="width:100%; padding:8px;" />
                </p>
                <p>
                    <label>Senha (mín. 6 caracteres) *</label><br>
                    <input type="password" name="senha" minlength="6" required style="width:100%; padding:8px;" />
                </p>
                <button type="submit" style="width:100%; padding:10px; background:#0f172a; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                    Criar Conta de Gestor
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
