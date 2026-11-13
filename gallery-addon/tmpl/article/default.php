<?php
/*------------------------------------------------------------------------

# TZ Portfolio Gallery Add-on

# ------------------------------------------------------------------------

# Copyright (C) 2011-2026 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

-------------------------------------------------------------------------*/

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;

$params = $this->params;
$view   = $this;

$getGalleryParam = static function ($name, $default = null) use ($params, $view) {
    if (method_exists($view, 'getGalleryParam')) {
        return $view->getGalleryParam($name, $default);
    }

    $value = $params && method_exists($params, 'get') ? $params->get($name, $default) : $default;

    return ($value === '' || $value === null) ? $default : $value;
};

if ((int) $getGalleryParam('mt_gallery_show', 1) !== 1) {
    return;
}

$gallery = $this->gallery ?? null;
if (is_string($gallery)) {
    $gallery = json_decode($gallery);
}
if (is_array($gallery)) {
    $gallery = (object) $gallery;
}
if (!is_object($gallery)) {
    return;
}

$content = $gallery->gallery_content ?? null;
if (is_string($content)) {
    $decoded = json_decode($content);
    $content = is_object($decoded) || is_array($decoded) ? $decoded : null;
}
if (is_array($content)) {
    $content = (object) $content;
}
if (!is_object($content)) {
    return;
}

$images = $content->gallery_image ?? [];
if ($images instanceof \Traversable) {
    $images = iterator_to_array($images, false);
}
if (!is_array($images) || count($images) === 0) {
    return;
}
$images = array_values($images);

$captions = $content->gallery_image_title ?? ($content->caption ?? ($gallery->caption ?? []));
if ($captions instanceof \Traversable) {
    $captions = iterator_to_array($captions, false);
}
if (!is_array($captions)) {
    $captions = [];
}
$captions = array_values($captions);

$item   = $this->item ?? null;
$itemId = is_object($item) && isset($item->id) ? (int) $item->id : 0;
if ($itemId <= 0) {
    return;
}

$galleryType = (string) $getGalleryParam('mt_gallery_type', 'masonry');
if (!in_array($galleryType, ['grid', 'masonry', 'horizontal_masonry', 'carousel'], true)) {
    $galleryType = 'masonry';
}

$titlePosition = (string) $getGalleryParam('mt_grid_image_title_position', 'under_image');
if (!in_array($titlePosition, ['under_image', 'on_overlay'], true)) {
    $titlePosition = 'under_image';
}

$carouselGutter = min(5, max(0, (int) $getGalleryParam('mt_gallery_carousel_gutter', 0)));
$carouselHeight = max(
    1,
    (int) preg_replace('/[^0-9]/', '', (string) $getGalleryParam('mt_gallery_carousel_height', '600'))
);
$masonryWidth = max(
    1,
    (int) preg_replace('/[^0-9]/', '', (string) $getGalleryParam('mt_gallery_width', '400'))
);

$title = isset($gallery->title) ? trim((string) $gallery->title) : '';
$headingSelector = isset($gallery->title_element) ? strtolower((string) $gallery->title_element) : 'h3';
if (!in_array($headingSelector, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
    $headingSelector = 'h3';
}

$customClass = isset($gallery->custom_class) ? trim((string) $gallery->custom_class) : '';
$wrapperId   = !empty($this->galleryId)
    ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $this->galleryId)
    : 'tz-portfolio-gallery-' . $itemId;

$galleryClasses = [
    'tz_portfolio_gallery',
    'tz_portfolio_plus_gallery',
    'astroid-slick',
    $galleryType . '-container',
];
if ($galleryType === 'carousel') {
    $galleryClasses[] = 'gx-' . $carouselGutter;
}

/**
 * Resolve one image against every directory used by TZ Portfolio generations.
 * TZ Portfolio 4.1 copies legacy images to images/com_tz_portfolio, while its
 * gallery field still writes and previews images/tz_portfolio_plus.
 */
$resolveImage = static function (string $filename, int $articleId, string $size = 'o'): array {
    $filename = basename(str_replace('\\', '/', $filename));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return ['', ''];
    }

    $baseCandidates = [
        'images/com_tz_portfolio/gallery/' . $articleId . '/',
        'images/tz_portfolio/gallery/' . $articleId . '/',
        'images/tz_portfolio_plus/gallery/' . $articleId . '/',
    ];

    $original = '';
    $basePath = '';

    foreach ($baseCandidates as $candidate) {
        if (File::exists(JPATH_ROOT . '/' . $candidate . $filename)) {
            $basePath = $candidate;
            $original = $candidate . $filename;
            break;
        }
    }

    // Keep a deterministic URL even if the file is temporarily missing.
    if ($original === '') {
        $basePath = $baseCandidates[0];
        $original = $basePath . $filename;
    }

    $thumb = $original;
    if ($size !== '' && $size !== 'o') {
        $size = preg_replace('/[^a-zA-Z0-9_-]/', '', $size);
        if ($size !== '') {
            $candidateThumb = $basePath . 'resize/'
                . File::stripExt($filename)
                . '_' . $size . '.' . File::getExt($filename);

            if (File::exists(JPATH_ROOT . '/' . $candidateThumb)) {
                $thumb = $candidateThumb;
            }
        }
    }

    return [$original, $thumb];
};

