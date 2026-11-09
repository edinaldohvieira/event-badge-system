# Roadmap de Implementação

## Fase 1: Transformação da PoC em API REST (Microserviço)
- [ ] Refatorar o `index.php` atual do laboratório para receber dados via `POST` JSON (`php://input`).
- [ ] Adicionar validação de Token de segurança via variável de ambiente no `docker-compose.yml`.
- [ ] Retornar o binário `image/png` diretamente na resposta HTTP (com status `200`) ou JSON com erro (`401`/`500`).
- [ ] Configurar a rede externa `eventos-shared-net` para comunicação entre stacks Docker.

## Fase 2: Estrutura Base do Plugin WordPress
- [ ] Criar o esqueleto do plugin `wp-event-badge-manager` com ativação/desativação limpa.
- [ ] Criar a página de configurações no `/wp-admin` (Campos: URL da API de Crachás e Token Bearer).
- [ ] Registrar a role `event_manager` e os Custom Post Types (`org_evento` e `org_participante`).

## Fase 3: Fluxo de Cadastro e Painel do Gestor (Front-end)
- [ ] Criar shortcode `[portal_gestor_cadastro]` para registro de novos gestores.
- [ ] Criar shortcode `[portal_gestor_dashboard]` com listagem filtrada por `author == current_user_id`.
- [ ] Implementar formulários front-end para criar Eventos (com upload de logo) e Participantes (com upload de foto).

## Fase 4: Integração WordPress -> Microserviço
- [ ] Desenvolver a classe `Badge_API_Client` usando `wp_remote_post()`.
- [ ] Implementar o gatilho de geração de crachá no painel do gestor.
- [ ] Salvar o PNG retornado na pasta de uploads do WordPress e exibir botão de download/visualização.

## Fase 5: Recursos Avançados e Publicação no GitHub
- [ ] Adicionar biblioteca de QR Code no microserviço para leitura de check-in.
- [ ] Implementar geração em lote (botão "Gerar todos os crachás do evento").
- [ ] Escrever `README.md` final, revisar `.gitignore` (excluindo `.env` e uploads) e publicar no GitHub.
