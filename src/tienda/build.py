#!/usr/bin/env python3
"""Genera la tienda CTRL POINT: sitio publicable (web/) y vista previa (build/preview.html)."""
import html, json, os, sys
from decimal import Decimal, ROUND_HALF_UP, ROUND_CEILING

# Uso: python3 src/tienda/build.py  (desde la raíz del repo)
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
SITE = os.path.join(ROOT, "web")               # sitio publicable
OUT = os.path.join(ROOT, "build")              # vista previa (no se versiona)
os.makedirs(OUT, exist_ok=True)
os.makedirs(SITE, exist_ok=True)

STORE = "CTRL POINT"
CITY = "San Juan de los Morros"
HOURS = "Lunes a sábado, 8:00 a. m. a 6:00 p. m."
DOMAIN = "https://www.tudominio.com"  # provisional: reemplazar al tener dominio
WA = "584127782492"
WA_TXT = "0412-778-2492"

CATS = [("iluminacion", "Iluminación"), ("tripodes", "Trípodes y soportes"),
        ("almacenamiento", "Almacenamiento"), ("tabletas", "Tabletas gráficas")]

P = [
 ("ulanzi-mt-78-tripode", "ULANZI MT-78 Trípode extensible de metal", "tripodes", 70, 2,
  ["1,85 m", "Rótula 360°", "Metal", "Clip 2 en 1"],
  "Trípode y selfie stick de metal de 72,83 in con cabeza de bola 360°, clip para teléfono 2 en 1 y doble zapata fría. Compatible con iPhone, Sony y Canon. Ligero para viajes y vlog.", True),
 ("kf-concept-sp25c", "K&F Concept SP25C Linterna RGB 25 W", "iluminacion", 90, 1,
  ["25 W", "RGB", "20 gobos", "4000 lux"],
  "Linterna de fotografía RGB con 20 gobos y 4 colores, proyector con zoom y batería de 9000 mAh. 4000 lux para retrato, video y creación de contenido. Color plata.", True),
 ("neewer-fl10", "NEEWER BASICS FL10 Linterna 10 W", "iluminacion", 70, 3,
  ["10 W", "Portátil"], "Linterna portátil de fotografía de 10 W.", True),
 ("neewer-tl96-rgb", "NEEWER BASICS TL96 RGB Varilla magnética", "iluminacion", 95, 1,
  ["RGB", "Magnética", "De mano"], "Varilla de luz RGB magnética de mano.", True),
 ("neewer-gc30c", "NEEWER BASICS GC30C 30 W RGB inflable", "iluminacion", 120, 1,
  ["30 W", "RGB", "Inflable"], "Luz de relleno LED RGB de mano, inflable, de 30 W.", True),
 ("neewer-sl30", "NEEWER SL30 Luz LED", "iluminacion", 40, 2,
  ["LED"], "Luz LED NEEWER SL30.", False),
 ("neewer-gc21b", "NEEWER BASICS GC21B 21 W bicolor inflable", "iluminacion", 80, 1,
  ["21 W", "Bicolor", "Inflable"], "Luz de relleno LED inflable portátil bicolor de 21 W.", False),
 ("neewer-gc10b", "NEEWER GC10B Luz 10 W", "iluminacion", 45, 1,
  ["10 W"], "Luz NEEWER GC10B de 10 W.", False),
 ("neewer-tl21c-rgb", "NEEWER TL21C RGB", "iluminacion", 110, 1,
  ["RGB"], "Luz NEEWER TL21C RGB.", False),
 ("neewer-rgb62", "NEEWER RGB62 magnética con app", "iluminacion", 85, 1,
  ["RGB", "Magnética", "Control por app"], "Luz RGB magnética NEEWER RGB62 con control desde app.", True),
 ("crucial-p310-1tb", "Crucial P310 SSD 1 TB M.2 2230", "almacenamiento", 275, 1,
  ["1 TB", "M.2 2230", "PCIe Gen4", "7100 MB/s"],
  "SSD NVMe PCIe Gen4 de hasta 7.100 MB/s. Ideal para Steam Deck, ASUS ROG Ally, MSI Claw, Mini PC y ultrabooks (CT1000P310SSD2).", True),
 ("ulanzi-ma55-carcasa-ssd", "ULANZI MA55 Carcasa magnética para SSD", "almacenamiento", 65, 1,
  ["USB-C 10 Gbps", "MagSafe", "2230/2242"],
  "Carcasa externa M.2 NVMe con MagSafe, USB 3.2 Gen 2 tipo C (10 Gbps). Compatible con SSD 2230/2242 y iPhone 16 y 17.", False),
 ("xp-pen-deco-01-v3", "XP-PEN Deco 01 V3 Tableta gráfica", "tabletas", 75, 1,
  ["XP-PEN", "Deco 01 V3"], "Tableta digitalizadora XP-PEN Deco 01 V3.", False),
 ("xp-pen-deco-pro-mw-2", "XP-PEN Deco Pro MW 2.ª gen Tableta gráfica", "tabletas", 150, 1,
  ["XP-PEN", "2.ª gen", "Deco Pro MW"], "Tableta digitalizadora XP-PEN Deco Pro MW de segunda generación (MTO962B).", True),
]

