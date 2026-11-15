// Panel CTRL+ · scripts sin dependencias (CSP: solo 'self', sin JS en línea)
(function () {
  'use strict';

  // Vista previa del precio de venta en la ficha de producto (la regla real se aplica en el servidor)
  var calc = document.getElementById('calc');
  if (calc) {
    var costo = document.getElementById('costo_usd');
    var margen = document.getElementById('margen');
    var pv = document.getElementById('pv');
    var pvbs = document.getElementById('pvbs');
    var num = function (v) { return parseFloat(String(v).replace(',', '.')); };
    var fmt = function (n, pre) { return pre + n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var actualizar = function () {
      var c = num(costo.value);
      if (isNaN(c)) { pv.textContent = '—'; pvbs.textContent = ''; return; }
      var m = margen.value.trim() === '' ? num(calc.dataset.margen) : num(margen.value);
      var r = num(calc.dataset.redondeo) || 1;
      var bruto = Math.round(c * (1 + m / 100) * 100) / 100;
      var precio = Math.round(Math.ceil(Math.round(bruto / r * 1e6) / 1e6) * r * 100) / 100;
      pv.textContent = fmt(precio, '$');
      var t = num(calc.dataset.tasa);
      pvbs.textContent = isNaN(t) ? '' : fmt(Math.round(precio * t * (1 + (num(calc.dataset.dif) || 0) / 100) * 100) / 100, 'Bs ');
    };
    costo.addEventListener('input', actualizar);
    margen.addEventListener('input', actualizar);
  }

  // Rótulo de guía: QR y botón de imprimir
  var qr = document.querySelector('[data-qr]');
  if (qr && typeof qrcode === 'function') {
    var q = qrcode(0, 'M');
    q.addData(qr.getAttribute('data-qr'));
    q.make();
    var svg = new DOMParser().parseFromString(q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }), 'image/svg+xml').documentElement;
    qr.appendChild(document.importNode(svg, true));
  }
  var imprimir = document.getElementById('imprimir');
  if (imprimir) imprimir.addEventListener('click', function () { window.print(); });
})();
