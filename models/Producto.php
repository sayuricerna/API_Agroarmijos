<?php
class Producto {
    private $conn;
    private $table_name = "productos";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT p.*, c.nombre AS categoria, m.nombre AS marca, u.bodega, u.pasillo, u.estanteria
                  FROM " . $this->table_name . " p
                  INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                  INNER JOIN marcas m ON p.id_marca = m.id_marca
                  INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
                  WHERE p.estado = 1 
                  ORDER BY p.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBySearch($term) {
        $query = "SELECT p.*, c.nombre AS categoria, m.nombre AS marca, u.bodega, u.pasillo, u.estanteria
                  FROM " . $this->table_name . " p
                  INNER JOIN categorias c ON p.id_categoria = c.id_categoria
                  INNER JOIN marcas m ON p.id_marca = m.id_marca
                  INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
                  WHERE p.estado = 1 AND (p.codigo_interno = :term OR p.codigo_barras = :term OR p.nombre LIKE :likeTerm)";
        
        $stmt = $this->conn->prepare($query);
        $likeTerm = "%" . $term . "%";
        $stmt->bindParam(":term", $term);
        $stmt->bindParam(":likeTerm", $likeTerm);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_categoria, id_marca, id_ubicacion, codigo_interno, codigo_fabrica, codigo_barras, nombre, descripcion, modelo, unidad_medida, precio_compra, precio_venta, stock_minimo) 
                  VALUES 
                  (:id_categoria, :id_marca, :id_ubicacion, :codigo_interno, :codigo_fabrica, :codigo_barras, :nombre, :descripcion, :modelo, :unidad_medida, :precio_compra, :precio_venta, :stock_minimo)";
        
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id_categoria", $data['id_categoria']);
        $stmt->bindParam(":id_marca", $data['id_marca']);
        $stmt->bindParam(":id_ubicacion", $data['id_ubicacion']);
        $stmt->bindParam(":codigo_interno", $data['codigo_interno']);
        $stmt->bindParam(":codigo_fabrica", $data['codigo_fabrica']);
        $stmt->bindParam(":codigo_barras", $data['codigo_barras']);
        $stmt->bindParam(":nombre", $data['nombre']);
        $stmt->bindParam(":descripcion", $data['descripcion']);
        $stmt->bindParam(":modelo", $data['modelo']);
        $stmt->bindParam(":unidad_medida", $data['unidad_medida']);
        $stmt->bindParam(":precio_compra", $data['precio_compra']);
        $stmt->bindParam(":precio_venta", $data['precio_venta']);
        $stmt->bindParam(":stock_minimo", $data['stock_minimo']);

        return $stmt->execute();
    }

    public function ajustarStock($id_producto, $id_tipo_movimiento, $id_usuario, $cantidad, $observacion) {
        try {
            $this->conn->beginTransaction();

            $queryProd = "SELECT stock_actual, version FROM " . $this->table_name . " WHERE id_producto = :id FOR UPDATE";
            $stmtProd = $this->conn->prepare($queryProd);
            $stmtProd->bindParam(":id", $id_producto);
            $stmtProd->execute();
            $producto = $stmtProd->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                throw new Exception("El producto no existe.");
            }

            $stock_anterior = $producto['stock_actual'];
            
            if (in_array($id_tipo_movimiento, [1, 3, 8])) { // Entradas
                $stock_nuevo = $stock_anterior + $cantidad;
            } else { 
                $stock_nuevo = $stock_anterior - $cantidad;
            }

            $queryUpdate = "UPDATE " . $this->table_name . " 
                            SET stock_actual = :stock_nuevo, stock_disponible = :stock_nuevo - stock_reservado
                            WHERE id_producto = :id";
            $stmtUpdate = $this->conn->prepare($queryUpdate);
            $stmtUpdate->bindParam(":stock_nuevo", $stock_nuevo);
            $stmtUpdate->bindParam(":id", $id_producto);
            $stmtUpdate->execute();

            $querySP = "CALL sp_registrar_movimiento(:prod, :tipo, :user, NULL, NULL, NULL, :cant, :ant, :nue, :obs)";
            $stmtSP = $this->conn->prepare($querySP);
            $stmtSP->bindParam(":prod", $id_producto);
            $stmtSP->bindParam(":tipo", $id_tipo_movimiento);
            $stmtSP->bindParam(":user", $id_usuario);
            $stmtSP->bindParam(":cant", $cantidad);
            $stmtSP->bindParam(":ant", $stock_anterior);
            $stmtSP->bindParam(":nue", $stock_nuevo);
            $stmtSP->bindParam(":obs", $observacion);
            $stmtSP->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    public function update($id, $data) {
    $query = "UPDATE " . $this->table_name . "
              SET
                id_categoria = :id_categoria,
                id_marca = :id_marca,
                id_ubicacion = :id_ubicacion,
                codigo_interno = :codigo_interno,
                codigo_fabrica = :codigo_fabrica,
                codigo_barras = :codigo_barras,
                nombre = :nombre,
                descripcion = :descripcion,
                modelo = :modelo,
                unidad_medida = :unidad_medida,
                precio_compra = :precio_compra,
                precio_venta = :precio_venta,
                stock_minimo = :stock_minimo,
                version = version + 1
              WHERE id_producto = :id_producto";

    $stmt = $this->conn->prepare($query);

    $stmt->bindParam(":id_categoria", $data['id_categoria']);
    $stmt->bindParam(":id_marca", $data['id_marca']);
    $stmt->bindParam(":id_ubicacion", $data['id_ubicacion']);
    $stmt->bindParam(":codigo_interno", $data['codigo_interno']);
    $stmt->bindParam(":codigo_fabrica", $data['codigo_fabrica']);
    $stmt->bindParam(":codigo_barras", $data['codigo_barras']);
    $stmt->bindParam(":nombre", $data['nombre']);
    $stmt->bindParam(":descripcion", $data['descripcion']);
    $stmt->bindParam(":modelo", $data['modelo']);
    $stmt->bindParam(":unidad_medida", $data['unidad_medida']);
    $stmt->bindParam(":precio_compra", $data['precio_compra']);
    $stmt->bindParam(":precio_venta", $data['precio_venta']);
    $stmt->bindParam(":stock_minimo", $data['stock_minimo']);
    $stmt->bindParam(":id_producto", $id);

    return $stmt->execute();
}

public function deleteLogic($id) {
    $query = "UPDATE " . $this->table_name . "
              SET estado = 0
              WHERE id_producto = :id_producto";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":id_producto", $id);

    return $stmt->execute();
}
}