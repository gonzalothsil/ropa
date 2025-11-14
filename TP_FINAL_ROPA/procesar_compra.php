<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Acceso inválido'); window.location='carrito.php';</script>";
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nombre = $_POST['nombre'] ?? '';
$direccion = $_POST['direccion'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$pago = $_POST['pago'] ?? '';

if (!$nombre || !$direccion || !$telefono || !$pago) {
    echo "<script>alert('⚠️ Completa todos los campos antes de continuar.'); window.location='confirmar_compra.php';</script>";
    exit();
}

// 📞 Validación estricta del teléfono
if (!preg_match('/^[0-9]{10,11}$/', $telefono)) {
    echo "<script>alert('⚠️ El número de teléfono debe tener 10 u 11 dígitos reales.'); window.location='confirmar_compra.php';</script>";
    exit();
}

// Obtener total
$total_query = $conn->query("
  SELECT SUM(ci.cantidad * p.precio) AS total
  FROM carrito_items ci
  INNER JOIN productos p ON ci.producto_id = p.id
  INNER JOIN carritos c ON ci.carrito_id = c.id
  WHERE c.usuario_id = $usuario_id
");

$total = floatval($total_query->fetch_assoc()['total'] ?? 0);

if ($total <= 0) {
    echo "<script>alert('Tu carrito está vacío.'); window.location='productos.php';</script>";
    exit();
}

// =======================
//   BAJAR STOCK
// =======================
$items = $conn->query("
  SELECT ci.producto_id, ci.cantidad
  FROM carrito_items ci
  INNER JOIN carritos c ON ci.carrito_id = c.id
  WHERE c.usuario_id = $usuario_id
");

while ($item = $items->fetch_assoc()) {
    $producto_id = $item['producto_id'];
    $cantidad = $item['cantidad'];

    // Reducir stock
    $conn->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $producto_id");
}

// =======================
//   REGISTRAR COMPRA
// =======================
$metodo_pago = ($pago === "mp") ? "mercado_pago" : "tarjeta";

$conn->query("
    INSERT INTO compras (usuario_id, total, fecha, nombre, direccion, telefono, metodo_pago)
    VALUES ($usuario_id, $total, NOW(), '$nombre', '$direccion', '$telefono', '$metodo_pago')
");
$compra_id = $conn->insert_id;

// =======================
//   VACIAR CARRITO
// =======================
$conn->query("
    DELETE ci FROM carrito_items ci
    INNER JOIN carritos c ON ci.carrito_id = c.id
    WHERE c.usuario_id = $usuario_id
");

// =======================
//   REDIRECCIÓN
// =======================

if ($pago === "mp") {
    echo "<script>
            alert('Compra realizada con éxito. Le informaremos sobre su pedido.');
            window.location='index.php';
          </script>";
    exit();
}

echo "<script>
        alert('Compra realizada con tarjeta con éxito.');
        window.location='index.php';
      </script>";
exit();
?>
