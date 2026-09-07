# enrol_mercadopagocpro — handover for v1.1.0

State as of 2026-09-02. **v1.1.0 is a compliance release, not a feature release.** Its
single purpose is to make `enrol_mercadopagocpro` publishable on Moodle Marketplace. No
new functionality enters this version. Anything that changes what the plugin *does* waits
for v1.2.0.

Baseline is v1.0.1, delivered 2026-08-28: 65 PHPUnit tests / 191 assertions green, Behat
4/4 across 60 steps, phpcs `moodle-extra` at 0 errors and 0 warnings, the bundled SDK
byte-identical to `mercadopago/dx-php` 3.14.0, and a manual end-to-end payment completed
with test credentials.

Companion documents: `MARKETPLACE-SUBMISSION-RUNBOOK.md` (the process and the tooling),
`DEV-ENVIRONMENT-SETUP.md` (the dual-database box). This file is the scope contract.

---

## 1. Decisions frozen for v1.1.0

These are settled. Reopen only against evidence, and say which evidence.

**Copyright is the company's; authorship is the individual's.** Every file carries:

```php
 * @copyright  2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author     Julio Tentor <jtentor@juliotentor.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
```

`@author` rotates as the developer changes; `@copyright` does not. Where a file is derived
from someone else's work, the original `@copyright` line stays and the company's is added
below it — the checklist wants both facts visible: who is to blame for this code, and whose
work it builds on.

**Marketplace identity is `admin@juliotentor.com`.** The listing belongs to the company.
There are no migrated Plugins Directory listings to link, so the "same email as your
moodle.org account" rule does not constrain the choice. Moving a listing between accounts
afterwards is a service-desk ticket; getting it right at registration costs nothing.

**The plugin is free and stays GPLv3.** Anyone may download it, install it, fork it and
ship their own version. The company's consulting, implementation and support services are
sold outside the Marketplace and have no relationship to the listing. Practical
consequence: no paid-plugin track, therefore no annual re-review, no revenue share, and no
Terms of Sale to write.

**Credentials in every environment are Mercado Pago test credentials, seller and buyer
alike.** This is not a convenience; it is the rule. The development box runs cron
unattended, and a production token in `config.php` there would have `reconcile_payments`
talking to a live account with nobody watching.

**Only English strings ship in the package.** `lang/es/` moves out of the `lang/`
directory — see §3.2. The Spanish translation goes to AMOS once the plugin is approved.

**The component name does not change.** `enrol_mercadopagocpro` is 15 characters, inside
the `char(20)` limit of the core `enrol.enrol` column, and carries no underscore in the
plugin-name segment, so `enrol_plugin::get_name()` resolves correctly. The name is also
free of the collision with `enrol_mpcheckoutpro` that forced the last rename. This is
settled and expensive to revisit; the *display* name is a different question (§2.4).

---

## 2. Work items — code and strings

### 2.1 Copyright block across the tree

~50 files. Mechanical, but do it in one commit so the diff is reviewable as "headers only"
and does not hide a behavioural change. Run the suite afterwards; a malformed docblock
breaks `phpdoc`, which is one of the nine prechecks.

### 2.2 `version.php`

The Marketplace reads build number, release name, maturity and supported Moodle versions
**from the package** and will not let you override them in the submission form. Get them
right before zipping:

```php
$plugin->component = 'enrol_mercadopagocpro';
$plugin->version   = 2026090200;          // bump
$plugin->release   = 'v1.1.0';
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires  = 2026042002;          // see below
$plugin->supported = [502, 502];
```

`$plugin->supported` is what produces the compatibility line on the listing. Declaring it
explicitly is better than letting `requires` imply an open-ended upper bound the plugin has
never been tested against.

**`$plugin->requires = 2026042002` has still never been checked against
`public/version.php` on `MOODLE_502_STABLE`.** It was supplied as the value for 5.2.2 and
carried forward. Verify it on the new development box, where a real 5.2.2 tree is sitting
on disk. A wrong `requires` either blocks installation on sites that should work or permits
it on sites that will not.

### 2.3 Version claims must agree across the tree

The README states "Requires: Moodle 5.2.2 … PHP 8.2+". **Moodle 5.2 requires PHP 8.3 or
later**, so the PHP claim is wrong and a reviewer checking the obvious will find it. The
README also still says "Version: v1.0.0" while v1.0.1 shipped.

Fix both, and consider removing the hard-coded version from the README entirely — it has
drifted once already and `version.php` is the only place it needs to exist.

### 2.4 `$string['pluginname']` — decided

```php
$string['pluginname'] = 'Mercado Pago Checkout Pro (Tentor & Associates)';
```

English only; the Spanish string follows through AMOS after approval. `pluginname_desc`
and any other display string that repeats the old name must move with it — grep the whole
lang file rather than changing one line.

**Why this form.** `enrol_mpcheckoutpro` (redesitos) is listed and uses effectively the
same words, so a site with both installed sees two indistinguishable entries in the *Add
method* dropdown. That is a real usability defect independent of the review, and an
organisation marker fixes it while claiming nothing about behaviour.

