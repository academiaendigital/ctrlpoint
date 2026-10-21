(function () {
  "use strict";
  var PRODUCTS = {"ulanzi-mt-78-tripode": {"n": "ULANZI MT-78 Trípode extensible de metal", "p": 91.0, "s": 2}, "kf-concept-sp25c": {"n": "K&F Concept SP25C Linterna RGB 25 W", "p": 117.0, "s": 1}, "neewer-fl10": {"n": "NEEWER BASICS FL10 Linterna 10 W", "p": 91.0, "s": 3}, "neewer-tl96-rgb": {"n": "NEEWER BASICS TL96 RGB Varilla magnética", "p": 124.0, "s": 1}, "neewer-gc30c": {"n": "NEEWER BASICS GC30C 30 W RGB inflable", "p": 156.0, "s": 1}, "neewer-sl30": {"n": "NEEWER SL30 Luz LED", "p": 52.0, "s": 2}, "neewer-gc21b": {"n": "NEEWER BASICS GC21B 21 W bicolor inflable", "p": 104.0, "s": 1}, "neewer-gc10b": {"n": "NEEWER GC10B Luz 10 W", "p": 59.0, "s": 1}, "neewer-tl21c-rgb": {"n": "NEEWER TL21C RGB", "p": 143.0, "s": 1}, "neewer-rgb62": {"n": "NEEWER RGB62 magnética con app", "p": 111.0, "s": 1}, "crucial-p310-1tb": {"n": "Crucial P310 SSD 1 TB M.2 2230", "p": 358.0, "s": 1}, "ulanzi-ma55-carcasa-ssd": {"n": "ULANZI MA55 Carcasa magnética para SSD", "p": 85.0, "s": 1}, "xp-pen-deco-01-v3": {"n": "XP-PEN Deco 01 V3 Tableta gráfica", "p": 98.0, "s": 1}, "xp-pen-deco-pro-mw-2": {"n": "XP-PEN Deco Pro MW 2.ª gen Tableta gráfica", "p": 195.0, "s": 1}};
  var WA = "584127782492";
  var KEY = "ctrlpoint-cart";
  var cart = {};

  try {
    var saved = JSON.parse(localStorage.getItem(KEY) || "{}");
    // Solo se aceptan productos del catálogo y cantidades válidas
    Object.keys(saved).forEach(function (k) {
      var q = parseInt(saved[k], 10);
      if (PRODUCTS[k] && q > 0) cart[k] = Math.min(q, PRODUCTS[k].s);
    });
  } catch (e) {}

  var $ = function (id) { return document.getElementById(id); };
  var drawer = $("drawer"), scrim = $("scrim"), lines = $("lines");

  function money(v) { v = Math.round(v * 100) / 100; return v % 1 === 0 ? String(v) : v.toFixed(2).replace(".", ","); }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {} }
  function clean(s, max) { return String(s || "").replace(/[\u0000-\u001f<>]/g, "").trim().slice(0, max); }

  function openCart() {
    drawer.classList.add("open"); drawer.setAttribute("aria-hidden", "false");
    scrim.hidden = false; $("close-cart").focus();
  }
  function closeCart() {
    drawer.classList.remove("open"); drawer.setAttribute("aria-hidden", "true");
    scrim.hidden = true; $("open-cart").focus();
  }

  function message() {
    var out = ["Hola, quiero hacer este pedido:"], total = 0;
    Object.keys(cart).forEach(function (k) {
      var p = PRODUCTS[k], sub = Math.round(p.p * cart[k] * 100) / 100;
      total = Math.round((total + sub) * 100) / 100;
      out.push("• " + cart[k] + " x " + p.n + " — $" + money(sub));
    });
    out.push("Total: $" + money(total));
    var name = clean($("c-name").value, 60), s = ship();
    if (name) out.push("Nombre: " + name);
    if (s.mode === "nacional") {
      out.push("Envío: nacional" + (s.courier ? " por " + s.courier : " (cualquier empresa)"));
      out.push("Ciudad: " + (s.city || "por indicar"));
    } else out.push("Envío: retiro en tienda (San Juan de los Morros)");
    return { text: out.join("\n"), total: total, ok: s.mode !== "nacional" || !!s.city };
  }

  function ship() {
    var r = document.querySelector('input[name="ship"]:checked');
    return { mode: r ? r.value : "retiro", city: clean($("c-city").value, 60), courier: clean($("c-courier").value, 20) };
  }

  function render() {
    var keys = Object.keys(cart), count = 0;
    lines.textContent = "";
    keys.forEach(function (k) {
      var p = PRODUCTS[k], q = cart[k];
      count += q;
      var li = document.createElement("li"); li.className = "line";
      var n = document.createElement("span"); n.className = "line-name"; n.textContent = p.n;
      var pr = document.createElement("span"); pr.className = "line-price"; pr.textContent = "$" + money(p.p * q);
      var qty = document.createElement("span"); qty.className = "qty";
      var minus = document.createElement("button"); minus.type = "button"; minus.textContent = "−";
      minus.setAttribute("aria-label", "Quitar una unidad de " + p.n);
      minus.onclick = function () { setQty(k, q - 1); };
      var o = document.createElement("output"); o.textContent = q;
      var plus = document.createElement("button"); plus.type = "button"; plus.textContent = "+";
      plus.setAttribute("aria-label", "Agregar una unidad de " + p.n);
      plus.disabled = q >= p.s;
      plus.onclick = function () { setQty(k, q + 1); };
      qty.append(minus, o, plus);
      var rm = document.createElement("button"); rm.type = "button"; rm.className = "rm"; rm.textContent = "Quitar";
      rm.onclick = function () { setQty(k, 0); };
      li.append(n, pr, qty, rm);
      lines.appendChild(li);
    });
    var cc = $("cart-count");
    if (String(count) !== cc.textContent) { cc.classList.remove("bump"); void cc.offsetWidth; cc.classList.add("bump"); }
    cc.textContent = count;
    $("cart-empty").hidden = keys.length > 0;
    updateLink();
  }

  function updateLink() {
    var m = message(), a = $("checkout"), has = Object.keys(cart).length > 0;
    $("total").textContent = "$" + money(m.total);
    a.href = "https://wa.me/" + WA + "?text=" + encodeURIComponent(m.text);
    var nat = ship().mode === "nacional";
    $("ship-extra").hidden = !nat;
    $("city-hint").hidden = m.ok;
    a.setAttribute("aria-disabled", has && m.ok ? "false" : "true");
  }

  function setQty(k, q) {
    if (!PRODUCTS[k]) return;
    q = Math.max(0, Math.min(q, PRODUCTS[k].s));
    if (q === 0) delete cart[k]; else cart[k] = q;
    save(); render();
  }

  document.addEventListener("click", function (ev) {
    var b = ev.target.closest(".add");
    if (b) {
      var k = b.getAttribute("data-slug");
      setQty(k, (cart[k] || 0) + 1);
      b.classList.add("done"); b.textContent = "Agregado ✓";
      setTimeout(function () { b.classList.remove("done"); b.textContent = "Agregar al carrito"; }, 1200);
      openCart();
    }
    var go = ev.target.closest("[data-go]");
    if (go) applyFilter(go.getAttribute("data-go"));
  });
  $("open-cart").onclick = openCart;
  $("close-cart").onclick = closeCart;
  scrim.onclick = closeCart;
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && drawer.classList.contains("open")) closeCart(); });
  $("c-name").addEventListener("input", updateLink);
  $("c-city").addEventListener("input", updateLink);
  $("c-courier").addEventListener("change", updateLink);
  document.querySelectorAll('input[name="ship"]').forEach(function (r) { r.addEventListener("change", updateLink); });

  // Filtros y búsqueda del catálogo
  var current = "all";
  var grid = $("catalog-grid");
  function applyFilter(f) {
    current = f;
    document.querySelectorAll(".chip").forEach(function (c) { c.classList.toggle("is-on", c.getAttribute("data-f") === f); });
    filter();
  }
  function filter() {
    var q = clean($("q").value, 60).toLowerCase(), shown = 0;
    grid.querySelectorAll(".card").forEach(function (c) {
      var ok = (current === "all" || c.getAttribute("data-cat") === current) && c.getAttribute("data-name").indexOf(q) !== -1;
      c.hidden = !ok; if (ok) shown++;
    });
    $("empty").hidden = shown > 0;
  }
  document.querySelectorAll(".chip").forEach(function (c) { c.onclick = function () { applyFilter(c.getAttribute("data-f")); }; });
  $("q").addEventListener("input", filter);

  // Efectos tech: partículas en la portada, contador y foco del mouse en tarjetas
  var calm = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  var cv = $("hero-fx");
  if (cv && cv.getContext && !calm) {
    var ctx = cv.getContext("2d"), pts = [], W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    var size = function () {
      W = cv.offsetWidth; H = cv.offsetHeight; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.round(Math.min(70, W * H / 14000)); pts = [];
      for (var i = 0; i < n; i++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .4, vy: (Math.random() - .5) * .4, g: Math.random() < .35 });
    };
    size(); window.addEventListener("resize", size);
    var visible = true;
    if ("IntersectionObserver" in window) new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }).observe(cv);
    (function tick() {
      if (visible) {
        ctx.clearRect(0, 0, W, H);
        for (var i = 0; i < pts.length; i++) {
          var a = pts[i]; a.x += a.vx; a.y += a.vy;
          if (a.x < 0 || a.x > W) a.vx *= -1; if (a.y < 0 || a.y > H) a.vy *= -1;
          for (var j = i + 1; j < pts.length; j++) {
            var b = pts[j], dx = a.x - b.x, dy = a.y - b.y, d = dx * dx + dy * dy;
            if (d < 13000) { ctx.strokeStyle = "rgba(152,120,255," + (0.35 * (1 - d / 13000)) + ")"; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
          }
          ctx.fillStyle = a.g ? "#77dd77" : "#9878ff"; ctx.beginPath(); ctx.arc(a.x, a.y, a.g ? 1.8 : 1.4, 0, 6.3); ctx.fill();
        }
      }
      requestAnimationFrame(tick);
    })();
  }
  if (!calm) document.querySelectorAll("[data-count]").forEach(function (el) {
    var end = parseInt(el.getAttribute("data-count"), 10), t0 = null;
    (function step(t) { if (!t0) t0 = t; var k = Math.min(1, (t - t0) / 1200); el.textContent = Math.round(end * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); })(performance.now());
  });
  document.querySelectorAll(".card").forEach(function (c) {
    c.addEventListener("pointermove", function (e) {
      var r = c.getBoundingClientRect();
      c.style.setProperty("--mx", (e.clientX - r.left) + "px"); c.style.setProperty("--my", (e.clientY - r.top) + "px");
    });
  });

  // Cinta superior: rota los mensajes del embudo (atención, interés, deseo, acción)
  var promo = $("promo");
  if (promo && !calm) {
    var items = promo.querySelectorAll(".promo-item"), pi = 0, paused = false;
    promo.addEventListener("mouseenter", function () { paused = true; });
    promo.addEventListener("mouseleave", function () { paused = false; });
    promo.addEventListener("focusin", function () { paused = true; });
    promo.addEventListener("focusout", function () { paused = false; });
    setInterval(function () {
      if (paused || items.length < 2) return;
      var cur = items[pi]; cur.classList.remove("is-on"); cur.classList.add("is-out");
      setTimeout(function () { cur.classList.remove("is-out"); }, 450);
      pi = (pi + 1) % items.length; items[pi].classList.add("is-on");
    }, 4500);
  }

  render();
})();
