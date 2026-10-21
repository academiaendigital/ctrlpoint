"""Genera db/seed_productos.sql con las categorías y productos de src/admin/admin_products.json."""
import json, os

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
P = json.load(open(os.path.join(ROOT, "src", "admin", "admin_products.json"), encoding="utf-8"))

def q(s):
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

cats = sorted({p["c"] for p in P})
out = ["-- CTRL+ · Inventario inicial (costos del Excel \"Total Stock.xlsx\"). Generado por db/build_seed.py.",
       "SET NAMES utf8mb4;", "",
       "INSERT INTO categorias (nombre) VALUES " + ", ".join(f"({q(c)})" for c in cats) + ";", "",
       "INSERT INTO productos (sku, slug, nombre, categoria_id, costo_usd, margen, stock, stock_minimo) VALUES"]
rows = [f"  ({q(p['sku'])}, {q(p['slug'])}, {q(p['n'])}, (SELECT id FROM categorias WHERE nombre = {q(p['c'])}), "
        f"{p['cost']:.2f}, {'NULL' if p['m'] is None else p['m']}, {p['st']}, {p['min']})" for p in P]
out.append(",\n".join(rows) + ";")
out += ["", "INSERT INTO movimientos_stock (producto_id, cantidad, motivo, nota)",
        "SELECT id, stock, 'compra', 'Inventario inicial' FROM productos WHERE stock > 0;", ""]
open(os.path.join(ROOT, "db", "seed_productos.sql"), "w", encoding="utf-8").write("\n".join(out))
print("ok", len(P), "productos,", len(cats), "categorías")
