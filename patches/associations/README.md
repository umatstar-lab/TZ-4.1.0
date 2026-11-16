# TZ Portfolio 4.1.2: language associations fix

Install `tz_portfolio_tz-portfolio.v4.1.2-associations-fix.zip` with Joomla Extension Manager, then complete the Portfolio installation wizard. On an existing site choose **No** for sample data. This is the full installer based on this repository's previous 4.1.2 fixed package; back up custom component overrides before any full component update.

Adds the missing `tzcontentadmin.association` HTML service and its registration. Association queries use Portfolio's article table and context, not Joomla content. Language labels, titles and links are escaped. Links respect article edit permissions. Also populates the missing association indicator in FeaturedModel. No migration, deletion or association rewrite is performed by this fix.

Validation: Joomla 6.2.0 / PHP 8.4.27 / TZ Portfolio 4.1.2, compatibility plugin disabled. Reproduced HTTP 500 in Articles with a linked multilingual fixture before correction. Native installer and setup completed; Articles and Featured both return HTTP 200 and render links to the associated Portfolio article after correction. Embedded HTML in a fixture title is escaped. Test fixtures and language-filter settings restored after testing.

Build: `python patches/associations/build.py`.
