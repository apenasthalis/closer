# PROMPT MESTRE — DESENVOLVIMENTO COMPLETO DO CRM COMERCIAL, REACT, LARAVEL, JWT E DOCKER

## 1. OBJETIVO PRINCIPAL

Analise o projeto existente e implemente um CRM comercial completo, moderno, seguro e funcional, preservando a arquitetura atual sempre que possível.

O projeto deverá ter:

- **Frontend principal em React**, localizado na pasta `frontend`.
- **Frontend Vue existente em `frontend-vue`**, utilizado como referência visual para replicar fielmente a interface no React, sem destruir ou modificar desnecessariamente o original.
- **Backend em Laravel**, aproveitando a estrutura já existente.
- **Autenticação JWT própria**, implementada sem instalar ou depender de ferramentas, pacotes ou soluções externas de autenticação do Laravel.
- **Banco de dados PostgreSQL**.
- **Docker e Docker Compose**, respeitando todos os containers e serviços que já estejam em execução.
- **Botão de Docker na barra superior do frontend React**, permitindo verificar e iniciar o ambiente por uma única ação.
- **Inicialização coordenada do frontend e do backend**, sem duplicar processos, containers ou serviços.
- CRM comercial com Kanban, gestão de leads, tarefas, follow-ups, dashboards, transcrição de calls, inteligência artificial, comissões e relatórios.

O objetivo não é criar apenas uma interface demonstrativa. O sistema deve funcionar de ponta a ponta, com persistência real, regras de negócio, autenticação, autorização, tratamento de erros e integrações preparadas ou implementadas conforme os recursos disponíveis.

**Regra fundamental:** antes de alterar qualquer arquivo, audite o projeto, entenda sua estrutura, identifique os serviços ativos e preserve o que já funciona.

---

## 2. REGRAS OBRIGATÓRIAS DE PRESERVAÇÃO DO AMBIENTE

Antes de executar qualquer comando ou realizar qualquer alteração:

1. Inspecione a estrutura de pastas e os arquivos existentes.
2. Verifique o estado do Git e preserve alterações locais do usuário.
3. Identifique a versão do Laravel, PHP, Node.js, React, Vue, PostgreSQL, Docker e Docker Compose.
4. Inspecione os arquivos `docker-compose.yml`, `compose.yml`, Dockerfiles, arquivos `.env`, scripts de inicialização e configurações de rede.
5. Liste os containers ativos, seus nomes, portas, redes, volumes e respectivos estados de saúde.
6. Identifique os processos já ativos de frontend, backend, workers e outros serviços.
7. Verifique quais funcionalidades já existem e reutilize-as.
8. Identifique conflitos de portas, serviços duplicados, dependências ausentes e possíveis riscos antes de iniciar qualquer processo.

### Proibições

Não execute, de forma indiscriminada:

- `docker compose down`
- `docker compose down -v`
- `docker rm`
- `docker container prune`
- `docker system prune`
- `docker volume prune`
- Remoção de volumes ou redes existentes.
- Reinicialização global do Docker.
- Reconstrução de todas as imagens sem necessidade comprovada.
- Comandos que apaguem bancos de dados ou dados persistidos.
- Migrações destrutivas sem análise e autorização.
- Comandos que substituam arquivos `.env` existentes.
- Operações que parem, removam ou recriem containers ativos apenas para simplificar a configuração.

Não altere portas, nomes de serviços, redes, volumes ou configurações de containers sem avaliar o impacto.

Se houver um serviço já ativo e saudável, reutilize-o. Se um serviço estiver parado, inicie apenas o que for necessário e seguro.

Se uma operação apresentar risco de interrupção, perda de dados ou incompatibilidade, interrompa essa operação específica, explique o risco e apresente uma alternativa segura.

**Não sacrifique a estabilidade do ambiente para obter uma inicialização aparentemente mais simples.**

---

## 3. ARQUITETURA E ORGANIZAÇÃO DO PROJETO

Mantenha a estrutura atual sempre que for compatível com os requisitos.

A estrutura desejada, a ser adaptada após a auditoria, é:

```text
projeto/
├── frontend/                 # Aplicação React principal
├── frontend-vue/             # Referência visual Vue existente
├── backend/                  # Aplicação Laravel existente
├── docker-compose.yml        # Orquestração dos serviços
├── Dockerfile                # Conforme a arquitetura existente
├── dev.sh                    # Inicialização segura do ambiente
├── Makefile                  # Opcional, se fizer sentido
├── .env                      # Configuração local não versionada
├── .env.example              # Exemplo sem segredos
└── README.md                 # Instruções de uso
```

Essa árvore é uma referência, não uma ordem para mover ou recriar pastas. Se o projeto já tiver outra organização, adapte a implementação sem quebrar caminhos, dependências ou configurações existentes.

### Backend

Preserve o Laravel atual. Organize a implementação de forma clara, usando:

- Controllers.
- Services.
- Models.
- Requests de validação.
- Middleware.
- Policies e Gates quando apropriado.
- Migrations.
- Seeders.
- Jobs e filas quando necessários.
- Rotas de API versionadas, preferencialmente sob `/api/v1`.

### Frontend

Use React na pasta `frontend` como aplicação principal.

Adote a arquitetura, as dependências e as convenções que já existirem. Se for necessário escolher uma estrutura nova, priorize componentes reutilizáveis, separação entre interface e regras de negócio, serviços de API, tratamento centralizado de erros e tipagem quando viável.

Não crie um segundo backend ou uma segunda aplicação principal desnecessariamente.

---

## 4. REPLICAÇÃO VISUAL DO VUE PARA O REACT

A aplicação em `frontend-vue` é a referência visual obrigatória.

Antes de desenvolver a interface React:

1. Analise todas as páginas e rotas do Vue.
2. Identifique os componentes, layouts, menus, modais, tabelas, cards, formulários e elementos interativos.
3. Extraia ou identifique os tokens visuais existentes.
4. Analise cores, tipografia, tamanhos, espaçamentos, bordas, sombras, ícones, animações e estados de interação.
5. Verifique o comportamento responsivo em diferentes resoluções.
6. Identifique os padrões de navegação e a hierarquia das informações.

### Requisitos de fidelidade

Reproduza no React:

