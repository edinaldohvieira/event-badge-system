<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Frontend_Dashboard
{
    public function __construct()
    {
        add_shortcode('ebm_painel_gestor', [$this, 'render_dashboard']);
        add_action('init', [$this, 'processar_acoes_painel']);
    }

    /**
     * Auxiliar para upload seguro de imagens no front-end para a Biblioteca de Mídia
     */
    private function fazer_upload_imagem(string $campo_file): int
    {
        if (empty($_FILES[$campo_file]['name'])) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload($campo_file, 0);
        return is_wp_error($attachment_id) ? 0 : (int) $attachment_id;
    }

    /**
     * Processa criação de Evento, criação de Participante e acionamento do Crachá
     */
    public function processar_acoes_painel(): void
    {
        if (!is_user_logged_in() || !isset($_POST['ebm_dash_action'])) {
            return;
        }

        $user_id = get_current_user_id();
        $action  = sanitize_text_field($_POST['ebm_dash_action']);

        // 1. CRIAR NOVO EVENTO
        if ($action === 'criar_evento' && wp_verify_nonce($_POST['ebm_nonce'] ?? '', 'ebm_criar_evento')) {
            $titulo = sanitize_text_field($_POST['titulo_evento'] ?? '');
            $cor    = sanitize_hex_color($_POST['cor_primaria'] ?? '#38bdf8') ?: '#38bdf8';

            if ($titulo) {
                $evento_id = wp_insert_post([
                    'post_type'   => 'org_evento',
                    'post_title'  => $titulo,
                    'post_status' => 'publish',
                    'post_author' => $user_id,
                ]);

                if ($evento_id && !is_wp_error($evento_id)) {
                    update_post_meta($evento_id, '_evento_cor_primaria', $cor);
                    $logo_id = $this->fazer_upload_imagem('logo_evento');
                    if ($logo_id) {
                        update_post_meta($evento_id, '_evento_logo_id', $logo_id);
                    }
                }
            }
            wp_safe_redirect(add_query_arg('evento_id', $evento_id, remove_query_arg('ebm_msg', wp_get_referer())));
            exit;
        }

        // 2. CADASTRAR PARTICIPANTE NO EVENTO
        if ($action === 'criar_participante' && wp_verify_nonce($_POST['ebm_nonce'] ?? '', 'ebm_criar_participante')) {
            $evento_id = (int) ($_POST['evento_id'] ?? 0);
            $evento    = get_post($evento_id);

            // Garante isolamento: o evento deve pertencer ao gestor logado
            if (!$evento || (int) $evento->post_author !== $user_id) {
                wp_die('Você não tem permissão para adicionar participantes neste evento.');
            }

            $nome      = sanitize_text_field($_POST['nome_participante'] ?? '');
            $cargo     = sanitize_text_field($_POST['cargo'] ?? '');
            $empresa   = sanitize_text_field($_POST['empresa'] ?? '');
            $categoria = sanitize_text_field($_POST['categoria'] ?? 'PARTICIPANTE');

            if ($nome) {
                $part_id = wp_insert_post([
                    'post_type'   => 'org_participante',
                    'post_title'  => $nome,
                    'post_parent' => $evento_id,
                    'post_status' => 'publish',
                    'post_author' => $user_id,
                ]);

                if ($part_id && !is_wp_error($part_id)) {
                    $codigo = 'EVT-' . $evento_id . '-' . str_pad((string) $part_id, 4, '0', STR_PAD_LEFT);
                    update_post_meta($part_id, '_part_cargo', $cargo);
                    update_post_meta($part_id, '_part_empresa', $empresa);
                    update_post_meta($part_id, '_part_categoria', $categoria);
                    update_post_meta($part_id, '_part_codigo_unico', $codigo);

                    $foto_id = $this->fazer_upload_imagem('foto_participante');
                    if ($foto_id) {
                        update_post_meta($part_id, '_part_foto_id', $foto_id);
                    }
                }
            }
            wp_safe_redirect(add_query_arg(['evento_id' => $evento_id, 'ebm_msg' => 'part_criado'], wp_get_referer()));
            exit;
        }

        // 3. ACIONAR MICROSERVIÇO PARA GERAR CRACHÁ DO PARTICIPANTE
        if ($action === 'gerar_cracha' && wp_verify_nonce($_POST['ebm_nonce'] ?? '', 'ebm_gerar_cracha')) {
            $part_id = (int) ($_POST['participante_id'] ?? 0);
            $part    = get_post($part_id);

            if (!$part || (int) $part->post_author !== $user_id) {
                wp_die('Acesso negado ao participante.');
            }

            $evento_id   = (int) $part->post_parent;
            $logo_id     = (int) get_post_meta($evento_id, '_evento_logo_id', true);
            $cor_evento  = get_post_meta($evento_id, '_evento_cor_primaria', true) ?: '#38bdf8';
            $foto_id     = (int) get_post_meta($part_id, '_part_foto_id', true);

            $payload = [
                'nome'         => $part->post_title,
                'cargo'        => get_post_meta($part_id, '_part_cargo', true),
                'empresa'      => get_post_meta($part_id, '_part_empresa', true),
                'categoria'    => get_post_meta($part_id, '_part_categoria', true),
                'codigo'       => get_post_meta($part_id, '_part_codigo_unico', true),
                'cor_primaria' => $cor_evento,
                'logo_base64'  => EBM_Badge_API_Client::attachment_to_base64($logo_id),
                'foto_base64'  => EBM_Badge_API_Client::attachment_to_base64($foto_id),
            ];

            $resultado = EBM_Badge_API_Client::gerar_e_salvar_cracha($payload);

            if (!is_wp_error($resultado)) {
                update_post_meta($part_id, '_part_cracha_url', $resultado);
                $msg = 'cracha_gerado';
            } else {
                $msg = 'cracha_erro';
            }

            wp_safe_redirect(add_query_arg(['evento_id' => $evento_id, 'ebm_msg' => $msg], wp_get_referer()));
            exit;
        }
    }

    public function render_dashboard(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para acessar o painel de eventos. ' . wp_login_form(['echo' => false]) . '</p>';
        }

        $user_id     = get_current_user_id();
        $organizacao = get_user_meta($user_id, '_ebm_organizacao', true) ?: 'Minha Organização';
        $evento_sel  = (int) ($_GET['evento_id'] ?? 0);
        $msg         = sanitize_text_field($_GET['ebm_msg'] ?? '');

        // Busca apenas os eventos criados pelo gestor logado
        $meus_eventos = get_posts([
            'post_type'      => 'org_evento',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        if (!$evento_sel && !empty($meus_eventos)) {
            $evento_sel = $meus_eventos[0]->ID;
        }

        ob_start();
        ?>
        <div style="max-width: 960px; margin: 20px auto; font-family: sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #e2e8f0; padding-bottom:15px; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0;">Painel do Gestor — <?php echo esc_html($organizacao); ?></h2>
                    <small>Gerencie seus eventos e emita crachás de identificação</small>
                </div>
                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Sair</a>
            </div>

            <?php if ($msg === 'cracha_gerado') : ?>
                <div style="padding:12px; background:#dcfce7; color:#166534; border-radius:6px; margin-bottom:20px;">
                    Crachá gerado com sucesso! Clique em "Ver Crachá" na lista abaixo.
                </div>
            <?php elseif ($msg === 'cracha_erro') : ?>
                <div style="padding:12px; background:#fee2e2; color:#991b1b; border-radius:6px; margin-bottom:20px;">
                    Erro ao comunicar com o servidor de crachás. Verifique as configurações.
                </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 25px;">
                <!-- COLUNA ESQUERDA: CRIAR EVENTO E SELECIONAR -->
                <div>
                    <div style="background:#f8fafc; padding:18px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:20px;">
                        <h4 style="margin-top:0;">+ Novo Evento</h4>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="ebm_dash_action" value="criar_evento" />
                            <?php wp_nonce_field('ebm_criar_evento', 'ebm_nonce'); ?>
                            <p>
                                <label>Nome do Evento *</label><br>
                                <input type="text" name="titulo_evento" required style="width:100%; padding:6px;" />
                            </p>
                            <p>
                                <label>Cor Principal do Crachá</label><br>
                                <input type="color" name="cor_primaria" value="#38bdf8" style="width:100%; height:36px;" />
                            </p>
                            <p>
                                <label>Logotipo do Evento (PNG/JPG)</label><br>
                                <input type="file" name="logo_evento" accept="image/*" />
                            </p>
                            <button type="submit" style="width:100%; padding:8px; background:#0f172a; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                                Criar Evento
                            </button>
