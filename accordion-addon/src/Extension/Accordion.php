<?php
/*------------------------------------------------------------------------

# TZ Portfolio Extension

# ------------------------------------------------------------------------

# Author:    DuongTVTemPlaza

# Copyright: Copyright (C) 2011-2024 TZ Portfolio.com. All Rights Reserved.

# @License - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

# Website: http://www.tzportfolio.com

# Technical Support:  Forum - https://www.tzportfolio.com/help/forum.html

# Family website: http://www.templaza.com

# Family Support: Forum - https://www.templaza.com/Forums.html

-------------------------------------------------------------------------*/

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use Psr\Container\ContainerInterface;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn\AddOn;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\Helper\AddonHelper;

defined('_JEXEC') or die;

/**
 * Field Vote Add-On
 */
class Accordion extends AddOn
{
    // Portfolio 4 dispatches this event after its article and category mappings are saved.
    public function onAddOnAfterSave($context, $article, $isNew, $data)
    {
        if (!in_array($context, ['com_tz_portfolio.article', 'com_tz_portfolio.form'], true)
            || !is_object($article) || (int) ($article->id ?? 0) < 1
            || !is_array($data)) {
            return;
        }
        $value = $data['content']['accordion'] ?? $this->_myFormDataBeforeSave['content']['accordion'] ?? null;
        if ($value === null) return;
        $model = $this->getModel('Accordion', 'Administrator');
        if (!$model || !$this->addon) return;
        $model->save([
            'extension_id'=>(int) $this->addon->id,
            'element'=>'accordion',
            'content_id'=>(int) $article->id,
            'published'=>1,
            'value'=>SafeContent::data($value),
        ]);
    }

    public function onContentValidateData($form, $data)
    {
        if (in_array($form->getName(), ['com_tz_portfolio.article', 'com_tz_portfolio.form'], true)
            && is_array($data) && isset($data['content']['accordion'])) {
            $this->_myFormDataBeforeSave = ['content'=>['accordion'=>SafeContent::data($data['content']['accordion'])]];
        }
    }

    // The inherited legacy handler caches unfiltered data and runs before Portfolio's own event.
    public function onContentAfterSave($context, $data, $isnew)
    {
    }

    public function onAfterDisplayAdditionInfo($context, &$article, $params, $page = 0, $layout = 'default', $module = null)
    {
        [$extension, $vName] = array_pad(explode('.', (string) $context, 2), 2, '');
        $item = $article;

        if ($extension == 'module' || $extension == 'modules') {
            if ($path = $this->getModuleLayout($this->_type, $this->_name, $extension, $vName, $layout)) {
                // Display html
                ob_start();
                include $path;
                $html = ob_get_contents();
                ob_end_clean();
                $html = trim($html);
                return $html;
            }
        } elseif (in_array($context, array('com_tz_portfolio.portfolio', 'com_tz_portfolio.date'
        , 'com_tz_portfolio.featured', 'com_tz_portfolio.tags', 'com_tz_portfolio.users'))){
            if($html = $this -> _getViewHtml($context,$item, $params)){
                return $html;
            }
        }
    }

    public function onAddFormToArticleDescription($article = null){
        $position   = $this -> __addFormToPosition($article);
        return $position;
    }
}