**Why not a capability marker yet.** A name asserting country detection would be false in
v1.1.0. Detecting the country and currency from the collecting account is `collector` in
`enrol_mercadopagosub`; `enrol_mercadopagocpro` still offers a currency dropdown defaulting
to ARS. Porting `collector` is functional work and therefore out of scope here (§7). When
it lands, `pluginname` can become `Mercado Pago Checkout Pro (multi-country)` — it is a
display string, not the frankenstyle, so changing it later costs nothing.

**Why not "Checkout Pro+".** "Checkout Pro" is Mercado Pago's own product name. Appending a
modifier directly to it reads as a Mercado Pago product tier that does not exist, which is
the wrong side of the line drawn in §6.2: describing what the plugin integrates with is
nominative use and fine; appearing to name a product of theirs is not. The qualifier stays
separate and identifies *us*.

**Why not "Global".** Mercado Pago operates in seven Latin American countries. An
administrator in Spain or Kenya who reads "Global" and installs this has wasted their time,
and a reviewer may read it as an inaccurate description.

Note for future releases: `pluginname` is what an administrator sees in a dropdown, and
AMOS translators re-translate it every time it moves. Version and evolution markers belong
in `$plugin->release`, the CHANGELOG and the listing description — which is a separate,
editable field where there is room to say more.

### 2.5 Privacy provider — one item closed, two opened

**Closed.** `add_external_location_link('mercadopago', …)` is present and declares email,
first name, last name, external reference, metadata and item. Verified 2026-09-03 by
reading `classes/privacy/provider.php`. This was the checklist's own emphasis for plugins
integrating with an external system, and it is satisfied.

**Open — `enrol_mercadopagocpro_wh` is neither declared nor deleted.** The table holds
`payload`, `dataid`, `requestid` and `errormessage`, and joins to a user through `txnid`.
`get_metadata()` declares only `enrol_mercadopagocpro_txn`, and `delete_data_for_user()`
deletes only from that table — so erasing a user leaves webhook rows pointing at a `txnid`
that no longer exists. That is an incomplete erasure, and an incorrectly implemented
privacy API is approval blocker #5.

**Open — export declares twelve fields and exports fourteen.** `export_user_data()` emits
`statusdetail`, `enrolmentstate` and `paymenttypeid`, none of which appear in
`get_metadata()`. A reviewer comparing the two methods will see it.

**Scope decision required.** Both are compliance corrections rather than features, and both
are contained: three language strings, a metadata block, one `delete_records` call in each
of the three delete methods, and an extension to `privacy_provider_test`. This file's rule
is that v1.1.0 changes no functionality — privacy erasure is arguably not functionality,
but the call is Julio's, and doing nothing means submitting with a known erasure gap.

### 3.6 Line endings

73 of the 76 files outside `vendor/` are stored with CRLF, including `db/install.xml`, the
Mustache templates and both language files. The Moodle coding style uses Unix line endings.

**This is hygiene, not a precheck blocker, and the evidence says so:** phpcs under
`moodle-extra` reported 0 errors and 0 warnings on this tree in its current state, so the
standard is not enforcing a line-ending sniff. Convert anyway — the diff noise alone earns
it, and `.gitattributes` now pins `eol=lf` so it cannot come back.

**`vendor/` is unaffected.** Spot-checked 2026-09-03: `MercadoPagoConfig.php` is LF ASCII,
consistent with the SDK having been extracted from a Composer download and never rewritten
by the tree's own tooling. Confirm across the whole bundle before packaging:

```bash
find vendor -name '*.php' -print0 | xargs -0 file | grep -c CRLF   # expect 0
```

A non-zero answer means the bundle is no longer byte-identical to upstream 3.14.0 and
`thirdpartylibs.xml` is making a false declaration. The fix in that case is to restore from
upstream, not to convert: converting produces LF but proves nothing about identity.

### 2.6 `cli/diagnose.php` section numbering

Prints sections 4→6 and 8→11 with visible gaps when conditional sections are skipped. A
"skipped, pass `--courseid=N`" line closes it. Cosmetic, low risk, and it is the first
thing a reviewer running the CLI will notice.

---

## 3. Work items — packaging

### 3.1 `.gitattributes` and the ZIP

```
lang/es/              export-ignore
.github/              export-ignore
.vscode/              export-ignore
.gitattributes        export-ignore
.gitignore            export-ignore
.phpcs.xml            export-ignore
.moodle-plugin-ci.yml export-ignore
```

```bash
git archive --format=zip --prefix=mercadopagocpro/ \
    -o enrol_mercadopagocpro-v1.1.0.zip v1.1.0
```

`export-ignore` does not affect `clone`, so the repository keeps everything while the
package carries only what a site needs. The `--prefix` is what produces the single
top-level `mercadopagocpro/` directory the installer expects — not
`moodle-enrol_mercadopagocpro/`, and not files at the archive root. This is the most common
mechanical rejection.

`tests/` stays in the package. It is harmless and it demonstrates the work.

### 3.2 `lang/es/` moves to `translations/es.php`

Not merely excluded from the ZIP — moved out of `lang/`. Two reasons. It stops being a
loadable language directory, so the question does not arise even for someone reading the
repository. And once AMOS becomes the canonical source of the Spanish strings, a file
sitting at `lang/es/enrol_mercadopagocpro.php` is a second copy that will diverge, and
somebody will eventually reimport the stale one.

Keep the 293 strings verbatim in the move. They are the seed for the AMOS import after
approval, and re-translating them later is wasted work.

