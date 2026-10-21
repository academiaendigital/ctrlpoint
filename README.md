# CTRL+

Tienda virtual de equipo para foto, video y creación de contenido en San Juan de los Morros, Guárico (Venezuela). Los pedidos se envían por WhatsApp.

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `web/` | Sitio publicable (estático): `index.html`, `styles.css`, `app.js`, `robots.txt`, `sitemap.xml`, `_headers` (cabeceras para Cloudflare Pages o Netlify; en Vercel las aplica `vercel.json`). |
| `src/tienda/` | Fuente de la tienda. `build.py` contiene el catálogo y genera `web/`. |
| `admin-prototipo/` | Prototipo navegable del panel de administración (fase 2). Datos en memoria, solo para demostración. |
| `src/admin/` | Fuente del prototipo del panel. |

## Uso

```bash
python3 src/tienda/build.py        # regenera web/ y build/preview.html
python3 src/admin/build_admin.py   # regenera admin-prototipo/index.html
```

Para verla en local, abre `web/index.html` en el navegador.

## Publicar en Vercel

`vercel.json` publica la carpeta `web/` sin paso de compilación y aplica las cabeceras de seguridad.

1. Entra en https://vercel.com/new e inicia sesión con GitHub.
2. Importa `academiaendigital/ctrlpoint` y pulsa **Deploy** (Vercel lee `vercel.json`; no cambies nada).
3. Cada push a `main` publica producción y cada pull request genera su propia URL de vista previa.

## Publicar en HostGator (hosting compartido)

1. En cPanel, abre el **Administrador de archivos** y entra a `public_html`.
2. Sube **todo el contenido** de `web/`, incluido `.htaccess` (activa "Mostrar archivos ocultos").
3. Activa el SSL gratuito del dominio en cPanel → **SSL/TLS Status**. El `.htaccess` ya fuerza HTTPS y aplica las cabeceras de seguridad.
4. Cambia `DOMAIN` en `src/tienda/build.py` por el dominio real y vuelve a generar `web/`.

## Base de datos del panel (MySQL)

La tienda no usa base de datos. El panel de administración sí:

1. En cPanel → **Bases de datos MySQL**, crea la base y un usuario con contraseña fuerte, y asígnale todos los privilegios.
2. En **phpMyAdmin**, importa `db/schema.sql` y después `db/seed_productos.sql` (14 productos con su costo y stock).
3. La vista `v_precios` calcula el precio de venta (costo + margen, redondeado hacia arriba). Coincide con los precios de la tienda.
4. Las credenciales de la base van en un archivo de configuración **fuera** de `public_html`; nunca en el repositorio.

Si cambia el inventario en `src/admin/admin_products.json`, regenera la carga inicial con `python3 db/build_seed.py`.

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
