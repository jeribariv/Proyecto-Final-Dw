<?php
/**
 * admin/eliminar.php — BACKEND. Elimina una moto: primero su foto del disco y luego el registro.
 */
require '../includes/conexion.php';
verificarAdmin();

// Página a la que se vuelve (solo valores permitidos)
$volver = in_array($_POST['volver'] ?? '', ['motos.php', 'solicitudes.php'], true) ? $_POST['volver'] : 'motos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ir('admin/' . $volver);
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT foto FROM motos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $moto = $stmt->fetch();

    if ($moto) {
        borrarFoto($moto['foto']);                                   // 1) borrado físico del archivo
        $del = $pdo->prepare('DELETE FROM motos WHERE id = :id');    // 2) borrado del registro
        $del->execute([':id' => $id]);
        flash('success', 'Moto eliminada.');
    } else {
        flash('warning', 'La moto ya no existe.');
    }
} catch (PDOException $ex) {
    flash('danger', 'No se pudo eliminar la moto.');
}

ir('admin/' . $volver);
