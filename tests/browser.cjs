const { chromium } = require(process.env.CRM_PLAYWRIGHT_MODULE);
const fs = require('node:fs');
let debugPage;
(async () => {
  const browser = await chromium.launch({headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  const page = await context.newPage(); debugPage = page; const errors = [];
  fs.mkdirSync(process.env.CRM_SCREENSHOTS, {recursive: true});
  page.on('pageerror', error => errors.push(error.message));
  const base = 'http://127.0.0.1:8899';
  await page.goto(base + '/wp-login.php');
  await page.locator('#user_login').fill('admin'); await page.locator('#user_pass').fill('integration-only');
  await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
  const registry = await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-privacy');
  if (registry.status() !== 200) throw new Error('Administrator cannot open privacy register.');
  await page.getByRole('heading', {name: 'Обработка персональных данных'}).waitFor();
  await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-submissions');
  const views = page.locator('.bfcamel-crm-quick-views');
  for (const label of ['Требуют внимания', 'Мои заявки', 'Все заявки']) {
    await views.getByRole('link', {name: label, exact: true}).waitFor();
  }
  for (const width of [1440, 390]) {
    await page.setViewportSize({width, height: 1000});
    const boxes = await views.locator('a').evaluateAll(links => links.map(link => link.getBoundingClientRect().toJSON()));
    for (let i = 1; i < boxes.length; i++) {
      if (boxes[i].left - boxes[i-1].right < 7 && boxes[i].top - boxes[i-1].bottom < 7) throw new Error('Inbox buttons touch.');
    }
  }
  await page.setViewportSize({width: 1440, height: 1000});
  for (const [area, field] of [['contacts', 'created'], ['submissions', 'status']]) {
    await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-' + area + '&view=all');
    const url = await page.locator('a[href*="bfcamel_crm_export"][href*="format=csv"]').first().getAttribute('href');
    await page.goto(url);
    await page.locator('input[name="columns[]"][value="id"]').uncheck();
    await page.locator('input[name="columns[]"][value="' + field + '"]').check();
    const [download] = await Promise.all([page.waitForEvent('download'), page.locator('button[name="download"]').click()]);
    const data = fs.readFileSync(await download.path(), 'utf8').replace(/^\uFEFF/, '').trim().split(/\r?\n/);
    if (data.length < 2 || data.some(line => line.includes(','))) throw new Error('CSV field projection failed: ' + area);
  }
  await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-submissions');
  await page.locator('[data-bfcamel-inbox]').waitFor();
  if (await page.locator('.bfcamel-crm-extra-filters[open]').count()) throw new Error('Secondary filters must be collapsed initially.');
  if (await page.locator('.bfcamel-crm-bulk-bar:visible').count()) throw new Error('Bulk actions visible without selection.');
  fs.mkdirSync(process.env.CRM_SCREENSHOTS, {recursive: true});
  await page.screenshot({path: process.env.CRM_SCREENSHOTS + '/inbox-desktop.png', fullPage: true});
  const row = page.locator('[data-bfcamel-row-id="' + process.env.CRM_UI_ID + '"]');
  await row.locator('input[type="checkbox"]').check();
  await page.locator('.bfcamel-crm-bulk-bar:visible').waitFor();
  await row.locator('input[type="checkbox"]').uncheck();
  await row.locator('a[data-bfcamel-open]').first().click();
  await page.locator('[data-bfcamel-submission-id]').waitFor();
  await page.locator('form[data-bfcamel-claim] button').click();
  await page.locator('form[data-bfcamel-complete]').waitFor();
  await page.screenshot({path: process.env.CRM_SCREENSHOTS + '/submission-preview.png', fullPage: true});
  await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-submissions&view=all');
  await page.locator('#wp-admin-bar-bfcamel-crm-notifications a').click();
  await page.locator('#bfcamel-notification-center:visible').waitFor();
  await page.locator('[data-bfcamel-notification-close]').click();
  const nonce = await page.evaluate(() => window.BfCamelCRMInbox.nonce);
  const denied = await context.request.post(base + '/wp-admin/admin-ajax.php', {form: {action: 'bfcamel_crm_notification_feed', nonce: 'invalid'}});
  if (denied.status() !== 403) throw new Error('Invalid nonce accepted.');
  const valid = await context.request.post(base + '/wp-admin/admin-ajax.php', {form: {action: 'bfcamel_crm_notification_feed', nonce}});
  if (!valid.ok() || !(await valid.json()).success) throw new Error('Authorized feed failed.');
  const anonymous = await browser.newContext();
  const anonymousFeed = await anonymous.request.post(base + '/wp-admin/admin-ajax.php', {form: {action: 'bfcamel_crm_notification_feed', nonce}});
  if (anonymousFeed.status() < 400) throw new Error('Anonymous notification access accepted.');
  await anonymous.close();
  await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-submissions&view=all&s=victim%40example.test');
  await page.locator('.bfcamel-crm-request-person a').first().click();
  await page.waitForURL('**/*page=bfcamel-crm-contacts*');
  await page.locator('.bfcamel-crm-page-title .bfcamel-crm-actions > a').first().click();
  await page.waitForURL('**/*view=all*');
  if (new URL(page.url()).searchParams.get('s') !== 'victim@example.test') throw new Error('Contact return changed the search context.');
  await page.setViewportSize({width: 390, height: 844});
  await page.goto(base + '/wp-admin/admin.php?page=bfcamel-crm-submissions&view=all');
  const mobileLink = page.locator('a[data-bfcamel-open]').first(); await mobileLink.click();
  await page.waitForURL('**/*&id=*');
  if (await page.locator('.bfcamel-crm-preview:visible').count()) throw new Error('Mobile should open a full card.');
  await page.screenshot({path: process.env.CRM_SCREENSHOTS + '/submission-mobile.png', fullPage: true});
  if (await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2)) throw new Error('Mobile page overflows viewport.');
  if (errors.length) throw new Error(errors.join('\n'));
  await browser.close(); console.log('Browser UI, preview, ownership, mobile navigation and AJAX permission tests passed.');
})().catch(async error => { console.error(error); if (debugPage) { console.error('URL:', debugPage.url()); console.error((await debugPage.locator('body').innerText()).slice(-6000)); await debugPage.screenshot({path: process.env.CRM_SCREENSHOTS + '/failure.png', fullPage: true}); } process.exit(1); });
