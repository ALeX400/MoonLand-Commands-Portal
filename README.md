# MoonLand Commands Portal

MoonLand Commands Portal is a lightweight PHP application that powers a multilingual landing page and an admin interface for managing content. This guide walks through the project layout, the data sources it uses, and the day-to-day workflow for updating text, translations, and language metadata. The instructions are intentionally generic so they apply regardless of how the project is currently configured.

## Project Overview

- **Public site (`index.php`)** – renders the MoonLand landing page by reading structured content from `config/commands-data.json`.
- **Admin panel (`admin/index.php`)** – password protected dashboard for editing the content JSON through a friendly UI.
- **Language metadata (`config/languages.json`)** – provides display names and ISO country codes for each language so the UI can surface proper labels and flags.
- **Data access (`lib/DataStore.php`)** – helper that reads and writes JSON data atomically, keeping file operations consistent.

The application is intentionally flat: there is no framework or build step. Everything runs with standard PHP and static assets served directly by your web server.

## Prerequisites

1. **PHP 8.1+** – the scripts use modern language features (`declare(strict_types=1)` and typed properties). Any recent PHP runtime is fine (Apache + mod_php, Nginx + PHP-FPM, PHP built-in server, etc.).
2. **Write access to `config/`** – both the admin panel and manual edits need to write to `config/commands-data.json` and potentially `config/languages.json`.
3. **Session support** – the admin panel depends on PHP sessions for authentication, so ensure sessions are enabled and writable.

## Getting Started

1. Clone or copy the project into your web root (`htdocs`, `public_html`, etc.).
2. Update the admin credentials in `config/commands-config.php`:

   ```php
   'admin' => [
       'username' => 'your-admin-user',
       'password' => 'very-strong-password',
   ],
   ```

3. Point your browser to `/admin/` and log in. The first visit after a fresh install uses whatever default credentials you configured in the step above.
4. Use the admin UI to review or update content. Every save will write back to `config/commands-data.json`.
5. Visit the root URL (where `index.php` lives) to confirm the public site reflects your changes.

## Editing Content via Admin Panel

The admin panel stores all copy inside a single JSON tree. Each language has a parallel structure that mirrors the sections rendered on the public page.

- **Languages** – the dropdown at the top selects which translation you are editing. Click “Add language” to create a new translation by cloning the current one. Click “Set default” so the public site opens in that language.
- **Hero** – controls the main headline, description, and primary/secondary call-to-action buttons.
- **Guide** – each step contains a title, summary, and optional list of command tokens.
- **Catalog** – organized into categories, each with commands (label, usage example, description).
- **Tips / FAQ / Footer** – additional supporting sections that display exactly as entered.

Every change is temporary until you press “Save changes.” The status pill in the toolbar shows whether the working copy has unsaved edits.

## Managing Content Manually

If you prefer working with JSON directly (e.g., for bulk changes or version control), edit `config/commands-data.json`. The structure looks like this:

```json
{
  "languages": ["ro", "en"],
  "defaultLanguage": "ro",
  "translations": {
    "ro": {
      "meta": {
        "siteTitle": "...",
        "siteTagline": "...",
        "discordUrl": "...",
        "lastUpdated": "2025-11-30"
      },
      "hero": {
        "title": "...",
        "description": "...",
        "primaryCta": {
          "label": "Catalog",
          "href": "#catalog"
        },
        "secondaryCta": {
          "label": "Join Discord",
          "href": "https://discord.example.com",
          "target": "_blank",
          "rel": "noopener"
        },
        "logo": {
          "visible": true,
          "url": "https://.../logo.png"
        }
      },
      "guide": {
        "title": "Quick start",
        "description": "...",
        "steps": [
          {
            "title": "Step 1",
            "summary": "...",
            "commands": ["/spawn", "/sethome"]
          }
        ]
      },
      "catalog": {
        "title": "Command catalog",
        "subtitle": "...",
        "ctaLabel": "Open Discord",
        "ctaHref": "https://...",
        "categories": [
          {
            "title": "Claims",
            "summary": "...",
            "commands": [
              {
                "label": "/claim",
                "usage": "/claim",
                "description": "Claim the current chunk."
              }
            ]
          }
        ]
      },
      "tips": {
        "title": "Tips",
        "items": ["Tip 1", "Tip 2"]
      },
      "faq": {
        "title": "FAQ",
        "items": [
          {
            "question": "Question?",
            "answer": "Answer."
          }
        ]
      },
      "footer": {
        "text": "MoonLand Network",
        "links": [
          {
            "label": "Discord",
            "href": "https://discord.example.com",
            "target": "_blank",
            "rel": "noopener"
          }
        ]
      }
    }
  }
}
```

