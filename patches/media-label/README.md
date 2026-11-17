# Content Gallery media label in article lists

`tz_portfolio_tz-portfolio.v4.1.2-media-label-fix.zip` is the complete Portfolio 4.1.2 installer, including the previous language-association correction. Install through Joomla Extension Manager, then complete Portfolio's setup wizard. Choose **No** for sample data on an existing site.

The content-gallery addon adds `content_gallery` to the article form, but ArticlesModel only resolves labels advertised by mediatype addons. It consequently showed No Media for the persisted gallery value. Resolve this one synthetic type using the same EN/RU/UK label as the addon selector. FeaturedModel inherits this resolver. No database or frontend media changes; other addons retain their existing label logic.

Verified on Joomla 6.2.0 / PHP 8.4.27 with Portfolio 4.1.2. Reproduced incorrect No Media for a gallery fixture before installation in both article lists. Installed the corrected ZIP and completed its setup; gallery, none, image and flipbook fixtures show the correct labels in both lists. Database snapshots before and after retained all four original media values. Isolated fixtures removed after verification.

Build: `python patches/media-label/build.py`.
