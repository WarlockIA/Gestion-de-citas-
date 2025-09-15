<?php
session_start();
// Este archivo actúa como el enrutador principal de tu aplicación

// Definir la vista por defecto (login)
$view = 'login.html';

// Comprobar si existe una sesión activa
if (isset($_SESSION['user'])) {
    $user = json_decode($_SESSION['user']);
    if (isset($user->is_temp_password) && $user->is_temp_password == 1) {
        $view = 'profile-completion.html';
    } else {
        $view = 'dashboard.html';
    }
}

// Cargar la vista correspondiente
// El servidor ahora buscará en la carpeta 'views'
include 'Views/' . $view;
?>