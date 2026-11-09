# PRD — Sistema de Gestão de Eventos e Geração de Crachás (WordPress + Microserviço)

## 1. Visão Geral do Produto
O projeto consiste em uma solução dividida em dois módulos principais: um **Plugin WordPress** para gestão multi-organização de eventos e participantes, e um **Microserviço de Renderização** (PHP + Browserless/Chromium) responsável por converter templates HTML/CSS em crachás PNG de alta resolução via requisições HTTP (cURL).

## 2. Perfis de Usuário (Personas)
* **Administrador da Plataforma (Super Admin):** Configura a URL do microserviço de renderização, gerencia chaves de API e supervisiona todas as organizações.
* **Gestor de Organização (Event Manager):** Usuário externo que realiza autocadastro na plataforma, cria sua organização, gerencia seus próprios eventos, cadastra participantes e gera/baixa os crachás.
* **Participante:** Pessoa inscrita em um evento (não precisa de login; seus dados e foto geram o crachá).

## 3. Requisitos Funcionais (RF)

### Módulo A: Portal do Gestor (WordPress Plugin)
* **RF01 — Autocadastro de Gestor:** Formulário front-end para criação de conta com atribuição automática da role `event_manager` e vínculo a uma Organização.
* **RF02 — Isolamento de Dados (Multi-tenancy):** Um Gestor só pode visualizar, editar e excluir eventos e participantes pertencentes à sua própria Organização.
* **RF03 — Gestão de Eventos:** CRUD (Criar, Ler, Atualizar, Excluir) de eventos com upload de logotipo e definição de identidade visual (cor principal do crachá).
* **RF04 — Gestão de Participantes:** CRUD de participantes vinculados a um evento específico (Nome, Cargo, Empresa, Categoria, E-mail e Foto).
* **RF05 — Acionamento de Crachá:** Botão de ação individual (e posteriormente em lote) que envia os dados do participante via cURL para o microserviço e armazena o retorno.

### Módulo B: Microserviço de Crachás (API Docker)
* **RF06 — Endpoint de Renderização:** Receber payload JSON contendo dados do participante, ativos em Base64 (logo/foto) e configurações de layout.
* **RF07 — Geração de QR Code:** Gerar QR Code dinâmico com o identificador único do participante.
* **RF08 — Entrega de Imagem:** Renderizar o HTML/CSS via Browserless e retornar a imagem PNG em alta definição (ou salvar em volume compartilhado/retornar Base64/binário).

## 4. Requisitos Não Funcionais (RNF)
* **RNF01 — Segurança:** Comunicação entre WordPress e o Microserviço protegida por Token Bearer no cabeçalho HTTP.
* **RNF02 — Desacoplamento:** O WordPress não deve possuir dependências de Node.js ou Chromium; todo processamento gráfico ocorre no container dedicado.
* **RNF03 — Qualidade de Impressão:** Crachás gerados em escala `2x` (`1200x1800px` para layout base `600x900px`).

