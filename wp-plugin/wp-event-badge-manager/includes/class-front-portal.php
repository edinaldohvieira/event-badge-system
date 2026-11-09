<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Front_Portal
{
    public function __construct()
    {
        add_shortcode('ebm_cadastro_gestor', [$this, 'render_cadastro_gestor']);
        add_shortcode('ebm_painel_gestor', [$this, 'render_painel_gestor']);
        add_action('init', [$this, 'processar_acoes_portal']);
    }

    /**
     * Tela de Autocadastro do Gestor
     */
    public function render_cadastro_gestor(): string
    {
        if (is_user_logged_in()) {
            return '<p>Você já está logado. Acesse o painel do gestor para administrar seus eventos.</p>';
        }

        ob_start();
        ?>
        <div class="ebm-portal-form" style="max-width: 480px;">
            <h2>Cadastro de Nova Organização</h2>
            <form method="post" action="">
                <?php wp_nonce_field('ebm_novo_gestor_nonce', 'ebm_nonce'); ?>
                <p>
                    <label>Nome do Gestor / Organização</label><br>
                    <input type="text" name="gestor_nome" required style="width: 100%; padding: 8px;">
                </p>
                <p>
                    <label>E-mail Corporativo (Login)</label><br>
                    <input type="email" name="gestor_email" required style="width: 100%; padding: 8px;">
                </p>
                <p>
                    <label>Senha de Acesso</label><br>
                    <input type="password" name="gestor_senha" required style="width: 100%; padding: 8px;">
                </p>
                <p>
                    <input type="submit" name="ebm_action_cadastro" value="Criar Minha Conta" class="button button-primary">
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Roteador Principal do Painel: Lista de Eventos ou Gestão de Participantes
     */
    public function render_painel_gestor(): string
    {
        if (!is_user_logged_in() || !current_user_can('read')) {
            return '<p>Acesso restrito. Por favor, faça login para gerenciar seus eventos.</p>';
        }

        $evento_id = isset($_GET['evento_id']) ? (int) $_GET['evento_id'] : 0;

        if ($evento_id > 0) {
            return $this->render_tela_participantes($evento_id);
        }

        return $this->render_tela_eventos();
    }

    /**
     * Visão 1: Criação e Listagem de Eventos do Gestor
     */
    private function render_tela_eventos(): string
    {
        $user_id = get_current_user_id();
        $msg     = sanitize_text_field(wp_unslash($_GET['ebm_msg'] ?? ''));

        $eventos = get_posts([
            'post_type'      => 'org_evento',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        ob_start();
        ?>
        <div class="ebm-painel" style="max-width: 960px; margin: 0 auto;">
            <h2>Painel do Gestor de Eventos</h2>

            <?php if ($msg === 'evento_criado') : ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <strong>Sucesso!</strong> Novo evento cadastrado com sucesso.
                </div>
            <?php elseif ($msg === 'evento_excluido') : ?>
                <div style="background: #fef9c3; color: #854d0e; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    Evento removido da sua lista.
                </div>
            <?php endif; ?>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 24px; border-radius: 8px; margin-bottom: 32px;">
                <h3 style="margin-top: 0;">Cadastrar Novo Evento</h3>
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('ebm_criar_evento_nonce', 'ebm_evento_nonce'); ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label><strong>Nome do Evento *</strong></label><br>
                            <input type="text" name="evento_titulo" placeholder="Ex: Tech Summit Recife 2026" required style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label><strong>Data do Evento *</strong></label><br>
                            <input type="date" name="evento_data" required style="width: 100%; padding: 8px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label><strong>Logotipo do Evento (PNG transparente, 480x110px)</strong></label><br>
                            <input type="file" name="evento_logo" accept="image/png,image/jpeg,image/webp" style="width: 100%; padding: 6px 0;">
                        </div>
                        <div>
                            <label><strong>Cor Principal do Crachá</strong></label><br>
                            <input type="color" name="evento_cor" value="#38bdf8" style="width: 80px; height: 38px; cursor: pointer;">
                        </div>
                    </div>

                    <button type="submit" name="ebm_action_criar_evento" value="1" class="button button-primary">
                        Salvar Evento
                    </button>
                </form>
            </div>

            <h3>Meus Eventos (<?php echo count($eventos); ?>)</h3>

            <?php if (empty($eventos)) : ?>
                <p>Você ainda não possui eventos cadastrados. Utilize o formulário acima para criar o primeiro.</p>
            <?php else : ?>
                <table style="width: 100%; border-collapse: collapse; background: #fff;">
                    <thead>
                        <tr style="background: #1e293b; color: #fff; text-align: left;">
                            <th style="padding: 12px;">Logo</th>
                            <th style="padding: 12px;">Evento</th>
                            <th style="padding: 12px;">Data</th>
                            <th style="padding: 12px;">Cor</th>
                            <th style="padding: 12px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos as $evento) :
                            $data_ev  = get_post_meta($evento->ID, '_evento_data', true);
                            $cor_ev   = get_post_meta($evento->ID, '_evento_cor_primaria', true) ?: '#38bdf8';
                            $logo_id  = (int) get_post_meta($evento->ID, '_evento_logo_id', true);
                            $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

                            $delete_url = wp_nonce_url(
                                add_query_arg(['ebm_del_evento' => $evento->ID]),
                                'ebm_del_evento_' . $evento->ID
                            );
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px; width: 120px; background: #0f172a; text-align: center;">
                                    <?php if ($logo_url) : ?>
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-height: 36px; max-width: 100px;">
                                    <?php else : ?>
                                        <span style="color: #64748b; font-size: 12px;">Sem logo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;"><strong><?php echo esc_html($evento->post_title); ?></strong></td>
                                <td style="padding: 12px;"><?php echo $data_ev ? esc_html(date_i18n('d/m/Y', strtotime($data_ev))) : '-'; ?></td>
                                <td style="padding: 12px;">
                                    <span style="display: inline-block; width: 22px; height: 22px; border-radius: 4px; background: <?php echo esc_attr($cor_ev); ?>; vertical-align: middle; border: 1px solid #cbd5e1;"></span>
                                    <code style="font-size: 12px;"><?php echo esc_html($cor_ev); ?></code>
                                </td>
                                <td style="padding: 12px;">
                                    <a href="<?php echo esc_url(add_query_arg(['evento_id' => $evento->ID])); ?>" class="button button-primary button-small">
                                        Participantes
                                    </a>
                                    <a href="<?php echo esc_url($delete_url); ?>"
                                       onclick="return confirm('Tem certeza que deseja excluir este evento?');"
                                       style="color: #dc2626; margin-left: 10px; text-decoration: none; font-size: 13px;">
                                        Excluir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Visão 2: Cadastro de Participantes e Geração de Crachás (Individual e em Lote)
     */
    private function render_tela_participantes(int $evento_id): string
    {
        $user_id = get_current_user_id();
        $evento  = get_post($evento_id);

        if (!$evento || $evento->post_type !== 'org_evento' || (int) $evento->post_author !== $user_id) {
            return '<p>Evento não encontrado ou você não tem permissão para acessá-lo.</p>';
        }

        $msg        = sanitize_text_field(wp_unslash($_GET['ebm_msg'] ?? ''));
        $erro_texto = sanitize_text_field(wp_unslash($_GET['ebm_erro'] ?? ''));
        $qtd_gerada = (int) ($_GET['ebm_qtd'] ?? 0);
        $voltar_url = remove_query_arg(['evento_id', 'ebm_msg', 'ebm_erro', 'ebm_qtd']);

        $participantes = get_posts([
            'post_type'      => 'org_participante',
            'post_parent'    => $evento_id,
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        // Conta quantos participantes ainda não possuem crachá gerado
        $pendentes_count = 0;
        foreach ($participantes as $p) {
            if (!get_post_meta($p->ID, '_part_cracha_url', true)) {
                $pendentes_count++;
            }
        }

        $lote_pendentes_url = wp_nonce_url(
            add_query_arg([
                'evento_id'        => $evento_id,
                'ebm_gerar_lote'   => 'pendentes',
            ]),
            'ebm_gerar_lote_' . $evento_id
        );

        $lote_todos_url = wp_nonce_url(
            add_query_arg([
                'evento_id'        => $evento_id,
                'ebm_gerar_lote'   => 'todos',
            ]),
            'ebm_gerar_lote_' . $evento_id
        );

        ob_start();
        ?>
        <div class="ebm-painel-participantes" style="max-width: 960px; margin: 0 auto;">
            <p><a href="<?php echo esc_url($voltar_url); ?>">&larr; Voltar para Meus Eventos</a></p>
            <h2>Participantes: <?php echo esc_html($evento->post_title); ?></h2>

            <?php if ($msg === 'part_criado') : ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <strong>Sucesso!</strong> Participante cadastrado. Clique em "Gerar Crachá" na lista abaixo.
                </div>
            <?php elseif ($msg === 'cracha_gerado') : ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <strong>Crachá gerado!</strong> A imagem PNG em alta definição já está disponível para download.
                </div>
            <?php elseif ($msg === 'lote_concluido') : ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <strong>Geração em lote concluída!</strong> <?php echo esc_html($qtd_gerada); ?> crachá(s) processado(s) com sucesso.
                </div>
            <?php elseif ($msg === 'part_excluido') : ?>
                <div style="background: #fef9c3; color: #854d0e; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    Participante removido.
                </div>
            <?php elseif ($msg === 'erro_api' && $erro_texto) : ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <strong>Erro ao gerar crachá:</strong> <?php echo esc_html($erro_texto); ?>
                </div>
            <?php endif; ?>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 24px; border-radius: 8px; margin-bottom: 32px;">
                <h3 style="margin-top: 0;">Adicionar Participante</h3>
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('ebm_criar_part_nonce', 'ebm_part_nonce'); ?>
                    <input type="hidden" name="evento_id" value="<?php echo esc_attr($evento_id); ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label><strong>Nome Completo (como sairá no crachá) *</strong></label><br>
                            <input type="text" name="part_nome" placeholder="Ex: Mariana Silva" required style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label><strong>Cargo / Função</strong></label><br>
                            <input type="text" name="part_cargo" placeholder="Ex: Engenheira de Software" style="width: 100%; padding: 8px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label><strong>Empresa / Instituição</strong></label><br>
                            <input type="text" name="part_empresa" placeholder="Ex: TechLab Recife" style="width: 100%; padding: 8px;">
                        </div>
                        <div>
                            <label><strong>Categoria / Faixa Inferior *</strong></label><br>
                            <select name="part_categoria" style="width: 100%; padding: 8px;">
                                <option value="PARTICIPANTE">PARTICIPANTE</option>
                                <option value="PALESTRANTE">PALESTRANTE</option>
                                <option value="ORGANIZAÇÃO">ORGANIZAÇÃO</option>
                                <option value="VIP">VIP</option>
                                <option value="IMPRENSA">IMPRENSA</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label><strong>Foto do Participante (JPG/PNG quadrado, ideal 500x500px)</strong></label><br>
                        <input type="file" name="part_foto" accept="image/png,image/jpeg,image/webp" style="padding: 6px 0;">
                    </div>

                    <button type="submit" name="ebm_action_criar_participante" value="1" class="button button-primary">
                        Cadastrar Participante
                    </button>
                </form>
            </div>

            <!-- Cabeçalho da Lista com Botões de Geração em Lote -->
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px;">
                <h3 style="margin: 0;">Inscritos neste Evento (<?php echo count($participantes); ?>)</h3>

                <?php if (!empty($participantes)) : ?>
                    <div style="display: flex; gap: 8px;">
                        <?php if ($pendentes_count > 0) : ?>
                            <a href="<?php echo esc_url($lote_pendentes_url); ?>"
                               onclick="this.innerText='Gerando em lote... Aguarde'; this.style.pointerEvents='none';"
                               class="button button-primary"
                               style="background: #0284c7; border-color: #0369a1;">
                                Gerar Pendentes em Lote (<?php echo $pendentes_count; ?>)
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($lote_todos_url); ?>"
                           onclick="if(!confirm('Isso vai gerar/regerar os crachás de todos os <?php echo count($participantes); ?> inscritos. Continuar?')) return false; this.innerText='Processando todos...'; this.style.pointerEvents='none';"
                           class="button button-secondary">
                            Regerar Todos (<?php echo count($participantes); ?>)
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($participantes)) : ?>
                <p>Nenhum participante cadastrado neste evento ainda.</p>
            <?php else : ?>
                <table style="width: 100%; border-collapse: collapse; background: #fff;">
                    <thead>
                        <tr style="background: #1e293b; color: #fff; text-align: left;">
                            <th style="padding: 12px;">Foto</th>
                            <th style="padding: 12px;">Participante</th>
                            <th style="padding: 12px;">Categoria / Código</th>
                            <th style="padding: 12px;">Crachá</th>
                            <th style="padding: 12px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participantes as $part) :
                            $cargo      = get_post_meta($part->ID, '_part_cargo', true);
                            $empresa    = get_post_meta($part->ID, '_part_empresa', true);
                            $categoria  = get_post_meta($part->ID, '_part_categoria', true) ?: 'PARTICIPANTE';
                            $codigo     = get_post_meta($part->ID, '_part_codigo_unico', true);
                            $foto_id    = (int) get_post_meta($part->ID, '_part_foto_id', true);
                            $foto_url   = $foto_id ? wp_get_attachment_image_url($foto_id, 'thumbnail') : '';
                            $cracha_url = get_post_meta($part->ID, '_part_cracha_url', true);

                            $gerar_url = wp_nonce_url(
                                add_query_arg(['ebm_gerar_cracha' => $part->ID]),
                                'ebm_gerar_cracha_' . $part->ID
                            );

                            $del_url = wp_nonce_url(
                                add_query_arg(['ebm_del_part' => $part->ID]),
                                'ebm_del_part_' . $part->ID
                            );
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px; width: 64px;">
                                    <?php if ($foto_url) : ?>
                                        <img src="<?php echo esc_url($foto_url); ?>" alt="Foto" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                                    <?php else : ?>
                                        <div style="width: 48px; height: 48px; border-radius: 50%; background: #cbd5e1;"></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <strong><?php echo esc_html($part->post_title); ?></strong><br>
                                    <span style="font-size: 13px; color: #64748b;">
                                        <?php echo esc_html(trim($cargo . ($empresa ? ' — ' . $empresa : ''))); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="display: inline-block; background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                        <?php echo esc_html($categoria); ?>
                                    </span><br>
                                    <code style="font-size: 12px; color: #475569;"><?php echo esc_html($codigo); ?></code>
                                </td>
                                <td style="padding: 12px;">
                                    <?php if ($cracha_url) : ?>
                                        <a href="<?php echo esc_url($cracha_url); ?>" target="_blank" class="button button-small" style="background: #16a34a; color: #fff; border-color: #15803d;">
                                            Ver / Baixar PNG
                                        </a>
                                    <?php else : ?>
                                        <span style="font-size: 12px; color: #94a3b8;">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <a href="<?php echo esc_url($gerar_url); ?>" class="button button-primary button-small">
                                        <?php echo $cracha_url ? 'Regerar' : 'Gerar Crachá'; ?>
                                    </a>
                                    <a href="<?php echo esc_url($del_url); ?>"
                                       onclick="return confirm('Excluir este participante?');"
                                       style="color: #dc2626; margin-left: 10px; text-decoration: none; font-size: 13px;">
                                        Excluir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper reutilizável para montar payload e gerar o crachá de um participante
     */
    private function processar_cracha_individual(WP_Post $part, string $logo_base64, string $cor_primaria)
    {
        $foto_id = (int) get_post_meta($part->ID, '_part_foto_id', true);

        $payload = [
            'nome'         => $part->post_title,
            'cargo'        => get_post_meta($part->ID, '_part_cargo', true),
            'empresa'      => get_post_meta($part->ID, '_part_empresa', true),
            'categoria'    => get_post_meta($part->ID, '_part_categoria', true) ?: 'PARTICIPANTE',
            'codigo'       => get_post_meta($part->ID, '_part_codigo_unico', true),
            'cor_primaria' => $cor_primaria,
            'logo_base64'  => $logo_base64,
            'foto_base64'  => EBM_Badge_API_Client::attachment_to_base64($foto_id),
        ];

        $resultado = EBM_Badge_API_Client::gerar_e_salvar_cracha($payload);

        if (!is_wp_error($resultado)) {
            update_post_meta($part->ID, '_part_cracha_url', $resultado);
        }

        return $resultado;
    }

    /**
     * Processa todas as ações de formulário e URL do Portal
     */
    public function processar_acoes_portal(): void
    {
        // 1. Autocadastro de novo Gestor
        if (!empty($_POST['ebm_action_cadastro'])) {
            if (!isset($_POST['ebm_nonce']) || !wp_verify_nonce($_POST['ebm_nonce'], 'ebm_novo_gestor_nonce')) {
                wp_die('Falha de segurança na requisição.');
            }

            $nome  = sanitize_text_field($_POST['gestor_nome']);
            $email = sanitize_email($_POST['gestor_email']);
            $senha = $_POST['gestor_senha'];

            if (username_exists($email) || email_exists($email)) {
                wp_die('Este e-mail já está cadastrado no sistema.');
            }

            $user_id = wp_create_user($email, $senha, $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $nome,
                    'role'         => 'event_manager',
                ]);

                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                wp_safe_redirect(home_url());
                exit;
            }
        }

        // 2. Criação de Evento pelo Gestor logado
        if (!empty($_POST['ebm_action_criar_evento']) && is_user_logged_in()) {
            if (!isset($_POST['ebm_evento_nonce']) || !wp_verify_nonce($_POST['ebm_evento_nonce'], 'ebm_criar_evento_nonce')) {
                wp_die('Falha de segurança ao criar evento.');
            }

            $titulo = sanitize_text_field($_POST['evento_titulo'] ?? '');
            $data   = sanitize_text_field($_POST['evento_data'] ?? '');
            $cor    = sanitize_hex_color($_POST['evento_cor'] ?? '#38bdf8') ?: '#38bdf8';

            if (empty($titulo)) {
                wp_die('O nome do evento é obrigatório.');
            }

            $evento_id = wp_insert_post([
                'post_type'   => 'org_evento',
                'post_title'  => $titulo,
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            ]);

            if (!is_wp_error($evento_id)) {
                update_post_meta($evento_id, '_evento_data', $data);
                update_post_meta($evento_id, '_evento_cor_primaria', $cor);

                if (!empty($_FILES['evento_logo']['name'])) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';

                    $attachment_id = media_handle_upload('evento_logo', $evento_id);
                    if (!is_wp_error($attachment_id)) {
                        update_post_meta($evento_id, '_evento_logo_id', $attachment_id);
                    }
                }

                $redirect = add_query_arg('ebm_msg', 'evento_criado', wp_get_referer() ?: home_url());
                wp_safe_redirect($redirect);
                exit;
            }
        }

        // 3. Exclusão de Evento
        if (!empty($_GET['ebm_del_evento']) && is_user_logged_in()) {
            $evento_id = (int) $_GET['ebm_del_evento'];
            check_admin_referer('ebm_del_evento_' . $evento_id);

            $evento = get_post($evento_id);
            if ($evento && $evento->post_type === 'org_evento' && (int) $evento->post_author === get_current_user_id()) {
                wp_trash_post($evento_id);
                $redirect = remove_query_arg(['ebm_del_evento', '_wpnonce']);
                $redirect = add_query_arg('ebm_msg', 'evento_excluido', $redirect);
                wp_safe_redirect($redirect);
                exit;
            }
        }

        // 4. Cadastro de Participante vinculado ao Evento
        if (!empty($_POST['ebm_action_criar_participante']) && is_user_logged_in()) {
            if (!isset($_POST['ebm_part_nonce']) || !wp_verify_nonce($_POST['ebm_part_nonce'], 'ebm_criar_part_nonce')) {
                wp_die('Falha de segurança ao cadastrar participante.');
            }

            $evento_id = (int) ($_POST['evento_id'] ?? 0);
            $evento    = get_post($evento_id);

            if (!$evento || $evento->post_type !== 'org_evento' || (int) $evento->post_author !== get_current_user_id()) {
                wp_die('Evento inválido ou acesso não autorizado.');
            }

            $nome      = sanitize_text_field($_POST['part_nome'] ?? '');
            $cargo     = sanitize_text_field($_POST['part_cargo'] ?? '');
            $empresa   = sanitize_text_field($_POST['part_empresa'] ?? '');
            $categoria = sanitize_text_field($_POST['part_categoria'] ?? 'PARTICIPANTE');
            $codigo    = 'EVT-' . $evento_id . '-' . wp_rand(1000, 9999);

            $part_id = wp_insert_post([
                'post_type'   => 'org_participante',
                'post_title'  => $nome,
                'post_parent' => $evento_id,
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            ]);

            if (!is_wp_error($part_id)) {
                update_post_meta($part_id, '_part_cargo', $cargo);
                update_post_meta($part_id, '_part_empresa', $empresa);
                update_post_meta($part_id, '_part_categoria', $categoria);
                update_post_meta($part_id, '_part_codigo_unico', $codigo);

                if (!empty($_FILES['part_foto']['name'])) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';

                    $foto_id = media_handle_upload('part_foto', $part_id);
                    if (!is_wp_error($foto_id)) {
                        update_post_meta($part_id, '_part_foto_id', $foto_id);
                    }
                }

                $redirect = add_query_arg(['evento_id' => $evento_id, 'ebm_msg' => 'part_criado'], wp_get_referer() ?: home_url());
                wp_safe_redirect($redirect);
                exit;
            }
        }

        // 5. Geração Individual do Crachá via Microserviço Docker
        if (!empty($_GET['ebm_gerar_cracha']) && is_user_logged_in()) {
            $part_id = (int) $_GET['ebm_gerar_cracha'];
            check_admin_referer('ebm_gerar_cracha_' . $part_id);

            $part = get_post($part_id);
            if (!$part || $part->post_type !== 'org_participante' || (int) $part->post_author !== get_current_user_id()) {
                wp_die('Participante não encontrado ou acesso negado.');
            }

            $evento_id    = (int) $part->post_parent;
            $logo_id      = (int) get_post_meta($evento_id, '_evento_logo_id', true);
            $cor_primaria = get_post_meta($evento_id, '_evento_cor_primaria', true) ?: '#38bdf8';
            $logo_base64  = EBM_Badge_API_Client::attachment_to_base64($logo_id);

            $resultado = $this->processar_cracha_individual($part, $logo_base64, $cor_primaria);

            $redirect = remove_query_arg(['ebm_gerar_cracha', '_wpnonce', 'ebm_erro', 'ebm_qtd']);
            if (is_wp_error($resultado)) {
                $redirect = add_query_arg([
                    'ebm_msg'  => 'erro_api',
                    'ebm_erro' => urlencode($resultado->get_error_message()),
                ], $redirect);
            } else {
                $redirect = add_query_arg('ebm_msg', 'cracha_gerado', $redirect);
            }

            wp_safe_redirect($redirect);
            exit;
        }

        // 6. Geração em Lote (Pendentes ou Todos do Evento)
        if (!empty($_GET['ebm_gerar_lote']) && is_user_logged_in()) {
            $evento_id = (int) ($_GET['evento_id'] ?? 0);
            $modo      = sanitize_text_field($_GET['ebm_gerar_lote']);
            check_admin_referer('ebm_gerar_lote_' . $evento_id);

            $evento = get_post($evento_id);
            if (!$evento || $evento->post_type !== 'org_evento' || (int) $evento->post_author !== get_current_user_id()) {
                wp_die('Evento inválido ou acesso não autorizado.');
            }

            // Aumenta o limite de tempo do PHP para lotes maiores
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $participantes = get_posts([
                'post_type'      => 'org_participante',
                'post_parent'    => $evento_id,
                'author'         => get_current_user_id(),
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]);

            // Converte o logo do evento apenas UMA vez para economizar memória e CPU no lote
            $logo_id      = (int) get_post_meta($evento_id, '_evento_logo_id', true);
            $cor_primaria = get_post_meta($evento_id, '_evento_cor_primaria', true) ?: '#38bdf8';
            $logo_base64  = EBM_Badge_API_Client::attachment_to_base64($logo_id);

            $gerados = 0;
            $erro_encontrado = '';

            foreach ($participantes as $part) {
                $ja_possui = (bool) get_post_meta($part->ID, '_part_cracha_url', true);
                if ($modo === 'pendentes' && $ja_possui) {
                    continue;
                }

                $res = $this->processar_cracha_individual($part, $logo_base64, $cor_primaria);
                if (is_wp_error($res)) {
                    $erro_encontrado = $res->get_error_message();
                    break;
                }
                $gerados++;
            }

            $redirect = remove_query_arg(['ebm_gerar_lote', '_wpnonce', 'ebm_erro']);
            if ($erro_encontrado && $gerados === 0) {
                $redirect = add_query_arg([
                    'ebm_msg'  => 'erro_api',
                    'ebm_erro' => urlencode($erro_encontrado),
                ], $redirect);
            } else {
                $redirect = add_query_arg([
                    'ebm_msg' => 'lote_concluido',
                    'ebm_qtd' => $gerados,
                ], $redirect);
            }

            wp_safe_redirect($redirect);
            exit;
        }

        // 7. Exclusão de Participante
        if (!empty($_GET['ebm_del_part']) && is_user_logged_in()) {
            $part_id = (int) $_GET['ebm_del_part'];
            check_admin_referer('ebm_del_part_' . $part_id);

            $part = get_post($part_id);
            if ($part && $part->post_type === 'org_participante' && (int) $part->post_author === get_current_user_id()) {
                wp_trash_post($part_id);
                $redirect = remove_query_arg(['ebm_del_part', '_wpnonce']);
                $redirect = add_query_arg('ebm_msg', 'part_excluido', $redirect);
                wp_safe_redirect($redirect);
                exit;
            }
        }
    }
}