### 3.3 Composer — resolved 2026-09-04, and it was bigger than one file

The question was framed as "does `composer.json` ship". Reading the tree showed the plugin
contradicting itself in four places, which is why the shadowing incident happened at all.

**On one side**, `sdk::register()` checked for `vendor/autoload.php` inside the plugin
directory first and returned on finding it, never reaching the bundle — and three documents
told people to create exactly that file. `README.md`: "If you prefer to manage it yourself,
run `composer install --no-dev` inside the plugin directory." `docs/DEPLOYMENT.md`: the same
command, in the production checklist. `docs/TROUBLESHOOTING.md`: offered it as the *fix* for
a missing SDK.

**On the other side**, `cli/diagnose.php` lists `vendor/autoload.php`, `vendor/composer` and
`vendor/mercadopago/dx-php` as stray artefacts to delete, and `docs/TESTING.md` warns
against producing them.

So an administrator following the deployment checklist would replace the audited bundle that
`thirdpartylibs.xml` declares as unmodified upstream 3.14.0, and nothing would say so. The
earlier incident was not carelessness; the documentation instructed it.

**Resolved: the bundled SDK is the only supported configuration.** Moodle requires a plugin
to install without an administrator running Composer, so there was never a supported
configuration on the other side. Four changes, coherent with each other:

1. `composer.json` removed from the repository, not merely from the package. The version pin
   it documented is already in `thirdpartylibs.xml`, which is the file Moodle reads.
2. The `vendor/autoload.php` branch removed from `sdk::register()`. Removing only the
   `composer.json` would have left the mechanism intact — `composer require` creates its own
   manifest.
3. `README.md`, `docs/DEPLOYMENT.md` and `docs/TROUBLESHOOTING.md` corrected.
4. `cli/diagnose.php` and `docs/TESTING.md` unchanged; they were already right.

Classified as a fix rather than a feature: the plugin's own diagnostic declared a state an
error while its loader treated that state as preferred. No test referenced the removed
branch.

### 3.4 `thirdpartylibs.xml`

The `thirdparty` precheck exists for exactly this file, and a mismatch is expensive: paths
that do not resolve mean the 250 bundled SDK files get run through `phpcs`, and the report
drowns in errors that are not yours.

Verify: the declared path matches `vendor/mercadopago` as it appears **in the ZIP**, the
version reads 3.14.0, and the licence is GPL-compatible. Then confirm byte-identity of the
bundled tree against upstream 3.14.0 one more time, from the built archive rather than the
working copy — `phpcbf` has drifted this tree before.

Also confirm the tree is pure PHP. Binary files violate GPL unless source accompanies them.

### 3.5 `styles.css` and `templates/`

Two prechecks that never mattered for this plugin's own test suite now do:

- **css** — all `styles.css` files across every installed plugin are concatenated and
  served on every page. Selectors must be namespaced. `.path-enrol-mercadopagocpro …` or an
  equivalent plugin-scoped prefix; a bare `.contentarea` affects the whole site.
- **mustache** — templates are linted for structure and for the documentation block Moodle
  expects at the top of each.

Neither has been checked. Run `moodle-plugin-ci` before assuming they pass.

---

## 4. Work items — repository and CI

### 4.1 What must be in the repository

- `LICENSE` (GPL-3.0), present.
- `README.md` with the same substance as the listing description.
- `CHANGELOG.md`, present, with a v1.1.0 entry.
- **GitHub Issues enabled and visible.** Approval blocker #1 is the absence of a public
  tracker. Confirm it is actually on, not merely un-disabled by default.
- `.github/workflows/` — see §4.3.
- Repository name `moodle-enrol_mercadopagocpro`, already correct, and the repository root
  is the plugin folder root, also already correct.
- A `v1.1.0` git tag, annotated, with release notes.

### 4.2 What must not be in the repository

- Any credential, token, webhook secret or key, in any file, in any commit, including
  history. Run a secret scan over the full history before the repository gets attention.
- `.vscode/`, currently committed. Editor configuration belongs in a global gitignore.
- Build artefacts, `node_modules`, ZIPs, `vendor/` for anything other than the declared
  third-party SDK.
- Anything under `docs/` that reads as an internal work log rather than user
  documentation. **`docs/HUMAN-TASKS.md` becomes `docs/ADMINISTRATOR-SETUP.md`** — done,
  and the merged file is in the v1.1.0 branch set. Having now read the original: it was
  already substantially administrator-facing and more specific than the replacement draft
  written without it, so the merge kept its structure, its P0/P1/P2/P3 ordering and its
  content almost entirely. What changed: the title and framing, the "Known gaps" heading
  (the items survive under "Limits you should hear from us rather than discover"), setting
  names verified against `settings.php`, the corrected capability defaults, three test-mode
  traps added, and two genuinely internal lines removed — "nothing calls `oauth_helper::
  refresh()` on a schedule yet", now stated as a token-expiry obligation on the
  administrator, and the note about the bundled Spanish pack, which no longer ships.

### 4.3 CI

There is currently **no** `.github/workflows/`. Add one. This is the highest-value item on
the whole list, because it closes approval blocker #2 as a side effect.

Matrix: `php: [8.3, 8.4] × database: [pgsql, mariadb]` against `MOODLE_502_STABLE`. Steps
per the `moodle-plugin-ci` documentation: `phplint`, `phpcs --max-warnings 0`,
`phpdoc --max-warnings 0`, `validate`, `savepoints`, `mustache`, `grunt`, `phpunit`.

Add `.moodle-plugin-ci.yml` at the plugin root so the vendored SDK is excluded from
linting:

```yaml
filter:
  notPaths:
    - vendor
