# Co-author and Supervisor Alert — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.0.1.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/coauthorAlert/releases/download/1.0.1.0/coauthorAlert-1.0.1.0.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Journal Systems (OJS)** that makes sure authors of supervised work
add their supervisor as a co-author **while they still can**: a prominent notice on the
contributors step that reacts to the author list, and a last-chance notice on the review step
with an acknowledgement the author must tick before submitting.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.1.0 |

## The problem

Many journals publish work by undergraduate and graduate students, written under a supervisor.
Students often submit alone, and two things then go wrong:

- **The author list is frozen at submission.** Once the submission is sent, the author cannot
  add the supervisor any more, and the work may no longer count for what the student needed it
  for (a final project, for instance).
- **The editor does not know who the supervisor is.** Without that, the supervisor can be
  invited to review their own student's work, which defeats double-blind review.

Some authors genuinely submit alone, so blocking single-author submissions is not an option.
The author has to be warned unmistakably, at the right moment, and has to acknowledge it.

## What it does

- **Contributors step:** a notice right below the contributor list, followed by a status strip
  that is red while the submission has a single author and turns green as soon as a co-author
  is added — live, without reloading, because it reads the wizard's own author list.
- **Review step:** a last-chance notice next to the contributors review panel and an
  acknowledgement checkbox. Until it is ticked, the submit button does nothing: the error
  message appears, the box is highlighted and scrolled into view. Once ticked, the wizard's
  own confirmation follows as usual.
- The "Continue" button of the earlier steps is never affected.
- **Every text is configurable, per journal and per language**, and comes prefilled with a
  default wording in every language the plugin ships. An emptied field falls back to the
  default wording of that language.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so that you get `plugins/generic/coauthorAlert/`.
   Do not rename the folder: OJS derives the plugin's class namespace from the directory name.
2. Enable **Co-author and Supervisor Alert** in the *Generic* plugins list.
3. Open the plugin's **Settings** to review the texts.

## Configuration

| Setting | Default | What it means |
|---------|---------|---------------|
| Notice heading / text (contributors step) | prefilled | Plain-text heading; the text accepts the HTML the journal allows (`allowed_html`) |
| Message with a single author / with co-authors | prefilled | The red and green status strips |
| Final notice heading / text (review step) | prefilled | Shown next to the contributors review panel |
| Require the acknowledgement | on | Off: the notices stay, without the checkbox and without blocking |
| Require it only for single-author submissions | off | On: with two or more authors the checkbox disappears |
| Acknowledgement text / error message | prefilled | The checkbox label and the message shown when submitting without it |

## How it works (technical)

**Where the acknowledgement is enforced.** The tick that holds the Submit button is enforced in
the browser: the plugin listens for the click before the wizard does and stops it while the box is
not ticked. The server does not refuse a submission for a missing tick, and it is not meant to —
this is a reminder to the author, not an editorial rule. A journal that needs a rule the server
enforces should ask for the co-author in the metadata itself (see `requiredAuthorMetadata`).


Only official hooks, no core file is changed:

- **`Template::SubmissionWizard::Section`** for the contributors step. That call sits inside the
  Vue loop over every section of every step, so the markup is emitted once but instantiated by
  Vue for each section: it carries `v-if="section.id === 'contributors'"`.
- **`Template::SubmissionWizard::Section::Review`** for the review step, filtered to the
  `contributors` review panel.
- **`TemplateManager::display`** loads the stylesheet and script on `submission/wizard.tpl` only.

**Why the submit button is gated by intercepting the click.** The wizard's `canSubmit` depends
on the options fields of the `confirmSubmission` section, but that section only exists when the
journal has a copyright notice — on journals without one, a field injected there would silently
vanish. The plugin uses its own checkbox and a capture-phase click listener on the wizard
footer's primary button, which survives any Vue re-render of the button. The listener only acts
while the checkbox is visible, and inactive steps are rendered with `hidden`, which is why the
earlier "Continue" buttons are never blocked.

This is a browser-side check, exactly like the wizard's own copyright acknowledgement
(`ConfirmSubmission` uses `FormComponent::ACTION_EMIT`, and its value never reaches the server).

**Journal texts are never compiled by Vue.** The wizard is a Vue template compiled from the page
itself, and the browser decodes HTML entities before Vue reads it — so escaping `{{` on the
server does not stop a text such as `{{ 7*7 }}` from being evaluated. Every element holding a
configured text carries `v-pre`. On top of that, plain-text settings are stripped and escaped,
and HTML settings go through `PKPString::stripUnsafeHtml()` when saved and again when printed.

## Tests