- Paleta de cores.
- Tipografia e pesos de fonte.
- Espaçamentos e alinhamentos.
- Larguras, alturas e proporções dos componentes.
- Bordas, raios, sombras e separadores.
- Cabeçalhos, menus e navegação lateral.
- Botões e estados de hover, foco, desabilitado e carregamento.
- Cards, tabelas, formulários e filtros.
- Modais, menus suspensos e notificações.
- Ícones e ilustrações existentes, respeitando suas licenças.
- Layouts responsivos.
- Estados vazios, erros, carregamento e sucesso.

Crie tokens compartilhados no React quando isso ajudar a manter consistência, sem alterar a identidade visual da referência.

Não faça um redesign independente. Não substitua a interface por um template genérico de dashboard.

### Interatividade real

Todos os elementos devem ter comportamento funcional. Não deixe botões decorativos que não executem ações, filtros sem efeito, formulários sem validação ou links sem destino.

A aplicação React deve se conectar ao backend Laravel e utilizar dados reais. Utilize dados fictícios apenas em demonstrações explicitamente identificadas ou seeders de desenvolvimento.

**Preserve a pasta `frontend-vue` intacta**, exceto se uma alteração nela for expressamente necessária e autorizada.

---

## 5. AUTENTICAÇÃO JWT PRÓPRIA — SEM PACOTES EXTERNOS DE AUTENTICAÇÃO DO LARAVEL

Implemente um sistema de autenticação completo com JWT, utilizando a infraestrutura já existente do Laravel e recursos nativos do PHP.

### 5.1. Restrições obrigatórias

Não instalar, utilizar ou depender de ferramentas externas ou pacotes adicionais de autenticação, incluindo, mas não se limitando a:

- Laravel Sanctum.
- Laravel Passport.
- Laravel Breeze.
- Laravel Jetstream.
- Laravel Fortify.
- `tymon/jwt-auth`.
- Outras bibliotecas ou pacotes de terceiros dedicados à autenticação JWT.

Não introduza uma dependência externa de autenticação apenas para acelerar a implementação.

Use os recursos nativos do PHP, como `hash_hmac`, `hash_equals`, `random_bytes` e as funções OpenSSL quando necessárias, juntamente com os componentes já disponíveis no Laravel, como validação, hashing de senhas, middleware, banco de dados e configuração.

**Não implemente algoritmos criptográficos próprios.** Implemente a estrutura JWT utilizando funções criptográficas consolidadas do PHP.

Antes de escrever o código, confirme as versões de PHP e Laravel para selecionar as APIs compatíveis com o projeto.

### 5.2. Implementação do JWT

Implemente um serviço próprio e isolado, por exemplo:

```text
backend/app/Services/Auth/JwtService.php
backend/app/Services/Auth/TokenService.php
backend/app/Http/Middleware/JwtAuthenticate.php
backend/app/Http/Controllers/Api/AuthController.php
```

Os nomes podem ser adaptados às convenções existentes.

O serviço JWT deve implementar corretamente:

- Codificação e decodificação Base64 URL-safe.
- Geração do cabeçalho JWT.
- Geração do payload.
- Assinatura criptográfica.
- Validação da assinatura.
- Verificação do algoritmo permitido.
- Validação de expiração.
- Validação de `iat`, `nbf` e `exp`, quando presentes.
- Validação de `iss` e `aud`, quando configurados.
- Validação do identificador do usuário.
- Tratamento de tokens inválidos, malformados, adulterados ou expirados.
- Comparação segura de assinaturas.
- Rejeição de algoritmos não permitidos, especialmente `none`.
- Tratamento consistente de erros sem expor informações sensíveis.

Utilize `HS256` com uma chave secreta forte, gerada de forma segura e mantida exclusivamente no servidor, ou outro algoritmo seguro compatível com a infraestrutura existente, caso exista uma necessidade arquitetural concreta.

Não aceite o algoritmo declarado no cabeçalho do token sem validar contra uma lista de algoritmos permitidos.

A chave de assinatura deve:

- Ser configurada por variável de ambiente.
- Ser suficientemente forte e gerada criptograficamente.
- Nunca ficar no código-fonte.
- Nunca ser enviada ao frontend.
- Nunca aparecer em logs, mensagens de erro ou respostas da API.
- Ser validada durante a inicialização ou por um comando de diagnóstico.
- Não utilizar valores padrão fracos em produção.

Se a chave não estiver configurada adequadamente, o sistema deverá falhar de maneira segura, sem gerar tokens com segredos inseguros.

### 5.3. Conteúdo do token

O payload deve conter apenas as informações necessárias, como:

- `sub`: identificador do usuário.
- `iss`: emissor, quando configurado.
- `aud`: público destinatário, quando configurado.
- `iat`: instante de emissão.
- `nbf`: instante a partir do qual o token é válido, quando utilizado.
- `exp`: instante de expiração.
- `jti`: identificador único do token.

Não inclua senha, hash de senha, dados pessoais desnecessários, segredos ou informações comerciais confidenciais no payload.

O JWT assinado não é criptografado por padrão. Portanto, trate seu conteúdo como legível por quem possuir o token.

### 5.4. Login e gerenciamento de usuários

Implemente os endpoints adequados à arquitetura existente, incluindo:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

Implemente também cadastro, convite ou recuperação de senha apenas conforme as necessidades reais do projeto e as regras de autorização definidas.

O login deve:

1. Validar os campos recebidos.
2. Normalizar o identificador de login de forma consistente.
3. Localizar o usuário.
4. Verificar se a conta pode autenticar.
5. Validar a senha utilizando hashing seguro.
6. Gerar o token de acesso.
7. Gerar e registrar a sessão de renovação, quando utilizada.
8. Retornar somente os dados necessários ao frontend.

Use o mecanismo seguro de hashing de senhas já disponível no Laravel, com algoritmos e parâmetros adequados à versão instalada.

Nunca armazene senhas em texto puro ou use criptografia reversível para senhas.

Para credenciais inválidas, retorne mensagens genéricas, evitando revelar se um usuário existe.

### 5.5. Access token e refresh token

Implemente uma estratégia segura de renovação de sessão.

Como referência:

- Access token com duração curta, por exemplo, 10 a 15 minutos.
- Refresh token com duração maior, configurável conforme a política de segurança.
- Rotação do refresh token a cada renovação.
- Registro de sessões e tokens de renovação no banco.
- Armazenamento apenas do hash do refresh token no banco de dados.
- Revogação da sessão no logout.
- Detecção de reutilização de refresh tokens, quando viável.
- Expiração e limpeza segura de sessões antigas.