```

This file and `thirdpartylibs.xml` must agree about what is third-party. They serve
different consumers — the former CI, the latter the Marketplace precheck — and a
disagreement means one of them is lying.

### 4.4 The PostgreSQL run

The plugin has only ever run on MariaDB. CI covers the automated suite; the development box
covers the interactive path. Both are needed, because the 65 unit tests never go through
the instance **form** — `test_add_instance()` calls `add_instance()` with a PHP array, so
moodleform construction, `validate_param_types()`, the `hideIf` rules and the real save are
untested by PHPUnit on either engine.

Candidates for engine-specific failure: anything outside the DML API, any `GROUP BY`, any
implicit integer/string comparison, any `LIKE` against a numeric column. `transactions.php`
and the reporting queries are where to look first — `transactions_table` has already had one
hand-built SQL defect.

---

## 5. Work items — listing and review

### 5.1 Reviewer credentials pack

A reviewer with no Mercado Pago account cannot exercise a single code path in this plugin.
The checklist requires demo credentials for exactly this reason, and for a payment gateway
it is the difference between a review and a stall.

Prepare, as test credentials only:

- A Mercado Pago **test seller** application: access token, public key, webhook secret.
- A **test buyer** account.
- A five-step walkthrough to reach the pay button.

Include three operational facts the reviewer cannot be expected to know, each of which
otherwise looks like a bug:

- The buyer must be **logged in to Mercado Pago before** starting the purchase, or the
  checkout falls back to guest mode — card entry only, no account money, no saved cards.
- The plugin **refuses to enable an instance on a non-HTTPS site**, by design, because
  Mercado Pago requires HTTPS for `notification_url` and `back_urls`. A reviewer on a local
  HTTP sandbox will hit this.
- The **notification URL must be registered by hand** in *Your integrations*, and there is
  one per Mercado Pago application, so a site running two Mercado Pago plugins needs two
  applications.

### 5.2 Mercado Pago trademark and brand assets

Audit `pix/` and the README. Naming the plugin after the service it integrates is normal
and defensible; redistributing the vendor's logo is a different thing, and the submission
carries an intellectual-property warranty that everything uploaded is yours or licensed.
Replace anything without written permission with neutral iconography.

### 5.3 Listing copy and screenshots

Short description (one or two sentences), full description consistent with the README,
installation and setup instructions including that no Composer step is required, plus
screenshots of: the instance settings form, the enrolment page with the pay button, the
per-course transactions report, and the site settings page.

Keep the README's *AI-Assisted Technology Statement*. It is unusual, it is honest, and it
pre-empts a question at no cost. Whether to repeat it in the listing description is
optional; there is no reason not to.

### 5.4 Anticipate two reviewer questions

- **`error_log()` behind a `phpcs:ignore`.** The justification is written down and it is
  sound — a payment audit trail must survive `debugging()` being switched off — but have it
  ready rather than discovering the question in a review comment.
- **The guest payment path cannot be tested at all.** A test collector accepts only test
  payers; a real collector accepts guests but no payment completes against a test buyer.
  There is no combination in which an unregistered payer authorises. State this in the
  submission rather than letting it be reported as missing coverage.

---

## 6. Standing norms

Two practices that outlive this release and apply to every version of every plugin in this
family, including `enrol_mercadopagosub`.

### 6.1 Re-check the Marketplace requirements before every submission

The binding requirements live in the MMPD Confluence space, not on `moodledev.io`, and the
platform is weeks old and changing. Treat the requirement set as a moving target:

- **Before each submission or resubmission**, re-read *Plugin submission guidelines* and
  *Launch limitations and upcoming functionality*. What was true at the previous
  submission may not be.
- **The style, branding and asset rules are the ones most likely to move**, because they
  are policy rather than code. Trademark handling, screenshot requirements, listing copy
  constraints and the commercial-listing policy have all changed at least once since
  February 2026.
- **Record what was checked and when** in §8, so a future submission starts from evidence
  rather than from memory.

`moodledev.io`'s contribution checklist stays useful as the technical bar — coding style,
security, privacy, namespacing, strings — but it is now labelled legacy and it does not
describe the process.

### 6.2 Check intellectual property and trademarks, always

The submission carries a warranty that everything uploaded is your property or is licensed
to you. That warranty is signed per submission, so the check is per submission — not once.
Two trademark owners are in play and they are not symmetric:

- **Moodle's marks** are governed by the Moodle Trademarks Policy, and misuse is approval
  blocker #9. Do not imply endorsement, certification or partnership. "A Moodle plugin" is
  fine; anything that reads as "official" is not.
- **Mercado Pago's marks** are not covered by any Moodle policy, which is precisely why
  they get missed. The company is not a party to the submission and has no reason to be
  forgiving.

The line this project works to:

| Use | Position |
| --- | --- |
| Naming the service the plugin integrates with, in the plugin name, README and docs | Nominative use. Accurate, necessary to describe the product, no endorsement implied. Fine. |
| Linking to Mercado Pago's own developer documentation | Fine. |
| Appending a modifier to their product name so it reads as their tier (§2.4) | Not fine. |
| Redistributing their logo, icon, brand colours or UI assets in `pix/`, docs or screenshots | Not fine without written permission. Replace with neutral iconography. |
| Screenshots that incidentally show their checkout page during a payment flow | Grey. Prefer screenshots of Moodle's own screens; if a checkout screenshot is genuinely needed, keep it minimal and factual. |

Practical checklist for each release, all of it cheap:

1. `pix/` — every image, its origin and its licence.
2. `README.md` and `docs/` — badges, embedded images, any logo.
3. Listing screenshots and the description copy.
4. The bundled SDK's licence, and that `thirdpartylibs.xml` states it correctly.
5. Any new dependency, image or font added since the last release.

Record the result in §9 with a date. "We checked this in August" is not an answer to a
warranty signed in November.

---

## 7. Explicitly out of scope for v1.1.0

- **Widening support below 5.2.** Backward compatibility is out of scope for this project
  by standing decision, and it does not need an alternative: the requirement is that a new
  plugin support **at least one** currently maintained Moodle version, and 5.2 is one.
  Declaring `$plugin->supported = [502, 502]` is compliant on its own. Reach is the only
  argument for widening, and it costs version guards and deprecated-API fallbacks that this
  codebase deliberately does not carry.
- **Migrating `@covers` docblocks to `#[CoversClass]`.** PHPUnit 11 deprecates the doc-comment
  form, but Moodle 5.2 does not set `failOnPhpunitDeprecation` and core's own `enrol_fee`
  still uses docblocks. Migrate when core does.
