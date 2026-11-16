# Accordion 1.0.1 for TZ Portfolio 4.1.2

Install `accordion_content_addon_v1.0.1_joomla6_tz412_ru_uk.zip` in **TZ Portfolio → Addons → Install** (the native Portfolio addon installer). Update the existing addon; do not uninstall it first. This is a complete addon package with English, Russian and Ukrainian translations.

If the article uses Portfolio's layout builder, add/retain its Accordion content block in that layout. This update does not change site layouts or enable blocks globally.

Changes:
- Correct Portfolio 4 article-save event; sanitized addon data saved against the actual article ID.
- Allowlisted rich HTML; plain titles, escaped attributes, validated heading names, class tokens, typography, colours and responsive margins. Old database records are also validated at rendering time.
- Unique instance and panel identifiers, keyboard-operable buttons, correct collapse state, scoped CSS, working section spacing and title settings.
- No dependence on the site's Bootstrap stylesheet; uses Joomla's registered collapse script.
- Removed the unused Portfolio+ 2.x PHP model and obsolete field lookup path. Removed unrelated contact-form language strings; every remaining addon-owned UI key has EN/RU/UK values.

Validation: native ZIP upload, article save/reload and frontend collapse on Joomla 6.2.0, PHP 8.4.27, TZ Portfolio 4.1.2, Joomla compatibility plugin disabled. Checked stored HTML/attribute/URL/CSS injection, malformed settings, missing CSRF token, anonymous writes, PHP syntax and native loading of all three languages. No new upload, filesystem, SQL, network or execution endpoint is added. These checks cover identified issues; they are not a guarantee against every possible vulnerability in Joomla, Portfolio or third-party template overrides.

Rich content supports ordinary text formatting, links, images and tables. Executable HTML, inline styles, embedded frames/documents and forms are intentionally removed. Existing database content is not bulk-rewritten on installation.

Build: `python accordion-addon/build.py`.