O refresh token pode ser um valor aleatório criptograficamente seguro, armazenado em formato de hash no banco, sem precisar ser outro JWT.

Não prolongue a validade de um access token expirado simplesmente alterando seu campo `exp`.

Se o projeto não precisar de renovação persistente, documente a decisão e implemente uma estratégia segura de reautenticação. Não crie um fluxo de refresh inseguro apenas para atender a um requisito superficial.

### 5.6. Persistência de sessões

Se necessário, crie uma migration específica para sessões de autenticação, com campos adequados, como:

- Identificador.
- Usuário associado.
- Hash do refresh token.
- Data de criação.
- Data de expiração.
- Data de revogação.
- Identificador da sessão.
- Informações mínimas do dispositivo, quando justificadas.

A estrutura final deve ser adaptada aos modelos existentes.

Utilize chaves estrangeiras, índices e regras de integridade adequadas.

Não armazene tokens completos em logs nem em tabelas de auditoria.

### 5.7. Middleware de autenticação JWT

Implemente middleware próprio para proteger as rotas da API.

O middleware deve:

1. Extrair o token do cabeçalho `Authorization: Bearer <token>`.
2. Rejeitar requisições sem token quando a rota exigir autenticação.
3. Validar assinatura, algoritmo, expiração e demais claims exigidos.
4. Carregar o usuário correspondente.
5. Verificar se a conta está ativa.
6. Associar o usuário autenticado ao contexto da requisição.
7. Rejeitar tokens inválidos ou revogados conforme a estratégia implementada.
8. Retornar respostas HTTP coerentes, como `401` para ausência ou invalidade de autenticação.

Não confie em identificadores de usuário, roles ou permissões enviados pelo frontend. O backend é a fonte de verdade.

### 5.8. Autorização por perfil e permissões

Implemente autorização no backend para os perfis e permissões do CRM.

Considere, conforme as necessidades do projeto:

- Administrador.
- Gestor comercial.
- Closer.
- SDR ou pré-vendas.
- Outros perfis necessários.

Defina quais usuários podem visualizar, editar, excluir, atribuir e exportar leads, acessar relatórios, visualizar comissões, gerenciar usuários e alterar configurações.

Utilize Policies, Gates ou serviços próprios de autorização, conforme a arquitetura existente, sem instalar pacotes externos desnecessários.

Não basta esconder botões no React. Todas as permissões relevantes devem ser verificadas no servidor.

Garanta isolamento dos dados por usuário, equipe ou organização quando aplicável.

### 5.9. Proteção do frontend

No React:

- Crie uma camada centralizada de autenticação.
- Implemente rotas protegidas.
- Carregue o usuário atual através do endpoint `/me`.
- Trate respostas `401` e `403`.
- Implemente logout e expiração de sessão.
- Trate renovação de token de forma centralizada.
- Evite múltiplas renovações simultâneas.
- Limpe o estado de autenticação quando a sessão terminar.
- Redirecione para o login quando necessário.
- Preserve o retorno à rota originalmente solicitada após a autenticação, quando seguro.

Prefira manter o access token em memória e utilizar cookies `HttpOnly`, `Secure` e `SameSite` para refresh tokens quando a arquitetura permitir.

Não armazene refresh tokens em `localStorage`.

Se a implementação precisar manter access tokens em armazenamento persistente do navegador, avalie explicitamente o risco de XSS e documente a decisão. Não escolha armazenamento inseguro por conveniência.

Se cookies forem utilizados para autenticação ou renovação, implemente as proteções CSRF apropriadas e configure CORS e cookies corretamente para os domínios utilizados.

Não desabilite CORS, CSRF ou verificações de origem indiscriminadamente.

### 5.10. Segurança adicional da autenticação

Implemente:

- Limitação de tentativas de login com os recursos disponíveis no projeto.
- Proteção contra força bruta e enumeração de usuários.
- Validação rigorosa dos dados recebidos.
- HTTPS em produção.
- Logs de segurança sem credenciais ou tokens.
- Tratamento seguro de exceções.
- Política de expiração e revogação.
- Proteção contra escalada de privilégios.
- Configuração de CORS restritiva.
- Proteção contra atribuição indevida de campos em operações de usuário.
- Testes para adulteração, expiração e assinatura inválida.

Não confie apenas no JWT para controlar permissões dinâmicas. Se um usuário for desativado ou tiver suas permissões removidas, o backend deverá respeitar a alteração de acordo com a estratégia de validação e revogação implementada.

### 5.11. Testes obrigatórios do JWT

Crie testes automatizados para:

- Login válido.
- Senha incorreta.
- Usuário inexistente.
- Usuário inativo.
- Requisição sem token.
- Token válido.
- Token expirado.
- Token com assinatura adulterada.
- Token malformado.
- Algoritmo não permitido.
- Logout.
- Refresh token válido.
- Refresh token expirado.
- Reutilização de refresh token, quando houver detecção implementada.
- Acesso sem permissão.
- Tentativas excessivas de login.
- Acesso a dados pertencentes a outro usuário ou equipe.
- Rotas públicas e privadas.

A autenticação só deve ser considerada concluída após os testes e a verificação das rotas protegidas.

---

## 6. DOCKER NA BARRA SUPERIOR DO FRONTEND

Adicione um botão ou indicador de Docker na barra superior da aplicação React.

O controle deverá ser visível, intuitivo e integrado ao design do Vue existente, sem prejudicar o layout.

### 6.1. Objetivo do botão

O usuário deve conseguir, com uma única ação, verificar e iniciar o ambiente necessário para usar o CRM.

O fluxo deverá coordenar:

- Frontend React em `frontend`.
- Backend Laravel.
- PostgreSQL.
- Nginx, se utilizado.
- Workers, filas ou outros serviços realmente necessários.

O botão deve iniciar apenas os serviços necessários para deixar o ambiente funcional.

**Frontend e backend devem ser iniciados uma única vez, sem duplicar processos ou containers.**

### 6.2. Verificação antes de iniciar

Antes de executar qualquer operação, verifique:

- Se o Docker está disponível.
- Se o daemon está acessível.
- Se os containers necessários já existem.
- Se estão ativos e saudáveis.
- Se as portas necessárias estão disponíveis.
- Se o frontend já está respondendo.
- Se o backend Laravel já está respondendo.
- Se o PostgreSQL está acessível.
- Se o Nginx está funcional, quando utilizado.
- Se há processos duplicados.
- Se há conflitos ou falhas de inicialização.

