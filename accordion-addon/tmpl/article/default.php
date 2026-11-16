<?php
/** Safe accordion renderer; values are revalidated for pre-existing records too. */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension\SafeContent;
if (!$this->params->get('accordion_show', 1)) return;
$data = SafeContent::data($this->accordion);
if (!$data['accordion_items']) return;
$id = $this->accordionId;
$escape = [SafeContent::class, 'escape'];
$heading = $data['title_element'];
?>
<div id="<?php echo $escape($id); ?>" class="tz-portfolio-accordion <?php echo $escape($data['custom_class']); ?>">
<?php if ($data['title'] !== '') : ?>
<<?php echo $heading; ?> class="tz-accordion-title"><?php echo $escape($data['title']); ?></<?php echo $heading; ?>>
<?php endif; ?>
<div class="accordion <?php echo $escape($data['gutter']); ?>" id="<?php echo $escape($id); ?>-group">
<?php $index = 0; foreach ($data['accordion_items'] as $item) :
    $panel = $id . '-panel-' . $index;
    $button = $id . '-button-' . $index;
    $open = $index === 0;
?>
<div class="accordion-item">
<h3 class="tz-accordion-item-title accordion-header">
<button id="<?php echo $escape($button); ?>" class="accordion-button<?php echo $open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $escape($panel); ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo $escape($panel); ?>"><?php echo $escape($item['title'] !== '' ? $item['title'] : Text::_('PLG_CONTENT_ACCORDION_ITEM') . ' ' . ($index + 1)); ?></button>
</h3>
<div id="<?php echo $escape($panel); ?>" class="accordion-collapse collapse<?php echo $open ? ' show' : ''; ?>" aria-labelledby="<?php echo $escape($button); ?>" data-bs-parent="#<?php echo $escape($id); ?>-group"><div class="accordion-body"><?php echo $item['content']; ?></div></div>
</div>
<?php ++$index; endforeach; ?>
</div>
</div>
