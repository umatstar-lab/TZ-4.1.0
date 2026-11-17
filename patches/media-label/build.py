from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
root=Path(__file__).resolve().parents[2]
source=root/'tz_portfolio_tz-portfolio.v4.1.2-associations-fix.zip'
target=root/'tz_portfolio_tz-portfolio.v4.1.2-media-label-fix.zip'
name='tz_portfolio-4.1.2/setup/packages/admin/src/Model/ArticlesModel.php'
anchor=b"                if(isset($item -> type) && in_array($item -> type, $values)){"
replacement=b'''                // Content Gallery contributes a synthetic media selector value from a content addon.
                // Keep the saved value intact in the database; only resolve the list label here.
                if (($item->type ?? '') === 'content_gallery') {
                    $language = substr($app->getLanguage()->getTag(), 0, 2);
                    $item->type = in_array($language, ['ru', 'uk'], true) ? '\xd0\x93\xd0\xb0\xd0\xbb\xd0\xb5\xd1\x80\xd0\xb5\xd1\x8f' : 'Gallery';
                } elseif(isset($item -> type) && in_array($item -> type, $values)){'''
with ZipFile(source) as src,ZipFile(target,'w',ZIP_DEFLATED) as out:
 for info in src.infolist():
  data=src.read(info.filename)
  if info.filename==name:
   assert data.count(anchor)==1
   data=data.replace(anchor,replacement.replace(b'\n',b'\r\n'))
  out.writestr(info,data)
print(target.name)