Se o ambiente já estiver funcionando, apenas atualize o estado visual do botão.

Não reinicie serviços saudáveis apenas para confirmar que estão ativos.

### 6.3. Inicialização segura

Crie um mecanismo centralizado e idempotente de inicialização, reutilizado pelo botão da interface e pelo comando único do projeto.

Esse mecanismo deve:

1. Verificar o ambiente.
2. Identificar serviços ativos.
3. Iniciar somente os serviços necessários que estiverem parados.
4. Aguardar os serviços ficarem prontos.
5. Confirmar a disponibilidade do frontend e do backend.
6. Verificar a conectividade com o banco de dados.
7. Apresentar o resultado real ao usuário.

Não utilize `docker compose down` para reiniciar o ambiente.

Não remova containers ou volumes.

Não reconstrua todas as imagens automaticamente.

Se for necessário reconstruir uma imagem ou alterar uma configuração, avalie os impactos e execute apenas a operação específica necessária, sem interromper serviços alheios à mudança.

O mecanismo deve ser idempotente: executar a operação repetidamente não pode criar duplicatas nem causar efeitos colaterais.

### 6.4. Estados visuais do botão

Implemente estados reais, como:

- Verificando ambiente.
- Docker indisponível.
- Iniciando serviços.
- Ambiente parcialmente disponível.
- Ambiente pronto.
- Serviço com falha.
- Operação em andamento.

Desabilite temporariamente novas solicitações enquanto uma inicialização estiver em andamento.

Exiba mensagens claras sobre quais serviços estão ativos, quais estão sendo iniciados e quais apresentaram falha.

Não exiba sucesso antes de confirmar a disponibilidade real dos serviços.

### 6.5. Segurança do controle de Docker

Não permita que o navegador execute comandos arbitrários de shell.

Não exponha o socket do Docker diretamente ao frontend nem crie uma API pública de execução de comandos.

O botão deve acionar um mecanismo administrativo local, restrito e explicitamente autorizado, com operações permitidas e previamente definidas.

A implementação deve considerar:

- Autorização administrativa.
- Validação das operações permitidas.
- Ausência de execução arbitrária de comandos.
- Proteção contra injeção de comandos.
- Restrição de acesso à API de controle.
- Logs de operações administrativas.
- Limites de tempo e tratamento de falhas.
- Separação entre o ambiente local de desenvolvimento e a operação de produção.

Se a arquitetura não permitir que o navegador controle o Docker de maneira segura, implemente uma integração local ou um comando operacional seguro e explique a limitação. Não simule um botão funcional.

---

## 7. COMANDO ÚNICO PARA SUBIR O AMBIENTE

Crie um comando único, preferencialmente:

```bash
./dev.sh up
```

Alternativamente, utilize um alvo `make up` se isso se encaixar melhor na arquitetura existente.

Esse comando deve compartilhar a mesma lógica de inicialização utilizada pelo botão do frontend.

O comando deve:

- Verificar pré-requisitos.
- Reutilizar containers ativos e saudáveis.
- Iniciar somente serviços necessários.
- Evitar processos duplicados.
- Verificar a saúde do backend.
- Verificar a saúde do frontend.
- Verificar a conectividade com o banco.
- Mostrar o resultado de cada etapa.
- Encerrar com código de saída adequado quando houver falha.
- Preservar os dados e volumes existentes.

Não utilize comandos indiscriminados que forcem recriação de todos os serviços.

Não utilize `docker compose down` como etapa padrão.

Não use `--build` global automaticamente. Reconstrua somente quando houver necessidade real e de forma segura.

### Critério de aceitação

Uma única execução do comando deve deixar o ambiente funcional, aproveitando tudo que já estiver em execução, sem duplicar frontend, backend ou containers e sem interromper serviços existentes desnecessariamente.

---

## 8. CRM KANBAN COMERCIAL

Implemente um Kanban de vendas com as seguintes etapas:

1. Lead Agendado.
2. FUP.
3. Remarcado.
4. Negociação.
5. Fechado.
6. Loss.
7. Mal Qualificado.

### Funcionalidades

- Arrastar e soltar leads entre etapas.
- Atualização persistente da etapa no backend.
- Atualização otimista da interface, com reversão caso a API falhe.
- Histórico de movimentações.
- Filtros por closer, nicho, origem, temperatura, período e etapa.
- Busca por nome, telefone, e-mail ou empresa.
- Ordenação por prioridade, data da próxima ação e valor estimado.
- Indicadores de quantidade e valor por etapa.
- Confirmação ou formulário obrigatório em transições que exijam dados adicionais.
- Motivo obrigatório ao marcar Loss.
- Motivo obrigatório ao marcar Mal Qualificado.
- Registro do usuário responsável por cada alteração.

Evite perder alterações quando houver falhas de conexão ou atualizações concorrentes.

---

## 9. CADASTRO E FICHA COMPLETA DO LEAD

Cada lead deve possuir os seguintes campos, adaptados ao modelo de dados existente:

- Nome completo.
- Empresa, quando aplicável.
- Telefone.
- WhatsApp.
- E-mail.
- Nicho.
- Origem.
- Closer responsável.
- Responsável pela pré-venda, quando aplicável.
- Etapa do Kanban.
- Temperatura: quente, morno ou frio.
- Ticket estimado.
- Valor negociado.
- Valor fechado.
- Receita prevista.
- Data de criação.
- Data da última interação.
- Data da próxima ação.
- Observações.
- Motivo de Loss.
- Motivo de desqualificação.
- Histórico comercial.

### Histórico

Registre:

- Calls realizadas.
- Calls agendadas e remarcadas.
- No-shows.
- Mensagens.
- E-mails.
- Alterações de etapa.
- Propostas enviadas.
- Contratos gerados.
- Pagamentos e parcelas.
- Tarefas concluídas.
- Tarefas adiadas.
- Interações relevantes da equipe.
- Análises e resultados produzidos pela IA.

Cada evento deve ter data, autor e informações relevantes.

### Anexos

Permita anexar arquivos relacionados ao lead, incluindo:

- Propostas.
- Contratos.
- Comprovantes.
- Transcrições.
- Documentos comerciais.
- Outros arquivos autorizados.

Valide tipo, tamanho e conteúdo dos arquivos. Armazene-os de forma segura, com controle de acesso. Não disponibilize documentos privados por URLs públicas permanentes.

