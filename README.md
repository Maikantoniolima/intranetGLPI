# Intranet — Plugin para GLPI

Um hub interno, bonito e útil, dentro do seu GLPI.  
O **Intranet** reúne **notícias internas**, **banner/avisos**, **links rápidos** e **widgets** (ex.: clima) em um único painel para colaboradores — direto no GLPI, sem depender de ferramentas externas.

![Status](https://img.shields.io/badge/status-ativo-success)
![Compatibilidade](https://img.shields.io/badge/GLPI-10.0.0_%E2%86%92_10.0.20-blue)
![Licença](https://img.shields.io/badge/licen%C3%A7a-GPLv2%2B-lightgrey)

## Por que eu criei este plugin?

Em muitas equipes o GLPI já é “a porta de entrada” do dia a dia. Faltava um **portal interno simples** dentro dele para:
- comunicar avisos importantes logo que o usuário entra;
- publicar **notícias internas** sem gambiarras;
- centralizar **links úteis** (RH, políticas, sistemas internos);
- reduzir a dependência de e-mail/grupos para comunicados.

## O que ele resolve

- **Comunicação interna visível**: banners e notícias no próprio GLPI.  
- **Organização**: links rápidos padronizados para o que o time usa todo dia.  
- **Onboarding**: novos usuários encontram tudo no mesmo lugar.  
- **Agilidade**: menos trocas de contexto entre ferramentas.

## Principais recursos

- **Dashboard da Intranet** com banner e botões de atalho.  
- **Gestor de Notícias** (publicar, expirar, listar).  
- **Widget de Clima** (cidade configurável).  
- **Layout responsivo e leve** (CSS simples).  
- **Fluxo de persistência previsível e seguro** (abaixo).

## Compatibilidade

- **GLPI 10.x** — **testado de 10.0.0 até 10.0.20** ✅  
- **GLPI 11.x** — em avaliação (abra uma issue com detalhes do seu ambiente se testar).

> Use versões de PHP/MySQL compatíveis com a versão do GLPI que você executa.

## Instalação

1. Copie/clone este repositório em:  
   `.../glpi/plugins/intranet/`
2. No GLPI, acesse **Configurar → Plug-ins**, localize **Intranet**, clique **Instalar** e depois **Habilitar**.  
   > Se o diretório tiver outro nome, renomeie para **`intranet`**.

## Configuração rápida

- **Banner**: faça **upload** pela aba *Banner* (**POST**).  
  O arquivo é salvo em `plugins/intranet/banner/banner.jpg` (um único arquivo; subir outro substitui) e a tela redireciona com `?salvar=1&banner=...&v=timestamp` (cache-busting).

- **Dados**: na aba *Dados* (**GET** com `salvar=1`) defina botões, links e cidade do clima.

- **Notícias**: use o **Gestor de Notícias** para criar/editar e configurar **publicação/expiração**.

## Permissões

- Telas administrativas (configurações e notícias) exigem direito apropriado (ex.: `config: UPDATE`).  
- A **visualização** do dashboard segue as permissões padrão do GLPI para usuários autenticados.

## Padrão do projeto (persistência & upload)

- **Persistência via GET** com `salvar=1` (**atualização parcial**: só altera os campos presentes na querystring).  
- **Upload via POST** (ex.: banner) salva o arquivo em disco e **redireciona para GET** com `salvar=1` + `?v=timestamp`.  
- **Sem gravação no banco pelo POST**.  
- **Checagem de permissões** antes de qualquer alteração de estado.  
- **Mensagens padronizadas** de sucesso/erro e redirect pós-ação.

## Estrutura (resumo)

```
plugins/intranet/
├─ setup.php
├─ hook.php
├─ intranet.xml
├─ assets/
│  └─ intranet.css
├─ front/
│  ├─ dashboard.php
│  ├─ config.php
│  ├─ news.php
│  └─ news.form.php
├─ inc/
│  ├─ config.class.php
│  ├─ news.class.php
│  ├─ dashboard.class.php
│  └─ menu.class.php
└─ sql/
   └─ mysql/
      └─ update-1.0.0.sql
```

## Roadmap

- Perfis/ACL por módulo (ver/editar/excluir notícias).  
- Ajustes finos e validação completa para **GLPI 11.x**.  
- Blocos extras no dashboard (agenda, comunicados rápidos, etc.).

## Contribuição

- Encontrou um problema? **Abra uma issue** com: versão do GLPI, versão do PHP, passos para reproduzir, prints/logs.  
- Pull Requests são bem-vindos — mantenha o padrão **GET para persistência** / **POST só upload** e as mensagens/redirects padronizados.

## Licença

GPL v2+  
© Maik Lima — **Intranet para GLPI**