ICONS = {
 "iluminacion": '<path d="M24 6v6M10 12l4 4M38 12l-4 4M6 26h6M36 26h6"/><path d="M16 30a8 8 0 1 1 16 0c0 3-2 4-2 7H18c0-3-2-4-2-7z"/><path d="M19 41h10"/>',
 "tripodes": '<rect x="17" y="6" width="14" height="9" rx="2"/><path d="M24 15v8M24 23 12 42M24 23l12 19M24 23v19"/>',
 "almacenamiento": '<rect x="6" y="16" width="36" height="16" rx="2"/><path d="M12 22h10v4H12zM28 21v6M32 21v6M36 21v6"/>',
 "tabletas": '<rect x="5" y="11" width="32" height="24" rx="3"/><rect x="11" y="16" width="20" height="14" rx="1"/><path d="m42 8-9 22-2 4 4-2 9-22z"/>',
}
catname = dict(CATS)
e = html.escape
MARGIN = Decimal("0.30")  # precio de venta = costo del Excel + 30 %
def sale(cost):
    # redondeo hacia arriba al dólar entero (protege el margen)
    return (Decimal(cost) * (1 + MARGIN)).quantize(Decimal("1"), ROUND_CEILING)
def money(v):
    v = Decimal(v)
    return f"{int(v)}" if v == v.to_integral() else f"{v:.2f}".replace(".", ",")

def card(p, anchor=True):
    slug, name, cat, cost, stock, specs, desc, feat = p
    price = money(sale(cost))
    chips = "".join(f"<li>{e(s)}</li>" for s in specs)
    return f'''<article class="card"{f' id="{slug}"' if anchor else ""} data-cat="{cat}" data-name="{e(name.lower())}">
  <div class="thumb thumb-{cat}" role="img" aria-label="{e(name)}"><svg viewBox="0 0 48 48" aria-hidden="true">{ICONS[cat]}</svg><span class="ph">Foto pendiente</span></div>
  <div class="card-body">
    <p class="card-cat">{e(catname[cat])}</p>
    <h3>{e(name)}</h3>
    <ul class="specs">{chips}</ul>
    <details><summary>Ver detalles</summary><p>{e(desc)}</p></details>
    <div class="card-foot">
      <p class="price"><span>$</span>{price}</p>
      <p class="stock">{stock} {"disponible" if stock == 1 else "disponibles"}</p>
    </div>
    <button class="add" type="button" data-slug="{slug}">Agregar al carrito</button>
  </div>
</article>'''

featured = "\n".join(card(p, False) for p in P if p[7])
catalog = "\n".join(card(p) for p in P)
filters = '<button type="button" class="chip is-on" data-f="all">Todo</button>' + "".join(
    f'<button type="button" class="chip" data-f="{k}">{e(v)}</button>' for k, v in CATS)

PRODUCTS_JS = json.dumps({p[0]: {"n": p[1], "p": float(sale(p[3])), "s": p[4]} for p in P}, ensure_ascii=False)