### Próximos passos

Permita criar próximos passos manualmente ou a partir de recomendações da IA.

Uma sugestão gerada pela IA não deve se tornar uma ação crítica ou ser enviada a terceiros automaticamente sem as confirmações e regras de autorização apropriadas.

---

## 10. LEMBRETES E AUTOMAÇÕES COMERCIAIS

Implemente um centro de tarefas e lembretes que reúna todas as ações pendentes.

### Tipos de lembrete

- Follow-up de leads sem resposta.
- Retorno após envio de proposta.
- Retorno após negociação.
- Cobrança de valores restantes.
- Cobrança de parcelas.
- Confirmação de reunião.
- Remarcação de call.
- Recuperação de no-show.
- Proposta próxima do vencimento.
- Contrato aguardando assinatura.
- Tarefa manual criada pelo closer.
- Próximo passo sugerido por IA.
- Outras tarefas comerciais configuráveis.

### Funcionalidades

- Visualização diária.
- Visualização por semana.
- Filtros por closer, equipe, lead, tipo e prioridade.
- Marcar como feito.
- Adiar.
- Reagendar.
- Alterar responsável.
- Abrir a ficha do lead.
- Abrir o WhatsApp do contato.
- Histórico de execução e reagendamento.

### Priorização automática

Priorize tarefas com base em:

- Atraso.
- Importância comercial.
- Valor potencial do negócio.
- Temperatura do lead.
- Proximidade do vencimento.
- Tempo desde a última interação.
- Data da próxima reunião.
- Risco de perda da oportunidade.

As regras devem ser explícitas, configuráveis e auditáveis.

### Prevenção de duplicidade

As automações devem ser idempotentes. Não gere vários lembretes iguais a cada execução do sistema.

Registre a origem da tarefa, seu estado, a regra responsável e a última execução relevante.

Use jobs, filas ou agendadores existentes quando apropriado. Não crie um segundo sistema de agendamento sem necessidade.

---

## 11. INTEGRAÇÃO COM WHATSAPP

Implemente uma camada de integração para comunicação com leads e equipe comercial.

### Funcionalidades desejadas

- Abrir uma conversa com o lead.
- Preparar mensagens de follow-up.
- Preparar mensagens de confirmação de call.
- Preparar mensagens de remarcação.
- Preparar mensagens de cobrança.
- Preparar mensagens de retorno de proposta.
- Enviar notificações autorizadas aos closers.
- Registrar o histórico de comunicação quando houver integração disponível.
- Vincular as mensagens ao lead e à tarefa correspondente.

Utilize a API oficial ou um provedor autorizado, quando disponível.

Não invente endpoints, tokens ou credenciais.

Não apresente uma mensagem como enviada se ela apenas tiver sido preparada ou se o envio não tiver sido confirmado.

Se as credenciais não estiverem configuradas, ofereça uma alternativa funcional, como abrir o WhatsApp com uma mensagem pré-preenchida, quando apropriado, e sinalize claramente a limitação.

Não realize disparos em massa sem autorização, controle de consentimento e respeito às regras aplicáveis.

---

## 12. DASHBOARD COMERCIAL

Crie um dashboard moderno, responsivo e alinhado à identidade visual do Vue.

### Indicadores gerais

- Reuniões realizadas por dia, semana e mês.
- Reuniões agendadas.
- Reuniões canceladas.
- No-show.
- Taxa de comparecimento.
- Conversão geral.
- Conversão por etapa.
- Conversão por closer.
- Ticket médio.
- Faturamento previsto.
- Faturamento realizado.
- Receita contratada.
- Valores recebidos.
- Valores pendentes.
- Ciclo médio de fechamento.
- Volume de leads por etapa.
- Quantidade de leads novos.
- Quantidade de oportunidades perdidas.
- Taxa de desqualificação.
- Comissões geradas.

### Comparativo por nicho

Permita comparar nichos como:

- Mercado Livre.
- Visagismo.
- Mente Renovada.
- Outros nichos cadastrados pelo administrador.

Os nichos devem ser dados configuráveis no banco, e não opções fixas impossíveis de ampliar.

### Ranking de closers

Exiba, conforme as permissões:

- Reuniões realizadas.
- Taxa de comparecimento.
- Conversão.
- Negócios fechados.
- Receita fechada.
- Ticket médio.
- Ciclo de fechamento.
- Comissão gerada.
- Metas e progresso.

### Filtros

Permita filtrar por:

- Período.
- Closer.
- Equipe.
- Nicho.
- Origem.
- Etapa.
- Tipo de oportunidade.

### Regras de cálculo

Defina claramente os critérios utilizados nos indicadores.

Não misture faturamento previsto, contratado e efetivamente recebido.

Evite dupla contagem de leads, reuniões, vendas, parcelas ou comissões.

Calcule indicadores no backend ou em uma camada de domínio consistente, utilizando dados persistidos.

Aplique as permissões de acesso também aos dados do dashboard.

---

## 13. TRANSCRIÇÃO E ANÁLISE DE CALLS COM IA

Implemente um módulo para associar gravações, transcrições e análises às oportunidades comerciais.

### Entrada de dados

O sistema deve aceitar:

- Transcrição inserida manualmente.
- Arquivo de transcrição.
- Integração com serviço de transcrição, quando configurada.
- Referência para gravação, respeitando as permissões de acesso.

Não afirme que uma gravação foi transcrita se nenhum mecanismo de transcrição estiver configurado.

Não exponha arquivos de áudio ou transcrições confidenciais sem autorização.

### Análise automática

Quando houver um provedor de IA configurado, gere:

1. Resumo executivo da call.
2. Principais necessidades do lead.
3. Problemas e gargalos identificados.
4. Objetivos e resultados desejados.
5. Objeções apresentadas.
6. Respostas do closer às objeções.
7. Perguntas feitas e perguntas ausentes.
8. Checklist SPIN.
9. Avaliação de aderência ao playbook.
10. Próximos passos sugeridos.
11. Trechos relevantes.
12. Indícios de interesse ou resistência.
13. Riscos de perda da oportunidade.
14. Sugestões de follow-up.
15. Sugestões de melhoria para o closer.

### Checklist SPIN

Avalie:

- Situação.
- Problema.
- Implicação.
- Necessidade de solução.

Para cada categoria, mostre:

- Perguntas identificadas.
- Evidências da transcrição.
- Perguntas que poderiam ter sido feitas.
- Pontos que precisam de investigação adicional.

Não marque uma pergunta como realizada sem evidência suficiente na transcrição.

### Talk-time ratio

Calcule a proporção de fala entre closer e lead quando houver identificação confiável dos participantes e duração suficiente da gravação.

Exiba:

- Tempo de fala do closer.
- Tempo de fala do lead.
- Percentual de cada participante.
- Limitações de precisão, quando existirem.

Não apresente estimativas como medições exatas se os dados não permitirem essa precisão.

### Score do playbook

Crie uma avaliação configurável com critérios como:

- Abertura.
- Rapport.
- Definição da agenda.
- Diagnóstico.
- Identificação da dor.
- Exploração do impacto.
- Consequência futura.
- Visão de futuro.
- Necessidade de solução.
- Confirmação de entendimento.
- Apresentação da solução.
- Conexão da solução à dor.
- Ancoragem de valor.
- Investimento.
- Tratamento de objeções.
- Fechamento.

O score deve ser explicado com evidências e não apenas apresentado como uma nota sem justificativa.

### Sentimento e highlights

Identifique sinais de interesse, dúvida, urgência, resistência ou intenção de compra como indicadores aproximados, sempre com evidências textuais e sem alegar certeza psicológica.

Destaque os trechos associados a:

- Momentos de maior interesse.
- Objeções importantes.
- Necessidades explícitas.
- Sinais de compra.
- Barreiras de decisão.
- Compromissos e próximos passos.

### Próximos passos automáticos

A IA pode sugerir tarefas com responsável, prazo e contexto.

A criação de tarefas deve respeitar regras de confirmação, evitar duplicidades e registrar que a tarefa foi sugerida por IA.

### Privacidade

Não envie transcrições ou gravações a serviços externos sem configuração, autorização e avaliação dos requisitos de privacidade aplicáveis.

Não invente análises quando o provedor estiver indisponível.

---

## 14. BIBLIOTECA DE SCRIPTS E PLAYBOOKS

Crie uma biblioteca comercial organizada por nicho e etapa da venda.

Permita:

- Criar scripts.
- Editar scripts.
- Criar versões.
- Organizar por nicho.
- Organizar por etapa do processo.
- Organizar por tipo de objeção.
- Definir playbooks recomendados.
- Associar scripts a calls e leads.
- Comparar versões.
- Consultar resultados históricos.
- Controlar quem pode editar ou publicar.

Inclua estruturas para abertura, rapport, diagnóstico, implicação, apresentação, tratamento de objeções e fechamento.

Os critérios e pesos dos scores devem ser configuráveis, mantendo rastreabilidade das alterações.

---

## 15. BANCO HISTÓRICO DE OBJEÇÕES

Crie um repositório de objeções comerciais.

Cada registro poderá conter:

- Objeção.
- Categoria.
- Nicho.
- Etapa da venda.
- Contexto.
- Resposta utilizada.
- Resultado observado.
- Closer responsável.
- Data.
- Associação ao lead ou à call.
- Eficácia histórica.

Permita filtrar, pesquisar e comparar resultados.

Calcule métricas de eficácia apenas quando houver dados suficientes e defina claramente o denominador utilizado.

Não confunda correlação com causalidade.

A IA poderá sugerir respostas e exemplos com base no histórico autorizado, mas não deverá inventar estatísticas de eficácia.

---

## 16. GOOGLE CALENDAR

Implemente uma camada de integração com Google Calendar, caso as credenciais e permissões necessárias estejam disponíveis.

Funcionalidades:

- Criar eventos.
- Consultar eventos.
- Atualizar eventos.
- Cancelar eventos.
- Sincronizar reuniões.
- Registrar alterações.
- Evitar duplicidade de eventos.
- Tratar conflitos de horário.
- Vincular eventos a leads e closers.

Utilize OAuth e os escopos mínimos necessários.

Não armazene senhas do Google.

Não invente credenciais nem afirme que uma sincronização foi concluída sem confirmação da integração.

Quando não houver credenciais, permita utilizar o CRM sem a integração e mostre seu estado como não configurado.

---

## 17. PROPOSTAS, CONTRATOS E DOCUMENTOS PDF

Permita gerar propostas e contratos a partir da etapa Negociação.

### Funcionalidades

- Templates editáveis.
- Dados do lead.
- Dados da empresa.
- Serviços ou produtos.
- Valores.
- Condições de pagamento.
- Parcelamento.
- Validade da proposta.
- Cláusulas configuráveis.
- Data de emissão.
- Responsável comercial.
- Geração de PDF.
- Histórico de versões.
- Download autorizado.
- Registro de envio e status.

Utilize uma solução compatível com as dependências existentes para gerar PDFs.

Não invente assinaturas digitais ou validade jurídica.

Se houver integração com assinatura eletrônica, implemente-a somente com provedor e credenciais configurados.

A geração de documentos deve validar os dados e manter o histórico das versões emitidas.

---

## 18. CONTROLE DE COMISSÕES

Implemente um módulo de comissões com regras configuráveis.

Considere:

- Closer.
- Equipe.
- Venda.
- Valor contratado.
- Valor efetivamente recebido.
- Percentual de comissão.
- Comissão fixa, quando aplicável.
- Regras de divisão.
- Parcelas.
- Estornos ou cancelamentos.
- Ajustes autorizados.
- Período de apuração.
- Status de pagamento.

Permita configurar regras por closer, equipe, produto ou tipo de venda, conforme a operação.

Diferencie claramente:

- Comissão estimada.
- Comissão calculada.
- Comissão aprovada.
- Comissão paga.
- Comissão estornada.

Registre a regra aplicada no momento do cálculo, para que alterações futuras não modifiquem silenciosamente valores históricos.

Implemente trilha de auditoria para alterações financeiras.

Não permita que um usuário altere sua própria comissão ou a de outros usuários sem a autorização apropriada.

---

## 19. RELATÓRIOS AUTOMÁTICOS

Implemente relatórios semanais com os principais resultados comerciais.

Os relatórios podem incluir:

- Leads recebidos.
- Reuniões realizadas.
- No-shows.
- Conversões.
- Vendas fechadas.
- Faturamento contratado.
- Valores recebidos.
- Comissões.
- Oportunidades perdidas.
- Motivos de Loss.
- Motivos de desqualificação.
- Desempenho por nicho.
- Desempenho por closer.
- Principais gargalos.
- Tarefas atrasadas.
- Oportunidades que exigem atenção.