- **Any feature work.** v1.1.0 exists to be publishable; it must not change what the
  plugin does. Named explicitly so it does not creep in: the unified gateway across both
  plugins, discounts, and the marketplace split-payment flow in `oauth.php` beyond what
  already exists.

### Committed to v1.2.0

Two items were found during this release, understood, and deliberately deferred rather than
forgotten. They belong together because the second is what makes the first automatic.

**1. Port `collector` from `enrol_mercadopagosub`.** It reads `GET /users/me`, caches a
reduced record, and derives the currency from `site_id`. It also detects a test account
structurally — `test_data.test_user` first, the `test_user` tag second, the `TESTUSER`
nickname prefix only as a fallback. `enrol_mercadopagocpro` currently offers a currency
dropdown defaulting to ARS and has no idea what kind of account it is talking to. This is
the lesson the sibling plugin already paid for, and it is what earns the `(multi-country)`
display name discussed in §2.4.

**2. Rework the Environment setting on top of it.** The fields stay in v1.1.0 — Mercado
Pago does issue both a test and a production credential set for an ordinary integration, so
the setting describes something real. What it cannot do today is tell the administrator
whether the credentials in use will move real money, because that is decided by the account
rather than by the setting or the credential prefix.

The rule, measured and now stated in the language file and in
`docs/ADMINISTRATOR-SETUP.md`: **both parties must be of the same kind.** A test buyer can
only pay an integration created under a test seller account; a real buyer can only pay a
real one. Any other combination gets as far as the checkout and is refused at the last
step. So end-to-end testing requires a test seller integration plus a test buyer plus the
test cards the dashboard generates per card brand — and a real account's `TEST-`
credentials, despite the name, cannot complete a payment.

With `collector` in place the plugin can read the account and say which case it is in,
instead of asking the administrator to declare it. Whether the test credential fields
survive that rework is a decision for then; if they are removed, `db/upgrade.php` carries
the migration. That is not a reason to keep them now — further releases will almost
certainly add columns anyway (the welcome-message handling already present in
`enrol_mercadopagosub` is one candidate), so there will be an upgrade step regardless.

### Version numbering, decided 2026-09-07

**`v1.1.x` is reserved for whatever the Marketplace review asks for.** Nothing else goes
there. A reviewer's requested change needs a number the moment it arrives, and it should not
have to compete with planned work for one.

**`v1.2.x` is functionality carried over from `enrol_mercadopagosub`**, starting with
`collector`. Deriving the currency and the account type from the collecting account changes
behaviour rather than patching it, so a minor version is what semantic versioning asks for
anyway.

In v1.1.0 this is recorded as a finding and described accurately to administrators. Nothing
is reported to Mercado Pago and nothing in the plugin's own copy characterises the platform
beyond stating what was measured.
- **Release automation.** Wire `moodlehq/moodle-plugin-release` only after confirming the
  Marketplace upload API is actually live — two official pages contradicted each other on
  2026-09-02. The first submission is manual regardless.

---

## 8. Operational findings from the PostgreSQL run, 2026-09-04

**Both engines are green.** 67 tests, 202 assertions, on MariaDB 12.3.3 and PostgreSQL
17.11, both under PHP 8.4.24 on Moodle 5.2.2. The baseline was 65/191; the two new privacy
tests account for the difference exactly. No portability defect surfaced -- approval
blocker #2 is closed on evidence rather than on argument.

This also settles the three assumptions the new privacy tests were built on, which had
never been executed anywhere: `database_table::get_name()`, `external_location::get_name()`
and `get_privacy_fields()` all exist with those names in 5.2. Had any of them not, those
tests would have errored rather than passed.

