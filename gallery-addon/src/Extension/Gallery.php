<?php
/*------------------------------------------------------------------------

# TZ Portfolio Extension

# ------------------------------------------------------------------------

# Copyright (C) 2011-2026 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

-------------------------------------------------------------------------*/

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Psr\Container\ContainerInterface;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn\AddOn;

/**
 * Content Gallery add-on.
 */
class Gallery extends AddOn
{
    /**
     * Register add-on assets.
     */
    public function boot(ContainerInterface $container)
    {
        parent::boot($container);

        $application = Factory::getApplication();
        $document    = null;

        // During onAfterRoute Joomla's administrator application may not yet
        // have an HTML document assigned. Never pass null to method_exists().
        if (is_object($application) && method_exists($application, 'getDocument')) {
            $document = $application->getDocument();
        }

        // Joomla 6 still provides this compatibility factory and it can create
        // the document when the application has not attached it yet.
        if (!is_object($document)) {
            try {
                $document = Factory::getDocument();
            } catch (\Throwable $exception) {
                return;
            }
        }

        if (!is_object($document) || !method_exists($document, 'getWebAssetManager')) {
            return;
        }

        $wa   = $document->getWebAssetManager();
        $base = rtrim(Uri::root(), '/') . '/components/com_tz_portfolio/add-ons/content/gallery/';

        // Keep the historical asset names, but point them to files that actually exist.
        $wa->registerStyle(
            'com_tz_portfolio.addon.content.gallery.slick',
            $base . 'css/slick.min.css'
        );
        $wa->registerStyle(
            'com_tz_portfolio.addon.content.gallery',
            $base . 'css/style.css'
        );
        $wa->registerStyle(
            'com_tz_portfolio.addon.content.gallery.admin-style',
            $base . 'admin/css/style.css'
        );
        $wa->registerScript(
            'com_tz_portfolio.addon.content.gallery.slick',
            $base . 'js/slick.min.js',
            ['version' => 'auto'],
            [],
            ['jquery']
        );
        $wa->registerScript(
            'com_tz_portfolio.addon.content.gallery.image_gallery_uploader',
            $base . 'admin/js/image_gallery_uploader.js',
            ['version' => 'auto'],
            [],
            ['jquery']
        );
        $wa->registerScript(
            'com_tz_portfolio.addon.content.gallery.lightbox',
            $base . 'js/lightbox.min.js',
            ['version' => 'auto'],
            [],
            ['jquery']
        );
        $wa->registerScript(
            'com_tz_portfolio.addon.content.gallery.style_ui',
            $base . 'admin/js/style-ui.js',
            ['version' => 'auto'],
            [],
            ['jquery', 'com_tz_portfolio.jquery-ui']
        );
    }

    /** Add a selector option without registering a second media editor tab. */
    public function onContentPrepareForm($form, $data)
    {
        $result = parent::onContentPrepareForm($form, $data);
        if (!in_array($form->getName(), ['com_tz_portfolio.article', 'com_tz_portfolio.form'], true)) {
            return $result;
        }
        $xml = $form->getXml();
        foreach ($xml->xpath('//field[@name="type"]') ?: [] as $field) {
            if (strtolower((string) $field['type']) !== 'mediatypes'
                || $field->xpath('option[@value="content_gallery"]')) {
                continue;
            }
            $language = Factory::getApplication()->getLanguage()->getTag();
            $label = in_array(substr($language, 0, 2), ['ru', 'uk'], true) ? 'Галерея' : 'Gallery';
            $option = $field->addChild('option', $label);
            $option->addAttribute('value', 'content_gallery');
        }
        return $result;
    }

    /** Render in the standard Media block, in both single-layout modes. */
    public function onContentDisplayMediaType($context, &$article, $params, $page = 0, $layout = 'default')
    {
        if ($context !== 'com_tz_portfolio.article' || !is_object($article)
            || ($article->type ?? '') !== 'content_gallery') {
            return null;
        }
        return $this->_getViewHtml($context, $article, $params, $layout ?: 'default') ?: null;
    }

    /** The content hook must not duplicate the Media block. */
    public function onContentDisplayArticleView($context, &$article, $params, $page = 0, $layout = 'default')
    {
        return null;
    }

    public function onAfterDisplayAdditionInfo(
        $context,
        &$article,
        $params,
        $page = 0,
        $layout = 'default',
        $module = null
    ) {
        if ($context === 'com_tz_portfolio.article' || !is_object($article)
            || ($article->type ?? '') !== 'content_gallery') {
            return null;
        }

        $parts     = explode('.', (string) $context, 2);
        $extension = $parts[0] ?? '';
        $viewName  = $parts[1] ?? '';
        $item      = $article;

        if ($extension === 'module' || $extension === 'modules') {
            if ($path = $this->getModuleLayout($this->_type, $this->_name, $extension, $viewName, $layout)) {
                ob_start();
                include $path;
                return trim((string) ob_get_clean());
            }

            return null;
        }

        if (in_array($context, [
            'com_tz_portfolio.portfolio',
            'com_tz_portfolio.date',
            'com_tz_portfolio.featured',
            'com_tz_portfolio.tags',
            'com_tz_portfolio.users',
            'com_tz_portfolio.search',
        ], true)) {
            return $this->_getViewHtml($context, $item, $params) ?: null;
        }

        return null;
    }

    public function onAddFormToArticleDescription($article = null)
    {
        return $this->__addFormToPosition($article);
    }
}
