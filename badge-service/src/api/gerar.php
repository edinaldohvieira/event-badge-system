<?php

// Define cabeçalhos padrão de API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função auxiliar para retornar erros padronizados em JSON
function responderErro(int $status, string $mensagem): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => true, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Valida o método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido. Utilize POST.');
}

// 2. Valida o Token de Segurança (Bearer Token)
$tokenEsperado = getenv('API_SECRET_TOKEN');
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$tokenEsperado || $authHeader !== 'Bearer ' . $tokenEsperado) {
    responderErro(401, 'Não autorizado. Token inválido ou ausente.');
}

// 3. Lê e decodifica o JSON enviado na requisição
$corpoRequisicao = file_get_contents('php://input');
$dados = json_decode($corpoRequisicao, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
    responderErro(400, 'Payload JSON inválido.');
}

// 4. Valida campos obrigatórios e higieniza textos
if (empty($dados['nome']) || empty($dados['codigo'])) {
    responderErro(422, 'Os campos "nome" e "codigo" são obrigatórios.');
}

$nome       = htmlspecialchars($dados['nome'], ENT_QUOTES, 'UTF-8');
$cargo      = htmlspecialchars($dados['cargo'] ?? '', ENT_QUOTES, 'UTF-8');
$empresa    = htmlspecialchars($dados['empresa'] ?? '', ENT_QUOTES, 'UTF-8');
$categoria  = htmlspecialchars($dados['categoria'] ?? 'PARTICIPANTE', ENT_QUOTES, 'UTF-8');
$codigo     = htmlspecialchars($dados['codigo'], ENT_QUOTES, 'UTF-8');
$qrConteudo = trim((string) ($dados['qr_conteudo'] ?? $dados['codigo']));

// Cor personalizada enviada pelo evento (com fallback seguro)
$corPrimaria = preg_match('/^#[a-fA-F0-9]{6}$/', $dados['cor_primaria'] ?? '')
    ? $dados['cor_primaria']
    : '#38bdf8';

// Imagens em Base64 enviadas pelo WordPress
$logoBase64 = $dados['logo_base64'] ?? '';
$fotoBase64 = $dados['foto_base64'] ?? '';

/**
 * Gerador leve e nativo de QR Code (Versão 2-L / 25x25 módulos, Byte Mode, ECC-L) em Base64 PNG via GD.
 * Suporta strings de até 32 bytes (ideal para códigos de credenciamento como EVT-46-6712).
 */
