from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root=Path(__file__).resolve().parents[2]
source=root/'tz_portfolio_tz-portfolio.v4.1.2-fixed.zip'
target=root/'tz_portfolio_tz-portfolio.v4.1.2-associations-fix.zip'
component='tz_portfolio-4.1.2/setup/packages/admin/src/Extension/TZ_PortfolioComponent.php'
helper='tz_portfolio-4.1.2/setup/packages/admin/src/Service/HTML/TzAssociationsAdmin.php'
anchor=b"        $this->getRegistry()->register('tztemplates', new TZTemplates());"
with ZipFile(source) as src, ZipFile(target,'w',ZIP_DEFLATED) as out:
    for info in src.infolist():
        data=src.read(info.filename)
        if info.filename==component:
            assert data.count(anchor)==1
            data=data.replace(anchor,anchor+b"\r\n        $this->getRegistry()->register('tzcontentadmin', new \\TemPlaza\\Component\\TZ_Portfolio\\Administrator\\Service\\HTML\\TzAssociationsAdmin());")
        if info.filename=='tz_portfolio-4.1.2/setup/packages/admin/src/Model/FeaturedModel.php':
            anchor2=b"        $query->from('#__tz_portfolio_plus_content AS a');"
            assert data.count(anchor2)==1
            data=data.replace(anchor2,anchor2+b"\r\n        // Keep language associations available in the featured list as well.\r\n        $query->select(\"EXISTS (SELECT 1 FROM #__associations af JOIN #__associations bf ON bf.`key` = af.`key` AND bf.context = af.context WHERE af.context = 'com_tz_portfolio.article.item' AND af.id = a.id AND bf.id <> a.id) AS association\");")
        out.writestr(info,data)
    out.writestr(helper,(root/'patches/associations/TzAssociationsAdmin.php').read_bytes())
print(target.name)
