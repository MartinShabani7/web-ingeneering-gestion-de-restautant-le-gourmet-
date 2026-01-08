<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$back = $_GET['back'] ?? 'menu_plats_principaux.php';
if ($id > 0) {
    if (isset($_SESSION['likes'][$id])) { unset($_SESSION['likes'][$id]); }
    else { $_SESSION['likes'][$id] = 1; }
}
header('Location: ' . $back);
exit;
?>


