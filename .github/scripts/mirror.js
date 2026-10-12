// Mirrors PHP pages to static HTML. Consent-gated AdSense: ads load ONLY after "Accept".
const puppeteer = require('puppeteer');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL;
const PUB = process.env.ADSENSE_PUB;            // e.g. pub-9638774372490271
const PAGES = (process.env.PAGES || '').split(',').map(s => s.trim()).filter(Boolean);
const outName = p => (p === 'port_kirui.php' ? 'index.html' : p.replace(/\.php$/, '.html'));
const sleep = ms => new Promise(r => setTimeout(r, ms));

// Runs inside every mirrored page: waits for consent, then loads AdSense once.
const CONSENT_LOADER = `
(function(){
  window.__MIRROR_BASE__ = ${JSON.stringify(BASE_URL)};
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
  document.addEventListener('click',function(e){            // works even if a page lacks the event
    if(e.target.closest('#cookieAccept,.cookie-accept')) setTimeout(function(){ if(accepted()) loadAds(); },50);
  });
})();`;

async function processPage(browser, pageName) {
  const page = await browser.newPage();
  try {
    await page.setViewport({ width: 1280, height: 900 });
    // cache-bust so a stale server/CDN copy is never mirrored
    await page.goto(`${BASE_URL}${pageName}?mirror=${Date.now()}`, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(1500);

    await page.evaluate((base, pageName, pub, loaderSrc) => {
      const pageUrl = base + pageName;
      const abs = v => {
        if (!v) return v;
        const t = v.trim();
        if (/^(#|data:|mailto:|tel:|javascript:|blob:|about:)/i.test(t)) return v;
        try { return new URL(t, pageUrl).href; } catch (e) { return v; }
      };
      // 1) absolutize URLs (src/href/action/poster/data-*, srcset)
      document.querySelectorAll('*').forEach(el => {
        ['src','href','action','poster','data-src','data-href','data-pdf','data-url'].forEach(a => {
          if (el.hasAttribute(a)) el.setAttribute(a, abs(el.getAttribute(a)));
        });
        if (el.hasAttribute('srcset')) {
          el.setAttribute('srcset', el.getAttribute('srcset').split(',').map(p => {
            const [u, ...r] = p.trim().split(/\s+/); return [abs(u), ...r].join(' ');
          }).join(', '));
        }
        // relative upload paths used inside inline onclick handlers
        const oc = el.getAttribute('onclick');
        if (oc && /openPdfModal\(/.test(oc)) {
          el.setAttribute('onclick', oc.replace(/(["'])((?:uploads|assets|images)\/[^"']+)\1/g, (m, q, p) => q + abs(p) + q));
        }
      });
      // 2) strip anything ads left behind + reset the cookie banner to its initial state
      document.querySelectorAll('script[src*="adsbygoogle"], ins.adsbygoogle, iframe[id^="google_ads"], [id^="aswift"]').forEach(n => n.remove());
      const cb = document.getElementById('cookie-consent');
      if (cb) { cb.classList.remove('visible'); cb.removeAttribute('style'); }
      document.body.style.removeProperty('overflow');
      // 3) AdSense account meta (needed for site verification, sets no cookies)
      if (!document.querySelector('meta[name="google-adsense-account"]')) {
        const m = document.createElement('meta');
        m.name = 'google-adsense-account'; m.content = 'ca-' + pub; document.head.appendChild(m);
      }
      // 4) consent-gated loader, placed first in <head> so it is ready before the banner script
      const s = document.createElement('script'); s.textContent = loaderSrc;
      document.head.insertBefore(s, document.head.firstChild);
    }, BASE_URL, pageName, PUB, CONSENT_LOADER);

    const html = '<!DOCTYPE html>\n' + await page.evaluate(() => document.documentElement.outerHTML);
    if (html.length < 5000) throw new Error(`suspiciously small output (${html.length} bytes)`);
    if (/Fatal error|Parse error|Warning:.*on line/i.test(html)) throw new Error('PHP error text found in page');
    fs.writeFileSync(outName(pageName), html);
    console.log(`OK   ${pageName} -> ${outName(pageName)} (${html.length} bytes)`);
  } finally {
    await page.close();
  }
}

(async () => {
  fs.writeFileSync('ads.txt', `google.com, ${PUB}, DIRECT, f08c47fec0942fa0\n`);
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const failed = [];
  for (const p of PAGES) {
    let ok = false;
    for (let attempt = 1; attempt <= 3 && !ok; attempt++) {
      try { await processPage(browser, p); ok = true; }
      catch (e) { console.log(`TRY ${attempt}/3 failed for ${p}: ${e.message}`); await sleep(attempt * 4000); }
    }
    if (!ok) failed.push(p);   // keeps the previous good .html in the repo
  }
  await browser.close();
  if (failed.length) { console.log('FAILED: ' + failed.join(', ')); process.exitCode = failed.length === PAGES.length ? 1 : 0; }
})();