Permita configurar destinatários, periodicidade e canais de entrega.

O envio por e-mail ou WhatsApp deve depender de integrações efetivamente configuradas.

Registre cada execução, seu resultado e eventuais falhas.

Evite envios duplicados, principalmente após reinicializações ou reprocessamento de jobs.

Não envie dados comerciais a destinatários sem autorização.

---

## 20. ANÁLISE DE LOSS E MAL QUALIFICADO

Crie relatórios para identificar padrões de perda e desqualificação.

Permita analisar:

- Motivos mais frequentes.
- Distribuição por nicho.
- Distribuição por closer.
- Distribuição por origem.
- Distribuição por etapa.
- Ticket estimado.
- Tempo até a perda.
- Objeções recorrentes.
- Falhas de follow-up.
- Taxas de conversão relacionadas.
- Evolução histórica.

Os motivos de Loss e de Mal Qualificado devem ser obrigatórios quando o lead for movido para essas etapas.

Permita administrar as categorias e acrescentar observações específicas.

Quando houver dados suficientes, a IA poderá apontar padrões e oportunidades de melhoria. Apresente a base utilizada e evite conclusões causais sem evidência.

---

## 21. MODELAGEM DO BANCO DE DADOS

Audite o esquema existente antes de criar tabelas.

Reutilize tabelas e relações existentes sempre que possível.

A modelagem poderá envolver entidades como:

- Users.
- Teams.
- Roles ou Permissions, conforme a implementação atual.
- Leads.
- Lead Activities.
- Pipeline Stages.
- Pipeline History.
- Tasks.
- Reminders.
- Calls.
- Transcripts.
- Call Analyses.
- Playbooks.
- Scripts.
- Objections.
- Proposals.
- Contracts.
- Attachments.
- Payments.
- Installments.
- Commission Rules.
- Commissions.
- Integration Settings.
- Auth Sessions.
- Audit Logs.
- Notification Logs.

Esses nomes são referências, não uma exigência para criar todas as tabelas sem necessidade.

### Regras de persistência

- Use migrations versionadas.
- Adicione índices para consultas frequentes.
- Defina chaves estrangeiras.
- Defina regras de exclusão consistentes.
- Utilize transações em operações críticas.
- Evite duplicação de dados.
- Mantenha timestamps.
- Utilize exclusão lógica quando apropriado.
- Preserve registros financeiros e de auditoria.
- Implemente paginação no backend.
- Evite consultas N+1.
- Defina políticas de retenção e exclusão de dados.

Não execute migrations destrutivas automaticamente em ambientes com dados existentes.

Antes de alterações que possam causar perda de dados, avalie compatibilidade, backup e estratégia de reversão.

---

## 22. API E REGRAS DE NEGÓCIO

Crie APIs consistentes, versionadas e documentadas.

### Requisitos

- Validação no servidor.
- Respostas JSON consistentes.
- Códigos HTTP apropriados.
- Paginação.
- Filtros.
- Ordenação controlada.
- Tratamento centralizado de erros.
- Autenticação JWT.
- Autorização.
- Registro de auditoria.
- Transações nas operações críticas.
- Proteção contra requisições duplicadas quando aplicável.
- Controle de concorrência nas atualizações relevantes.

Nunca confie em valores financeiros, permissões, responsáveis ou status enviados pelo frontend sem validação.

Não exponha stack traces, consultas SQL, tokens ou segredos em respostas de produção.

Implemente documentação das rotas e dos contratos da API, utilizando recursos já disponíveis ou documentação mantida no projeto, sem introduzir ferramentas externas desnecessárias.

---

## 23. SEGURANÇA GERAL

Além da autenticação JWT, implemente:

- Validação de entrada no backend.
- Proteção contra SQL injection.
- Proteção contra XSS.
- Proteção contra CSRF quando aplicável à estratégia de autenticação.
- CORS restritivo.
- Controle de acesso aos arquivos.
- Validação de uploads.
- Rate limiting.
- Segredos fora do repositório.
- Configuração segura de cookies.
- Tratamento de erros.
- Logs sem dados sensíveis.
- Auditoria de alterações críticas.
- Proteção dos endpoints administrativos.
- Controle de acesso aos dados financeiros.
- Separação entre desenvolvimento e produção.

Não inclua senhas, tokens, chaves de API ou credenciais reais em commits.

Revise os arquivos `.env.example` para que contenham apenas nomes de variáveis e valores demonstrativos não sensíveis.

---

## 24. TRATAMENTO DE ERROS E EXPERIÊNCIA DO USUÁRIO

Implemente estados de interface consistentes para:

- Carregamento.
- Sucesso.
- Erro.
- Ausência de dados.
- Falta de permissão.
- Sessão expirada.
- Falha de conexão.
- Serviço indisponível.
- Operação em andamento.
- Validação de formulários.

Evite mensagens técnicas desnecessárias ao usuário final.

Preserve detalhes técnicos nos logs de desenvolvimento, removendo segredos e informações sensíveis.

Quando uma operação falhar, apresente uma alternativa ou orientação clara, sem fingir que os dados foram salvos.

---

## 25. TESTES E GARANTIA DE QUALIDADE

Antes de concluir, execute os testes compatíveis com o projeto.

### Backend

- Testes unitários.
- Testes de integração.
- Testes de autenticação JWT.
- Testes de autorização.
- Testes das regras de negócio.
- Testes de migrations em ambiente apropriado.
- Testes das APIs principais.
- Testes de tarefas e automações.
- Testes de cálculos financeiros.

### Frontend

- Compilação.
- Verificação de tipos, quando aplicável.
- Lint.
- Testes dos componentes principais.
- Testes dos formulários.
- Testes de rotas protegidas.
- Testes de login, logout e expiração de sessão.
- Testes dos filtros e do Kanban.
- Testes de estados de erro.
- Verificação visual em diferentes resoluções.

### Integração

- Login no React utilizando a API Laravel.
- Acesso a rotas protegidas.
- Leitura e gravação de leads.
- Atualização do Kanban.
- Criação e conclusão de tarefas.
- Verificação dos indicadores do dashboard.
- Disponibilidade do PostgreSQL.
- Disponibilidade do frontend e backend.
- Inicialização segura por comando único.
- Inicialização pelo botão de Docker.
- Reutilização dos serviços ativos.
- Ausência de processos duplicados.