class SimpleQRCodeGD
{
    public static function toDataUri(string $text, int $scale = 6): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        // Trunca em 32 caracteres para caber com segurança na Versão 2-L (capacidade 32 bytes)
        $text = substr($text, 0, 32);
        $matrix = self::buildMatrix($text);
        $size = count($matrix);
        $margin = 2;
        $imgSize = ($size + $margin * 2) * $scale;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 15, 23, 42);
        imagefill($img, 0, 0, $white);

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x1 = ($c + $margin) * $scale;
                    $y1 = ($r + $margin) * $scale;
                    imagefilledrectangle($img, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private static function buildMatrix(string $text): array
    {
        $size = 25; // Versão 2 (25x25)
        $m = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder patterns (7x7 nos 3 cantos) + separadores
        self::placeFinder($m, $reserved, 0, 0);
        self::placeFinder($m, $reserved, 0, $size - 7);
        self::placeFinder($m, $reserved, $size - 7, 0);

        // 2. Alignment pattern (5x5 em 16,16 para Versão 2)
        self::placeAlignment($m, $reserved, 16, 16);

        // 3. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            $m[6][$i] = $bit;
            $reserved[6][$i] = true;
            $m[$i][6] = $bit;
            $reserved[$i][6] = true;
        }

        // 4. Dark module & reserva de áreas de formato
        $m[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;

        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // 5. Codifica dados em Byte Mode + ECC Reed-Solomon (Versão 2-L: 34 data codewords, 10 ECC codewords)
        $codewords = self::encodeDataAndEcc($text);
        $bits = [];
        foreach ($codewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($cw >> $b) & 1;
            }
        }

        // 6. Preenche os módulos em zigue-zague aplicando Máscara 0 ((r + c) % 2 === 0)
        $bitIdx = 0;
        $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $size; $i++) {
                $row = $up ? ($size - 1 - $i) : $i;
                for ($dx = 0; $dx < 2; $dx++) {
                    $c = $col - $dx;
                    if (!$reserved[$row][$c]) {
                        $val = $bits[$bitIdx] ?? 0;
                        $bitIdx++;
                        if ((($row + $c) % 2) === 0) {
                            $val ^= 1;
                        }
                        $m[$row][$c] = $val;
                    }
                }
            }
            $up = !$up;
        }

        // 7. Informações de formato para ECC-L (01) + Mask 0 (000) -> 15 bits: 111011111000100
        $fmt = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];
        $coords1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]
        ];
        $coords2 = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8],
            [$size - 5, 8], [$size - 6, 8], [$size - 7, 8], [8, $size - 8],
            [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4],
            [8, $size - 3], [8, $size - 2], [8, $size - 1]
        ];
        for ($i = 0; $i < 15; $i++) {
            $m[$coords1[$i][0]][$coords1[$i][1]] = $fmt[$i];
            $m[$coords2[$i][0]][$coords2[$i][1]] = $fmt[$i];
        }

        return $m;
    }

    private static function placeFinder(array &$m, array &$reserved, int $r0, int $c0): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $r0 + $r;
                $cc = $c0 + $c;
                if ($rr < 0 || $rr >= 25 || $cc < 0 || $cc >= 25) {
                    continue;
                }
                $isDark = ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) &&
                          ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $m[$rr][$cc] = $isDark ? 1 : 0;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignment(array &$m, array &$reserved, int $rCenter, int $cCenter): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $isDark = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                $m[$rCenter + $r][$cCenter + $c] = $isDark ? 1 : 0;
                $reserved[$rCenter + $r][$cCenter + $c] = true;
            }
        }
    }

    private static function encodeDataAndEcc(string $text): array
    {
        $len = strlen($text);
        $bitStream = [];

        // Mode indicator: Byte (0100)
        $bitStream = array_merge($bitStream, [0, 1, 0, 0]);

        // Character count (8 bits para Versão 2)
        for ($b = 7; $b >= 0; $b--) {
            $bitStream[] = ($len >> $b) & 1;
        }

        // Bytes do texto
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($text[$i]);
            for ($b = 7; $b >= 0; $b--) {
                $bitStream[] = ($ord >> $b) & 1;
            }
        }

        // Terminator (até 4 zeros)
        $capacityBits = 34 * 8;
        for ($i = 0; $i < 4 && count($bitStream) < $capacityBits; $i++) {
            $bitStream[] = 0;
        }
        while (count($bitStream) % 8 !== 0) {
            $bitStream[] = 0;
        }

        $dataBytes = [];
        for ($i = 0; $i < count($bitStream); $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | $bitStream[$i + $b];
            }
            $dataBytes[] = $byte;
        }

        // Pad bytes alternados (0xEC, 0x11) até completar 34 codewords
        $pads = [0xEC, 0x11];
        $pIdx = 0;
        while (count($dataBytes) < 34) {
            $dataBytes[] = $pads[$pIdx % 2];
            $pIdx++;
        }

        // Calcula 10 bytes de correção de erro Reed-Solomon sobre GF(256)
        $eccBytes = self::rsCompute($dataBytes, 10);

        return array_merge($dataBytes, $eccBytes);
    }

    private static function rsCompute(array $data, int $eccLen): array
    {
        // Polinômio gerador de grau 10 para QR Code
        $gen = [1, 216, 194, 159, 111, 199, 94, 95, 113, 157, 193];
        $res = array_merge($data, array_fill(0, $eccLen, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $res[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $res[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($res, count($data));
    }

    private static function gfMul(int $x, int $y): int
    {
        $r = 0;
        while ($y > 0) {
            if ($y & 1) {
                $r ^= $x;
            }
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
            $y >>= 1;
        }
        return $r;
    }
}

// Gera o QR Code em Base64 PNG
$qrBase64 = SimpleQRCodeGD::toDataUri($qrConteudo, 6);

// 5. Carrega fonte local do microserviço (se existir)
$caminhoFonte = __DIR__ . '/../assets/fonts/Inter-Bold.ttf';
$cssFonte = '';
if (file_exists($caminhoFonte)) {
    $fonteData = 'data:font/ttf;base64,' . base64_encode(file_get_contents($caminhoFonte));
    $cssFonte = "@font-face { font-family: 'CustomFont'; src: url('{$fonteData}') format('truetype'); }";
}

// 6. Monta o HTML dinâmico do Crachá com QR Code integrado
$htmlLogo = $logoBase64 ? "<img src=\"{$logoBase64}\" class=\"logo\" alt=\"Logo\" />" : "";
$htmlFoto = $fotoBase64 ? "<img src=\"{$fotoBase64}\" class=\"foto\" alt=\"Foto\" />" : "";
$htmlQr   = $qrBase64   ? "<div class=\"qr-card\"><img src=\"{$qrBase64}\" class=\"qr-img\" alt=\"QR Code\" /></div>" : "";

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

  .header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 40px 40px 26px;
    text-align: center;
    border-bottom: 2px solid #334155;
  }

  .furo-cordao {
    width: 90px;
    height: 16px;
    background: #020617;
    border-radius: 10px;
    margin: 0 auto 22px;
    border: 1px solid #334155;
  }

  .logo {
    max-height: 55px;
    max-width: 240px;
  }

  .conteudo {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 40px;
    text-align: center;
  }

  .foto-wrapper {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    padding: 6px;
    background: {$corPrimaria};
    margin-bottom: 22px;
  }

  .foto {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #1e293b;
  }

  .nome {
    font-size: 36px;
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 8px;
    color: #ffffff;
  }

  .cargo {
    font-size: 19px;
    color: #94a3b8;
    margin-bottom: 5px;
  }

  .empresa {
    font-size: 21px;
    font-weight: 700;
    color: {$corPrimaria};
    margin-bottom: 22px;
  }

  .credencial-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    background: #1e293b;
    border: 1px solid #334155;
    padding: 12px 20px;
    border-radius: 12px;
  }

  .qr-card {
    background: #ffffff;
    padding: 6px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .qr-img {
    width: 96px;
    height: 96px;
    display: block;
  }

  .codigo-info {
    text-align: left;
  }

  .codigo-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #94a3b8;
    margin-bottom: 4px;
  }

  .codigo-valor {
    font-family: monospace;
    font-size: 19px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #f8fafc;
  }

  .rodape-categoria {
    background: {$corPrimaria};
    color: #0f172a;
    text-align: center;
    padding: 22px;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 4px;
    text-transform: uppercase;
  }
</style>
</head>
<body>

  <div class="header">
    <div class="furo-cordao"></div>
    {$htmlLogo}
  </div>

  <div class="conteudo">
    <div class="foto-wrapper">
      {$htmlFoto}
    </div>
    <div class="nome">{$nome}</div>
    <div class="cargo">{$cargo}</div>
    <div class="empresa">{$empresa}</div>

    <div class="credencial-row">
      {$htmlQr}
      <div class="codigo-info">
        <div class="codigo-label">ID Credenciamento</div>
        <div class="codigo-valor">{$codigo}</div>
      </div>
    </div>
  </div>

  <div class="rodape-categoria">
    {$categoria}
  </div>

</body>
</html>
HTML;

// 7. Aciona o Browserless na rede interna
$payloadBrowserless = json_encode([
    'html' => $html,
    'options' => [
        'type' => 'png',
        'fullPage' => false
    ],
    'viewport' => [
        'width' => 600,
        'height' => 900,
        'deviceScaleFactor' => 2
    ]
]);

$ch = curl_init('http://chrome-renderer:3000/screenshot');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadBrowserless);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$imagemBinaria = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErro = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$imagemBinaria) {
    responderErro(502, 'Falha na comunicação com o motor de renderização: ' . $curlErro);
}

// 8. Devolve a imagem PNG diretamente como resposta da API
header('Content-Type: image/png');
header('Content-Length: ' . strlen($imagemBinaria));
echo $imagemBinaria;
