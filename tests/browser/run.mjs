/**
 * Browser tests for consent.js.
 *
 * Three things the README promises live only in the browser, so the PHP suite
 * cannot reach them: the localStorage mirror, the Global Privacy Control
 * signal, and parked scripts. This runs the shipped file against a fixture page
 * — no Statamic, no PHP — which is cheap enough to run on every push.
 */
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const PAGE = 'file://' + resolve(here, 'fixture.html');
const EXECUTABLE = process.env.CHROME_PATH || '/usr/bin/google-chrome';

let failures = 0;

function check(name, condition, detail = '') {
  if (condition) {
    console.log(`  ok    ${name}`);
  } else {
    failures++;
    console.log(`  FAIL  ${name}${detail ? ' — ' + detail : ''}`);
  }
}

const decision = (granted) => JSON.stringify({
  v: 1,
  granted,
  ts: 1755000000000,
  how: 'test',
  id: 'fixture',
});

const browser = await chromium.launch({
  executablePath: EXECUTABLE,
  args: ['--no-sandbox', '--disable-gpu'],
});

async function fresh(init) {
  const context = await browser.newContext();
  const page = await context.newPage();
  if (init) await page.addInitScript(init);
  return { context, page };
}

/* --------------------------------------------------------------------------
   1. The localStorage mirror.

   The README justifies it with "pages served from a cache that strips cookies".
   Written and read it was; the cookie-less path was never exercised.
-------------------------------------------------------------------------- */
{
  console.log('localStorage mirror');
  const { context, page } = await fresh();
  await page.goto(PAGE);
  await page.evaluate(v => localStorage.setItem('statamic_consent', v), decision(['youtube']));
  // No cookie is set — only the mirror carries the decision.
  await page.reload();
  await page.waitForTimeout(300);

  const cookies = (await context.cookies()).filter(c => c.name === 'statamic_consent');
  check('no cookie is present', cookies.length === 0);
  check('the gate is unlocked from the mirror alone',
    await page.$eval('[data-consent-gate]', el => el.hasAttribute('data-consent-unlocked')));
  check('the banner stays hidden',
    await page.$eval('[data-consent-banner]', el => el.hasAttribute('hidden')));
  await context.close();
}

/* --------------------------------------------------------------------------
   2. Global Privacy Control.

   A visitor sending the signal has already objected. Asking again would be
   asking them to repeat themselves, so it is recorded as a rejection.
-------------------------------------------------------------------------- */
{
  console.log('Global Privacy Control');
  const { context, page } = await fresh(() => {
    Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true });
  });
  await page.goto(PAGE);
  await page.waitForTimeout(300);

  const stored = await page.evaluate(() => localStorage.getItem('statamic_consent'));
  const parsed = JSON.parse(stored || '{}');

  check('a decision is recorded without asking', parsed.how === 'gpc', `how=${parsed.how}`);
  check('nothing optional is granted', Array.isArray(parsed.granted) && !parsed.granted.includes('youtube'));
  check('the required service survives', (parsed.granted || []).includes('session'));
  check('the banner is not shown',
    await page.$eval('[data-consent-banner]', el => el.hasAttribute('hidden')));
  check('the gate stays blocked',
    !(await page.$eval('[data-consent-gate]', el => el.hasAttribute('data-consent-unlocked'))));
  await context.close();
}

{
  console.log('Global Privacy Control, switched off');
  const { context, page } = await fresh(() => {
    Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true });

    // Patched while the document is still parsing. consent.js is a plain script
    // at the end of <body>, so it runs before DOMContentLoaded — a listener on
    // that event would arrive after the config had already been read.
    const observer = new MutationObserver(() => {
      const el = document.getElementById('statamic-consent-config');
      if (!el) return;
      observer.disconnect();
      const cfg = JSON.parse(el.textContent);
      cfg.respectGpc = false;
      el.textContent = JSON.stringify(cfg);
    });
    observer.observe(document.documentElement || document, { childList: true, subtree: true });
  });
  await page.goto(PAGE);
  await page.waitForTimeout(300);

  // The config decides. With respectGpc off the visitor is asked as usual,
  // which is what makes the option meaningful rather than decorative.
  check('the banner is shown instead',
    !(await page.$eval('[data-consent-banner]', el => el.hasAttribute('hidden'))));
  await context.close();
}

/* --------------------------------------------------------------------------
   3. Parked scripts.

   Setting .type on the existing node does nothing — the browser decided how to
   treat it at parse time — so the script is re-created. Verified by the side
   effect, not by reading the DOM.
-------------------------------------------------------------------------- */
{
  console.log('parked scripts');
  const { context, page } = await fresh();
  await page.goto(PAGE);
  await page.waitForTimeout(300);

  check('the parked script has not run', await page.evaluate(() => window.__pixelRan !== true));
  check('it is still inert in the document',
    await page.$eval('script[data-consent-service="analytics_pixel"]', el => el.type === 'text/plain'));

  await page.click('[data-consent-accept-all]');
  await page.waitForTimeout(300);

  check('it runs once consent is given', await page.evaluate(() => window.__pixelRan === true));
  check('the gate unlocked in the same pass',
    await page.$eval('[data-consent-gate]', el => el.hasAttribute('data-consent-unlocked')));
  await context.close();
}

/* --------------------------------------------------------------------------
   4. A rejection is a rejection.
-------------------------------------------------------------------------- */
{
  console.log('rejection');
  const { context, page } = await fresh();
  await page.goto(PAGE);
  await page.waitForTimeout(300);
  await page.click('[data-consent-necessary]');
  await page.waitForTimeout(300);

  check('the parked script stays inert', await page.evaluate(() => window.__pixelRan !== true));
  check('the gate stays blocked',
    !(await page.$eval('[data-consent-gate]', el => el.hasAttribute('data-consent-unlocked'))));
  check('no iframe reached the document', (await page.$$('iframe')).length === 0);
  await context.close();
}

await browser.close();

console.log('');
if (failures) {
  console.log(`${failures} failing`);
  process.exit(1);
}
console.log('all browser checks passed');
