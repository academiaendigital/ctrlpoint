// Genera og.png, apple-touch-icon.png y logo.png en web/ a partir del logo vectorizado.
// Uso: node src/brand/raster.mjs  (requiere Playwright con Chromium)
import { chromium } from "/opt/node22/lib/node_modules/playwright/index.mjs";
import { readFileSync } from "node:fs";
const d = readFileSync(new URL("./logo-path.txt", import.meta.url), "utf8").trim();
const svg = (fill) => `<svg viewBox="0 0 644 327" style="display:block;width:100%;height:auto"><path fill="${fill}" d="${d}"/></svg>`;
const shots = [
  ["web/og.png", 1200, 630, `<div style="width:1200px;height:630px;display:grid;place-items:center;background:#fff;background-image:linear-gradient(rgba(127,85,245,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(127,85,245,.08) 1px,transparent 1px);background-size:40px 40px;font-family:system-ui,sans-serif"><div style="text-align:center"><div style="width:620px;margin:0 auto;filter:drop-shadow(0 0 24px rgba(127,85,245,.35))">${svg("#7f55f5")}</div><p style="margin:40px 0 0;font-size:38px;font-weight:700;color:#1b0f24">Equipo de foto y video</p><p style="margin:8px 0 0;font-size:28px;color:#2f8f2f">San Juan de los Morros · Pedidos por WhatsApp</p></div></div>`],
  ["web/apple-touch-icon.png", 180, 180, `<div style="width:180px;height:180px;display:grid;place-items:center;background:#7f55f5"><div style="width:150px">${svg("#fff")}</div></div>`],
  ["web/logo.png", 512, 260, `<div style="width:512px;height:260px">${svg("#7f55f5")}</div>`],
];
const b = await chromium.launch();
for (const [out, w, h, html] of shots) {
  const p = await b.newPage({ viewport: { width: w, height: h } });
  await p.setContent(`<body style="margin:0;background:transparent">${html}</body>`);
  await p.screenshot({ path: out, omitBackground: true });
  await p.close();
}
await b.close();
