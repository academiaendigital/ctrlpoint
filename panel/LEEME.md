# Panel CTRL+ (PHP + MySQL para HostGator)

Panel privado para Jenny y Rubén: inventario, costos, precio de venta, ventas con flujo de pago y guía de envío nacional con QR. La tienda pública sigue siendo estática; el panel no publica nada en ella.

## Qué hace

| Sección | Qué permite |
|---|---|
| Inicio | Ventas y ganancia del mes, inventario a costo, tasa BCV vigente, pagos por confirmar, envíos sin guía, stock bajo |
| Inventario | Productos con costo, margen (global o propio), precio de venta USD y Bs, stock y stock mínimo. Entradas/salidas con historial. Exportar catálogo CSV **sin costos** para la tienda |
| Precios y tasa | Margen global (30 % inicial), redondeo hacia arriba ($1, $5, $10), diferencial Bs/EUR, tasa BCV del día |
| Ventas | Pedido de WhatsApp → pago Pendiente → Recibido (referencia) → Confirmado (descuenta stock y fija la tasa) o Rechazado. Estados Preparado → Despachado → Entregado. Cancelar devuelve el stock |
| Guía | Envíos nacionales Zoom/MRW/Tealca con pago confirmado: guía interna `CP-G-0001` y rótulo 10×15 cm con QR. No se puede marcar «Despachado» sin guía. Campo para el n.º de rastreo oficial |
| Usuarios | Cambiar contraseña, crear cuentas (admin/ventas/despacho), desactivar, bitácora de auditoría |

**Precio de venta** = costo × (1 + margen ÷ 100), redondeado hacia arriba al dólar (o múltiplo elegido). **Bs** = precio USD × tasa BCV × (1 + diferencial ÷ 100). Cada venta guarda el precio y el costo del momento.

## Seguridad incluida

- Contraseñas con `password_hash()` (bcrypt), mínimo 10 caracteres; bloqueo 15 min tras 5 intentos fallidos.
- Sesión con cookie `HttpOnly`, `Secure`, `SameSite=Strict`, ID regenerado al entrar, cierre tras 30 min sin uso.
- Token CSRF en todos los formularios y verificación de origen; cierre de sesión solo por POST.
- Consultas preparadas (PDO sin emulación) y escape de toda salida HTML.
- CSP estricta sin JavaScript en línea, `X-Frame-Options: DENY`, `noindex` (no aparece en Google).
- Stock con transacciones y bloqueo de filas: no se puede vender la misma unidad dos veces.
- Sin datos bancarios en el panel ni en la tienda; el QR del rótulo solo lleva códigos internos.
- Bitácora de quién hizo qué (precios, pagos, stock, accesos).

## Instalar en HostGator (cPanel)

1. **PHP**: cPanel → *MultiPHP Manager* → PHP 8.1 o superior para el dominio.
2. **SSL**: cPanel → *SSL/TLS Status* → activar AutoSSL. El panel exige HTTPS.
3. **Base de datos**: cPanel → *Bases de datos MySQL* → crear base (ej. `usuario_ctrlplus`) y usuario con contraseña larga; asignarle solo SELECT, INSERT, UPDATE, DELETE (y CREATE VIEW solo para importar).
4. **Importar**: phpMyAdmin → la base → *Importar* `db/schema.sql` y luego `db/seed_productos.sql`.
5. **Subir el panel**: en el *Administrador de archivos* subir el contenido de `panel/` a `public_html/panel/` (o a un subdominio, ej. `panel.tudominio.com`, recomendado).
6. **Configuración**: copiar `panel/app/config.example.php` como `/home/TU_USUARIO/ctrlplus-config.php` (fuera de `public_html`, recomendado) o como `panel/app/config.php`. Completar los datos de la base y poner una `clave_instalacion` de 16+ caracteres.
7. **Crear las cuentas**: abrir `https://tudominio.com/panel/instalar.php`, escribir la clave de instalación y crear las cuentas de Jenny y Rubén.
8. **Cerrar la instalación**: borrar `instalar.php` del servidor y dejar `clave_instalacion` vacía. (Aunque quede, deja de funcionar en cuanto existe una cuenta.)
9. **Uso diario**: registrar la tasa BCV del día en *Precios y tasa* (copiada de bcv.org.ve) antes de confirmar pagos.

## Probar en tu PC

```bash
mysql -e "CREATE DATABASE ctrl CHARACTER SET utf8mb4"
mysql ctrl < db/schema.sql && mysql ctrl < db/seed_productos.sql
cp panel/app/config.example.php panel/app/config.php   # editar datos y poner forzar_https => false
php -S 127.0.0.1:8080 -t panel
```

## Archivos

- `app/` (bloqueado desde la web): arranque y seguridad (`bootstrap.php`), reglas de precio (`precios.php`), lógica de ventas, pagos y guías (`ventas.php`), plantilla (`vista.php`).
- `assets/qrcode.js`: generador de QR de Kazuhiko Arase (licencia MIT), servido localmente.