$rootUrl = rtrim(Uri::root(), '/') . '/';
$imageSize = (string) $getGalleryParam('mt_gallery_size', 'o');
if ($imageSize === '') {
    $imageSize = 'o';
}

$lightboxButtons = $getGalleryParam(
    'gallery_lightbox_option',
    ['zoom', 'slideShow', 'fullScreen', 'thumbs', 'close']
);
if (!is_array($lightboxButtons)) {
    $decodedButtons = json_decode((string) $lightboxButtons, true);
    $lightboxButtons = is_array($decodedButtons) ? $decodedButtons : [];
}
$allowedButtons = ['zoom', 'share', 'slideShow', 'fullScreen', 'download', 'thumbs', 'close'];
$lightboxButtons = array_values(array_intersect($allowedButtons, array_map('strval', $lightboxButtons)));
if ($lightboxButtons === []) {
    $lightboxButtons = ['zoom', 'slideShow', 'fullScreen', 'thumbs', 'close'];
}
$lightboxButtonsJson = json_encode($lightboxButtons, JSON_UNESCAPED_SLASHES);
?>
<div id="<?php echo htmlspecialchars($wrapperId, ENT_QUOTES, 'UTF-8'); ?>"
     class="tz-portfolio-gallery<?php echo $customClass !== '' ? ' ' . htmlspecialchars($customClass, ENT_QUOTES, 'UTF-8') : ''; ?>"
     data-lightbox-buttons="<?php echo htmlspecialchars((string) $lightboxButtonsJson, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($title !== '') : ?>
        <<?php echo $headingSelector; ?> class="tz-addon-title tz-gallery-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></<?php echo $headingSelector; ?>>
    <?php endif; ?>

    <div class="<?php echo htmlspecialchars(implode(' ', $galleryClasses), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $galleryType === 'masonry' ? ' style="--tz-gallery-column-width:' . $masonryWidth . 'px;"' : ''; ?>>
        <?php foreach ($images as $index => $storedImage) : ?>
            <?php
            $storedImage = (string) $storedImage;
            [$original, $thumb] = $resolveImage($storedImage, $itemId, $imageSize);
            if ($original === '' || $thumb === '') {
                continue;
            }

            $caption = isset($captions[$index]) ? trim((string) $captions[$index]) : '';
            $dataId  = 'gallery-' . $itemId . '-' . $index;
            $imageStyle = $galleryType === 'carousel'
                ? 'height:' . $carouselHeight . 'px;'
                : '';
            ?>
            <div class="gallery-listing element"
                 data-date=""
                 data-hits=""
                 data-title="<?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="gallery-inner">
                    <div class="gallery-image"<?php echo $imageStyle !== '' ? ' style="' . htmlspecialchars($imageStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                        <?php if ($galleryType !== 'carousel') : ?>
                            <a class="gallery-title"
                               data-thumb="<?php echo htmlspecialchars($rootUrl . ltrim($thumb, '/'), ENT_QUOTES, 'UTF-8'); ?>"
                               data-id="<?php echo htmlspecialchars($dataId, ENT_QUOTES, 'UTF-8'); ?>"
                               href="<?php echo htmlspecialchars($rootUrl . ltrim($original, '/'), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($caption !== '' && $titlePosition === 'on_overlay') : ?>
                                    <h5 class="image-caption on_overlay"><?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?></h5>
                                <?php endif; ?>
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <span class="visually-hidden"><?php echo htmlspecialchars($caption !== '' ? $caption : basename($storedImage), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php endif; ?>

                        <img src="<?php echo htmlspecialchars($rootUrl . ltrim($thumb, '/'), ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?>"
                             loading="lazy"
                             decoding="async"<?php echo $galleryType === 'carousel' ? ' class="object-fit-cover w-100 h-100"' : ''; ?> />
                    </div>

                    <?php if ($caption !== '' && $titlePosition === 'under_image') : ?>
                        <h5 class="image-caption under_image"><?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
