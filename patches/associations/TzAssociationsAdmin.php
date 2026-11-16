<?php
/** Portfolio article associations HTML service. GPL-2.0-or-later. */
namespace TemPlaza\Component\TZ_Portfolio\Administrator\Service\HTML;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

final class TzAssociationsAdmin
{
    public function association($articleId): string
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$app->isClient('administrator') || !$user->authorise('core.manage', 'com_tz_portfolio') || (int) $articleId < 1) {
            return '';
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT c.id, c.title, c.language, c.created_by, l.title AS language_title')
            ->from($db->quoteName('#__associations', 'a'))
            ->join('INNER', $db->quoteName('#__associations', 'b') . ' ON b.' . $db->quoteName('key') . ' = a.' . $db->quoteName('key') . ' AND b.context = a.context')
            ->join('INNER', $db->quoteName('#__tz_portfolio_plus_content', 'c') . ' ON c.id = b.id')
            ->join('LEFT', $db->quoteName('#__languages', 'l') . ' ON l.lang_code = c.language')
            ->where('a.context = ' . $db->quote('com_tz_portfolio.article.item'))
            ->where('a.id = ' . (int) $articleId)
            ->where('c.id <> ' . (int) $articleId)
            ->order('c.language, c.id');
        $rows = $db->setQuery($query)->loadObjectList();
        if (!$rows) {
            return '';
        }
        $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<ul class="item-associations list-inline">';
        foreach ($rows as $row) {
            $asset = 'com_tz_portfolio.article.' . (int) $row->id;
            $title = $escape(($row->language_title ?: $row->language) . ': ' . $row->title);
            $label = $escape($row->language);
            $html .= '<li class="list-inline-item">';
            if ($user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && (int) $row->created_by === (int) $user->id)) {
                $url = $escape(Route::_('index.php?option=com_tz_portfolio&task=article.edit&id=' . (int) $row->id));
                $html .= '<a class="badge bg-secondary" href="' . $url . '" title="' . $title . '">' . $label . '</a>';
            } else {
                $html .= '<span class="badge bg-secondary" title="' . $title . '">' . $label . '</span>';
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }
}
