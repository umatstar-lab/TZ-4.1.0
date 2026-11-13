(function(){'use strict';
 const root=document.getElementById('tzmt');if(!root)return;
 const cfg=Joomla.getOptions('com_tz_media_transfer'),el=id=>document.getElementById('tzmt-'+id);
 let inventory=[],materials=[],scanned=false,job=null,busy=false,pumping=false,cancelling=false;
 const statuses={running:'Выполняется',conflict:'Ожидает решения',file_error:'Ошибка файла — ожидает решения',done:'Завершено',error:'Остановлено с ошибкой',cancelled:'Остановлено',rolling_back:'Идёт откат',undone:'Отменено, данные восстановлены'};
 const text=(node,value)=>{node.textContent=String(value??'');};
 function message(value,kind='info'){el('message').replaceChildren();if(value){const n=document.createElement('div');n.className='alert alert-'+kind;n.setAttribute('role','alert');n.textContent=value;el('message').append(n);}}
 async function api(action,data={}){const body=new URLSearchParams({[cfg.token]:'1',payload:JSON.stringify({action,...data})});const r=await fetch(cfg.url,{method:'POST',body,credentials:'same-origin'});let j;try{j=await r.json();}catch{throw new Error('Сессия истекла или сервер вернул неполный ответ. Обновите страницу; операция доступна в журнале.');}if(!r.ok||!j.success)throw new Error(j.message||'Не удалось выполнить запрос.');return j.data;}
 function button(label,handler,cls='btn btn-sm btn-outline-primary'){const b=document.createElement('button');b.type='button';b.className=cls;b.textContent=label;b.addEventListener('click',handler);return b;}
 function cell(tr,value){const td=document.createElement('td');td.textContent=String(value??'');tr.append(td);return td;}
 function mode(){return root.querySelector('[name="tzmt-mode"]:checked').value;}
 function selected(){return [...el('materials').querySelectorAll('input:checked')].filter(x=>x.dataset.invalid!=='1').map(x=>Number(x.value));}
 function lock(value){busy=value;for(const id of ['source','target','scan','all','activate','select-all','select-none'])el(id).disabled=value;root.querySelectorAll('[name="tzmt-mode"],#tzmt-materials input').forEach(n=>n.disabled=value||n.dataset.invalid==='1');selection();}
 function selection(){const checks=[...el('materials').querySelectorAll('input[data-invalid="0"]')];const checked=checks.filter(n=>n.checked).length;el('all').checked=checks.length>0&&checked===checks.length;el('all').indeterminate=checked>0&&checked<checks.length;el('all').disabled=busy||!checks.length;el('select-all').disabled=busy||!checks.length||checked===checks.length;el('select-none').disabled=busy||!checked;const ids=selected(),source=inventory.find(a=>a.key===el('source').value);let count=0;for(const a of source?.articles||[])if(ids.includes(a.id))count+=a.images;text(el('selected'),scanned?`Выбрано материалов: ${ids.length}; изображений: ${count}`:'');el('start').disabled=busy||!ids.length||el('source').value===el('target').value||!el('target').value;text(el('start'),mode()==='copy'?'Начать копирование':'Начать перемещение');}
 function summary(a){return scanned&&a?`Найдено ${a.images} изображений, ${a.captions} подписей, материалов с изображениями: ${a.articles.filter(n=>n.images>0).length}.`:'';}
 function renderMaterials(){
  const source=inventory.find(a=>a.key===el('source').value),target=inventory.find(a=>a.key===el('target').value);
  text(el('source-summary'),summary(source));text(el('target-summary'),summary(target));el('materials').replaceChildren();
  const columns=['flipbook_gallery','gallery','grid_gallery'].map(key=>inventory.find(a=>a.key===key)||{key,label:({flipbook_gallery:'Флипбук',gallery:'Галерея',grid_gallery:'Грид галерея'})[key]});
  text(el('first-heading'),columns[0]?.label.split(' — ')[0]||'Флипбук');text(el('second-heading'),columns[1]?.label.split(' — ')[0]||'Галерея');text(el('third-heading'),'Грид галерея');
  const indexes=new Map(inventory.map(a=>[a.key,new Map(a.articles.map(n=>[n.id,n]))]));
  for(const item of materials){
   const from=indexes.get(source?.key)?.get(item.id),to=indexes.get(target?.key)?.get(item.id);
   const tr=document.createElement('tr'),td=document.createElement('td'),check=document.createElement('input');
   check.type='checkbox';check.value=item.id;check.setAttribute('aria-label',item.title);
   check.dataset.invalid=!scanned||!from?.images||from?.error||to?.error?'1':'0';check.disabled=busy||check.dataset.invalid==='1';check.checked=scanned&&check.dataset.invalid==='0';
   if(from?.error||to?.error)check.title=from?.error||to?.error;
   check.addEventListener('change',selection);td.append(check);tr.append(td);cell(tr,`${item.title} (ID ${item.id})`);
   for(let i=0;i<columns.length;i++){const counts=indexes.get(columns[i]?.key)?.get(item.id);cell(tr,scanned?(counts?.images||0):'—');cell(tr,scanned?(counts?.captions||0):'—');}
   el('materials').append(tr);
  }
  if(!materials.length){const tr=document.createElement('tr');cell(tr,'Нет доступных материалов.').colSpan=8;el('materials').append(tr);}selection();
 }
 function applyInventory(r){
  inventory=r.adapters;materials=r.materials||[];scanned=!!r.scanned;
  text(el('trash'),scanned?`Материалов в корзине исключено: ${r.trash_skipped}`:'');
  const oldSource=el('source').value,oldTarget=el('target').value;
  for(const id of ['source','target']){el(id).replaceChildren();for(const a of inventory){if(id==='target'&&!a.writable)continue;const o=document.createElement('option');o.value=a.key;o.textContent=a.label;el(id).append(o);}}
  el('source').value=inventory.some(a=>a.key===oldSource)?oldSource:(inventory.find(a=>a.key==='flipbook_gallery')?.key||inventory[0]?.key||'');
  el('target').value=inventory.some(a=>a.key===oldTarget&&a.writable)&&oldTarget!==el('source').value?oldTarget:inventory.find(a=>a.writable&&a.key!==el('source').value)?.key||'';
  renderMaterials();
 }
 async function loadCatalog(){lock(true);try{applyInventory(await api('catalog'));message('Нажмите «Найти изображения», чтобы проверить файлы и подписи.');}catch(e){message(e.message,'danger');}finally{lock(false);}}
 async function scan(){
  if(busy)return;lock(true);scanned=false;renderMaterials();text(el('trash'),'');message('Идёт поиск изображений…');
  try{applyInventory(await api('inventory'));message(inventory.length>=2&&inventory.some(a=>a.writable)?'Поиск завершён. Показаны существующие изображения и их подписи. Выберите материалы и действие.':'Для переноса нужны хотя бы два установленных поддерживаемых дополнения Portfolio.','info');}
  catch(e){message(e.message,'danger');}finally{lock(false);}
 }
 function showJob(){if(!job)return;el('progress-panel').hidden=false;const undo=['rolling_back','undone'].includes(job.status),processed=undo?job.undo_processed:job.processed,total=undo?job.undo_total:job.total;text(el('progress-text'),`${statuses[job.status]||job.status}: ${processed} из ${total}`);el('progress').max=total||1;el('progress').value=processed;text(el('stats'),`Копировано: ${job.stats.copied}; перемещено: ${job.stats.moved}; пропущено: ${job.stats.skipped}; заменено: ${job.stats.replaced}; переименовано: ${job.stats.renamed}; копий для второй подписи: ${job.stats.caption_copies}`);text(el('warning'),job.error||job.warnings.join('\n'));el('resume').hidden=!['running','conflict','file_error','rolling_back'].includes(job.status)||pumping;el('cancel').hidden=!['running','conflict','file_error'].includes(job.status);el('undo').hidden=['rolling_back','undone'].includes(job.status)||pumping;el('results').replaceChildren();for(const item of job.results){const tr=document.createElement('tr');cell(tr,`${item.title} (${item.article})`);cell(tr,item.name);cell(tr,item.result);el('results').append(tr);}showSkipped(); }
 function showSkipped(){
  const skipped=job?.skipped_files||[],count=job?.stats.skipped||0;el('skipped-panel').hidden=!count;
  text(el('skipped-total'),`Всего пропущено файлов: ${count}.`);el('skipped-files').replaceChildren();
  for(const item of skipped){const tr=document.createElement('tr');cell(tr,item.path);cell(tr,item.reason);el('skipped-files').append(tr);}
  if(count&&!skipped.length){const tr=document.createElement('tr');cell(tr,'В этой старой операции пути пропусков не сохранялись.').colSpan=2;el('skipped-files').append(tr);}
 }
 function showFileError(){
  const error=job?.file_error;if(!error)return;
  text(el('file-error-path'),'Файл: '+error.path);text(el('file-error-message'),error.message);
  if(!el('file-error').open)el('file-error').showModal();
 }
 function fileDecisionLock(value){for(const id of ['file-error-cancel','file-error-skip','file-error-all'])el(id).disabled=value;}
 async function skipFile(all){
  if(!job?.file_error||cancelling)return;fileDecisionLock(true);lock(true);
  try{job=await api('skip',{job:job.id,file_error:job.file_error.id,all});el('file-error').close();await pump();}
  catch(e){text(el('file-error-message'),e.message);message(e.message,'danger');}
  finally{fileDecisionLock(false);lock(false);}
 }
 function bytes(n){return n>1048576?(n/1048576).toFixed(1)+' МБ':Math.ceil(n/1024)+' КБ';}
 function showConflict(){const c=job.conflict;if(!c)return;text(el('conflict-title'),c.kind==='image'?'Изображение уже существует':'У изображения уже есть другая подпись');text(el('conflict-description'),`Материал: ${c.title} (ID ${c.article}). `+(c.kind==='image'?(c.same_content?'Содержимое файлов одинаковое.':'Совпало имя; содержимое отличается или файл назначения отсутствует.'):'Можно сохранить обе подписи, создав отдельную копию фотографии.'));
  for(const key of ['source','target']){const data=c[key],img=el(key+'-image');if(data.url){img.src=data.url;img.hidden=false;}else{img.removeAttribute('src');img.hidden=true;}text(el(key+'-file'),`${data.name} · ${bytes(data.bytes)}${data.modified?' · '+data.modified:''}`);text(el(key+'-caption'),data.caption||'Подписи нет');}
  el('choices').replaceChildren();const legend=document.createElement('legend');legend.textContent='Как поступить';el('choices').append(legend);
  const options=c.kind==='image'?[['replace','Заменить изображение назначения'],['keep',c.same_content?'Использовать существующее изображение; подпись проверить отдельно':'Оставить существующее, пропустить исходное изображение'],['rename','Переименовать исходную копию и сохранить оба изображения']]:[['replace','Заменить подпись текстом источника'],['keep','Оставить подпись назначения'],['duplicate','Создать отдельную копию фотографии со второй подписью']];
  for(const [value,label] of options){const n=document.createElement('label'),r=document.createElement('input');r.type='radio';r.name='tzmt-choice';r.value=value;r.required=true;n.append(r,document.createTextNode(label));el('choices').append(n);}el('apply-all').checked=false;
  if(!el('conflict').open)el('conflict').showModal();
 }
 async function pump(){if(!job||pumping)return;pumping=true;lock(true);showJob();try{while(['running','rolling_back'].includes(job.status)){job=await api(job.status==='rolling_back'?'undoStep':'step',{job:job.id});showJob();await new Promise(r=>setTimeout(r,40));}if(job.status==='conflict')showConflict();else if(job.status==='file_error')showFileError();else if(job.status==='error')message(job.error,'danger');else if(job.status==='done')message('Операция завершена. Результаты и откат доступны в журнале.','success');else if(job.status==='undone')message('Откат завершён. Исходные записи и файлы восстановлены.','success');}
 catch(e){message(e.message+' Можно продолжить операцию из журнала.','danger');}finally{pumping=false;lock(false);showJob();await history();}}
 async function history(){try{const data=await api('history');el('history').replaceChildren();for(const j of data.jobs){const tr=document.createElement('tr');tr.dataset.job=j.id;cell(tr,j.created+' UTC');const label=k=>inventory.find(a=>a.key===k)?.label||k;cell(tr,`${label(j.source)} → ${label(j.target)} (${j.mode==='move'?'перемещение':'копирование'})`);cell(tr,statuses[j.status]||j.status);cell(tr,`${j.processed} / ${j.total}`);const td=document.createElement('td');td.append(button('Открыть',async()=>{if(pumping)return;try{job=await api('status',{job:j.id});showJob();if(job.status==='conflict')showConflict();else if(job.status==='file_error')showFileError();}catch(e){message(e.message,'danger');}}));if(!['running','conflict','file_error','rolling_back'].includes(j.status))td.append(button('Удалить журнал',async()=>{if(!confirm('Удалить журнал и резервные копии? Откат этой операции станет недоступен. Изображения в дополнениях останутся.'))return;try{await api('purge',{job:j.id});if(job?.id===j.id){job=null;el('progress-panel').hidden=true;}await history();}catch(e){message(e.message,'danger');}},'btn btn-sm btn-outline-danger'));tr.append(td);el('history').append(tr);}if(!data.jobs.length){const tr=document.createElement('tr');cell(tr,'Операций пока нет.').colSpan=5;el('history').append(tr);}}catch(e){message(e.message,'danger');}}
 el('scan').addEventListener('click',scan);el('source').addEventListener('change',()=>{if(!el('target').value||el('source').value===el('target').value)el('target').value=inventory.find(a=>a.writable&&a.key!==el('source').value)?.key||'';renderMaterials();});el('target').addEventListener('change',renderMaterials);function selectAll(checked){if(busy)return;el('materials').querySelectorAll('input:not(:disabled)').forEach(n=>n.checked=checked);selection();}el('all').addEventListener('change',()=>selectAll(el('all').checked));el('select-all').addEventListener('click',()=>selectAll(true));el('select-none').addEventListener('click',()=>selectAll(false));
 root.querySelectorAll('[name="tzmt-mode"]').forEach(n=>n.addEventListener('change',()=>{el('activate').checked=mode()==='move';text(el('mode-help'),mode()==='copy'?'Изображения и подписи останутся в источнике.':'После проверки копии исходные записи и файлы будут удалены из источника. Пропущенные изображения останутся. Резервные копии сохранятся для отката.');selection();}));
 el('start').addEventListener('click',async()=>{const payload={source:el('source').value,target:el('target').value,mode:mode(),activate:el('activate').checked,articles:selected()};lock(true);message('Подготовка операции…');try{job=await api('start',payload);await pump();}catch(e){message(e.message,'danger');}finally{lock(false);}});
 el('conflict-form').addEventListener('submit',async e=>{e.preventDefault();const choice=el('choices').querySelector('input:checked');if(!choice)return;el('apply').disabled=true;el('conflict-cancel').disabled=true;try{job=await api('resolve',{job:job.id,conflict:job.conflict.id,choice:choice.value,all:el('apply-all').checked});el('conflict').close();await pump();}catch(e){message(e.message,'danger');}finally{el('apply').disabled=false;el('conflict-cancel').disabled=false;}});
 el('later').addEventListener('click',()=>{el('conflict').close();showJob();});el('conflict').addEventListener('cancel',()=>showJob());
 el('resume').addEventListener('click',async()=>{try{job=await api('status',{job:job.id});if(job.status==='conflict')showConflict();else if(job.status==='file_error')showFileError();else await pump();}catch(e){message(e.message,'danger');}});
 async function cancelJob(){if(!job||cancelling)return;cancelling=true;el('cancel').disabled=true;el('conflict-cancel').disabled=true;el('apply').disabled=true;fileDecisionLock(true);try{job=await api('cancel',{job:job.id});if(el('conflict').open)el('conflict').close();if(el('file-error').open)el('file-error').close();showJob();message('Операция остановлена. Уже выполненные изменения можно откатить кнопкой «Откатить операцию».');await history();}catch(e){message(e.message,'danger');if(el('conflict').open)text(el('conflict-description'),'Не удалось остановить операцию: '+e.message);if(el('file-error').open)text(el('file-error-message'),'Не удалось остановить операцию: '+e.message);}finally{cancelling=false;el('cancel').disabled=false;el('conflict-cancel').disabled=false;el('apply').disabled=false;fileDecisionLock(false);}}
 el('file-error-cancel').addEventListener('click',cancelJob);el('file-error-skip').addEventListener('click',()=>skipFile(false));el('file-error-all').addEventListener('click',()=>skipFile(true));el('file-error').addEventListener('cancel',e=>{e.preventDefault();cancelJob();});
 el('cancel').addEventListener('click',cancelJob);el('conflict-cancel').addEventListener('click',cancelJob);
 el('undo').addEventListener('click',async()=>{if(!confirm('Откатить выполненные изменения этой операции? Последующие правки не будут перезаписаны.'))return;try{job=await api('undo',{job:job.id});await pump();}catch(e){message(e.message,'danger');}});
 el('refresh-history').addEventListener('click',history);loadCatalog().then(history);
})();