jsonld = json.dumps({
  "@context": "https://schema.org",
  "@graph": [
    {"@type": "Store", "name": STORE, "url": DOMAIN + "/", "telephone": "+58 412-778-2492",
     "address": {"@type": "PostalAddress", "addressLocality": CITY, "addressRegion": "Guárico", "addressCountry": "VE"},
     "openingHoursSpecification": [{"@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"], "opens": "08:00", "closes": "18:00"}],
     "priceRange": "$$", "currenciesAccepted": "USD, VES",
     "paymentAccepted": "Pago Móvil, transferencia bancaria, Binance", "areaServed": "VE"},
    {"@type": "ItemList", "name": "Catálogo", "itemListElement": [
      {"@type": "ListItem", "position": i + 1, "item": {
        "@type": "Product", "name": p[1], "description": p[6], "category": catname[p[2]],
        "url": f"{DOMAIN}/#{p[0]}",
        "offers": {"@type": "Offer", "price": f"{sale(p[3]):.2f}", "priceCurrency": "USD",
                   "availability": "https://schema.org/InStock"}}} for i, p in enumerate(P)]}
  ]}, ensure_ascii=False)

CSS = open(os.path.join(os.path.dirname(__file__), "styles.css"), encoding="utf-8").read()
JS = open(os.path.join(os.path.dirname(__file__), "app.js"), encoding="utf-8").read().replace("__PRODUCTS__", PRODUCTS_JS).replace("__WA__", WA)

WA_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3a.5.5 0 0 0 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>'

