# Vectoriza el logo PNG a un trazado SVG (pip install potracer).
# Uso: python3 src/brand/trace_logo.py src/brand/CTRL_Moradito.png > salida.txt  (1.ª línea: ancho alto; 2.ª: trazado)
import sys, numpy as np, potrace
from PIL import Image
im = Image.open(sys.argv[1]).convert("RGBA"); W,H = im.size
a = np.array(im)[:,:,3] <= 127
bm = potrace.Bitmap(a)
path = bm.trace(turdsize=4, alphamax=1.0, opticurve=True, opttolerance=0.2)
def f(v): return f"{v:.1f}".rstrip('0').rstrip('.')
d=[]
for c in path:
    s=c.start_point; d.append(f"M{f(s.x)} {f(s.y)}")
    for seg in c.segments:
        if seg.is_corner: d.append(f"L{f(seg.c.x)} {f(seg.c.y)}L{f(seg.end_point.x)} {f(seg.end_point.y)}")
        else: d.append(f"C{f(seg.c1.x)} {f(seg.c1.y)} {f(seg.c2.x)} {f(seg.c2.y)} {f(seg.end_point.x)} {f(seg.end_point.y)}")
    d.append("Z")
print(f'{W} {H}\n'+"".join(d))
