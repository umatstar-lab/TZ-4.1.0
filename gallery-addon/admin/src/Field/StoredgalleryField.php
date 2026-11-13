<?php
namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Administrator\Field;
defined('_JEXEC') or die;
use Joomla\CMS\Uri\Uri;
use TemPlaza\Component\TZ_Portfolio\Administrator\Field\TzgalleryField;
/** Keep the core uploader, resolving stored previews independently of Portfolio+. */
class StoredgalleryField extends TzgalleryField
{
    protected function getInput()
    {
        $html = parent::getInput();
        return preg_replace_callback('~src="[^"]*/images/tz_portfolio_plus/gallery/([0-9]+)/([^"<>]*)"~', static function ($match) {
            $name = rawurldecode(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'));
            if ($name === '' || basename($name) !== $name || str_contains($name, '\\') || str_contains($name, "\0")) {
                return $match[0];
            }
            foreach (['com_tz_portfolio', 'tz_portfolio', 'tz_portfolio_plus'] as $directory) {
                $path = 'images/' . $directory . '/gallery/' . (int) $match[1] . '/' . $name;
                if (is_file(JPATH_ROOT . '/' . $path)) {
                    $url = rtrim(Uri::root(), '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
                    return 'src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            return $match[0];
        }, $html);
    }
}