BODY = f'''<a class="skip" href="#catalogo">Saltar al catálogo</a>
<div class="promo" id="promo" aria-label="Novedades"><div class="wrap promo-in">
  <p class="promo-item is-on"><span class="promo-tag">Nuevo</span><span>Equipo para creadores en {CITY}: luces, trípodes, SSD y tabletas.</span><a class="promo-cta" href="#catalogo">Ver catálogo</a></p>
  <p class="promo-item"><span class="promo-tag">Asesoría</span><span>¿No sabes qué luz elegir? Te asesoramos sin compromiso.</span><a class="promo-cta" href="https://wa.me/{WA}?text=Hola%2C%20quiero%20asesor%C3%ADa%20para%20elegir%20un%20equipo" target="_blank" rel="noopener">Pedir asesoría</a></p>
  <p class="promo-item"><span class="promo-tag">Stock</span><span>Unidades limitadas: varios productos tienen solo 1 disponible.</span><a class="promo-cta" href="#destacados">Ver disponibles</a></p>
  <p class="promo-item"><span class="promo-tag">Envíos</span><span>Enviamos a toda Venezuela por Zoom, MRW y Tealca.</span><a class="promo-cta" href="https://wa.me/{WA}?text=Hola%2C%20quiero%20hacer%20un%20pedido" target="_blank" rel="noopener">Pedir por WhatsApp</a></p>
</div></div>
<header class="top"><div class="wrap top-in">
  <a class="logo" href="#inicio" aria-label="{STORE}, inicio"><span class="logo-mark">CTRL</span><span class="logo-word">POINT</span></a>
  <nav aria-label="Principal"><a href="#inicio">Inicio</a><a href="#catalogo">Catálogo</a><a href="#como-comprar">Cómo comprar</a><a href="#contacto">Contacto</a></nav>
  <button class="cart-btn" type="button" id="open-cart" aria-label="Abrir carrito"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11h11L21 7H6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="9" cy="19.5" r="1.5" fill="currentColor"/><circle cx="17" cy="19.5" r="1.5" fill="currentColor"/></svg><span class="count" id="cart-count">0</span></button>
</div></header>

<main id="inicio">
<section class="hero"><canvas class="hero-fx" id="hero-fx" aria-hidden="true"></canvas><div class="hero-floor" aria-hidden="true"></div><div class="scan" aria-hidden="true"></div><div class="wrap hero-in">
  <div class="hero-copy">
    <p class="eyebrow"><span class="prompt">&gt;</span> Importados · Foto y video · {CITY}<span class="caret" aria-hidden="true">_</span></p>
    <h1 class="glitch" data-text="Equipo para crear contenido, listo para entregar.">Equipo para crear contenido, <span class="neon">listo para entregar.</span></h1>
    <p class="lead">Luces NEEWER, trípodes ULANZI, SSD y tabletas XP-PEN con stock disponible en {CITY}. Arma tu pedido y finalízalo por WhatsApp.</p>
    <div class="cta"><a class="btn btn-primary" href="#catalogo">Ver catálogo</a><a class="btn btn-ghost" href="https://wa.me/{WA}?text=Hola%2C%20quiero%20informaci%C3%B3n%20sobre%20sus%20productos" target="_blank" rel="noopener">{WA_ICON}Escríbenos</a></div>
  </div>
  <ul class="hero-stats" aria-label="Datos de la tienda">
    <li><strong data-count="{len(P)}">{len(P)}</strong><span>productos en catálogo</span></li>
    <li><strong data-count="4">4</strong><span>categorías</span></li>
    <li><strong data-count="3">3</strong><span>formas de pago</span></li>
  </ul>
</div></section>

<section class="benefits" aria-label="Beneficios"><div class="wrap ben-grid">
  <div><span class="ben-ico" aria-hidden="true"><svg viewBox="0 0 48 48"><path class="ln" d="M24 7c-9.4 0-17 6.8-17 15.2 0 4.6 2.3 8.7 5.9 11.5L11 41l8.1-4c1.6.4 3.2.6 4.9.6 9.4 0 17-6.8 17-15.4S33.4 7 24 7z"/><circle class="dot d1" cx="17" cy="22.5" r="2.4"/><circle class="dot d2" cx="24" cy="22.5" r="2.4"/><circle class="dot d3" cx="31" cy="22.5" r="2.4"/></svg></span><h2>Pedido por WhatsApp</h2><p>Sin registros ni formularios. Tu carrito llega como mensaje listo.</p></div>
  <div><span class="ben-ico" aria-hidden="true"><svg viewBox="0 0 48 48"><path class="ln" d="M5 42h38M5 6v36"/><rect class="bar b1" x="10" y="26" width="6" height="14" rx="1.5"/><rect class="bar b2" x="19" y="18" width="6" height="22" rx="1.5"/><rect class="bar b3" x="28" y="22" width="6" height="18" rx="1.5"/><rect class="bar b4" x="37" y="12" width="6" height="28" rx="1.5"/></svg></span><h2>Stock a la vista</h2><p>Cada producto muestra las unidades disponibles.</p></div>
  <div><span class="ben-ico" aria-hidden="true"><svg viewBox="0 0 48 48"><rect class="ln" x="5" y="11" width="38" height="26" rx="4"/><path class="ln" d="M5 18h38"/><rect class="chip" x="10" y="24" width="8" height="6" rx="1.5"/><path class="ln" d="M24 30h13"/><path class="swipe" d="M2 9v30"/></svg></span><h2>Pagos locales</h2><p>Pago Móvil, transferencia Banesco y Binance.</p></div>
  <div><span class="ben-ico" aria-hidden="true"><svg viewBox="0 0 48 48"><path class="ln" d="M18 33c0-4-5-6.5-5-13.5a11 11 0 0 1 22 0C35 26.5 30 29 30 33z"/><path class="ln" d="M19 38h10M21 42h6"/><path class="ray" d="M24 2v3M7 7l2 2M41 7l-2 2M2 20h3M43 20h3"/><path class="fil" d="M21 33v-7l3-3 3 3v7"/></svg></span><h2>Asesoría antes de comprar</h2><p>Te ayudamos a elegir la luz o el equipo adecuado.</p></div>
</div></section>

<section class="sec" id="destacados" aria-labelledby="h-dest"><div class="wrap">
  <div class="sec-head"><h2 id="h-dest">Los más buscados</h2><a href="#catalogo">Ver todo el catálogo</a></div>
  <div class="grid">{featured}</div>
</div></section>

<section class="sec sec-alt" id="catalogo" aria-labelledby="h-cat"><div class="wrap">
  <div class="sec-head"><h2 id="h-cat">Catálogo</h2>
    <label class="search" for="q"><span class="sr">Buscar producto</span><input id="q" type="search" placeholder="Buscar: NEEWER, SSD, trípode…" maxlength="60" autocomplete="off"></label>
  </div>
  <div class="filters" role="group" aria-label="Filtrar por categoría">{filters}</div>
  <div class="grid" id="catalog-grid">{catalog}</div>
  <p class="empty" id="empty" hidden>No encontramos productos con esa búsqueda.</p>
</div></section>

<section class="sec" id="como-comprar" aria-labelledby="h-how"><div class="wrap">
  <h2 id="h-how">Cómo comprar</h2>
  <ol class="steps">
    <li><strong>Agrega al carrito</strong><span>Elige productos y cantidades.</span></li>
    <li><strong>Envía tu pedido por WhatsApp</strong><span>El mensaje sale con el detalle y el total.</span></li>
    <li><strong>Paga y recibe</strong><span>Confirmamos stock, te enviamos los datos de pago y coordinamos la entrega.</span></li>
  </ol>
</div></section>

<section class="sec sec-contact" id="contacto" aria-labelledby="h-contact"><div class="wrap contact-in">
  <div><h2 id="h-contact">Contacto</h2>
    <p>WhatsApp: <strong class="mono">{WA_TXT}</strong></p>
    <p>Ubicación: <strong>{CITY}, Guárico</strong></p>
    <p>Horario: <strong>{HOURS}</strong></p>
    <p>Zona de entrega: <span class="tbd">por definir</span></p>
    <p>Instagram: <span class="tbd">por definir</span></p>
  </div>
  <a class="btn btn-wa" href="https://wa.me/{WA}" target="_blank" rel="noopener">{WA_ICON}Hablar por WhatsApp</a>
</div></section>
</main>

<footer class="foot"><div class="wrap foot-in">
  <div><a class="logo" href="#inicio"><span class="logo-mark">CTRL</span><span class="logo-word">POINT</span></a><p>Equipo para foto, video y creación de contenido.</p></div>
  <nav aria-label="Categorías"><p class="foot-h">Tienda</p>{"".join(f'<a href="#catalogo" data-go="{k}">{e(v)}</a>' for k, v in CATS)}</nav>
  <div><p class="foot-h">Contacto</p><p class="mono">{WA_TXT}</p><p>{CITY}, Guárico</p><p>{HOURS}</p><p>Precios en USD. Sujetos a confirmación de stock.</p></div>
</div><div class="wrap copy">© 2026 {STORE}</div></footer>

<a class="wa-float" href="https://wa.me/{WA}" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">{WA_ICON}</a>

<div class="scrim" id="scrim" hidden></div>
<aside class="drawer" id="drawer" aria-label="Tu carrito" aria-hidden="true">
  <div class="drawer-head"><h2>Tu carrito</h2><button type="button" class="x" id="close-cart" aria-label="Cerrar carrito">×</button></div>
  <ul class="lines" id="lines"></ul>
  <p class="empty" id="cart-empty">Tu carrito está vacío.</p>
  <div class="drawer-foot">
    <label for="c-name">Tu nombre (opcional)</label><input id="c-name" type="text" maxlength="60" autocomplete="name">
    <fieldset class="ship"><legend>Método de envío</legend>
      <label class="opt"><input type="radio" name="ship" value="retiro" checked><span>Retiro en tienda<small>San Juan de los Morros · lun a sáb 8am–6pm</small></span></label>
      <label class="opt"><input type="radio" name="ship" value="nacional"><span>Envío nacional<small>A todo el país por Zoom, MRW o Tealca</small></span></label>
    </fieldset>
    <div class="ship-extra" id="ship-extra" hidden>
      <label for="c-city">Ciudad de destino</label><input id="c-city" type="text" maxlength="60" autocomplete="address-level2" placeholder="Ej.: Valencia, Carabobo">
      <label for="c-courier">Empresa de envío</label><select id="c-courier"><option>Zoom</option><option>MRW</option><option>Tealca</option><option value="">Me da igual</option></select>
      <p class="hint" id="city-hint">Escribe la ciudad para finalizar el pedido.</p>
    </div>
    <p class="total"><span>Total</span><strong id="total">$0</strong></p>
    <a class="btn btn-wa btn-block" id="checkout" href="https://wa.me/{WA}" target="_blank" rel="noopener" aria-disabled="true">{WA_ICON}Finalizar por WhatsApp</a>
    <p class="note">Se abrirá WhatsApp con tu pedido. Los datos de pago se envían solo por ese chat.</p>
  </div>
</aside>'''

FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800;900&family=Manrope:wght@400;600;700&family=JetBrains+Mono:wght@500&display=swap">'
DESC = f"Luces NEEWER, trípodes ULANZI, SSD Crucial y tabletas XP-PEN en {CITY}, Guárico. Arma tu carrito y haz tu pedido por WhatsApp. Pago Móvil, transferencia y Binance."
TITLE = f"{STORE} | Equipo de foto y video en {CITY}"

# Versión desplegable (archivos separados, CSP estricta posible)
open(os.path.join(SITE, "index.html"), "w", encoding="utf-8").write(f'''<!doctype html>
<html lang="es-VE">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{e(TITLE)}</title>
<meta name="description" content="{e(DESC)}">
<link rel="canonical" href="{DOMAIN}/">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#7f00b2">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_VE">
<meta property="og:site_name" content="{STORE}">
<meta property="og:title" content="{e(TITLE)}">
<meta property="og:description" content="{e(DESC)}">
<meta property="og:url" content="{DOMAIN}/">
<meta property="og:image" content="{DOMAIN}/og.jpg">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
{FONTS}
<link rel="stylesheet" href="styles.css">
<script type="application/ld+json">{jsonld}</script>
</head>
<body>
{BODY}
<script src="app.js" defer></script>
</body>
</html>
''')
open(os.path.join(SITE, "styles.css"), "w", encoding="utf-8").write(CSS)
open(os.path.join(SITE, "app.js"), "w", encoding="utf-8").write(JS)
open(os.path.join(SITE, "favicon.svg"), "w").write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#7f00b2"/><path d="M9 16h14M16 9v14" stroke="#77dd77" stroke-width="4" stroke-linecap="round"/></svg>')
open(os.path.join(SITE, "404.html"), "w", encoding="utf-8").write(f'''<!doctype html>
<html lang="es-VE"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Página no encontrada | {STORE}</title><meta name="robots" content="noindex">
<style>body{{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.6 system-ui,sans-serif;background:#fff;color:#17111c;text-align:center;padding:24px}}h1{{color:#7f00b2;margin:0 0 8px}}a{{display:inline-block;margin-top:16px;padding:12px 20px;background:#77dd77;color:#10260f;font-weight:700;text-decoration:none;border-radius:10px}}</style>
</head><body><main><h1>404</h1><p>Esta página no existe.</p><a href="/">Volver a la tienda</a></main></body></html>
''')
open(os.path.join(SITE, "robots.txt"), "w").write(f"User-agent: *\nAllow: /\n\nSitemap: {DOMAIN}/sitemap.xml\n")
open(os.path.join(SITE, "sitemap.xml"), "w").write(f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n  <url><loc>{DOMAIN}/</loc><lastmod>2026-10-21</lastmod></url>\n</urlset>\n')
# Cabeceras de seguridad (Cloudflare Pages y Netlify leen _headers)
open(os.path.join(SITE, "_headers"), "w").write("""/*
  Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'none'; object-src 'none'; upgrade-insecure-requests
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
""")

# Vista previa para Artifact (todo en línea, sin <head>)
open(os.path.join(OUT, "preview.html"), "w", encoding="utf-8").write(f'''<title>Tienda CTRL POINT</title>
<meta name="claude:viewport-fit-sides" content="cover">
{FONTS}
<style>{CSS}</style>
{BODY}
<script>{JS}</script>
''')
print("ok", len(P))
