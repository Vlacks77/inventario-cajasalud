<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=inventario_cajasalud", "root", "");
    echo "Conexion PDO OK\n";
    $pdo = null;
} catch (Exception $e) {
    echo "Error PDO: " . $e->getMessage() . "\n";
}

// Probar tambien con localhost (socket)
try {
    $pdo2 = new PDO("mysql:host=localhost;port=3306;dbname=inventario_cajasalud", "root", "");
    echo "Conexion con localhost OK\n";
    $pdo2 = null;
} catch (Exception $e) {
    echo "Error localhost: " . $e->getMessage() . "\n";
}

// Verificar extensiones PDO cargadas
echo "Extensiones PDO: " . implode(", ", PDO::getAvailableDrivers()) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";