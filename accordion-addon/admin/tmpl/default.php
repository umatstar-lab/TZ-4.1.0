<?php
/*------------------------------------------------------------------------

# TZ Portfolio Plus Extension

# ------------------------------------------------------------------------

# author    DuongTVTemPlaza

# copyright Copyright (C) 2015 templaza.com. All Rights Reserved.

# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

# Websites: http://www.templaza.com

# Technical Support:  Forum - http://templaza.com/Forum

-------------------------------------------------------------------------*/

// No direct access.
defined('_JEXEC') or die;

$form   = $this -> form;
$group  = 'content.'.$this -> _name;
if($form){
    $fields = $form -> getFieldset();
    if(count($fields)){
        ?>
            <?php
            foreach($fields as $field){
                echo $field -> renderField();
            }
            ?>
        <?php
    }
}
?>