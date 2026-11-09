<?php

/**
 * Converte imagem ou fonte local em Data URI (Base64)
 */
function paraBase64(string $caminho, string $fallbackMime = 'application/octet-stream'): string
{
    if (!file_exists($caminho)) {
        return '';
    }
    $mime = mime_content_type($caminho) ?: $fallbackMime;
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($caminho));
}

// 1. Dados dinâmicos do participante (podem vir de um formulário ou banco de dados)
$participante = [
    'nome'      => 'Mariana Silva',
    'cargo'     => 'Engenheira de Software',
    'empresa'   => 'TechLab Recife',
    'categoria' => 'PALESTRANTE',
    'codigo'    => 'EVT-2026-8841'
];

$participante = [
    'nome'      => 'Sabryna Lais',
    'cargo'     => 'Atendente de Caixa',
    'empresa'   => 'Ótica DINIZ',
    'categoria' => 'Participante',
    'codigo'    => 'EVT-2026-8841'
];

// 2. Carrega ativos locais (ajuste os nomes dos arquivos na sua pasta assets)
// $fonteBase64 = paraBase64(__DIR__ . '/assets/fonts/Inter-Bold.ttf', 'font/ttf');
$fonteBase64 = paraBase64(__DIR__ . '/assets/fonts/Inter-VariableFont_opsz,wght.ttf', 'font/ttf');
// /docker/wp2297/src/assets/fonts/Inter/Inter-VariableFont_opsz,wght.ttf
$logoBase64  = paraBase64(__DIR__ . '/assets/images/logo.png');
$fotoBase64  = paraBase64(__DIR__ . '/assets/images/foto_participante100.jpg');

// Regra @font-face condicional (caso você já tenha colocado a fonte na pasta)
$cssFonte = $fonteBase64 
    ? "@font-face { font-family: 'CustomFont'; src: url('{$fonteBase64}') format('truetype'); }" 
    : "";

// 3. Monta o HTML do Crachá (600x900px)
$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  {$cssFonte}
  * { box-sizing: border-box; margin: 0; padding: 0; }
  
  body {
    width: 600px;
    height: 900px;
    font-family: 'CustomFont', system-ui, -apple-system, sans-serif;
    background: #0f172a;
    color: #f8fafc;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
  }

  /* Espaço do furo do cordão + Cabeçalho */
  .header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 45px 40px 30px;
    text-align: center;
    border-bottom: 2px solid #334155;
    position: relative;
  }

  .furo-cordao {
    width: 90px;
    height: 16px;
    background: #020617;
    border-radius: 10px;
    margin: 0 auto 25px;
    border: 1px solid #334155;
  }

  .logo {
    max-height: 55px;
    max-width: 240px;
  }

  /* Corpo principal com foto e dados */
  .conteudo {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 40px;
    text-align: center;
  }

  .foto-wrapper {
    width: 220px;
    height: 220px;
    border-radius: 50%;
    padding: 6px;
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    margin-bottom: 28px;
  }

  .foto {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #1e293b;
  }

  .nome {
    font-size: 38px;
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 10px;
    color: #ffffff;
  }

  .cargo {
    font-size: 20px;
    color: #94a3b8;
    margin-bottom: 6px;
  }

  .empresa {
    font-size: 22px;
    font-weight: 700;
    color: #38bdf8;
    margin-bottom: 25px;
  }

  .codigo-box {
    background: #1e293b;
    border: 1px solid #334155;
    padding: 10px 24px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 18px;
    letter-spacing: 2px;
    color: #cbd5e1;
  }

  /* Faixa inferior da categoria */
  .rodape-categoria {
    background: #38bdf8;
    color: #0f172a;
    text-align: center;
    padding: 24px;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 4px;
  }
</style>
</head>
<body>

  <div class="header">
    <div class="furo-cordao"></div>
    <img src="{$logoBase64}" class="logo" alt="Logo Evento" />
  </div>

  <div class="conteudo">
    <div class="foto-wrapper">
      <img src="{$fotoBase64}" class="foto" alt="Participante" />
    </div>
    <div class="nome">{$participante['nome']}</div>
    <div class="cargo">{$participante['cargo']}</div>
    <div class="empresa">{$participante['empresa']}</div>
    <div class="codigo-box">{$participante['codigo']}</div>
  </div>

  <div class="rodape-categoria">
    {$participante['categoria']}
  </div>

</body>
</html>
HTML;

// 4. Envia para o container Browserless gerar a imagem em alta resolução (2x)
$payload = json_encode([
    'html' => $html,
    'options' => [
        'type' => 'png',
        'fullPage' => false
    ],
    'viewport' => [
        'width' => 600,
        'height' => 900,
        'deviceScaleFactor' => 2 // Gera arquivo final de 1200x1800px (pronto para impressão)
    ]
]);

$ch = curl_init('http://chrome-renderer:3000/screenshot');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$imagemBinaria = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Salva na pasta output
if (!is_dir(__DIR__ . '/output')) {
    mkdir(__DIR__ . '/output', 0775, true);
}

if ($httpCode === 200 && $imagemBinaria) {
    $arquivoSaida = __DIR__ . '/output/cracha_' . strtolower($participante['codigo']) . '.png';
    file_put_contents($arquivoSaida, $imagemBinaria);
    echo "Crachá gerado com sucesso! Arquivo salvo em: " . $arquivoSaida;
} else {
    echo "Erro ao gerar crachá. Status HTTP: " . $httpCode;
}
