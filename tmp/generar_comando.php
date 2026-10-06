<?php
$target = __DIR__ . "/../app/Console/Commands/CargarInventarioReal.php";
$content = file_get_contents(__DIR__ . "/CargarInventarioReal.template.php");
file_put_contents($target, $content);
echo "Comando generado: $target\n";
echo "Tamano: " . strlen($content) . " bytes\n";