**`init.php` downloads its own `composer.phar` and self-updates it.** Not the Composer on
`PATH` -- Moodle fetches `composer.phar` into the Moodle root and runs `self-update` before
`install`. Under `sudo -u www-data`, `HOME` is `/var/www`, and Composer then fails on
`/var/www/.config/composer/keys.dev.pub` because the directory does not exist and is not
writable. The failure looks like a database or test problem and is neither.

Fix, and it must be on every `init.php` invocation, not just the first:

```bash
sudo -u www-data env PHPUNIT_DB=pgsql COMPOSER_HOME=/tmp/composer-home \
    php public/admin/tool/phpunit/cli/init.php
```

Or durably, since `/tmp` clears on reboot:

```bash
sudo install -d -o www-data -g www-data -m 0755 /var/www/.config /var/www/.config/composer
```

`/var/www` is not served -- the DocumentRoot is `/var/www/moodle/public` -- so dotfiles
there expose nothing. This belongs in `docs/TESTING.md`.

**`moodle-plugin-ci behat --tags=X` replaces the plugin tag rather than adding to it.**
The tool builds `--tags="@enrol_mercadopagocpro"` by default; passing `--tags` at all
discards it. A bare negation therefore selects "all of Moodle except that one scenario"
and runs the entire core Behat suite — 3150+ steps and still going at 35 minutes, with
failures from core scenarios that have nothing to do with this plugin. Behat has been
removed from the workflow rather than fixed: it is not required for publication, and the
one scenario that matters cannot run on a plain-http CI site anyway. If it is ever
restored, both conditions go in one expression:

    --tags='@enrol_mercadopagocpro&&~@enrol_mercadopagocpro_https'

with a `timeout-minutes` so a hang cannot burn an hour of runner time. Recorded in the
workflow itself and in `docs/TESTING.md`.

**The MariaDB run took 4m 25s and the PostgreSQL run 20s.** The suite is identical, so the
difference is almost certainly warm caches and a warm opcache on the second run rather than
anything about either engine. Worth not misreading as a performance finding.

---

## 9. What the secret scan found, 2026-09-04

**Nothing.** gitleaks 8.30.1 over 39 commits with `--log-opts="--all --full-history"`
reported 75 findings, every one of them the same pattern: `/** API version: <uuid> */`
docblocks in `vendor/mercadopago/src/MercadoPago/Resources/Order/*.php`. Those are the
SDK's own API version identifiers, matched by the default `generic-api-key` rule on
entropy alone. The tree is byte-identical to upstream 3.14.0, so none of it is ours.
`.gitleaks.toml` now allowlists `^vendor/` and documents why, so the scan reports zero and
a future non-zero result means something.

Context searches found no credential either. `git log --all -p -S` on `accesstoken`,
`publickey`, `webhooksecret` and `marketplaceclientsecret` touched only
`docs/ADMINISTRATOR-SETUP.md`, which contains the `config.php` example with `'...'`
placeholders. `dbpass` returned nothing at all, consistent with the production database
password having leaked through a chat rather than a commit — **that rotation is still
outstanding, see below**. The only `APP_USR-` strings in history are the literal
placeholders `APP_USR-secret` in a fixture and `APP_USR-other-token` in an SDK docblock.
Every IP address in history is `127.0.0.1`, `10.0.0.1` or `10.0.0.9`.

**One trace of retired infrastructure remains and is deliberately not being removed.**
`.vscode/settings.json` carried `/home/bitnami/.config/composer/vendor/bin/`. The file is
out of the tree but the string is in the history. It is a filesystem path on a host being
decommissioned, not a credential, and rewriting history costs every SHA in the repository.
Recorded rather than fixed.

**Caveat on the strength of this result.** The two custom rules produced no findings, which
is indistinguishable from the outside from the rules not working. Run a positive control —
a throwaway repository with a realistic-looking token and webhook secret — and confirm
gitleaks reports both before treating this row as closed.

---

## 10. The package artefact, verified 2026-09-04

`git archive --format=zip --prefix=mercadopagocpro/` was built from HEAD and inspected
rather than assumed:

- **One top-level directory, `mercadopagocpro/`.** This is the most common mechanical
  rejection and it is now checked rather than believed.
- **No development files.** `.github`, `.githooks`, `.vscode`, `.gitattributes`,
  `.gitignore`, `.gitleaks.toml`, `.editorconfig`, `.moodle-plugin-ci.yml`, `.phpcs.xml`
  and `translations/` are all absent.
- **Zero CRLF inside the archive.**
- **The SDK is byte-identical to upstream 3.14.0 in the artefact itself**, not only in the
  working tree. `diff -r` against the extracted release tarball reports no differences.

That last one was the point of the exercise. `.gitattributes` sets `* text=auto eol=lf`
and then disables it for the bundle with `vendor/** -text`. Had that exclusion not worked,
`git archive` would have normalised the SDK's line endings on the way into the ZIP, and
`thirdpartylibs.xml` would have been making a false declaration in the published package
while remaining true in the repository. The exclusion works.

**One trap found and removed while checking this.** `.gitattributes` carried two commented
lines, `# composer.json export-ignore` and `# composer.lock export-ignore`, left from when
the plugin's own `composer.json` was still a question. A `.gitattributes` pattern with no
slash matches at every depth, so uncommenting that line would have excluded
`vendor/mercadopago/composer.json` — which belongs to the SDK and must ship — and broken
the byte-identity above. The lines are gone, replaced by a note that any future rule has to
be anchored as `/composer.json`.

