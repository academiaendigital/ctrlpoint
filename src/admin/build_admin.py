#!/usr/bin/env python3
"""Genera el prototipo del panel (admin-prototipo/index.html) a partir de la plantilla y los productos."""
import os
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
tpl = open(os.path.join(HERE, "admin_tpl.html"), encoding="utf-8").read()
data = open(os.path.join(HERE, "admin_products.json"), encoding="utf-8").read()
os.makedirs(os.path.join(ROOT, "admin-prototipo"), exist_ok=True)
open(os.path.join(ROOT, "admin-prototipo", "index.html"), "w", encoding="utf-8").write(tpl.replace("__PRODUCTS__", data))
print("ok")
