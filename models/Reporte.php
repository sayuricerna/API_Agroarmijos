<?php
require_once __DIR__ . '/Movimiento.php';

class Reporte {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function ventasPorFecha($desde, $hasta) {
        $query = "SELECT
                    v.id_venta,
                    v.fecha,
                    v.subtotal,
                    v.descuento,
                    v.iva,
                    v.total,
                    v.estado,
                    CONCAT(c.nombres) AS cliente,
                    CONCAT(u.nombres, ' ', u.apellidos) AS vendedor
                  FROM ventas v
                  INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                  INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                  WHERE DATE(v.fecha) BETWEEN :desde AND :hasta
                    AND v.estado != 'ANULADA'
                  ORDER BY v.fecha DESC, v.id_venta DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function comprasPorFecha($desde, $hasta) {
        $query = "SELECT
                    c.id_compra,
                    c.numero_factura,
                    c.fecha,
                    c.subtotal,
                    c.descuento,
                    c.iva,
                    c.total,
                    c.estado,
                    p.razon_social AS proveedor,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario
                  FROM compras c
                  INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
                  INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
                  WHERE DATE(c.fecha) BETWEEN :desde AND :hasta
                    AND c.estado != 'ANULADA'
                  ORDER BY c.fecha DESC, c.id_compra DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Productos cuyo stock_actual ya llegó o cayó por debajo de su
     * stock_minimo configurado (incluye tanto stock bajo como agotado
     * en 0 — a diferencia de la alerta "Stock bajo" del Dashboard, que
     * desde LB-019 separa ambas categorías, este reporte las junta a
     * propósito: es una lista de "todo lo que necesita reposición",
     * fotografía del estado actual del inventario, no un histórico).
     */
    public function stockCritico() {
        $query = "SELECT
                    p.id_producto,
                    p.codigo_interno,
                    p.nombre,
                    cat.nombre AS categoria,
                    m.nombre AS marca,
                    u.bodega,
                    u.pasillo,
                    u.estanteria,
                    p.stock_actual,
                    p.stock_disponible,
                    p.stock_reservado,
                    p.stock_minimo,
                    (p.stock_minimo - p.stock_actual) AS unidades_faltantes
                  FROM productos p
                  INNER JOIN categorias cat ON p.id_categoria = cat.id_categoria
                  INNER JOIN marcas m ON p.id_marca = m.id_marca
                  INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
                  WHERE p.estado = 1
                    AND p.stock_actual <= p.stock_minimo
                  ORDER BY (p.stock_actual - p.stock_minimo) ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Ranking de productos por unidades vendidas en el período (suma de
     * detalle_ventas, no cantidad de ventas). Se ordena de mayor a menor
     * cantidad vendida: el frontend usa el mismo listado para "más
     * vendidos" (el principio) y "menos vendidos" (el final), sin
     * necesidad de dos consultas separadas.
     */
    public function productosPorFecha($desde, $hasta) {
        $query = "SELECT
                    pr.id_producto,
                    pr.codigo_interno,
                    pr.nombre AS producto,
                    cat.nombre AS categoria,
                    m.nombre AS marca,
                    SUM(dv.cantidad) AS cantidad_vendida,
                    SUM(dv.subtotal) AS total_vendido
                  FROM detalle_ventas dv
                  INNER JOIN ventas v ON dv.id_venta = v.id_venta
                  INNER JOIN productos pr ON dv.id_producto = pr.id_producto
                  INNER JOIN categorias cat ON pr.id_categoria = cat.id_categoria
                  INNER JOIN marcas m ON pr.id_marca = m.id_marca
                  WHERE DATE(v.fecha) BETWEEN :desde AND :hasta
                    AND v.estado != 'ANULADA'
                  GROUP BY pr.id_producto, pr.codigo_interno, pr.nombre, cat.nombre, m.nombre
                  ORDER BY cantidad_vendida DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Ranking de clientes por monto comprado en el período (ventas no
     * anuladas). Incluye fecha de la última compra para dar contexto de
     * qué tan reciente es la relación con ese cliente.
     */
    public function clientesPorFecha($desde, $hasta) {
        $query = "SELECT
                    c.id_cliente,
                    c.nombres AS cliente,
                    c.numero_documento,
                    COUNT(v.id_venta) AS cantidad_compras,
                    SUM(v.total) AS total_comprado,
                    MAX(v.fecha) AS ultima_compra
                  FROM ventas v
                  INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                  WHERE DATE(v.fecha) BETWEEN :desde AND :hasta
                    AND v.estado != 'ANULADA'
                  GROUP BY c.id_cliente, c.nombres, c.numero_documento
                  ORDER BY total_comprado DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Ranking de proveedores por monto comprado en el período (compras no
     * anuladas). Mismo criterio que clientesPorFecha(), del otro lado de
     * la relación comercial.
     */
    public function proveedoresPorFecha($desde, $hasta) {
        $query = "SELECT
                    p.id_proveedor,
                    p.razon_social AS proveedor,
                    COUNT(c.id_compra) AS cantidad_compras,
                    SUM(c.total) AS total_comprado,
                    MAX(c.fecha) AS ultima_compra
                  FROM compras c
                  INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
                  WHERE DATE(c.fecha) BETWEEN :desde AND :hasta
                    AND c.estado != 'ANULADA'
                  GROUP BY p.id_proveedor, p.razon_social
                  ORDER BY total_comprado DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Kárdex/movimientos de inventario como reporte exportable: mismos
     * campos y mismo mapa de tipos (Movimiento::mapaTipos()) que ya usa
     * la pantalla de Movimientos, pero sin paginar — aquí se trae todo el
     * rango de fechas de una vez, porque va a un archivo, no a una tabla
     * en pantalla.
     */
    public function kardexPorFecha($desde, $hasta) {
        $query = "SELECT
                    m.id_movimiento,
                    m.fecha,
                    p.codigo_interno,
                    p.nombre AS producto,
                    tm.nombre AS tipo_movimiento,
                    tm.descripcion AS tipo_movimiento_descripcion,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario,
                    m.observacion
                  FROM movimientos m
                  INNER JOIN productos p ON m.id_producto = p.id_producto
                  INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  WHERE DATE(m.fecha) BETWEEN :desde AND :hasta
                  ORDER BY m.fecha DESC, m.id_movimiento DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = Movimiento::mapaTipos();
        foreach ($movimientos as &$mov) {
            $info = $mapa[$mov['tipo_movimiento']] ?? null;
            $mov['tipo_etiqueta'] = $info['etiqueta'] ?? ($mov['tipo_movimiento_descripcion'] ?: $mov['tipo_movimiento']);
            $mov['tipo_general'] = $info['general']
                ?? (((float) $mov['stock_nuevo'] >= (float) $mov['stock_anterior']) ? 'ENTRADA' : 'SALIDA');
        }

        return $movimientos;
    }
}