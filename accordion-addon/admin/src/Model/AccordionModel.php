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

namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Administrator\Model;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn\AddOnAdminModel;
use TemPlaza\Component\TZ_Portfolio\Site\Helper\TZ_PortfolioHelper as TZ_PortfolioFrontHelper;

class AccordionModel extends AddOnAdminModel {
    public function save($data)
    {
        if (is_array($data) && array_key_exists('value', $data)) {
            $data['value'] = \TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension\SafeContent::data($data['value']);
        } elseif (is_object($data) && isset($data->value)) {
            $data = clone $data;
            $data->value = \TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension\SafeContent::data($data->value);
        }
        return parent::save($data);
    }

}
