<?php
$dir = __DIR__ . '/uploads/';
echo '<h3>Dossier uploads : ' . $dir . '</h3>';
echo is_dir($dir) ? '✅ Existe' : '❌ N\'existe pas';
echo '<br>';
echo is_writable($dir) ? '✅ Accessible en écriture' : '❌ Pas accessible en écriture';
echo '<br><br>';
echo '<h3>Fichiers dans uploads/ :</h3>';
$files = scandir($dir);
foreach ($files as $f) {
    if ($f !== '.' && $f !== '..') echo $f . '<br>';
}
if (count($files) <= 2) echo 'Aucun fichier.';
?>