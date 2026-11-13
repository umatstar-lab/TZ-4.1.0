<?php defined('_JEXEC') or die; ?>
<div id="tzmt" class="tzmt">
 <p class="tzmt-intro">Копирование и перемещение фотографий вместе с подписями между дополнениями TZ Portfolio 4.1+. Обрабатываются выбранные материалы; корзина исключена.</p>
 <div id="tzmt-message" role="status" aria-live="polite"></div>
 <section class="card mb-3"><div class="card-body">
  <h2>1. Выберите дополнения</h2>
  <div class="tzmt-columns">
   <div><label for="tzmt-source">Откуда</label><select id="tzmt-source" class="form-select" disabled></select><p id="tzmt-source-summary" class="tzmt-note"></p></div>
   <div><label for="tzmt-target">Куда</label><select id="tzmt-target" class="form-select" disabled></select><p id="tzmt-target-summary" class="tzmt-note"></p></div>
  </div>
  <div class="tzmt-actions"><button id="tzmt-scan" class="btn btn-outline-primary" type="button">Найти изображения</button><span id="tzmt-trash" class="tzmt-note"></span></div>
 </div></section>
 <section class="card mb-3"><div class="card-body">
  <h2>2. Материалы и действие</h2>
  <div class="tzmt-actions" role="group" aria-label="Выбор материалов"><button id="tzmt-select-all" class="btn btn-outline-secondary" type="button" disabled>Выбрать всё</button><button id="tzmt-select-none" class="btn btn-outline-secondary" type="button" disabled>Снять всё</button></div>
  <div class="tzmt-table"><table class="table"><thead><tr><th><input id="tzmt-all" type="checkbox" aria-label="Выбрать все материалы"></th><th>Материал</th><th>Изображения</th><th>Подписи</th><th>Проверка</th></tr></thead><tbody id="tzmt-materials"><tr><td colspan="5">Идёт поиск…</td></tr></tbody></table></div>
  <fieldset class="tzmt-mode"><legend>Что сделать</legend><label><input type="radio" name="tzmt-mode" value="copy" checked> Копировать</label><label><input type="radio" name="tzmt-mode" value="move"> Переместить</label></fieldset>
  <p id="tzmt-mode-help" class="tzmt-note">Изображения и подписи останутся в источнике.</p>
  <label class="tzmt-check"><input id="tzmt-activate" type="checkbox"> Переключить основной вывод материала на дополнение назначения</label>
  <p class="tzmt-note">Это изменит тип медиа и параметр показа назначения. Без флажка настройки останутся как есть; копии могут отображаться согласно уже настроенному макету. Другие блоки макета не отключаются.</p>
  <div class="tzmt-actions"><button id="tzmt-start" class="btn btn-success" type="button" disabled>Начать копирование</button><span id="tzmt-selected" class="tzmt-note"></span></div>
 </div></section>
 <section id="tzmt-progress-panel" class="card mb-3" hidden><div class="card-body">
  <h2>Ход операции</h2><p id="tzmt-progress-text" aria-live="polite"></p>
  <progress id="tzmt-progress" max="100" value="0" aria-label="Ход обработки"></progress>
  <p id="tzmt-stats"></p><p id="tzmt-warning" class="text-warning"></p>
  <div class="tzmt-actions"><button id="tzmt-resume" class="btn btn-primary" type="button" hidden>Продолжить</button><button id="tzmt-cancel" class="btn btn-outline-danger" type="button">Остановить</button><button id="tzmt-undo" class="btn btn-outline-secondary" type="button" hidden>Откатить операцию</button></div>
  <details><summary>Обработанные файлы</summary><div class="tzmt-table"><table class="table"><thead><tr><th>Материал</th><th>Файл</th><th>Результат</th></tr></thead><tbody id="tzmt-results"></tbody></table></div></details>
 </div></section>
 <section class="card"><div class="card-body"><div class="tzmt-actions"><h2>Журнал операций</h2><button id="tzmt-refresh-history" class="btn btn-sm btn-outline-secondary" type="button">Обновить журнал</button></div>
  <p class="tzmt-note">Решение «Применить ко всем» действует только в одной операции и отдельно для файлов и подписей. Пропущенные файлы остаются в источнике. После закрытия страницы незавершённую операцию можно продолжить из журнала. Резервные копии для отката занимают место до удаления журнала.</p>
  <div class="tzmt-table"><table class="table"><thead><tr><th>Дата</th><th>Направление</th><th>Состояние</th><th>Обработано</th><th>Действия</th></tr></thead><tbody id="tzmt-history"></tbody></table></div>
 </div></section>
 <dialog id="tzmt-conflict" aria-labelledby="tzmt-conflict-title">
  <form id="tzmt-conflict-form">
   <h2 id="tzmt-conflict-title"></h2><p id="tzmt-conflict-description"></p>
   <div class="tzmt-columns">
    <section><h3>В источнике</h3><img id="tzmt-source-image" alt="Изображение источника"><p id="tzmt-source-file"></p><div id="tzmt-source-caption" class="tzmt-caption"></div></section>
    <section><h3>В назначении</h3><img id="tzmt-target-image" alt="Изображение назначения"><p id="tzmt-target-file"></p><div id="tzmt-target-caption" class="tzmt-caption"></div></section>
   </div>
   <fieldset id="tzmt-choices"><legend>Как поступить</legend></fieldset>
   <label class="tzmt-check"><input id="tzmt-apply-all" type="checkbox"> Применить ко всем следующим совпадениям этого типа в этой операции</label>
   <p class="tzmt-note">Без галочки решение применяется только к текущему изображению или подписи.</p>
   <div class="tzmt-actions"><button id="tzmt-apply" class="btn btn-primary" type="submit">Применить</button><button id="tzmt-conflict-cancel" class="btn btn-outline-danger" type="button">Отменить</button><button id="tzmt-later" class="btn btn-outline-secondary" type="button">Решить позже</button></div>
  </form>
 </dialog>
</div>
