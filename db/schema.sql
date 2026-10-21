-- CTRL+ · Base de datos del panel de administración (MySQL 5.7+ / MariaDB 10.3+, HostGator cPanel)
-- Importar en phpMyAdmin: primero este archivo, luego seed_productos.sql.
-- La tienda pública no usa esta base: es estática y los pedidos llegan por WhatsApp.
-- Sin datos personales ni bancarios aquí; las usuarias se crean desde el instalador del panel.

SET NAMES utf8mb4;
SET time_zone = '-04:00'; -- Venezuela

-- Parámetros globales: margen, redondeo, tasas BCV y diferenciales
CREATE TABLE ajustes (
  clave        VARCHAR(40)  NOT NULL PRIMARY KEY,
  valor        VARCHAR(255) NOT NULL,
  descripcion  VARCHAR(160) NULL,
  actualizado  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ajustes (clave, valor, descripcion) VALUES
  ('margen_ganancia', '30',  'Margen global sobre el costo, en %'),
  ('redondeo_usd',    '1',   'Redondeo hacia arriba del precio de venta (1 = dólar entero, 5 = múltiplo de 5)'),
  ('diferencial_bs',  '0',   'Diferencial opcional sobre la tasa BCV para Bs, en %'),
  ('diferencial_eur', '0',   'Diferencial opcional para el precio en euros, en %');

-- Historial de tasas BCV (se registra la del día; cada venta guarda la suya)
CREATE TABLE tasas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fecha       DATE          NOT NULL,
  usd_bs      DECIMAL(12,4) NOT NULL COMMENT 'Bs por 1 USD (BCV)',
  eur_bs      DECIMAL(12,4) NOT NULL COMMENT 'Bs por 1 EUR (BCV)',
  creado      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tasas_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarias del panel (Jenny y Rubén: admin). Contraseña con password_hash() de PHP (bcrypt/argon2).
CREATE TABLE usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(60)  NOT NULL,
  correo        VARCHAR(120) NOT NULL,
  clave_hash    VARCHAR(255) NOT NULL,
  rol           ENUM('admin','ventas','despacho') NOT NULL DEFAULT 'ventas',
  totp_secreto  VARCHAR(64)  NULL COMMENT 'Segundo factor (opcional, recomendado)',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  intentos      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos seguidos',
  bloqueado_hasta DATETIME   NULL,
  ultimo_acceso DATETIME     NULL,
  creado        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
  id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario. Precios base en USD/USDT; venta = costo × (1 + margen) redondeado.
CREATE TABLE productos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(20)  NOT NULL,
  slug          VARCHAR(80)  NOT NULL,
  nombre        VARCHAR(160) NOT NULL,
  categoria_id  SMALLINT UNSIGNED NOT NULL,
  costo_usd     DECIMAL(10,2) NOT NULL,
  margen        DECIMAL(5,2) NULL COMMENT 'Margen propio en %; NULL = usa el global',
  stock         INT UNSIGNED NOT NULL DEFAULT 0,
  stock_minimo  INT UNSIGNED NOT NULL DEFAULT 1,
  activo        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = oculto en la tienda',
  creado        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_productos_sku (sku),
  UNIQUE KEY uq_productos_slug (slug),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entradas y salidas de stock (compras, ventas confirmadas, ajustes)
CREATE TABLE movimientos_stock (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  producto_id  INT UNSIGNED NOT NULL,
  cantidad     INT          NOT NULL COMMENT 'Positivo = entrada, negativo = salida',
  motivo       ENUM('compra','venta','ajuste','devolucion') NOT NULL,
  venta_id     INT UNSIGNED NULL,
  nota         VARCHAR(160) NULL,
  usuario_id   INT UNSIGNED NULL,
  creado       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mov_producto (producto_id),
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos (llegan por WhatsApp y se cargan en el panel). Código visible: CP-0001.
CREATE TABLE ventas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo        VARCHAR(12)  NOT NULL,
  cliente       VARCHAR(80)  NOT NULL,
  telefono      VARCHAR(20)  NULL,
  estado        ENUM('Nuevo','Esperando pago','Pago verificado','Preparado','Despachado','Entregado','Cancelado') NOT NULL DEFAULT 'Nuevo',
  total_usd     DECIMAL(10,2) NOT NULL DEFAULT 0,
  tasa_usd_bs   DECIMAL(12,4) NULL COMMENT 'Tasa BCV fijada al confirmar el pago',
  total_bs      DECIMAL(14,2) NULL,
  envio_tipo    ENUM('retiro','nacional') NOT NULL DEFAULT 'retiro',
  envio_ciudad  VARCHAR(60)  NULL,
  envio_empresa ENUM('Zoom','MRW','Tealca') NULL,
  nota          VARCHAR(255) NULL,
  creado_por    INT UNSIGNED NULL,
  creado        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ventas_codigo (codigo),
  KEY ix_ventas_estado (estado),
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renglones del pedido con el precio del momento (no cambia si luego se edita el producto)
CREATE TABLE venta_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  venta_id         INT UNSIGNED NOT NULL,
  producto_id      INT UNSIGNED NOT NULL,
  cantidad         INT UNSIGNED NOT NULL,
  precio_unit_usd  DECIMAL(10,2) NOT NULL,
  costo_unit_usd   DECIMAL(10,2) NOT NULL,
  KEY ix_items_venta (venta_id),
  CONSTRAINT fk_items_venta    FOREIGN KEY (venta_id)    REFERENCES ventas (id) ON DELETE CASCADE,
  CONSTRAINT fk_items_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagos: Pendiente → Recibido (con referencia) → Confirmado / Rechazado
CREATE TABLE pagos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  venta_id       INT UNSIGNED NOT NULL,
  metodo         ENUM('Pago Móvil','Binance','PayPal','Transferencia','Efectivo') NOT NULL,
  estado         ENUM('Pendiente','Recibido','Confirmado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  referencia     VARCHAR(40)  NULL,
  monto          DECIMAL(14,2) NULL,
  moneda         ENUM('USD','USDT','VES','EUR') NOT NULL DEFAULT 'USD',
  confirmado_por INT UNSIGNED NULL,
  confirmado_en  DATETIME     NULL,
  creado         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pagos_venta (venta_id),
  KEY ix_pagos_estado (estado),
  CONSTRAINT fk_pagos_venta   FOREIGN KEY (venta_id)       REFERENCES ventas (id) ON DELETE CASCADE,
  CONSTRAINT fk_pagos_usuario FOREIGN KEY (confirmado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guías de envío internas (CP-G-0001). El número de rastreo oficial lo da Zoom/MRW/Tealca.
CREATE TABLE guias (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  venta_id         INT UNSIGNED NOT NULL,
  numero           VARCHAR(14)  NOT NULL,
  empresa          ENUM('Zoom','MRW','Tealca') NOT NULL,
  rastreo_empresa  VARCHAR(40)  NULL,
  dest_nombre      VARCHAR(80)  NOT NULL,
  dest_cedula      VARCHAR(15)  NULL,
  dest_telefono    VARCHAR(20)  NULL,
  dest_estado      VARCHAR(30)  NOT NULL,
  dest_ciudad      VARCHAR(60)  NOT NULL,
  dest_direccion   VARCHAR(200) NULL COMMENT 'Oficina de la empresa o dirección',
  modalidad_pago   ENUM('Cobro a destino','Pagado en origen') NOT NULL DEFAULT 'Cobro a destino',
  bultos           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  peso_kg          DECIMAL(6,2) NULL,
  creado_por       INT UNSIGNED NULL,
  creado           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_guias_numero (numero),
  KEY ix_guias_venta (venta_id),
  CONSTRAINT fk_guias_venta   FOREIGN KEY (venta_id)   REFERENCES ventas (id) ON DELETE CASCADE,
  CONSTRAINT fk_guias_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de auditoría: quién hizo qué y cuándo
CREATE TABLE bitacora (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  accion      VARCHAR(255) NOT NULL,
  ip          VARCHAR(45)  NULL,
  creado      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bitacora_fecha (creado),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Precio de venta calculado (margen propio o global, redondeado hacia arriba)
CREATE OR REPLACE VIEW v_precios AS
SELECT p.id, p.sku, p.nombre, c.nombre AS categoria, p.costo_usd, p.stock, p.stock_minimo,
       COALESCE(p.margen, CAST(mg.valor AS DECIMAL(5,2))) AS margen_aplicado,
       CEIL(ROUND(p.costo_usd * (1 + COALESCE(p.margen, CAST(mg.valor AS DECIMAL(5,2))) / 100), 2)
            / CAST(rd.valor AS DECIMAL(6,2))) * CAST(rd.valor AS DECIMAL(6,2)) AS precio_venta_usd,
       (p.stock <= p.stock_minimo) AS stock_bajo
FROM productos p
JOIN categorias c ON c.id = p.categoria_id
JOIN ajustes mg ON mg.clave = 'margen_ganancia'
JOIN ajustes rd ON rd.clave = 'redondeo_usd'
WHERE p.activo = 1;