- **PHP suite** (`tests/`): the plugin classes compiled against the running PKP version (an
  override whose return type does not match the parent is a fatal error that `php -l` does not
  catch), the language fallback rules, sanitization of what the settings form saves, the
  `v-pre` guarantee in the templates, and the completeness of every translation — OJS 3.5 has
  no locale fallback, so a missing key would be displayed as `##key##`.

  The suite runs on PKP's own `PKPTestCase` under PKP's PHPUnit, the way the official plugins do:

  ```bash
  php lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml plugins/generic/coauthorAlert/tests
  ```

- **Cypress** (`cypress/tests/functional/CoauthorAlert.cy.js`): enabling the plugin, the
  prefilled settings round trip, the live status strip, the blocked submit, the acknowledgement
  and the fallback to the default text. It runs on the PKP test data by default and accepts
  `contextPath`, `adminUser`, `adminPassword` and `submissionId` through `--env`.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**, the same license as OJS.

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). `en` is the
master locale file; entries marked `fuzzy` are the ones still waiting for a native speaker.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Journal Systems (OJS)** que garante que autores de trabalhos
orientados incluam o orientador como coautor **enquanto ainda é possível**: um aviso destacado
na etapa de contribuidores, que reage à lista de autores, e um aviso de última chance na etapa
de revisão, com uma declaração de ciência que o autor precisa marcar antes de submeter.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).**

### Compatibilidade e branches

| Versão do OJS | Branch | Release do plugin |
|---------------|--------|-------------------|
| OJS 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.1.0 |

### O problema

Muitas revistas publicam trabalhos de alunos feitos sob orientação, e é comum o aluno submeter
sozinho. Aí duas coisas dão errado: **a lista de autores congela na submissão** — depois de
enviada, o autor não consegue mais incluir o orientador, e o trabalho pode deixar de valer para
o que o aluno precisava (a validação como TCC, por exemplo); e **o editor não sabe quem é o
orientador**, que pode acabar convidado a avaliar o trabalho do próprio aluno, comprometendo a
avaliação duplo-cega.

Como há autores que de fato submetem sozinhos, bloquear submissão de autor único não é opção. É
preciso avisar de forma inequívoca, no momento certo, e registrar que o autor foi avisado.

### O que faz

- **Etapa de contribuidores:** aviso logo abaixo da lista, seguido de uma faixa vermelha enquanto
  houver um só autor, que fica verde assim que um coautor é incluído — na hora, sem recarregar.
- **Etapa de revisão:** aviso de última chance junto ao painel de contribuidores e caixa de
  declaração de ciência. Enquanto não for marcada, o botão de submeter não faz nada: aparece a
  mensagem de erro, o bloco é destacado e a tela rola até ele.
- O botão "Continuar" das etapas anteriores nunca é afetado.
- **Todos os textos são configuráveis por revista e por idioma**, já preenchidos com uma redação
  padrão em cada idioma do plugin. Campo apagado volta ao texto padrão daquele idioma.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta em
`plugins/generic/` (ficando `plugins/generic/coauthorAlert/`). Não renomeie a pasta: o OJS
deriva o namespace da classe do nome do diretório. Depois ative o plugin na lista de
*Genéricos* e abra as **Configurações** para revisar os textos.

### Onde o aceite é exigido

A marcação que segura o botão Enviar é exigida **no navegador**: o plugin escuta o clique antes do
assistente e o interrompe enquanto a caixa não estiver marcada. O servidor não recusa a submissão
por falta da marcação, e não é essa a intenção — isto é um aviso ao autor, não uma regra editorial.
Revista que precise de uma regra exigida pelo servidor deve pedir o coautor nos próprios metadados
(ver `requiredAuthorMetadata`).

### Configuração

Títulos e textos dos dois avisos, mensagens da faixa de autor único e de coautores incluídos,
texto da declaração e mensagem de erro — todos por idioma. Duas opções: **exigir a declaração**
(ligada por padrão; desligada, os avisos continuam sem caixa e sem bloqueio) e **exigir só quando
houver um único autor** (desligada por padrão).

### Testes

Suíte PHP em `tests/` (compatibilidade das classes com a versão do PKP, regras de idioma,
sanitização, garantia do `v-pre` nos templates e completude das traduções), sobre o
`PKPTestCase` do próprio PKP, como nos plugins oficiais:

```bash
php lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml plugins/generic/coauthorAlert/tests
```

E teste Cypress em `cypress/tests/functional/CoauthorAlert.cy.js`, cobrindo ativação,
configurações, faixa reativa, bloqueio do envio, declaração e volta ao texto padrão.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**, a mesma licença do OJS.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
