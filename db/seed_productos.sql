-- CTRL+ · Inventario inicial (costos del Excel "Total Stock.xlsx"; lote nov-2026 con envío prorrateado). Generado por db/build_seed.py.
SET NAMES utf8mb4;

INSERT INTO categorias (nombre) VALUES ('Almacenamiento'), ('Audio'), ('Iluminación'), ('Tabletas gráficas'), ('Teclados'), ('Trípodes y soportes');

INSERT INTO productos (sku, slug, nombre, categoria_id, costo_usd, margen, stock, stock_minimo) VALUES
  ('CP-001', 'ulanzi-mt-78-tripode', 'ULANZI MT-78 Trípode extensible de metal', (SELECT id FROM categorias WHERE nombre = 'Trípodes y soportes'), 70.00, NULL, 2, 1),
  ('CP-002', 'kf-concept-sp25c', 'K&F Concept SP25C Linterna RGB 25 W', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 90.00, NULL, 1, 1),
  ('CP-003', 'neewer-fl10', 'NEEWER BASICS FL10 Linterna 10 W', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 70.00, NULL, 3, 1),
  ('CP-004', 'neewer-tl96-rgb', 'NEEWER BASICS TL96 RGB Varilla magnética', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 95.00, NULL, 1, 1),
  ('CP-005', 'neewer-gc30c', 'NEEWER BASICS GC30C 30 W RGB inflable', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 120.00, NULL, 1, 1),
  ('CP-006', 'neewer-sl30', 'NEEWER SL30 Luz LED', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 40.00, NULL, 2, 1),
  ('CP-007', 'neewer-gc21b', 'NEEWER BASICS GC21B 21 W bicolor inflable', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 80.00, NULL, 1, 1),
  ('CP-008', 'neewer-gc10b', 'NEEWER GC10B Luz 10 W', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 45.00, NULL, 1, 1),
  ('CP-009', 'neewer-tl21c-rgb', 'NEEWER TL21C RGB', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 110.00, NULL, 1, 1),
  ('CP-010', 'neewer-rgb62', 'NEEWER RGB62 magnética con app', (SELECT id FROM categorias WHERE nombre = 'Iluminación'), 85.00, NULL, 1, 1),
  ('CP-011', 'crucial-p310-1tb', 'Crucial P310 SSD 1 TB M.2 2230', (SELECT id FROM categorias WHERE nombre = 'Almacenamiento'), 275.00, NULL, 1, 1),
  ('CP-012', 'ulanzi-ma55-carcasa-ssd', 'ULANZI MA55 Carcasa magnética para SSD', (SELECT id FROM categorias WHERE nombre = 'Almacenamiento'), 65.00, NULL, 1, 1),
  ('CP-013', 'xp-pen-deco-01-v3', 'XP-PEN Deco 01 V3 Tableta gráfica', (SELECT id FROM categorias WHERE nombre = 'Tabletas gráficas'), 75.00, NULL, 1, 1),
  ('CP-014', 'xp-pen-deco-pro-mw-2', 'XP-PEN Deco Pro MW 2.ª gen Tableta gráfica', (SELECT id FROM categorias WHERE nombre = 'Tabletas gráficas'), 150.00, NULL, 1, 1),
  ('CP-015', 'combo-dji-mic-mini-2-cubierta', 'Combo DJI Mic Mini 2 + cubierta magnética multicolor', (SELECT id FROM categorias WHERE nombre = 'Audio'), 118.12, NULL, 2, 1),
  ('CP-016', 'qkz-file-blanco', 'Audífonos QKZ FiLe con cable (blanco)', (SELECT id FROM categorias WHERE nombre = 'Audio'), 5.46, NULL, 2, 1),
  ('CP-017', 'qkz-file-negro', 'Audífonos QKZ FiLe con cable (negro)', (SELECT id FROM categorias WHERE nombre = 'Audio'), 5.70, NULL, 2, 1),
  ('CP-018', 'qkz-file-purpura', 'Audífonos QKZ FiLe con cable (púrpura)', (SELECT id FROM categorias WHERE nombre = 'Audio'), 5.92, NULL, 2, 1),
  ('CP-019', 'ajazz-nk61', 'Teclado mecánico AJAZZ NK61 60 % LED (negro)', (SELECT id FROM categorias WHERE nombre = 'Teclados'), 46.36, NULL, 1, 1),
  ('CP-020', 'ajazz-ak820', 'Teclado mecánico gaming AJAZZ AK820 75 % (negro)', (SELECT id FROM categorias WHERE nombre = 'Teclados'), 33.38, NULL, 1, 1),
  ('CP-021', 'ajazz-ak992', 'Teclado mecánico retro AJAZZ AK992 100 % (gris)', (SELECT id FROM categorias WHERE nombre = 'Teclados'), 56.53, NULL, 2, 1);

INSERT INTO movimientos_stock (producto_id, cantidad, motivo, nota)
SELECT id, stock, 'compra', 'Inventario inicial' FROM productos WHERE stock > 0;