Não declare que os testes passaram se não tiver executado os comandos correspondentes.

Se algum teste não puder ser executado por falta de credenciais, serviços ou dependências, registre a limitação claramente.

---

## 26. PLANO DE EXECUÇÃO

Trabalhe em fases incrementais.

### Fase 1 — Auditoria

- Mapear a estrutura existente.
- Identificar os componentes Vue.
- Identificar o backend Laravel.
- Auditar Docker e containers ativos.
- Inspecionar o banco e as migrations.
- Identificar dependências e integrações.
- Registrar riscos e incompatibilidades.

### Fase 2 — Fundação e autenticação

- Preparar o frontend React.
- Preservar o Vue como referência.
- Implementar autenticação JWT própria.
- Implementar middleware e autorização.
- Criar ou adaptar os modelos e migrations necessários.
- Proteger as rotas da API.
- Implementar o fluxo de autenticação no React.
- Testar a autenticação ponta a ponta.

### Fase 3 — CRM essencial

- Implementar cadastro e ficha de leads.
- Implementar Kanban.
- Implementar histórico.
- Implementar tarefas e lembretes.
- Implementar filtros e busca.
- Implementar Loss e Mal Qualificado.

### Fase 4 — Gestão e indicadores

- Implementar dashboard.
- Implementar métricas comerciais.
- Implementar comparativos por nicho.
- Implementar ranking de closers.
- Implementar comissões.
- Implementar relatórios de perdas.

### Fase 5 — IA e integrações

- Implementar transcrições.
- Implementar análise de calls.
- Implementar biblioteca de scripts.
- Implementar banco de objeções.
- Implementar Google Calendar.
- Implementar WhatsApp.
- Implementar geração de propostas e contratos.
- Implementar relatórios automáticos.

A ordem pode ser ajustada conforme as dependências reais do projeto. Não simule integrações indisponíveis.

### Fase 6 — Docker e operação

- Implementar o comando único de inicialização.
- Implementar o botão de Docker no React.
- Garantir inicialização idempotente.
- Verificar os serviços de forma real.
- Evitar duplicação de processos.
- Confirmar a preservação dos containers ativos.

### Fase 7 — Validação final

- Executar testes.
- Corrigir erros encontrados.
- Verificar segurança.
- Verificar responsividade.
- Comparar visualmente o React com o Vue.
- Testar o fluxo completo de uso.
- Atualizar a documentação.

---

## 27. CRITÉRIOS DE ACEITAÇÃO FINAL

O trabalho só deverá ser considerado concluído quando os critérios aplicáveis abaixo forem atendidos.

### Interface

- O React em `frontend` reproduz fielmente a identidade visual do Vue.
- A pasta `frontend-vue` permanece preservada.
- A interface funciona em diferentes resoluções.
- Os componentes e botões possuem comportamento real.

### Autenticação

- O login utiliza JWT próprio.
- Não depende de pacotes externos de autenticação do Laravel.
- A assinatura e a expiração são validadas corretamente.
- O logout e a renovação de sessão funcionam conforme a estratégia implementada.
- As rotas privadas estão protegidas.
- As permissões são validadas no backend.
- Os testes de segurança da autenticação foram executados.

### CRM

- O Kanban funciona e persiste as mudanças.
- A ficha do lead registra o histórico.
- Loss e Mal Qualificado exigem os respectivos motivos.
- As tarefas podem ser concluídas, adiadas e reagendadas.
- Os dashboards utilizam dados persistidos.
- As comissões seguem regras configuráveis e auditáveis.
- As integrações indicam claramente quando estão ou não configuradas.

### Docker

- Existe um botão de Docker na barra superior.
- O botão verifica o estado real dos serviços.
- Uma única ação inicia o que for necessário.
- Frontend e backend não são iniciados em duplicidade.
- Os serviços ativos e saudáveis são reutilizados.
- O mecanismo de inicialização é idempotente.
- O comando `./dev.sh up`, ou seu equivalente documentado, funciona.
- Não há remoção ou interrupção desnecessária de containers, redes ou volumes.
- O controle administrativo não expõe execução arbitrária de comandos.

### Qualidade

- O código está organizado e documentado.
- As migrations são compatíveis com o banco existente.
- Os testes executados estão registrados.
- As limitações restantes estão documentadas.
- Não existem segredos versionados.
- Não há funcionalidades críticas apresentadas como concluídas quando ainda são simuladas.

---

## 28. INSTRUÇÕES FINAIS PARA EXECUTAR O TRABALHO

Não responda apenas com sugestões ou exemplos genéricos. Trabalhe diretamente nos arquivos do projeto, conforme as ferramentas e permissões disponíveis.

Siga estas prioridades:

1. **Preservar o ambiente existente e seus dados.**
2. **Auditar antes de modificar.**
3. **Implementar autenticação JWT própria, segura e testada, sem pacotes externos de autenticação do Laravel.**
4. **Reproduzir fielmente a interface Vue em React.**
5. **Implementar o CRM com persistência e regras de negócio reais.**
6. **Colocar o controle de Docker na barra superior.**
7. **Permitir iniciar o ambiente com uma única ação, sem duplicar frontend ou backend.**
8. **Testar e documentar os resultados reais.**

Não reescreva toda a aplicação sem necessidade. Não substitua tecnologias existentes apenas por preferência pessoal. Não elimine funcionalidades existentes. Não altere a configuração dos containers ativos sem avaliar os impactos.

Quando houver decisões arquiteturais relevantes, escolha a alternativa mais segura e compatível com o projeto, documentando a decisão.

Quando houver dependências externas indisponíveis, implemente o que for possível de forma real e deixe explícito o que exige configuração adicional.

Ao finalizar, apresente um resumo contendo:

- Arquivos criados e modificados.
- Funcionalidades implementadas.
- Como funciona a autenticação JWT.
- Como o botão de Docker opera.
- Como iniciar o ambiente com um único comando.
- Testes efetivamente executados e seus resultados.
- Integrações que dependem de credenciais.
- Limitações conhecidas.
- Riscos ou decisões que ainda exigem atenção.

**Resultado esperado:** um CRM comercial funcional e seguro, com frontend React visualmente fiel ao Vue, backend Laravel, autenticação JWT própria, PostgreSQL, controle de Docker integrado à interface e inicialização única, idempotente e segura, preservando os containers e dados que já existem.