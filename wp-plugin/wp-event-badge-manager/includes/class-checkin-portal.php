<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Checkin_Portal
{
    public function __construct()
    {
        add_shortcode('ebm_checkin_portaria', [$this, 'render_checkin_portaria']);
        add_action('wp_ajax_ebm_validar_checkin', [$this, 'ajax_validar_checkin']);
    }

    /**
     * Renderiza a tela de Check-in (Leitura de QR Code via Câmera ou Leitor USB)
     */
    public function render_checkin_portaria(): string
    {
        if (!is_user_logged_in() || !current_user_can('read')) {
            return '<p>Acesso restrito. Faça login como Gestor do Evento para operar a portaria.</p>';
        }

        $user_id = get_current_user_id();

        $eventos = get_posts([
            'post_type'      => 'org_evento',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($eventos)) {
            return '<p>Você ainda não possui eventos cadastrados para realizar check-in.</p>';
        }

        $nonce = wp_create_nonce('ebm_checkin_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <div class="ebm-checkin-wrapper" style="max-width: 640px; margin: 0 auto; font-family: system-ui, sans-serif;">
            <div style="background: #0f172a; color: #f8fafc; padding: 24px; border-radius: 12px; margin-bottom: 20px;">
                <h2 style="margin: 0 0 8px 0; color: #38bdf8;">Portaria & Check-in</h2>
                <p style="margin: 0 0 16px 0; color: #94a3b8; font-size: 14px;">Selecione o evento ativo e aponte a câmera para o QR Code do crachá ou digite o código.</p>

                <label for="ebm-evento-select" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Evento Ativo:</label>
                <select id="ebm-evento-select" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #334155; background: #1e293b; color: #fff; font-size: 15px;">
                    <?php foreach ($eventos as $ev) : ?>
                        <option value="<?php echo esc_attr($ev->ID); ?>">
                            <?php echo esc_html($ev->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Área do Leitor de Câmera -->
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
                <div id="ebm-qr-reader" style="width: 100%; max-width: 400px; margin: 0 auto 15px auto; overflow: hidden; border-radius: 8px;"></div>
                <button type="button" id="ebm-btn-camera" class="button button-secondary" style="margin-bottom: 15px;">
                    Ativar Câmera para Ler QR Code
                </button>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 10px;">
                    <label for="ebm-codigo-input" style="display: block; font-size: 13px; color: #475569; margin-bottom: 6px;">
                        Ou utilize leitor USB / digitação manual:
                    </label>
                    <form id="ebm-checkin-form" style="display: flex; gap: 8px; justify-content: center;">
                        <input type="text" id="ebm-codigo-input" placeholder="Ex: EVT-46-6712" autocomplete="off" required
                               style="padding: 10px 14px; border: 1px solid #94a3b8; border-radius: 6px; font-size: 16px; width: 220px; text-transform: uppercase;">
                        <button type="submit" class="button button-primary" style="padding: 0 20px; font-size: 15px;">
                            Validar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card de Resultado do Check-in -->
            <div id="ebm-resultado-box" style="display: none; padding: 20px; border-radius: 12px; margin-bottom: 20px;"></div>

            <!-- Histórico Recente da Sessão -->
            <div style="background: #fff; border: 1px solid #e2e8f0; padding: 16px; border-radius: 12px;">
                <h4 style="margin: 0 0 12px 0; color: #1e293b;">Últimas Leituras nesta Sessão</h4>
                <ul id="ebm-historico-lista" style="list-style: none; padding: 0; margin: 0; font-size: 14px; color: #475569;">
                    <li style="color: #94a3b8; font-style: italic;">Nenhuma leitura realizada ainda.</li>
                </ul>
            </div>
        </div>

        <!-- Biblioteca leve html5-qrcode via CDN para leitura pela câmera do celular/notebook -->
        <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
            const nonce = <?php echo wp_json_encode($nonce); ?>;
            const form = document.getElementById('ebm-checkin-form');
            const inputCodigo = document.getElementById('ebm-codigo-input');
            const selectEvento = document.getElementById('ebm-evento-select');
            const resultBox = document.getElementById('ebm-resultado-box');
            const historicoLista = document.getElementById('ebm-historico-lista');
            const btnCamera = document.getElementById('ebm-btn-camera');

            let html5QrCode = null;
            let cameraAtiva = false;
            let bloqueioLeitura = false;

            function processarCheckin(codigoLido) {
                const codigo = codigoLido.trim().toUpperCase();
                if (!codigo || bloqueioLeitura) return;

                bloqueioLeitura = true;
                resultBox.style.display = 'block';
                resultBox.style.background = '#f1f5f9';
                resultBox.style.border = '1px solid #cbd5e1';
                resultBox.innerHTML = '<p style="margin:0; text-align:center;">Validando código <strong>' + codigo + '</strong>...</p>';

                const formData = new FormData();
                formData.append('action', 'ebm_validar_checkin');
                formData.append('nonce', nonce);
                formData.append('evento_id', selectEvento.value);
                formData.append('codigo', codigo);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    exibirResultado(res);
                    inputCodigo.value = '';
                    inputCodigo.focus();
                })
                .catch(() => {
                    exibirResultado({
                        success: false,
                        data: { status: 'erro', mensagem: 'Falha de rede ao comunicar com o servidor.' }
                    });
                })
                .finally(() => {
                    setTimeout(() => { bloqueioLeitura = false; }, 1800);
                });
            }

            function exibirResultado(res) {
                const data = res.data || {};
                let bg = '#fee2e2';
                let border = '#ef4444';
                let titleColor = '#991b1b';
                let badge = 'NEGADO';

                if (res.success && data.status === 'aprovado') {
                    bg = '#dcfce7';
                    border = '#22c55e';
                    titleColor = '#166534';
                    badge = 'ACESSO LIBERADO';
                } else if (data.status === 'duplicado') {
                    bg = '#fef9c3';
                    border = '#eab308';
                    titleColor = '#854d0e';
                    badge = 'JÁ CREDENCIADO';
                }

                resultBox.style.display = 'block';
                resultBox.style.background = bg;
                resultBox.style.border = '2px solid ' + border;

                let html = `<div style="display:flex; align-items:center; gap:16px;">`;
                if (data.foto_url) {
                    html += `<img src="${data.foto_url}" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:2px solid ${border};">`;
                }
                html += `<div>
                    <span style="display:inline-block; background:${border}; color:#fff; font-weight:bold; font-size:12px; padding:3px 8px; border-radius:4px; margin-bottom:6px;">${badge}</span>
                    <h3 style="margin:0 0 4px 0; color:${titleColor};">${data.nome || 'Código Inválido'}</h3>
                    <p style="margin:0; font-size:14px; color:#334155;">${data.mensagem}</p>
                </div></div>`;

                resultBox.innerHTML = html;

                // Atualiza histórico
                if (historicoLista.querySelector('li[style*="italic"]')) {
                    historicoLista.innerHTML = '';
                }
                const agora = new Date().toLocaleTimeString('pt-BR');
                const item = document.createElement('li');
                item.style.padding = '6px 0';
                item.style.borderBottom = '1px solid #f1f5f9';
                item.innerHTML = `<strong>[${agora}]</strong> ${data.nome || data.codigo || '-'} — <span style="color:${titleColor}; font-weight:600;">${badge}</span>`;
                historicoLista.prepend(item);
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                processarCheckin(inputCodigo.value);
            });

            btnCamera.addEventListener('click', function() {
                if (cameraAtiva && html5QrCode) {
                    html5QrCode.stop().then(() => {
                        cameraAtiva = false;
                        btnCamera.textContent = 'Ativar Câmera para Ler QR Code';
                    });
                    return;
                }

                html5QrCode = new Html5Qrcode("ebm-qr-reader");
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => {
                        processarCheckin(decodedText);
                    },
                    () => {}
                ).then(() => {
                    cameraAtiva = true;
                    btnCamera.textContent = 'Desativar Câmera';
                }).catch(err => {
                    alert('Não foi possível acessar a câmera. Verifique as permissões HTTPS do navegador.');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Endpoint AJAX que valida o código no banco e registra o horário de entrada
     */
    public function ajax_validar_checkin(): void
    {
        check_ajax_referer('ebm_checkin_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['status' => 'erro', 'mensagem' => 'Sessão expirada.']);
        }

        $user_id   = get_current_user_id();
        $evento_id = (int) ($_POST['evento_id'] ?? 0);
        $codigo    = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));

        // Confirma que o evento pertence ao gestor logado
        $evento = get_post($evento_id);
        if (!$evento || $evento->post_type !== 'org_evento' || (int) $evento->post_author !== $user_id) {
            wp_send_json_error(['status' => 'erro', 'mensagem' => 'Evento inválido ou sem permissão.']);
        }

        // Busca o participante pelo código único dentro deste evento
        $participantes = get_posts([
            'post_type'      => 'org_participante',
            'post_parent'    => $evento_id,
            'author'         => $user_id,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_part_codigo_unico',
                    'value'   => $codigo,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($participantes)) {
            wp_send_json_error([
                'status'   => 'invalido',
                'codigo'   => $codigo,
                'mensagem' => 'Nenhum participante encontrado com o código ' . esc_html($codigo) . ' neste evento.',
            ]);
        }

        $part      = $participantes[0];
        $categoria = get_post_meta($part->ID, '_part_categoria', true) ?: 'PARTICIPANTE';
        $empresa   = get_post_meta($part->ID, '_part_empresa', true);
        $foto_id   = (int) get_post_meta($part->ID, '_part_foto_id', true);
        $foto_url  = $foto_id ? wp_get_attachment_image_url($foto_id, 'thumbnail') : '';

        // Verifica se já fez check-in anteriormente
        $checkin_em = get_post_meta($part->ID, '_part_checkin_em', true);
        if (!empty($checkin_em)) {
            wp_send_json_error([
                'status'   => 'duplicado',
                'nome'     => $part->post_title,
                'foto_url' => $foto_url,
                'mensagem' => 'Entrada já registrada anteriormente em ' . esc_html($checkin_em) . ' (' . esc_html($categoria) . ').',
            ]);
        }

        // Registra o check-in agora
        $agora = current_time('d/m/Y \à\s H:i:s');
        update_post_meta($part->ID, '_part_checkin_em', $agora);

        $detalhes = $categoria . ($empresa ? ' • ' . $empresa : '');

        wp_send_json_success([
            'status'   => 'aprovado',
            'nome'     => $part->post_title,
            'foto_url' => $foto_url,
            'mensagem' => 'Check-in confirmado às ' . $agora . ' (' . esc_html($detalhes) . ').',
        ]);
    }
}
