from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import re,xml.etree.ElementTree as E
r=Path(__file__).resolve().parent
values={
'PLG_CONTENT_ACCORDION':('Content - Accordion','Содержимое — Аккордеон','Вміст — Акордеон'),
'PLG_CONTENT_ACCORDION_XML_DESCRIPTION':('Collapsible content sections for TZ Portfolio 4.1.2.','Раскрывающиеся разделы содержимого для TZ Portfolio 4.1.2.','Розгортні розділи вмісту для TZ Portfolio 4.1.2.'),
'PLG_CONTENT_ACCORDION_TITLE':('Accordion','Аккордеон','Акордеон'),
'PLG_CONTENT_ACCORDION_ITEM':('Section','Раздел','Розділ'),
'PLG_CONTENT_ACCORDION_FRONT_END_FIELDSET_LABEL':('Display settings','Настройки отображения','Налаштування відображення'),
'PLG_CONTENT_ACCORDION_CONFIG_SETTINGS_DESC':('Configure the accordion display.','Настройка отображения аккордеона.','Налаштування відображення акордеона.'),
'PLG_CONTENT_ACCORDION_SHOW_LABEL':('Show accordion','Показывать аккордеон','Показувати акордеон'),
'PLG_CONTENT_ACCORDION_OPTIONS_LABEL':('Accordion content settings','Параметры содержимого аккордеона','Параметри вмісту акордеона'),
'TZPORTFOLIO_ADDON_CONTENT_ACCORDION_GUTTER':('Space between sections','Расстояние между разделами','Відстань між розділами'),
'TZPORTFOLIO_ADDON_VALUE_ITEMS':('Accordion sections','Разделы аккордеона','Розділи акордеона'),
'TZPORTFOLIO_ADDON_LEADER_ADD_ITEM':('Add section','Добавить раздел','Додати розділ'),
'COM_TZ_PORTFOLIO_PLUS_LABEL_TITLE':('Title','Заголовок','Заголовок'),
'COM_TZ_PORTFOLIO_PLUS_ATTRIBS_FIELDSET_LABEL':('Article settings','Параметры материала','Параметри матеріалу'),
'TZPORTFOLIO_ADDON_HTML_ELEMENT':('Heading element','Элемент заголовка','Елемент заголовка'),
'TZPORTFOLIO_ADDON_TITLE_FONT':('Title font','Шрифт заголовка','Шрифт заголовка'),
'TZPORTFOLIO_ADDON_TITLE_COLOR':('Title colour','Цвет заголовка','Колір заголовка'),
'TZPORTFOLIO_ADDON_TITLE_COLOR_HOVER':('Title colour on hover','Цвет заголовка при наведении','Колір заголовка при наведенні'),
'TZPORTFOLIO_ADDON_TITLE_MARGIN_TOP':('Space above title (px)','Отступ над заголовком (px)','Відступ над заголовком (px)'),
'TZPORTFOLIO_ADDON_TITLE_MARGIN_BOTTOM':('Space below title (px)','Отступ под заголовком (px)','Відступ під заголовком (px)'),
'TZPORTFOLIO_ADDON_GENERAL_OPTIONS':('General settings','Общие настройки','Загальні налаштування'),
'TZPORTFOLIO_ADDON_MARGIN':('Outer margins','Внешние отступы','Зовнішні відступи'),
'TZPORTFOLIO_ADDON_CONTENT_CLASS':('CSS classes','CSS-классы','CSS-класи'),
'PLG_CONTENT_ACCORDION_DEFAULT':('Default','По умолчанию','За замовчуванням'),
'PLG_CONTENT_ACCORDION_XSMALL':('Extra small','Очень маленькое','Дуже мала'),
'PLG_CONTENT_ACCORDION_SMALL':('Small','Маленькое','Мала'),
'PLG_CONTENT_ACCORDION_MEDIUM':('Medium','Среднее','Середня'),
'PLG_CONTENT_ACCORDION_LARGE':('Large','Большое','Велика'),
'PLG_CONTENT_ACCORDION_XLARGE':('Extra large','Очень большое','Дуже велика'),
}
for lang,idx in [('en-GB',0),('ru-RU',1),('uk-UA',2)]:
 p=r/'language'/lang/(lang+'.tp_addon_content_accordion.ini');p.parent.mkdir(exist_ok=True);p.write_text('; GPL-2.0-or-later; UTF-8\n'+'\n'.join(k+'="'+v[idx]+'"' for k,v in values.items())+'\n')
p=r/'admin/forms/accordion.xml';s=p.read_text();
for a,b in [('Default','DEFAULT'),('X-Small','XSMALL'),('Small','SMALL'),('Medium','MEDIUM'),('Large','LARGE'),('X-Large','XLARGE')]:s=s.replace('>'+a+'</option>','>PLG_CONTENT_ACCORDION_'+b+'</option>')
p.write_text(s)
p=r/'accordion.xml';s=p.read_text().replace('<version>1.0.0</version>','<version>1.0.1</version>').replace('version="4.*"','version="4.1.*"');p.write_text(s)
# All keys owned/referenced by the addon (Joomla core J* keys are supplied by Joomla).
keys=set()
for p in r.rglob('*'):
 if p.suffix in ['.php','.xml']:keys.update(re.findall(r'\b(?:PLG_CONTENT_ACCORDION|TZPORTFOLIO_ADDON|COM_TZ_PORTFOLIO_PLUS)_[A-Z0-9_]+\b',p.read_text()))
assert keys <= values.keys(),keys-values.keys()
out=r.parent/'accordion_content_addon_v1.0.1_joomla6_tz412_ru_uk.zip'
with ZipFile(out,'w',ZIP_DEFLATED) as z:
 for p in sorted(r.rglob('*')):
  if p.is_file() and p.name not in ['build.py','README.md']:z.write(p,p.relative_to(r))
print(out.name, len(values),'keys per language')