---

## 11. Where this release stands, 2026-09-06

**Ready to submit.** Every row in §12 is closed except two, both stated below rather than
left blank.

Verified end to end on a clean site: the v1.1.0 package, built with `git archive` from the
tag in `JulioTentorAssociates/moodle-enrol_mercadopagocpro`, installed through Moodle's own
web installer onto a fresh 5.2.2 with developer debugging on. No notices. `cli/diagnose.php`
reported 0 failures and 0 warnings across all eleven sections, and a full test payment
completed and enrolled the student.

The package itself: one `mercadopagocpro/` root, 324 files, zero CRLF, no development files,
no plugin `composer.json`, no `vendor/autoload.php`, and the bundled SDK byte-identical to
upstream 3.14.0 verified by `diff` on the shipped archive rather than on a working tree.

Repository hygiene: gitleaks over the full history reports nothing once the SDK's own
docblock UUIDs are allowlisted; GitHub secret scanning and push protection are enabled with
zero alerts; Issues is public. The pre-commit hook checks `vendor/`, PHP syntax and secrets,
all against the index rather than the working tree.

### The two rows that are not closed

**16 — Behat.** The three form scenarios were green on the clone that has since been
replaced, and have not been re-run. The fourth needs HTTPS and is excluded from CI by
design. Automated tests are not part of the Marketplace prechecks and the contribution
checklist does not mention them at all, so this does not block submission. Re-run it on
`plugintest` when convenient.

**20c — screenshots.** Closed for the fifteen that exist. If any screenshot is added or
retaken before submission, check it again: the rule in §6.2 is per submission, not once.

### What remains is administrative

Paste `MARKETPLACE-LISTING.md` into the submission form, upload the package built from the
tag, and attach `REVIEWER-GUIDE.md` and a completed `TEST-CREDENTIALS-SHEET.md` to the
private ticket. Credentials never go in the public listing.

Two things to do while the review is open, neither blocking: enable
`secret_scanning_non_provider_patterns` on the repository, expecting the SDK's docblock
UUIDs to raise the same false positives gitleaks did; and name the reserved Mercado Pago
test accounts somewhere durable, because fifteen accounts that cannot be deleted become
indistinguishable within months.

---

## 12. Submitted, 2026-09-07

The plugin is in the Marketplace review queue, state **Submitted for review**. Two of the
eight automated prechecks have reported: `phplint` (55 files, no syntax error) and `validate`
(required files, upgrade function, `pluginname`, table prefixes, Behat tags). The remaining
six are `phpcs`, `phpdoc`, `savepoints`, `mustache`, `grunt` and `thirdparty`.

**Five of those six are already green in this project's own CI.** `thirdparty` is the one
that has never run under any tool here: it reads `thirdpartylibs.xml`, resolves the declared
paths and checks the licence. Its usual failure mode is a path that does not resolve, which
sends the 250 bundled SDK files through `phpcs` and buries the report in errors that are not
ours. The declaration and the byte-identity have both been verified by hand, so there is no
action to take — this is recorded so that if `phpcs` comes back with hundreds of findings in
`vendor/`, the cause is understood immediately.

**While the state is *Submitted for review*, the package is frozen.** Changes are permitted
in this state but each one risks re-running the prechecks and, possibly, the queue position.
Change something only if it would otherwise fail the review.

**Do not terminate the `plugintest` clone.** Stop it, keep the Elastic IP associated, and
keep the DNS record. If a reviewer reports something that cannot be reproduced, having the
site back in one click is worth the storage.

### Open, and deliberately so

- **Behat (row 16).** Deferred; not required for publication.
- **`secret_scanning_non_provider_patterns`.** Being looked at by other developers in the
  company. Expect the SDK's `/** API version: <uuid> */` docblocks to raise the same 75
  false positives gitleaks did; they are dismissed from the Security tab, not fixed.
- **The reviewer credentials.** Confirm the private ticket carries
  `TEST-CREDENTIALS-SHEET.md` with the values filled in, and `REVIEWER-GUIDE.md` attached.
  This is the single most likely thing to stall a review, and it costs nothing to check
  today rather than in three weeks.

---

## 13. Carried over, not part of this release

**Closed 2026-09-04.** Two items previously carried here are resolved:

- *Rotate the production database password.* Not applicable. The password pasted into a
  chat on 2026-08-28 belonged to the Bitnami server, which no longer exists. The current
  Debian hosts use credentials that were never shared.
- *`.vscode/` removed.* Deleted from the repository. It survives only in early commits from
  the Bitnami period, along with the `/home/bitnami/...` path recorded in §9 — a filesystem
  path on a decommissioned host, not worth rewriting history for.

---

## 14. Verification log

Fill this in as each item is settled. Nothing is "done" on the strength of having been
written; the whole point of the dual-database box is that assertions get executed.

