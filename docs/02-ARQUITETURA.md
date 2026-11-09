# Arquitetura Técnica e Integração entre Containers

## 1. Topologia de Rede Docker
Como o **Container WordPress** e o **Microserviço de Crachás** residem em arquivos `docker-compose.yml` distintos no mesmo host (Linux Mint), a comunicação via PHP cURL exige que ambos compartilhem uma **rede externa Docker**.

### Passo a passo para conectar os dois projetos no Linux Mint:
1. Criar uma rede compartilhada no host:
   `docker network create eventos-shared-net`
2. Adicionar `eventos-shared-net` (como `external: true`) tanto no `docker-compose.yml` do WordPress quanto no `docker-compose.yml` do Microserviço de Crachás.
3. Com isso, o PHP dentro do container WordPress alcança o serviço de crachás diretamente pelo nome do container (ex: `http://cracha-php:80/api/gerar.php`), sem precisar passar pela internet ou pelo túnel Cloudflare.

## 2. Fluxo de Dados (Geração de Crachá)
1. **Gestor** clica em "Gerar Crachá" no painel WordPress.
2. **Plugin WP** coleta os metadados do participante, converte a foto e o logo do evento para Base64 e monta um JSON.
3. **Plugin WP** dispara uma requisição `POST` via `wp_remote_post()` (wrapper nativo do cURL no WordPress) para `http://cracha-php/api/gerar.php` enviando o header `Authorization: Bearer <TOKEN>`.
4. **API PHP (Microserviço)** valida o token, injeta os dados no template HTML/CSS e faz a chamada interna para `http://chrome-renderer:3000/screenshot`.
5. **Browserless** devolve o binário PNG para a API PHP.
6. **API PHP** devolve o PNG para o WordPress.
7. **Plugin WP** salva o arquivo na pasta `wp-content/uploads/crachas/{evento_id}/` e registra a URL nos metadados do participante.

## 3. Modelagem de Dados no WordPress
* **Custom Post Type `org_evento`:** Título (Nome do Evento), `post_author` (ID do Gestor), Post Meta (`_evento_logo_id`, `_evento_cor_primaria`, `_evento_data`).
* **Custom Post Type `org_participante`:** Título (Nome Completo), `post_parent` (ID do Evento), `post_author` (ID do Gestor), Post Meta (`_part_cargo`, `_part_empresa`, `_part_categoria`, `_part_codigo_unico`, `_part_foto_id`, `_part_cracha_url`).