Always keep the `languages` array, `defaultLanguage`, and `translations` keys aligned—every language listed must have an entry in `translations`.

## Language Labels and Flags

The public site and admin interface both use `config/languages.json` to resolve human-readable names and flags for each language code. Each entry maps a BCP 47 language key (usually two letters, sometimes with a region suffix) to a metadata object:

```json
{
  "en": { "label": "English", "countryCode": "GB" },
  "en-us": { "label": "English (US)", "countryCode": "US" },
  "ro": { "label": "Română", "countryCode": "RO" }
}
```

- `label` – what users see in dropdowns and buttons.
- `countryCode` – ISO 3166-1 alpha-2 code used to render the flag icon and emoji.

When `countryCode` is filled, the UI automatically shows both an emoji and the CSS-based rectangular flag using the [flag-icons](https://github.com/lipis/flag-icons) library.

### Adding a New Language

1. Add or update the entry in `config/languages.json` with the language code you plan to use, plus its label and country code.
2. Open the admin panel, click “Add language,” and enter the same code. The new translation will clone the currently active language.
3. Translate the content sections and save.
4. Optionally set the new language as the default.

### Removing a Language

1. In the admin panel, switch to the language you want to delete.
2. Click “Remove language.” The UI enforces at least one language so you cannot delete the final translation.
3. Edit `config/languages.json` manually if you want to remove the metadata entry.

## Styling and Assets

The application pulls external stylesheets at runtime:

- Bootstrap 5.3 (layout and base components).
- Bootstrap Icons (iconography in the admin panel).
- Google Fonts (Poppins for typography).
- Flag Icons (country flags for language selection).

If you need offline capability, download these assets and update the `<link>` tags in `index.php` and `admin/index.php` to point to local copies.

## Security Notes

- Change the default admin password immediately. The admin panel uses simple session-based auth; strong credentials are your primary defense.
- Consider adding HTTP basic auth or IP whitelisting if you expose the admin panel on the public internet.
- Keep `config/commands-data.json` backed up—this is the single source of truth for your content.

## Deployment Tips

- The project runs on any PHP hosting. For local testing, `php -S localhost:8080` from the project directory is sufficient.
- Use version control (git) to track changes in `config/` so you have a history of edits.
- If deploying behind a CDN or caching layer, purge the cache after content updates so visitors receive fresh translations.

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| Admin login loops or fails | Sessions not writable | Ensure `session.save_path` is valid and writable |
| Flags do not appear | Missing or invalid `countryCode` | Set correct ISO country code in `config/languages.json` |
| New language not showing | Language code missing in metadata or translations | Confirm entry exists in `config/languages.json` and in the `languages` array |
| JSON decode errors | Manual edits introduced invalid JSON | Validate with an online formatter or `php -l config/commands-data.json` |

## Extending the Project

- **Translate more sections** – any new keys added under `translations[lang]` should be handled consistently across languages.
- **Custom styling** – add your own CSS either inline in `index.php` or via separate stylesheets.
- **Integrations** – hook up analytics, contact forms, or dynamic content by extending the PHP templates.

## Summary

All site copy lives in `config/commands-data.json`, while language metadata resides in `config/languages.json`. Use the admin panel for everyday edits, and fall back to manual JSON edits for bulk or scripted updates. With the information above you should be able to onboard new contributors quickly and keep the MoonLand Commands Portal up to date.
