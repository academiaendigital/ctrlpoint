# CTRL POINT

Tienda virtual de equipo para foto, video y creación de contenido en San Juan de los Morros, Guárico (Venezuela). Los pedidos se envían por WhatsApp.

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `web/` | Sitio publicable (estático): `index.html`, `styles.css`, `app.js`, `robots.txt`, `sitemap.xml`, `_headers` (cabeceras de seguridad para Cloudflare Pages o Netlify). |
| `src/tienda/` | Fuente de la tienda. `build.py` contiene el catálogo y genera `web/`. |
| `admin-prototipo/` | Prototipo navegable del panel de administración (fase 2). Datos en memoria, solo para demostración. |
| `src/admin/` | Fuente del prototipo del panel. |

## Uso

```bash
python3 src/tienda/build.py        # regenera web/ y build/preview.html
python3 src/admin/build_admin.py   # regenera admin-prototipo/index.html
```

Para verla en local, abre `web/index.html` en el navegador.

## Reglas del catálogo

- Los precios del inventario son de **costo**. Precio de venta = costo + 30 %, redondeado hacia arriba al dólar entero.
- Bs = precio USDT × tasa BCV (diferencial opcional, configurable en el panel).

## Seguridad

- No se publican datos bancarios en la web ni en este repositorio: se envían por WhatsApp al confirmar el pedido.
- `web/_headers` define CSP, HSTS, X-Frame-Options, Referrer-Policy y Permissions-Policy.

## Pendiente antes de publicar

- Dominio definitivo: reemplazar `https://www.tudominio.com` en `src/tienda/build.py`.
- Fotos de producto, Instagram y zona de entrega.
- Imagen `og.jpg` (1200×630) para vista previa en redes.
