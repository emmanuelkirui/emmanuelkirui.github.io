// Mirrors the live PHP site to static GitHub Pages files.
//  - pages  : port_kirui.php -> index.html (+ any other top-level .php pages it links to)
//  - assets : every file the pages use from the live site (uploads/, images, css, js) is
//             DOWNLOADED into the repo and links point to the local copy => identical to the main domain
//  - ads    : AdSense loads only after the visitor taps "Accept"
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL;                       // https://creativepulse.42web.io/cps/
const PUB = process.env.ADSENSE_PUB;                         // pub-XXXXXXXXXXXXXXXX
const START = (process.env.PAGES || 'port_kirui.php').split(',').map(s => s.trim()).filter(Boolean);
const MAX_PAGES = 15;
const MAX_FILE = 95 * 1024 * 1024;                           // GitHub rejects files > 100 MB
const SKIP_PAGE = /^(admin|login|logout|install|setup)\w*\.php$/i;
const DENY_ASSET = /(^|\/)(data\.json|mirror_config\.php|\.[^/]*)$|\.php$/i;

const htmlName = n => (n === 'port_kirui.php' ? 'index.html' : n.replace(/\.php$/i, '.html'));
const sleep = ms => new Promise(r => setTimeout(r, ms));
const usedAssets = new Set();                                // local paths kept this run

const CONSENT_LOADER = `
(function(){
  var KEY='ek_cookie_consent', loaded=false;
  function accepted(){
    try{var d=JSON.parse(localStorage.getItem(KEY));
      return !!d && d.choice==='accepted' && new Date()<=new Date(d.expires);}catch(e){return false;}
  }
  function loadAds(){
    if(loaded||window.__adsLoaded) return; loaded=window.__adsLoaded=true;
    var s=document.createElement('script'); s.async=true; s.crossOrigin='anonymous';
    s.src='https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-${PUB}';
    document.head.appendChild(s);
  }
  if(accepted()) loadAds();
  window.addEventListener('ek:consent',function(e){ if(e.detail&&e.detail.choice==='accepted') loadAds(); });
  document.addEventListener('click',function(e){
    if(e.target.closest('#cookieAccept,.cookie-accept')) setTimeout(function(){ if(accepted()) loadAds(); },50);
  });
})();`;

