<?php
/*------------------------------------------------------------------------

# TZ Portfolio Gallery Add-on

# ------------------------------------------------------------------------

# Copyright (C) 2011-2026 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

-------------------------------------------------------------------------*/

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Site\View\Article;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use TemPlaza\Component\TZ_Portfolio\Site\Helper\ArticleHelper;

/**
 * Front-end gallery view.
 */
class HtmlView extends BaseHtmlView
{
    protected $item = null;
    protected $params = null;
    protected $addon_params = null;
    protected $gallery = null;
    protected $galleryId = '';
    protected $head = [];
    protected $form_builder = null;

    public function display($tpl = null)
    {
        $model         = $this->getModel();
        $this->gallery = $model->getAddonData();
        if (is_string($this->gallery)) {
            $this->gallery = json_decode($this->gallery);
        }
        if (is_array($this->gallery)) {
            $this->gallery = (object) $this->gallery;
        }
        $this->item    = $model->getItem();
        $this->params  = $model->getState('params');

        if (!$this->params instanceof Registry) {
            $this->params = new Registry($this->params ?: []);
        }

        $addon = $model->getAddon();
        if ($addon && isset($addon->params)) {
            $this->addon_params = $addon->params instanceof Registry
                ? $addon->params
                : new Registry($addon->params);
        } else {
            $this->addon_params = new Registry();
        }

        $itemId = isset($this->item->id) ? (int) $this->item->id : 0;
        $this->galleryId = 'tz-portfolio-gallery-' . $itemId . '-' . substr(md5((string) spl_object_id($this)), 0, 8);

        if ($this->hasImages()) {
            $this->styleInit($this->gallery);
        }

        parent::display($tpl);
    }

    /**
     * Resolve an option from merged article/category parameters, then add-on defaults.
     */
    public function getGalleryParam($name, $default = null)
    {
        $value = $this->params instanceof Registry
            ? $this->params->get($name, null)
            : null;

        if (($value === '' || $value === null) && $this->addon_params instanceof Registry) {
            $value = $this->addon_params->get($name, $default);
        }

        return ($value === '' || $value === null) ? $default : $value;
    }

    /** Article overrides are explicit; global mode reads only the add-on defaults. */
    public function getGalleryColumns(): array
    {
        $raw = $this->item->attribs ?? [];
        if ($raw instanceof Registry) {
            $article = $raw;
        } else {
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            $article = new Registry(is_array($raw) || is_object($raw) ? $raw : []);
        }
        $source = $article->get('mt_gallery_columns_mode', 'global') === 'custom'
            ? $article : $this->addon_params;
        $columns = [];
        foreach (['mobile' => 1, 'tablet' => 2, 'desktop' => 3] as $device => $default) {
            $value = $source instanceof Registry ? $source->get('mt_gallery_columns_' . $device, $default) : $default;
            $columns[$device] = is_scalar($value) && preg_match('/^(?:[1-9]|10)$/D', (string) $value)
                ? (int) $value : $default;
        }
        return $columns;
    }

    protected function hasImages(): bool
    {
        return count($this->getGalleryImages()) > 0;
    }