| # | Item | § | Verified how | Date |
| --- | --- | --- | --- | --- |
| 1 | Copyright headers, all files | 2.1 | 57 files, one variant, verified | 2026-09-03 |
| 2 | `$plugin->requires` checked against real 5.2.2 `public/version.php` | 2.2 | core `$version = 2026042002.00`, plugin requires the same; satisfied | 2026-09-04 |
| 3 | PHP/Moodle version claims consistent | 2.3 | README badge, README text, `composer.json` | 2026-09-03 |
| 4 | `pluginname` = `Mercado Pago Checkout Pro (Tentor & Associates)` | 2.4 | English string applied | 2026-09-03 |
| 5a | External location link to Mercado Pago declared | 2.5 | read `provider.php` | 2026-09-03 |
| 5b | `enrol_mercadopagocpro_wh` declared, exported and cascaded on delete | 2.5 | 67/67 green on MariaDB 12.3.3 and PostgreSQL 17.11 | 2026-09-04 |
| 5c | Exported fields match declared fields | 2.5 | fixed, plus a test that keeps them in step | 2026-09-03 |
| 6 | `.gitattributes` produces a correct package | 3.1 | single `mercadopagocpro/` root; no dev files; 0 CRLF inside the archive | 2026-09-04 |
| 7 | `lang/es/` out of the package | 3.2 | absent from the archive | 2026-09-03 |
| 8 | Composer contradiction resolved: `composer.json` removed, loader branch removed, three documents corrected | 3.3 | verified in the tree; no test referenced the removed branch | 2026-09-04 |
| 9 | `thirdpartylibs.xml` correct; SDK byte-identical **in the built ZIP** | 3.4 | `diff -r` against the upstream 3.14.0 tarball on the final v1.1.0 package, built from the tag in the new organisation: no differences | 2026-09-06 |
| 10a | `styles.css` selectors namespaced | 3.5 | read; all selectors carry `.enrol-mercadopagocpro` | 2026-09-03 |
| 10b | Mustache lint clean | 3.5 | green in all four CI jobs | 2026-09-04 |
| 10c | Line endings converted to LF outside `vendor/` | 3.6 | 0 CRLF in the archive | 2026-09-03 |
| 10d | `vendor/` confirmed LF and byte-identical to upstream 3.14.0 | 3.4 | spot check: `MercadoPagoConfig.php` is LF | 2026-09-03 |
| 11 | Secret scan over full git history, clean | 4.2 | gitleaks 8.30.1, 39 commits, `--all --full-history`; 75 findings all upstream SDK docblock UUIDs, allowlisted | 2026-09-04 |
| 12 | `.vscode/` removed | 4.2 | absent from the archive | 2026-09-03 |
| 13 | `docs/HUMAN-TASKS.md` → `docs/ADMINISTRATOR-SETUP.md` | 4.2 | merged, old file removed | 2026-09-03 |
| 14 | CI green: 2 PHP × 2 databases, all eight precheck steps | 4.3 | eight steps green in all four jobs; Behat removed from CI | 2026-09-04 |
| 15 | PHPUnit green on PostgreSQL | 4.4 | **67 tests, 202 assertions, PostgreSQL 17.11, PHP 8.4.24** | 2026-09-04 |
| 16 | Behat 4/4 green on the HTTPS site | 4.4 | **open** — the three form scenarios ran on the old clone; not re-run since. Not required for publication. | |
| 17 | ~~Instance form exercised by hand on PostgreSQL~~ | 4.4 | **Not applicable.** One Moodle instance by decision (2026-09-04); there is no browsable PostgreSQL site. What this row covered — SQL portability — is demonstrated by 67 tests green on both engines, and the instance form is not engine-dependent. | 2026-09-04 |
| 18a | Package shape, contents and line endings verified in the built ZIP | 3.1 | re-verified on the v1.1.0 tag build: single `mercadopagocpro/` root, 324 files, 0 CRLF, no dev files, no plugin `composer.json`, no `vendor/autoload.php` | 2026-09-06 |
| 18b | ZIP installs through the web installer on a clean site, developer debugging on | 3.1 | done on `plugintest.juliotentor.com`, fresh Moodle 5.2.2, developer debugging on; `cli/diagnose.php --courseid=2 --username=manager` reported 0 failures and 0 warnings across all eleven sections; a full test payment completed and enrolled the student | 2026-09-06 |
| 19 | Reviewer credentials pack prepared | 5.1 | `REVIEWER-GUIDE.md` + `TEST-CREDENTIALS-SHEET.md`; dedicated MLA test seller and buyer reserved for review | 2026-09-06 |
| 20a | `pix/icon.svg` free of third-party marks | 6.2 | generic card outline, `currentColor`, no MP asset | 2026-09-03 |
| 20b | README and docs free of third-party marks | 6.2 | three shields.io badges (Moodle, PHP, GNU); no MP imagery | 2026-09-03 |
| 20c | Listing screenshots checked | 6.2 | 15 screenshots, all Moodle UI; the Mercado Pago checkout captures were removed | 2026-09-06 |
| 21 | Listing copy written, screenshots taken | 5.3 | `MARKETPLACE-LISTING.md`; paste into the form | 2026-09-06 |
| 22 | GitHub Issues confirmed public | 4.1 | HTTP 200 unauthenticated on `JulioTentorAssociates/…` after the transfer | 2026-09-06 |
| 23 | `v1.1.0` tagged, CHANGELOG entry written | 4.1 | tag exists; package built from it | 2026-09-06 |

Items 1, 4, 7, 8 and 12 change files. Items 2, 5, 9, 10 are verifications that may or may
not turn into changes. Everything else is process or evidence.