// ---------- runs inside the page: find every same-site URL the page uses ----------
function collectInPage(base, pageUrl) {
  const found = new Set();
  const skip = /^(#|data:|mailto:|tel:|javascript:|blob:|about:)/i;
  const add = v => {
    if (!v) return; v = v.trim(); if (!v || skip.test(v)) return;
    try { const u = new URL(v, pageUrl); u.hash = ''; u.search = ''; if (u.href.startsWith(base)) found.add(u.href); } catch (e) {}
  };
  const urlRe = /url\(\s*(['"]?)([^)'"]+)\1\s*\)/g;
  const strRe = /(["'])([^"'\\]*\/[^"'\\]*\.[a-z0-9]{2,5})\1/gi;     // 'uploads/cv.pdf' inside inline JS
  const ATTRS = ['src', 'href', 'action', 'poster', 'data-src', 'data-href', 'data-pdf', 'data-url', 'data-file'];
  document.querySelectorAll('*').forEach(el => {
    ATTRS.forEach(a => add(el.getAttribute(a)));
    const ss = el.getAttribute('srcset');
    if (ss) ss.split(',').forEach(p => add(p.trim().split(/\s+/)[0]));
    for (const a of el.attributes) {
      if (/^on/i.test(a.name)) { let m; strRe.lastIndex = 0; while ((m = strRe.exec(a.value))) add(m[2]); }
      if (a.name === 'style') { let m; urlRe.lastIndex = 0; while ((m = urlRe.exec(a.value))) add(m[2]); }
    }
  });
  document.querySelectorAll('style').forEach(st => { let m; urlRe.lastIndex = 0; while ((m = urlRe.exec(st.textContent))) add(m[2]); });
  return [...found];
}

// ---------- runs inside the page (same origin => cookies/anti-bot pass): download one file ----------
async function fetchInPage(url, localSize) {
  const b64 = buf => { let s = ''; const C = 0x8000; for (let i = 0; i < buf.length; i += C) s += String.fromCharCode.apply(null, buf.subarray(i, i + C)); return btoa(s); };
  try {
    if (localSize >= 0) {
      const h = await fetch(url, { method: 'HEAD', credentials: 'include', cache: 'no-store' });
      const len = parseInt(h.headers.get('content-length') || '-1', 10);
      if (h.ok && len >= 0 && len === localSize) return { skip: true };
    }
    const r = await fetch(url, { credentials: 'include', cache: 'no-store' });
    if (!r.ok) return { error: 'HTTP ' + r.status };
    const type = (r.headers.get('content-type') || '').toLowerCase();
    if (type.includes('text/html') && !/\.html?$/i.test(url)) return { error: 'got an HTML page instead of a file' };
    const buf = new Uint8Array(await r.arrayBuffer());
    return { b64: b64(buf), size: buf.length };
  } catch (e) { return { error: String(e) }; }
}

// ---------- runs inside the page: rewrite + clean up ----------
function rewriteInPage(base, pageUrl, assetMap, pageMap, pub, loaderSrc) {
  const skip = /^(#|data:|mailto:|tel:|javascript:|blob:|about:)/i;
  const fix = v => {
    if (!v) return v; const t = v.trim(); if (!t || skip.test(t)) return v;
    try {
      const u = new URL(t, pageUrl); const hash = u.hash; u.hash = ''; u.search = '';
      if (assetMap[u.href]) return assetMap[u.href] + hash;
      if (pageMap[u.href]) return pageMap[u.href] + hash;
      return new URL(t, pageUrl).href;                      // anything else: absolute URL on the live site
    } catch (e) { return v; }
  };
  const urlRe = /url\(\s*(['"]?)([^)'"]+)\1\s*\)/g;
  const strRe = /(["'])([^"'\\]*\/[^"'\\]*\.[a-z0-9]{2,5})\1/gi;
  const ATTRS = ['src', 'href', 'action', 'poster', 'data-src', 'data-href', 'data-pdf', 'data-url', 'data-file'];
  document.querySelectorAll('*').forEach(el => {
    ATTRS.forEach(a => { if (el.hasAttribute(a)) el.setAttribute(a, fix(el.getAttribute(a))); });
    if (el.hasAttribute('srcset')) {
      el.setAttribute('srcset', el.getAttribute('srcset').split(',').map(p => {
        const [u, ...r] = p.trim().split(/\s+/); return [fix(u), ...r].join(' ');
      }).join(', '));
    }
    for (const a of [...el.attributes]) {
      if (/^on/i.test(a.name)) {
        el.setAttribute(a.name, a.value.replace(strRe, (m, q, p) => {
          try { const u = new URL(p, pageUrl); u.hash = ''; u.search = ''; if (assetMap[u.href]) return q + assetMap[u.href] + q; } catch (e) {}
          return m;
        }));
      }
      if (a.name === 'style') el.setAttribute('style', a.value.replace(urlRe, (m, q, p) => 'url(' + q + fix(p) + q + ')'));
    }
  });
  document.querySelectorAll('style').forEach(st => { st.textContent = st.textContent.replace(urlRe, (m, q, p) => 'url(' + q + fix(p) + q + ')'); });

  // remove leftovers from ads + reset the cookie banner to its initial (hidden) state
  document.querySelectorAll('script[src*="adsbygoogle"], ins.adsbygoogle, iframe[id^="google_ads"], [id^="aswift"]').forEach(n => n.remove());
  const cb = document.getElementById('cookie-consent');
  if (cb) { cb.classList.remove('visible'); cb.removeAttribute('style'); }
  document.body.style.removeProperty('overflow');
  document.querySelectorAll('.open').forEach(n => { if (/modal|overlay/i.test(n.id || '')) n.classList.remove('open'); });

  if (!document.querySelector('meta[name="google-adsense-account"]')) {
    const m = document.createElement('meta'); m.name = 'google-adsense-account'; m.content = 'ca-' + pub; document.head.appendChild(m);
  }
  const s = document.createElement('script'); s.textContent = loaderSrc;
  document.head.insertBefore(s, document.head.firstChild);
}

async function processPage(browser, pageName, discover) {
  const page = await browser.newPage();
  try {
    await page.setViewport({ width: 1280, height: 900 });
    const pageUrl = BASE_URL + pageName;
    await page.goto(`${pageUrl}?mirror=${Date.now()}`, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(1500);

    const urls = await page.evaluate(collectInPage, BASE_URL, pageUrl);
    const assetMap = {}, pageMap = {};
    for (const abs of urls) {
      const rel = abs.slice(BASE_URL.length);
      if (!rel || rel.endsWith('/')) continue;
      if (/^[\w-]+\.php$/i.test(rel)) {                      // another site page => mirror it too
        if (!SKIP_PAGE.test(rel)) { pageMap[abs] = htmlName(rel); discover(rel); }
        continue;
      }
      if (DENY_ASSET.test(rel) || rel.includes('..')) continue;
      assetMap[abs] = rel.split('/').map(decodeURIComponent).join('/');
    }

    // download every asset (skips files whose size already matches the copy in the repo)
    for (const [abs, rel] of Object.entries(assetMap)) {
      const local = path.join(process.cwd(), rel);
      const localSize = fs.existsSync(local) ? fs.statSync(local).size : -1;
      const r = await page.evaluate(fetchInPage, abs, localSize);
      if (r.skip) { usedAssets.add(rel); console.log(`  =    ${rel}`); continue; }
      if (r.error || r.size > MAX_FILE) {
        console.log(`  SKIP ${rel}: ${r.error || 'too large'} (link stays on the live site)`);
        delete assetMap[abs]; continue;
      }
      fs.mkdirSync(path.dirname(local), { recursive: true });
      fs.writeFileSync(local, Buffer.from(r.b64, 'base64'));
      usedAssets.add(rel);
      console.log(`  +    ${rel} (${r.size} bytes)`);
    }

    await page.evaluate(rewriteInPage, BASE_URL, pageUrl, assetMap, pageMap, PUB, CONSENT_LOADER);

    const html = '<!DOCTYPE html>\n' + await page.evaluate(() => document.documentElement.outerHTML);
    if (html.length < 3000) throw new Error(`suspiciously small output (${html.length} bytes)`);
    if (/Fatal error|Parse error|Warning:.*on line/i.test(html)) throw new Error('PHP error text found in page');
    fs.writeFileSync(htmlName(pageName), html);
    console.log(`OK   ${pageName} -> ${htmlName(pageName)} (${html.length} bytes, ${Object.keys(assetMap).length} files)`);
  } finally {
    await page.close();
  }
}

(async () => {
  fs.writeFileSync('ads.txt', `google.com, ${PUB}, DIRECT, f08c47fec0942fa0\n`);
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const queue = [...START], seen = new Set(START), failed = [];
  const discover = rel => { if (!seen.has(rel) && seen.size < MAX_PAGES) { seen.add(rel); queue.push(rel); } };

  while (queue.length) {
    const p = queue.shift();
    let ok = false;
    for (let attempt = 1; attempt <= 3 && !ok; attempt++) {
      try { await processPage(browser, p, discover); ok = true; }
      catch (e) { console.log(`TRY ${attempt}/3 failed for ${p}: ${e.message}`); await sleep(attempt * 4000); }
    }
    if (!ok) failed.push(p);
  }
  await browser.close();

  // a page that could not be mirrored keeps its previous copy, or redirects to the live page
  for (const p of failed) {
    if (!fs.existsSync(htmlName(p))) {
      fs.writeFileSync(htmlName(p), `<!DOCTYPE html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=${BASE_URL + p}"><a href="${BASE_URL + p}">Continue</a>`);
    }
  }

  // remove uploads that were deleted on the main site (only when every page mirrored cleanly)
  if (!failed.length && fs.existsSync('uploads')) {
    const walk = d => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(d, e.name)) : [path.join(d, e.name)]);
    for (const f of walk('uploads')) {
      const rel = f.split(path.sep).join('/');
      if (!usedAssets.has(rel)) { fs.unlinkSync(f); console.log(`  -    ${rel} (deleted on main site)`); }
    }
  }
  if (failed.length) { console.log('FAILED: ' + failed.join(', ')); process.exitCode = failed.length >= seen.size ? 1 : 0; }
})();
