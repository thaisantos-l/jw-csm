# JW US Invest — CMS

WordPress headless com WPGraphQL, plugin próprio `jw-core`, metaboxes nativos e recursos gratuitos. O frontend fica no repositório [jw-front](https://github.com/thaisantos-l/jw-front).

## Ambiente local entregue

- Administração: http://localhost/jw/jw-cms/wp-admin/
- Banco: `jw`; prefixo: `jw_`. Não compartilha conteúdo/configuração com Blooikos.
- WordPress 7.1.5, WPGraphQL 2.29.0, WP Mail SMTP 4.10.4 e Safe SVG 2.5.2.
- `JW US Invest → Editar Home`: textos, imagens, repetidores, FAQ e imóveis selecionados.
- `Imóveis`: cadastro das propriedades. A URL opcional torna o card inteiro clicável.
- `JW US Invest → Configurações`: logos, SEO, contatos, Instagram (`@` e URL independentes), destinatário e rodapé.
- `JW US Invest → Menus`: links do cabeçalho e rodapé. Âncoras: `#sobre`, `#tese`, `#portfolio`, `#como-investir`, `#contato`.

Na Home, desmarcar **Exibir imóveis na Home** oculta a seção e os links `#portfolio` dos menus. Os imóveis permanecem cadastrados. A relação determina a ordem, sem depender do título ou de IDs fixos. Repetidores aceitam até 20 itens; seletores administrativos mostram até 200 registros.

Os textos e as imagens iniciais foram importados do Figma `DAML6reLloLGruZ9qcCRDs`, frame `57:3`, preservando a redação solicitada. Das cinco perguntas, quatro estão sem resposta no Figma e permanecem editáveis, ocultas até serem preenchidas. Privacidade e Termos são rascunhos; não foi inventado conteúdo jurídico. O Instagram exibe o `@` informado, mas ainda não tem URL editorial cadastrada.

## Contrato e atualização

`contracts/jw-home.graphql` no plugin contém a operação `JWHome`. O root `jwContent` expõe Home, configurações públicas, menus e páginas legais publicadas. Nunca expõe o destinatário privado, credenciais SMTP ou mensagens. Mídias, páginas e imóveis privados/rascunhos não entram na projeção.

`GET /wp-json/jw/v1/site-content` executa essa operação e retorna `schemaVersion`, `revision`, `digest`, `generatedAt` e `data`. O digest SHA-256 do JSON UTF-8 canônico também é o ETag. `If-None-Match` permite HTTP 304. O cache é invalidado ao concluir a gravação de posts, metas, opções e menus relevantes; não usa cron, Actions ou rebuild editorial.

`JW_ALLOWED_ORIGINS` é uma lista exata no wp-config privado, sem wildcard. A configuração local de exemplo está em `config/`. Em produção, somente origens HTTPS explicitamente configuradas serão aceitas; não há domínio de produção presumido.

## Formulário e SMTP

`POST /wp-json/jw/v1/contact`: JSON até 32 KiB. `name`, `email`, `phone`, `message` obrigatórios; `company` opcional; `website` deve ficar vazio. Limites: 120/254/40/3000/120 caracteres. Nome mínimo 2; mensagem mínima 10; telefone de 8 a 15 dígitos. Campos desconhecidos são rejeitados. Não aceita destinatário vindo do navegador.

Um único destinatário é lido da configuração vigente em cada POST. Visitante somente em Reply-To, corpo texto simples e assunto técnico fixo. HTTP 200 `CONTACT_ACCEPTED` significa aceitação pelo transporte. Não comprova entrega externa. Falhas de envio retornam 503; limites retornam 429 com Retry-After. Quotas atômicas: 5 tentativas válidas por cliente/15 minutos e 60 por hora global; honeypot, origem restrita e armazenamento técnico limitado. Não há persistência de leads nem API de leitura de mensagens.

WP Mail SMTP está conectado **somente ao Mailpit local**, SMTP `127.0.0.1:1025`, painel http://127.0.0.1:8025, sem encaminhamento. A conexão real com a Hostinger será configurada posteriormente. Nunca versionar senha SMTP, wp-config ou export de banco.

## Instalação e manutenção

Este repositório versiona o produto próprio, a lista de dependências e ferramentas de qualidade. WordPress, plugins de terceiros, uploads, banco, credenciais e ferramentas locais não fazem parte do Git. Para restaurar o ambiente editorial completo, é necessário o backup separado do banco e de `wp-content/uploads`.

Em um checkout novo, use PHP 8.2+ e WP-CLI e execute `scripts/install-dependencies.ps1 -Php <php.exe> -WpCli <wp-cli.phar>`. Configure o banco vazio e wp-config privados, instale o WordPress e repita o comando para ativar as dependências. O script não sobrescreve dados editoriais nem configura publicação. Importe o backup ou crie a Home publicada e selecione-a em Configurações → Leitura; o plugin então oferece seus metaboxes.

Qualidade: `composer install` e `composer check`; PHP lint nos arquivos próprios. Testes locais, fixtures e evidências ficam fora do Git. O produto não depende deles.

**Deploy não configurado.** A publicação e a configuração real de SMTP aguardam autorização posterior.
