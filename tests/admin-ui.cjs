// Run against the local prepared Joomla site: node tests/admin-ui.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('/opt/codex/cua_node/lib/node_modules/playwright-core');
const base = 'http://127.0.0.1:8080/administrator/';
(async () => {
  const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(15000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    // The unrelated welcome tour otherwise redirects each first visit to the dashboard.
    await page.route('**/media/plg_system_guidedtours/js/**', route => route.abort());
    const vars = Object.fromEntries(fs.readFileSync('/workspace/tz-dev/.env', 'utf8').trim().split('\n').map(line => line.split('=')));
    await page.goto(base);
    await page.locator('#mod-login-username').fill('developer');
    await page.locator('#mod-login-password').fill(vars.TZ_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL('**/administrator/index.php'), page.locator('button[type=submit]').click()]);
    const title = `TZ UI regression ${Date.now()}`;
    await page.goto(base + 'index.php?option=com_tz_portfolio&task=article.add');
    await page.locator('#jform_title').fill(title);
    await page.locator('#jform_type').selectOption('none');
    await page.waitForFunction(() => window.tinymce?.activeEditor?.initialized);
    await page.evaluate(() => { tinymce.activeEditor.setContent('Regression test content'); tinymce.triggerSave(); });
    await page.locator('#toolbar-dropdown-save-group > button').click();
    assert(await page.locator('#toolbar-dropdown-save-group .dropdown-menu').isVisible());
    assert(await page.locator('[task="article.save2new"]').count());
    await Promise.all([page.waitForURL('**view=articles'), page.locator('#save-group-children-save button').click()]);
    console.log('PASS save dropdown and Save & Close');
    let row = page.locator('tbody tr').filter({ hasText: title });
    await row.locator('input[name="cid[]"]').check();
    await page.locator('#toolbar-status-group > button').click();
    assert(await page.locator('[task="articles.unpublish"] button').isVisible());
    await Promise.all([page.waitForNavigation(), page.locator('[task="articles.unpublish"] button').click()]);
    assert.match(await page.locator('#system-message-container').first().innerText(), /unpublished/i);
    console.log('PASS list Actions dropdown and Unpublish');
    await page.goto(base + 'index.php?option=com_menus&view=item&layout=edit&menutype=mainmenu');
    await page.locator('[data-button-action="select"]').first().click();
    const types = page.frameLocator('joomla-dialog iframe');
    await types.getByRole('button', { name: 'TZ Portfolio', exact: true }).click();
    await types.locator('[data-type="COM_TZ_PORTFOLIO_ARTICLE_VIEW_DEFAULT_TITLE"]').click();
    await page.locator('#jform_request_id_select').waitFor();
    await page.waitForFunction(() => typeof window.tppSelectArticle_jform_request_id === 'function');
    await page.locator('#jform_request_id_select').click();
    const picker = page.frameLocator('#tppModalArticle_jform_request_id iframe');
    await picker.locator('input[name="filter[search]"]').fill(title);
    const pickerFrame = page.frames().find(frame => frame.url().includes('layout=modal'));
    await Promise.all([pickerFrame.waitForNavigation(), picker.locator('input[name="filter[search]"]').press('Enter')]);
    await picker.locator('a.pointer').filter({ hasText: title }).click();
    assert(Number(await page.locator('#jform_request_id').inputValue()) > 0);
    assert.equal(await page.locator('#jform_request_id_name').inputValue(), title);
    await page.locator('#tppModalArticle_jform_request_id').waitFor({ state: 'hidden' });
    await page.locator('#jform_request_id_clear').click();
    assert.equal(await page.locator('#jform_request_id').inputValue(), '');
    console.log('PASS picker search, selection, modal close and clear');
    assert.deepEqual(errors, []);
    // Remove the fixture through the component's normal Trash action.
    await page.goto(base + 'index.php?option=com_tz_portfolio&view=articles');
    row = page.locator('tbody tr').filter({ hasText: title });
    await row.locator('input[name="cid[]"]').check();
    await page.locator('#toolbar-status-group > button').click();
    await Promise.all([page.waitForNavigation(), page.locator('[task="articles.trash"] button').click()]);
    console.log('PASS no JavaScript errors; test article moved to Trash');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