    protected function styleInit($item): void
    {
        $application = Factory::getApplication();
        if (!is_object($application) || !method_exists($application, 'getDocument')) {
            return;
        }

        $document = $application->getDocument();
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

        $wa = $document->getWebAssetManager();
        $assetBase = rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . '/components/com_tz_portfolio/add-ons/content/gallery/';

        // TZ Portfolio 4.1 normally registers Fancybox in joomla.asset.json.
        // On some upgraded installations that asset registry is not available yet,
        // therefore register the same component files directly as a safe fallback.
        $fancyboxStyleHandle  = 'com_tz_portfolio.fancybox';
        $fancyboxScriptHandle = 'com_tz_portfolio.fancybox';

        if ($wa->assetExists('style', $fancyboxStyleHandle)) {
            $wa->useStyle($fancyboxStyleHandle);
        } else {
            $fancyboxStyleHandle = 'tzportfolio.content.gallery.fancybox';

            if (!$wa->assetExists('style', $fancyboxStyleHandle)) {
                $wa->registerStyle(
                    $fancyboxStyleHandle,
                    'media/com_tz_portfolio/css/jquery.fancybox.min.css',
                    ['version' => '2.1.7']
                );
            }

            $wa->useStyle($fancyboxStyleHandle);
        }

        if ($wa->assetExists('script', $fancyboxScriptHandle)) {
            $wa->useScript($fancyboxScriptHandle);
        } else {
            $fancyboxScriptHandle = 'tzportfolio.content.gallery.fancybox';

            if (!$wa->assetExists('script', $fancyboxScriptHandle)) {
                $wa->registerScript(
                    $fancyboxScriptHandle,
                    'media/com_tz_portfolio/js/jquery.fancybox.min.js',
                    ['version' => '2.1.7'],
                    [],
                    ['jquery']
                );
            }

            $wa->useScript($fancyboxScriptHandle);
        }

        $wa->registerAndUseStyle(
            'tzportfolio.content.gallery',
            $assetBase . 'css/style.css',
            ['version' => '2.1.7']
        );
        $wa->registerAndUseScript(
            'tzportfolio.content.gallery',
            $assetBase . 'js/lightbox.min.js',
            ['version' => '2.1.7'],
            [],
            ['jquery', $fancyboxScriptHandle]
        );

        $selector = '#' . preg_replace('/[^a-zA-Z0-9_-]/', '', $this->galleryId);
        $css      = '';

        $titleStyle = '';
        if (is_object($item) && !empty($item->title_font)) {
            $titleStyle .= ArticleHelper::font_style($item->title_font);
        }

        $titleMarginTop = is_object($item) && isset($item->title_margin_top)
            ? (int) $item->title_margin_top
            : 0;
        $titleMarginBottom = is_object($item) && isset($item->title_margin_bottom)
            ? (int) $item->title_margin_bottom
            : 0;

        if ($titleMarginTop !== 0) {
            $titleStyle .= 'margin-top:' . $titleMarginTop . 'px;';
        }
        if ($titleMarginBottom !== 0) {
            $titleStyle .= 'margin-bottom:' . $titleMarginBottom . 'px;';
        }

        $titleColor = is_object($item) && isset($item->title_color)
            ? $this->sanitizeCssColor($item->title_color)
            : '';
        if ($titleColor !== '') {
            $titleStyle .= 'color:' . $titleColor . ';';
        }

        if ($titleStyle !== '') {
            $css .= $selector . ' .tz-gallery-title{' . $titleStyle . '}';
        }

        $hoverColor = is_object($item) && isset($item->title_color_hover)
            ? $this->sanitizeCssColor($item->title_color_hover)
            : '';
        if ($hoverColor !== '') {
            $css .= $selector . ' .tz-gallery-title{transition:.3s;}';
            $css .= $selector . ':hover .tz-gallery-title{color:' . $hoverColor . ';}';
        }

        $width       = max(1, (int) preg_replace('/[^0-9]/', '', (string) $this->getGalleryParam('mt_gallery_width', '400')));
        $height      = max(1, (int) preg_replace('/[^0-9]/', '', (string) $this->getGalleryParam('mt_gallery_height', '250')));
        $galleryType = (string) $this->getGalleryParam('mt_gallery_type', 'masonry');

        if (!in_array($galleryType, ['grid', 'masonry', 'horizontal_masonry', 'carousel'], true)) {
            $galleryType = 'masonry';
        }

        $responsive = $this->getGalleryColumns();
        $container = $selector . ' .tz_portfolio_gallery';
        $css .= $container . '{--tz-gallery-columns:' . $responsive['mobile'] . ';}';
        $css .= '@media(min-width:768px){' . $container . '{--tz-gallery-columns:' . $responsive['tablet'] . ';}}';
        $css .= '@media(min-width:1200px){' . $container . '{--tz-gallery-columns:' . $responsive['desktop'] . ';}}';

        if ($galleryType === 'grid') {
            $css .= $container . '.grid-container{grid-template-columns:repeat(var(--tz-gallery-columns),minmax(0,1fr));}';
            $css .= $container . '.grid-container .gallery-listing{height:' . $height . 'px;min-width:0;}';
        } elseif ($galleryType === 'masonry') {
            $css .= $container . '.masonry-container{column-width:auto;column-count:var(--tz-gallery-columns);column-gap:clamp(2px,2vw,30px);}';
        } elseif ($galleryType === 'horizontal_masonry') {
            $css .= $container . '.horizontal_masonry-container{display:grid;grid-template-columns:repeat(var(--tz-gallery-columns),minmax(0,1fr));gap:8px;margin-left:0;}';
            $css .= $container . '.horizontal_masonry-container .gallery-listing{height:' . $height . 'px;min-width:0;width:auto;margin:0;}';
        } elseif ($galleryType === 'carousel') {
            $wa->registerAndUseStyle(
                'tzportfolio.content.gallery.slick',
                $assetBase . 'css/slick.min.css',
                ['version' => '2.1.7']
            );
            $wa->registerAndUseScript(
                'tzportfolio.content.gallery.slick',
                $assetBase . 'js/slick.min.js',
                ['version' => '2.1.7'],
                [],
                ['jquery']
            );

            $columns = $responsive['desktop'];
            $settings = [
                'slidesToShow' => $columns,
                'responsive' => [
                    ['breakpoint' => 1200, 'settings' => ['slidesToShow' => $responsive['tablet']]],
                    ['breakpoint' => 768, 'settings' => ['slidesToShow' => $responsive['mobile']]],
                ],
                'autoplay'     => (bool) $this->getGalleryParam('mt_gallery_carousel_autoplay', 1),
                'autoplaySpeed'=> 3000,
                'dots'         => (bool) $this->getGalleryParam('mt_gallery_carousel_dot_navigation', 1),
                'arrows'       => (bool) $this->getGalleryParam('mt_gallery_carousel_navigation', 1),
                'centerMode'   => (bool) $this->getGalleryParam('mt_gallery_carousel_center_mode', 0),
            ];

            $padding = (string) $this->getGalleryParam('mt_gallery_carousel_center_padding', '18%');
            if (!preg_match('/^\d+(?:\.\d+)?(?:px|%|rem|em|vw|vh)$/', $padding)) {
                $padding = '18%';
            }
            $settings['centerPadding'] = $padding;

            $wa->addInlineScript(
                'jQuery(function($){var $g=$("#' . addslashes($this->galleryId) . ' .tz_portfolio_gallery.carousel-container");'
                . 'if($g.length&&!$g.hasClass("slick-initialized")){$g.slick(' . json_encode($settings, JSON_UNESCAPED_SLASHES) . ');}});'
            );
        }

        if ($css !== '') {
            $wa->addInlineStyle($css);
        }

    }

    protected function getGalleryImages(): array
    {
        $gallery = $this->gallery;
        if (is_string($gallery)) {
            $gallery = json_decode($gallery);
        }
        $content = is_array($gallery) ? ($gallery['gallery_content'] ?? null)
            : (is_object($gallery) ? ($gallery->gallery_content ?? null) : null);
        if (is_string($content)) {
            $content = json_decode($content);
        }
        $images = is_array($content) ? ($content['gallery_image'] ?? [])
            : (is_object($content) ? ($content->gallery_image ?? []) : []);
        if ($images instanceof \Traversable) {
            $images = iterator_to_array($images, false);
        }
        return is_array($images) ? array_values($images) : [];
    }

    protected function sanitizeCssColor($value): string
    {
        $value = trim((string) $value);

        if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\)|[a-zA-Z]+)$/', $value)) {
            return $value;
        }

        return '';
    }
}
