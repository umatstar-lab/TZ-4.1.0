<?php
/*------------------------------------------------------------------------

# TZ Portfolio Gallery Add-on

# ------------------------------------------------------------------------

# Copyright (C) 2011-2026 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

-------------------------------------------------------------------------*/

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Image\Image;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn\AddOnAdminModel;

/**
 * Saves gallery data and keeps files compatible with both legacy and 4.1 paths.
 */
class GalleryModel extends AddOnAdminModel
{
    /**
     * Keep new files outside the directory deleted by Portfolio+ uninstall.
     */
    private const WRITE_DIRECTORY = 'com_tz_portfolio';

    /** @var string[] */
    private const READ_DIRECTORIES = [
        'com_tz_portfolio',
        'tz_portfolio',
        'tz_portfolio_plus',
    ];

    public function save($data)
    {
        if (!is_array($data) || empty($data['content_id'])) {
            return parent::save($data);
        }

        $contentId = (int) $data['content_id'];
        $gallery   = $data['value']['gallery_content'] ?? [];

        if (is_object($gallery)) {
            $gallery = get_object_vars($gallery);
        }
        if (!is_array($gallery)) {
            $gallery = [];
        }

        $tmpFolder = $this->sanitizeTemporaryFolder((string) ($gallery['gallery_folder'] ?? ''));
        $images    = $this->normalizeFilenames($gallery['gallery_image'] ?? []);
        $sources   = $this->normalizeStringArray($gallery['gallery_source'] ?? []);
        $titles    = $this->normalizeStringArray($gallery['gallery_image_title'] ?? []);

        // Never persist transport-only values in the add-on JSON.
        unset($data['value']['gallery_content']['gallery_folder']);
        unset($data['value']['gallery_content']['gallery_source']);
        $data['value']['gallery_content']['gallery_image']       = $images;
        $data['value']['gallery_content']['gallery_image_title'] = $titles;

        if (isset($data['value']['gallery_content']['image_featured'])) {
            $data['value']['gallery_content']['image_featured'] = basename(
                str_replace('\\', '/', (string) $data['value']['gallery_content']['image_featured'])
            );
        }

        $application = Factory::getApplication();
        $input       = $application->input;
        $tmpRoot     = rtrim((string) $application->getConfig()->get('tmp_path'), '/\\');
        $tmpPath     = $tmpFolder !== '' ? $tmpRoot . DIRECTORY_SEPARATOR . $tmpFolder : '';
        $writeRoot   = $this->getGalleryRoot(self::WRITE_DIRECTORY);
        $writeFolder = $writeRoot . DIRECTORY_SEPARATOR . $contentId;
        $resizeFolder = $writeFolder . DIRECTORY_SEPARATOR . 'resize';
        $imageSizes  = $this->normalizeImageSizes($this->getState('params'));

        if (($images !== []) && !Folder::exists($writeFolder)) {
            Folder::create($writeFolder);
        }

        // When duplicating an article, copy the source gallery from whichever
        // path exists into the directory understood by the TZ 4.1 admin field.
        if ($input->getCmd('task') === 'save2copy') {
            $sourceId = $input->getInt('id');
            if ($sourceId > 0 && $sourceId !== $contentId) {
                $this->copyArticleGallery($sourceId, $writeFolder);
            }
        }

        $serverImages = [];
        $clientImages = [];

        foreach ($images as $index => $image) {
            $source = strtolower((string) ($sources[$index] ?? 'server'));
            if ($source === 'client') {
                $clientImages[] = $image;
            } else {
                $serverImages[] = $image;
            }
        }

        // Preserve legacy references by copying existing originals to the current directory.
        foreach ($serverImages as $image) {
            $destination = $writeFolder . DIRECTORY_SEPARATOR . $image;
            if (!File::exists($destination)) {
                $source = $this->findStoredFile($contentId, $image);
                if ($source !== null && $source !== $destination) {
                    File::copy($source, $destination);
                }
            }
        }

        // Move newly uploaded originals and any temporary thumbnails.
        foreach ($clientImages as $image) {
            if ($tmpPath === '') {
                continue;
            }

            $source = $tmpPath . DIRECTORY_SEPARATOR . $image;
            $destination = $writeFolder . DIRECTORY_SEPARATOR . $image;

            if (File::exists($source)) {
                if (File::exists($destination)) {
                    File::delete($destination);
                }
                File::move($source, $destination);
            }

            if (!Folder::exists($resizeFolder)) {
                Folder::create($resizeFolder);
            }

            foreach ($imageSizes as $size) {
                $temporaryResize = $tmpPath . DIRECTORY_SEPARATOR . 'resize' . DIRECTORY_SEPARATOR
                    . File::stripExt($image) . '_' . $size->image_name_prefix . '.' . File::getExt($image);
                $destinationResize = $resizeFolder . DIRECTORY_SEPARATOR
                    . File::stripExt($image) . '_' . $size->image_name_prefix . '.' . File::getExt($image);

                if (File::exists($temporaryResize)) {
                    if (File::exists($destinationResize)) {
                        File::delete($destinationResize);
                    }
                    File::move($temporaryResize, $destinationResize);
                }
            }
        }

        // Remove only originals that are no longer referenced by the article.
        // The modern copied directory is left untouched; it may be shared with
        // an earlier installation and is only removed when the article is deleted.
        if (Folder::exists($writeFolder)) {
            $storedFiles = Folder::files($writeFolder, '.', false, false);
            foreach ($storedFiles as $storedFile) {
                if (!in_array($storedFile, $images, true)) {
                    File::delete($writeFolder . DIRECTORY_SEPARATOR . $storedFile);
                    $this->deleteResizeImages($resizeFolder, $storedFile, $imageSizes);
                }
            }
        }

        // Generate missing thumbnails for migrated and newly uploaded files.
        if ($imageSizes !== [] && Folder::exists($writeFolder)) {
            if (!Folder::exists($resizeFolder)) {
                Folder::create($resizeFolder);
            }

            foreach ($images as $image) {
                $source = $writeFolder . DIRECTORY_SEPARATOR . $image;
                if (File::exists($source)) {
                    $this->createResizeImages($source, $resizeFolder, $imageSizes);
                }
            }
        }

        if ($tmpPath !== '' && Folder::exists($tmpPath)) {
            Folder::delete($tmpPath);
        }

        return parent::save($data);
    }

