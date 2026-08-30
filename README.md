# local_ai_tutor

A Moodle `local` plugin: a per-course AI chatbox that answers student questions grounded only in that course's content, with citations, "connects the dots" suggestions, and stuck-student practice problems — plus a teacher-only insights page (struggle patterns, content gaps).

It talks to a separate backend ("Foundry") over the `/chat` contract documented in [CLAUDE.md](CLAUDE.md). This repo only implements the Moodle side.

## Install

1. Copy (or symlink) this repo into your Moodle install, **renaming the folder** to drop the `local_` prefix (Moodle's `local/` plugin-type directory expects that):
   ```bash
   cp -r moodle-local_ai_tutor /path/to/moodle/local/ai_tutor
   ```
2. `composer install` inside the deployed plugin folder (vendors the Smalot PDF Parser fallback used by `file_extractor` for PDF text extraction when `pdftotext` isn't available on the server) — already committed under `vendor/` in this repo, so this is only needed if you didn't copy that folder over.
3. Visit *Site administration → Notifications* to run the install (creates `local_ai_tutor_turns`, `local_ai_tutor_content_cache`, and the "Enable AI Tutor" course custom field).
4. Build the AMD JS module: from the plugin folder, run `<moodle_root>/node_modules/.bin/grunt amd`. **This step is required, not optional** — Moodle's RequireJS loader only ever looks for `amd/build/<module>.min.js` (see `core_requirejs::find_one_amd_module`); there is no dev-mode fallback that serves `amd/src/*.js` directly. Skipping it produces a `No define call for local_ai_tutor/chatbox` console error. The built `amd/build/chatbox.min.js` is already committed in this repo, so this is only needed after editing `amd/src/chatbox.js`.
5. Purge caches (*Site administration → Development → Purge caches*) after (re)building the AMD module, since Moodle caches JS by revision.

## Configure

*Site administration → Plugins → Local plugins → AI Tutor*:
- **Foundry endpoint URL** — full URL up to and including the tenant and task code, e.g. `https://your-host/api/v1/{tenant-uuid}/private-tutor` (this plugin appends `/chat` itself). Get this and the API key below from the Foundry admin UI after creating the tenant.
- **API key** — the tenant's Bearer token.
- **Stream timeout** — how long to wait for a `/chat` response before giving up (default 300s).

## Enable per course

Open a course's *Settings* page — under the course custom fields section there's an **Enable AI Tutor** checkbox (unchecked by default). Checking it and saving:
- Makes the chat widget appear on that course's pages (via the `core\hook\output\before_footer_html_generation` hook, `classes/hook_callbacks.php`, gated to course context).
- Adds that course to the `rebuild_content_cache` scheduled task's harvest list (runs every 30 minutes by default — see *Site administration → Server → Scheduled tasks*). The first chat message on a freshly-enabled course triggers a synchronous harvest if the cache is still empty, so the widget works immediately without waiting for the schedule.

## Architecture notes

- **Requires Moodle 4.4+**: the widget hooks into `core\hook\output\before_footer_html_generation` (`db/hooks.php` + `classes/hook_callbacks.php`), which doesn't exist before 4.4 — there's no legacy `before_footer()` fallback in `lib.php` for older versions.
- **HTTP client**: Moodle's legacy `\curl` wrapper (`classes/api_client.php`), matching this org's sibling plugins (`local_ai_coursecreator`, `local_ai_reportcreator`) rather than `core\http_client` — see the plan history in this repo's conversation record for why.
- **Course opt-in**: a `customfield_checkbox` field (`classes/course_config.php`, created in `db/install.php`), not a custom form hook — this Moodle version has no generic `course_edit_form` plugin callback.
- **Content harvesting**: `classes/content_harvester.php` walks course modules via `get_fast_modinfo()`; `classes/task/rebuild_content_cache.php` runs it periodically per enabled course into `local_ai_tutor_content_cache`. See that class's docblock for the documented v1 simplifications (SCORM/H5P intro-only, fixed-question quiz slots only).
- **No PHPUnit requirement to call this "done"** — verification is manual, against a real Moodle instance and a running Foundry backend (see the plan's verification checklist). A small `tests/` suite exists for the cheaply-testable pieces (`file_extractor`), matching the sibling plugins' convention.
