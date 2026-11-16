<?php
namespace TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Site\View\Article;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension\SafeContent;

class HtmlView extends BaseHtmlView
{
    protected $item = null;
    protected $params = null;
    protected $accordion = null;
    protected $accordionId = '';
    private static int $instance = 0;

    public function display($tpl = null)
    {
        $model = $this->getModel();
        $this->accordion = SafeContent::data($model->getAddonData());
        $this->item = $model->getItem();
        $this->params = $model->getState('params');
        $this->accordionId = 'tz-accordion-' . (int) ($this->item->id ?? 0) . '-' . ++self::$instance;
        if ($this->params->get('accordion_show', 1) && $this->accordion['accordion_items']) $this->styleInit();
        parent::display($tpl);
    }
    protected function styleInit(): void
    {
        $item = $this->accordion;
        $selector = '#' . $this->accordionId;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('bootstrap.collapse');
        $wa->registerAndUseStyle('tzportfolio.content.accordion', 'components/com_tz_portfolio/add-ons/content/accordion/css/style.css', ['version'=>'1.0.1']);
        $title = '';
        foreach (['title_margin_top'=>'margin-top', 'title_margin_bottom'=>'margin-bottom'] as $key=>$property) {
            if ($item[$key] !== '') $title .= $property . ':' . $item[$key] . 'px;';
        }
        if ($item['title_color'] !== '') $title .= 'color:' . $item['title_color'] . ';';
        $font = json_decode($item['title_font'], true);
        foreach (['fontFamily'=>'font-family','fontWeight'=>'font-weight','fontSize'=>'font-size','lineHeight'=>'line-height','fontStyle'=>'font-style','textTransform'=>'text-transform','textDecoration'=>'text-decoration','letterSpacing'=>'letter-spacing'] as $key=>$property) {
            if (isset($font[$key])) $title .= $property . ':' . $font[$key] . ';';
        }
        $css = $title !== '' ? $selector . ' .tz-accordion-title{' . $title . '}' : '';
        if ($item['title_color_hover'] !== '') $css .= $selector . ':hover .tz-accordion-title{color:' . $item['title_color_hover'] . ';}';
        $margin = json_decode($item['accordion_margin'], true);
        foreach (['md'=>'', 'sm'=>'@media (min-width:768px) and (max-width:991px)', 'xs'=>'@media (max-width:767px)'] as $size=>$media) {
            $rules = '';
            foreach ($margin[$size] ?? [] as $side=>$value) $rules .= 'margin-' . $side . ':' . $value . ';';
            if ($rules !== '') $css .= ($media !== '' ? $media . '{' : '') . $selector . '{' . $rules . '}' . ($media !== '' ? '}' : '');
        }
        if ($css !== '') $wa->addInlineStyle($css);
    }
}
