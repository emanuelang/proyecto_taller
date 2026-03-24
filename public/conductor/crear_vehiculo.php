<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $patente = trim($_POST['patente']);
    $asientos = (int)$_POST['asientos'];

    $stmt = $pdo->prepare("
        INSERT INTO Vehiculos
        (Marca, Modelo, Color, Patente, CantidadAsientos)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $marca,
        $modelo,
        $color,
        $patente,
        $asientos
    ]);
    
    $vehiculo_id = $pdo->lastInsertId();
    
    $stmt_rel = $pdo->prepare("INSERT INTO ConductorVehiculo (ID_conductor, ID_vehiculo) VALUES (?, ?)");
    $stmt_rel->execute([$_SESSION['conductor_id'], $vehiculo_id]);

    header('Location: vehiculos.php');
    exit;
}
?>

<h2>Registrar vehículo</h2>

<form method="post">
    Marca: <input name="marca" required><br>
    Modelo: <input name="modelo" required><br>
    Color: <input name="color" required><br>
    Patente: <input name="patente" required><br>
    Asientos: <input type="number" name="asientos" min="1" required><br><br>
    <button>Guardar vehículo</button>
</form>

<a href="vehiculos.php">Cancelar</a>
