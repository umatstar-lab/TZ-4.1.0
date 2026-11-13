<?php
/*------------------------------------------------------------------------

# TZ Portfolio Gallery Add-on

# ------------------------------------------------------------------------

# Copyright (C) 2011-2026 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

-------------------------------------------------------------------------*/

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Image\Image;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn\AddOnController;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\Helper\AddonHelper;

/**
 * Legacy AJAX controller kept for installations that still call the add-on
 * directly. TZ Portfolio 4.1 normally uses com_tz_portfolio AjaxController.
 */
class GalleryController extends AddOnController
{
    private const SERVER_DIRECTORIES = [
        'tz_portfolio_plus',
        'com_tz_portfolio',
        'tz_portfolio',
    ];

    public function ajax()
    {
        $this->sendJsonHeaders();

        try {
            $application = Factory::getApplication();
            if (!$application->isClient('administrator')) {
                throw new \RuntimeException('You are not authorized.');
            }

            $input    = $application->input;
            $userfile = $input->files->get('file', null, 'raw');
            $folder   = $this->sanitizeTemporaryFolder($input->getString('folder', ''));

            if ($folder === '' || !is_array($userfile) || !isset($userfile['error']) || is_array($userfile['error'])) {
                throw new \RuntimeException('Invalid upload parameters.');
            }

            switch ((int) $userfile['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new \RuntimeException('No file sent.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new \RuntimeException('Exceeded file size limit.');
                default:
                    throw new \RuntimeException('Upload failed.');
            }

            $tmpSource = (string) ($userfile['tmp_name'] ?? '');
            if ($tmpSource === '' || !is_uploaded_file($tmpSource)) {
                throw new \RuntimeException('Invalid uploaded file.');
            }

            $extension = strtolower(File::getExt((string) ($userfile['name'] ?? '')));
            $allowedExtensions = $this->getAllowedExtensions();
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException('Unsupported image type.');
            }

            $imageInfo = @getimagesize($tmpSource);
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                throw new \RuntimeException('The uploaded file is not a valid image.');
            }

            $baseName = ApplicationHelper::stringURLSafe(File::stripExt((string) $userfile['name']));
            if ($baseName === '') {
                $baseName = 'gallery-image-' . substr(sha1((string) microtime(true)), 0, 10);
            }
            $filename = $baseName . '.' . $extension;

            $tmpRoot      = rtrim((string) $application->getConfig()->get('tmp_path'), '/\\');
            $tmpFolder    = $tmpRoot . DIRECTORY_SEPARATOR . $folder;
            $resizeFolder = $tmpFolder . DIRECTORY_SEPARATOR . 'resize';
            $destination  = $tmpFolder . DIRECTORY_SEPARATOR . $filename;

            if (!Folder::exists($tmpFolder) && !Folder::create($tmpFolder)) {
                throw new \RuntimeException('Failed to create upload folder.');
            }
            if (!Folder::exists($resizeFolder) && !Folder::create($resizeFolder)) {
                throw new \RuntimeException('Failed to create resize folder.');
            }
            if (File::exists($destination)) {
                File::delete($destination);
            }
            if (!File::upload($tmpSource, $destination)) {
                throw new \RuntimeException('Failed to move uploaded file.');
            }

            $this->createResizeImages($destination, $resizeFolder, $this->getImageSizes());
            $this->respond(['status' => 'ok', 'name' => $filename]);
        } catch (\Throwable $exception) {
            http_response_code(400);
            $this->respond(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    public function delete()
    {
        $this->sendJsonHeaders();

        try {
            $application = Factory::getApplication();
            if (!$application->isClient('administrator')) {
                throw new \RuntimeException('You are not authorized.');
            }

            $input  = $application->input;
            $file   = basename(str_replace('\\', '/', $input->getString('file', '')));
            $source = strtolower($input->getCmd('data_source', ''));

            if ($file === '' || $file === '.' || $file === '..' || !in_array($source, ['client', 'server'], true)) {
                throw new \RuntimeException('Invalid parameters.');
            }

            $folders = [];
            if ($source === 'client') {
                $tmpFolder = $this->sanitizeTemporaryFolder($input->getString('tmp_folder', ''));
                if ($tmpFolder === '') {
                    throw new \RuntimeException('Invalid temporary folder.');
                }
                $folders[] = rtrim((string) $application->getConfig()->get('tmp_path'), '/\\')
                    . DIRECTORY_SEPARATOR . $tmpFolder;
            } else {
                $articleId = $input->getInt('article_id');
                if ($articleId <= 0) {
                    throw new \RuntimeException('Invalid article.');
                }
                foreach (self::SERVER_DIRECTORIES as $directory) {
                    $folders[] = JPATH_ROOT . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR
                        . $directory . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR . $articleId;
                }
            }

            $deleted = false;
            $sizes   = $this->getImageSizes();
            foreach ($folders as $folder) {
                $path = $folder . DIRECTORY_SEPARATOR . $file;
                if (File::exists($path)) {
                    File::delete($path);
                    $deleted = true;
                }

                foreach ($sizes as $size) {
                    $resize = $folder . DIRECTORY_SEPARATOR . 'resize' . DIRECTORY_SEPARATOR
                        . File::stripExt($file) . '_' . $size->image_name_prefix . '.' . File::getExt($file);
                    if (File::exists($resize)) {
                        File::delete($resize);
                    }
                }
            }

            if (!$deleted) {
                throw new \RuntimeException('File not found.');
            }

            $this->respond(['status' => 'ok', 'name' => $file]);
        } catch (\Throwable $exception) {
            http_response_code(400);
            $this->respond(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    private function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data): void
    {
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Factory::getApplication()->close();
    }

    private function sanitizeTemporaryFolder(string $folder): string
    {
        $folder = basename(str_replace('\\', '/', trim($folder)));

        return preg_match('/^gallery_[a-zA-Z0-9._-]+$/', $folder) ? $folder : '';
    }

    /** @return string[] */
    private function getAllowedExtensions(): array
    {
        $addon  = AddonHelper::getAddOn('content', 'gallery');
        $params = new Registry($addon && isset($addon->params) ? $addon->params : null);
        $raw    = strtolower((string) $params->get('gallery_file_type', 'bmp,gif,jpg,jpeg,png,webp'));

        return array_values(array_unique(array_filter(array_map(
            static fn(string $extension): string => preg_replace('/[^a-z0-9]/', '', trim($extension)),
            explode(',', $raw)
        ))));
    }

    /** @return object[] */
    private function getImageSizes(): array
    {
        $addon  = AddonHelper::getAddOn('content', 'gallery');
        $params = new Registry($addon && isset($addon->params) ? $addon->params : null);
        $raw    = $params->get('gallery_size', []);

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

    /** @param object[] $sizes */
    private function createResizeImages(string $source, string $resizeFolder, array $sizes): void
    {
        foreach ($sizes as $size) {
            try {
                $image = new Image($source);
                $properties = $image->getImageFileProperties($source);
                if (empty($properties->width) || empty($properties->height)) {
                    continue;
                }

                $width  = min((int) $size->width, (int) $properties->width);
                $height = max(1, (int) round(((int) $properties->height * $width) / (int) $properties->width));
                $resized = $image->resize($width, $height);
                $path = $resizeFolder . DIRECTORY_SEPARATOR . File::stripExt(basename($source))
                    . '_' . $size->image_name_prefix . '.' . File::getExt($source);

                if (File::exists($path)) {
                    File::delete($path);
                }
                $resized->toFile($path, $properties->type);
                $resized->destroy();
                $image->destroy();
            } catch (\Throwable $exception) {
                continue;
            }
        }
    }
}
