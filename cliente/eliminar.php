<?php
/**
 * cliente/eliminar.php — FRONTEND. El cliente retira una de SUS publicaciones.
 * El WHERE incluye id_usuario para que nadie pueda borrar motos de otra persona.
 */
require '../includes/conexion.php';
verificarCliente();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ir('cliente/index.php');
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT foto FROM motos WHERE id = :id AND id_usuario = :usuario');
    $stmt->execute([':id' => $id, ':usuario' => $_SESSION['usuario_id']]);
    $moto = $stmt->fetch();

    if ($moto) {
        borrarFoto($moto['foto']);
        $pdo->prepare('DELETE FROM motos WHERE id = :id AND id_usuario = :usuario')
            ->execute([':id' => $id, ':usuario' => $_SESSION['usuario_id']]);
        flash('success', 'Publicación retirada.');
    } else {
        flash('warning', 'No se encontró esa publicación.');
    }
} catch (PDOException $ex) {
    flash('danger', 'No se pudo retirar la publicación.');
}

ir('cliente/index.php');
