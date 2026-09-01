# AI Tutor — Moodle Local Plugin

This plugin is part of the **LearningOps** suite by **Highskills and more**.
https://www.highskills.co.il/

## Overview

AI Tutor adds a per-course chat widget that answers student questions **grounded only in
that course's own content**. Answers stream back in real time with inline citations to the
exact page, chapter, forum post or file they came from, a "connects the dots" list of
related material, and — when a student appears to be stuck — a set of practice problems.

Teachers additionally get an **Insights** page showing *struggle patterns* (which topics
students get stuck on, and who) and *content gaps* (questions the course content couldn't
answer).

## Activation

To get your activation endpoint and API key, please [complete the setup process here](https://www.highskills.co.il/blog/ai/privatetutor-moodle).

## Requirements

- **Moodle 4.4** or later 
- **PHP 8.x** with the extensions `curl`, `zip`, and `mbstring`.
- Access to the **AI Private Tutor service** (provided by Highskills and more).

## Installation

1. Copy or clone this repository into your Moodle install, **renaming the folder** to drop
   the `local_` prefix:

   ```
   <moodle_root>/local/ai_tutor/
   ```

2. Log in as a site administrator and go to **Site administration → Notifications** to run
   the install. 

## Configuration

Go to **Site administration → Plugins → Local plugins → AI Tutor**.

| Setting | Description |
|---------|-------------|
| **Foundry endpoint URL** | Full URL .|
| **API key (Bearer token)** | The tenant API key provided by Highskills and more. |
| **Stream timeout (seconds)** | How long to wait for the backend to finish streaming an answer before giving up. Default: 300 s. Minimum: 30 s. |
| **Widget position** | Which corner of the course page the chat widget docks to: top left, top right, bottom left, or bottom right (default). |

## Enabling the tutor on a course

Open a course's **Settings** page. Under the course custom fields there is an **Enable AI
Tutor** checkbox (visible to teachers, unchecked by default). Checking it and saving:

- Makes the chat widget appear on that course's pages (for any user with the
  `local/ai_tutor:use` capability).
- Adds the course to the **Rebuild AI Tutor course content cache** scheduled task, which
  re-harvests enabled courses every 30 minutes (**Site administration → Server →
  Scheduled tasks**).
- The **first** chat message on a freshly enabled course triggers a one-off synchronous
  harvest, so the widget works immediately without waiting for the schedule.

## Teacher Insights

Users who can view insights get an **AI Tutor Insights** link in the course administration
navigation (`/local/ai_tutor/insights.php?courseid=…`). It shows, over a rolling 30-day
window:

- **Struggle patterns** — topics where students got stuck, with the named students per
  topic.
- **Content gaps** — out-of-scope questions the course content couldn't answer, grouped by
  a simple text normalization.

## Roles & capabilities

| Capability | Default roles (archetypes) | Context | Notes |
|------------|---------------------------|---------|-------|
| `local/ai_tutor:use` | Student, Non-editing teacher, Teacher, Manager | Course | Talk to the chat widget. Only takes effect on courses that opted in. |
| `local/ai_tutor:viewinsights` | Non-editing teacher, Teacher, Manager | Course | View the struggle-pattern / content-gap page. Flagged `RISK_PERSONAL` (exposes student names). |

Adjust access at **Site administration → Users → Permissions → Define roles**.

## Course content sent to the backend

On the first message of a chat session the plugin sends a plain-text snapshot of the
course. It harvests these module types:

- Pages, labels, book chapters, wiki pages
- File attachments in **File** and **Folder** resources (text pre-extracted — see below)
- Forum posts (subject + message)
- Assignment **descriptions** only
- Quiz **question text and general feedback** for fixed (non-random) slots only
- Glossary entries, database (`mod_data`) records, lesson pages
- SCORM and H5P activities — **intro/description only** in v1

Hard rules:

- **Plain text only** — HTML is stripped before sending.
- **No student personal data, ever** — no submissions, no grades, no individual quiz
  attempts.

## Supported file formats

Files in File/Folder resources are extracted to plain text before being sent:

| Format | Notes |
|--------|-------|
| `.txt` | Read as-is |
| `.csv` | Treated as plain text |
| `.html` / `.htm` | Tags stripped, text extracted |
| `.docx` | Text extracted from `word/document.xml` |
| `.pptx` | Text extracted from each `ppt/slides/slideN.xml`, in order |
| `.pdf` | `pdftotext` (poppler-utils) if available, otherwise the bundled Smalot PDF Parser. Image-only / scanned PDFs produce no text. |

Any other extension is skipped.

## Features

- Real-time **streamed answers** over Server-Sent Events, with a live "thinking" indicator
- **Inline citations** such as `[C1:high]` / `[C1:medium]` linking back to the source in
  Moodle, with a model-self-reported confidence hint
- **Connects the dots** — related course material for the current question
- **Practice problems** generated automatically when the student appears stuck
- **Teacher Insights** — struggle patterns and content gaps, built from local data
- **Configurable widget corner** (site-wide setting)
- **Hebrew (RTL)** language pack included


## Troubleshooting

| Symptom | Likely cause & fix |
|---------|-------------------|
| Widget doesn't appear on a course | The course hasn't ticked **Enable AI Tutor**; or the user lacks `local/ai_tutor:use`; or the endpoint URL / API key is blank in settings. |
| Chat shows "The AI Tutor is not configured" | Foundry endpoint URL or API key missing in plugin settings. |
| `cURL error 7` — could not connect | Foundry backend is unreachable — check the endpoint URL and network/firewall. |
| `cURL error 28` — operation timed out | Increase the **Stream timeout** setting; large courses need more processing time. |
| `HTTP 401 / 403` from the API | API key is wrong or expired — regenerate it in the Foundry admin UI. |
| Answer only appears all at once at the end | Response buffering in a proxy or web server. Disable gzip/buffering for the SSE response (e.g. `SetEnv no-gzip 1` on Apache; the plugin already sends `X-Accel-Buffering: no` for nginx). |
| PDF attachment produces no text | The PDF is image-only (scanned). Convert it to a text-based PDF first. |

## License

GNU General Public License v3 or later — see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).