    /**
     * Delete files from all directories used by TZ Portfolio versions, then
     * let the parent model remove the add-on database record.
     */
    public function delete(&$article)
    {
        $articleId = is_object($article) && !empty($article->id) ? (int) $article->id : 0;

        if ($articleId > 0) {
            foreach (self::READ_DIRECTORIES as $directory) {
                $folder = $this->getGalleryRoot($directory) . DIRECTORY_SEPARATOR . $articleId;
                if (Folder::exists($folder)) {
                    Folder::delete($folder);
                }
            }
        }

        return parent::delete($article);
    }

    private function getGalleryRoot(string $directory): string
    {
        return JPATH_ROOT . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR
            . $directory . DIRECTORY_SEPARATOR . 'gallery';
    }

    private function findStoredFile(int $articleId, string $filename): ?string
    {
        foreach (self::READ_DIRECTORIES as $directory) {
            $path = $this->getGalleryRoot($directory) . DIRECTORY_SEPARATOR
                . $articleId . DIRECTORY_SEPARATOR . $filename;
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function copyArticleGallery(int $sourceId, string $destinationFolder): void
    {
        foreach (self::READ_DIRECTORIES as $directory) {
            $sourceFolder = $this->getGalleryRoot($directory) . DIRECTORY_SEPARATOR . $sourceId;
            if (Folder::exists($sourceFolder)) {
                Folder::copy($sourceFolder, $destinationFolder, '', true);
                return;
            }
        }
    }

    private function sanitizeTemporaryFolder(string $folder): string
    {
        $folder = basename(str_replace('\\', '/', trim($folder)));

        return preg_match('/^gallery_[a-zA-Z0-9._-]+$/', $folder) ? $folder : '';
    }

    /** @return string[] */
    private function normalizeFilenames($values): array
    {
        $values = $this->normalizeStringArray($values);
        $result = [];

        foreach ($values as $value) {
            $filename = basename(str_replace('\\', '/', $value));
            if ($filename !== '' && $filename !== '.' && $filename !== '..') {
                $result[] = $filename;
            }
        }

        return $result;
    }

    /** @return string[] */
    private function normalizeStringArray($values): array
    {
        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values, false);
        } elseif (!is_array($values)) {
            $values = $values === null || $values === '' ? [] : [$values];
        }

        return array_values(array_map(static fn($value): string => (string) $value, $values));
    }

    /**
     * @return object[] Objects with width and image_name_prefix properties.
     */
    private function normalizeImageSizes($params): array
    {
        if (!$params || !method_exists($params, 'get')) {
            return [];
        }

        $raw = $params->get('gallery_size');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw);
            if (is_object($decoded)) {
                $raw = [$decoded];
            } elseif (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $decoded = json_decode('[' . $raw . ']');
                $raw = is_array($decoded) ? $decoded : [];
            }
        } elseif ($raw instanceof \Traversable) {
            $raw = iterator_to_array($raw, false);
        } elseif (!is_array($raw)) {
            $raw = [$raw];
        }

        $sizes = [];
        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $entry = json_decode($entry);
            } elseif (is_array($entry)) {
                $entry = (object) $entry;
            }

            if (!is_object($entry)) {
                continue;
            }

            $width  = isset($entry->width) ? (int) $entry->width : 0;
            $prefix = isset($entry->image_name_prefix)
                ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $entry->image_name_prefix)
                : '';

            if ($width <= 0 || $prefix === '') {
                continue;
            }

            $size = new \stdClass();
            $size->width = $width;
            $size->image_name_prefix = $prefix;
            $sizes[] = $size;
        }

        return $sizes;
    }

    /** @param object[] $imageSizes */
    private function createResizeImages(string $sourcePath, string $resizeFolder, array $imageSizes): void
    {
        foreach ($imageSizes as $size) {
            $newPath = $resizeFolder . DIRECTORY_SEPARATOR
                . File::stripExt(basename($sourcePath))
                . '_' . $size->image_name_prefix . '.' . File::getExt($sourcePath);

            // Preserve a valid existing thumbnail.
            if (File::exists($newPath) && filesize($newPath) > 0) {
                continue;
            }

            try {
                $image = new Image($sourcePath);
                $properties = $image->getImageFileProperties($sourcePath);

                if (empty($properties->width) || empty($properties->height)) {
                    continue;
                }

                $width  = min((int) $size->width, (int) $properties->width);
                $height = max(1, (int) round(((int) $properties->height * $width) / (int) $properties->width));
                $resized = $image->resize($width, $height);

                if (File::exists($newPath)) {
                    File::delete($newPath);
                }

                $resized->toFile($newPath, $properties->type);
                $resized->destroy();
                $image->destroy();
            } catch (\Throwable $exception) {
                // One broken image must not prevent saving the whole article.
                continue;
            }
        }
    }

    /** @param object[] $imageSizes */
    private function deleteResizeImages(string $resizeFolder, string $filename, array $imageSizes): void
    {
        foreach ($imageSizes as $size) {
            $path = $resizeFolder . DIRECTORY_SEPARATOR
                . File::stripExt($filename) . '_' . $size->image_name_prefix . '.' . File::getExt($filename);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
