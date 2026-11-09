<?php

if (!defined('ABSPATH')) {
    exit;
}

class EBM_Badge_API_Client
{
    /**
     * Converte um anexo da Biblioteca de Mídia do WordPress para Data URI (Base64)
     */
    public static function attachment_to_base64(int $attachment_id): string
    {
        if (!$attachment_id) {
            return '';
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return '';
        }

        $mime_type = get_post_mime_type($attachment_id) ?: 'image/png';
        $data = file_get_contents($file_path);

        return 'data:' . $mime_type . ';base64,' . base64_encode($data);
    }

    /**
     * Envia os dados para o microserviço e salva o PNG retornado na pasta de uploads
     *
     * @param array $dadosDados do participante e do evento
     * @return string|WP_Error URL pública do crachá gerado ou erro
     */
    public static function gerar_e_salvar_cracha(array $dados)
    {
        $api_url   = get_option('ebm_api_url', 'http://cracha-php/api/gerar.php');
        $api_token = get_option('ebm_api_token', '');

        if (empty($api_url) || empty($api_token)) {
            return new WP_Error('ebm_config_missing', 'URL ou Token da API não configurados.');
        }

        // Dispara requisição POST usando a API HTTP nativa do WordPress (wrapper sobre cURL)
        $response = wp_remote_post($api_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ],
            'body' => wp_json_encode($dados),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $erro_json = json_decode($body, true);
            $msg = $erro_json['mensagem'] ?? 'Erro desconhecido no microserviço (HTTP ' . $status_code . ').';
            return new WP_Error('ebm_api_error', $msg);
        }

        // Salva a imagem PNG na pasta wp-content/uploads/crachas/
        $upload_dir  = wp_upload_dir();
        $crachas_dir = $upload_dir['basedir'] . '/crachas';

        if (!file_exists($crachas_dir)) {
            wp_mkdir_p($crachas_dir);
        }

        $nome_arquivo = 'cracha_' . sanitize_file_name(strtolower($dados['codigo'])) . '_' . time() . '.png';
        $caminho_final = $crachas_dir . '/' . $nome_arquivo;

        if (file_put_contents($caminho_final, $body) === false) {
            return new WP_Error('ebm_save_error', 'Não foi possível salvar o arquivo PNG no disco.');
        }

        return $upload_dir['baseurl'] . '/crachas/' . $nome_arquivo;
    }
}
