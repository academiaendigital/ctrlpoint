(function () {
  "use strict";
  var PRODUCTS = __PRODUCTS__;
  var WA = "__WA__";
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
    var name = clean($("c-name").value, 60), zone = clean($("c-zone").value, 80);
    if (name) out.push("Nombre: " + name);
    if (zone) out.push("Zona de entrega: " + zone);
    return { text: out.join("\n"), total: total };
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
    a.setAttribute("aria-disabled", has ? "false" : "true");
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
  $("c-zone").addEventListener("input", updateLink);

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
            if (d < 13000) { ctx.strokeStyle = "rgba(171,73,204," + (0.35 * (1 - d / 13000)) + ")"; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
          }
          ctx.fillStyle = a.g ? "#77dd77" : "#ab49cc"; ctx.beginPath(); ctx.arc(a.x, a.y, a.g ? 1.8 : 1.4, 0, 6.3); ctx.fill();
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
