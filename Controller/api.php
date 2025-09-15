<?php
// Archivo: Controller/api.php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/AppointmentModel.php';
require_once __DIR__ . '/../Config/Database.php';

$userModel = new UserModel();
$appointmentModel = new AppointmentModel();

$response = ["success" => false, "message" => "Solicitud inválida."];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $response = $userModel->registerUser(
            $_POST['ci'] ?? '',
            $_POST['nombres'] ?? '',
            $_POST['apellidos'] ?? '',
            $_POST['telefono'] ?? '',
            $_POST['fecha_nacimiento'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? '',
            $_POST['role'] ?? ''
        );
        break;

    case 'create_doctor':
        function generateTemporaryPassword($length = 10)
        {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_+=';
            return substr(str_shuffle($chars), 0, $length);
        }
        $temp_password = generateTemporaryPassword();
        $response = $userModel->createDoctor(
            $_POST['ci'] ?? '',
            $_POST['nombres'] ?? '',
            $_POST['apellidos'] ?? '',
            $_POST['telefono'] ?? '',
            $_POST['fecha_nacimiento'] ?? '',
            $_POST['email'] ?? '',
            $_POST['especialidad'] ?? '',
            $_POST['numero_licencia'] ?? '',
            $_POST['horario_atencion'] ?? '',
            $temp_password
        );
        break;

    case 'login':
        $response = $userModel->loginUser($_POST['ci'] ?? '', $_POST['password'] ?? '');
        if ($response['success']) {
            $_SESSION['user_id'] = $response['user']['user_id'];
            $_SESSION['role'] = $response['user']['role'];
        }
        break;

    case 'change_password':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'No autorizado. Por favor, inicia sesión.';
            break;
        }
        $response = $userModel->changePassword(
            $_SESSION['user_id'],
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        break;

    case 'complete_profile':
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            $response['message'] = 'No autorizado. Por favor, inicia sesión.';
            break;
        }
        $response = $userModel->completeProfile(
            $_SESSION['user_id'],
            $_SESSION['role'],
            $_POST
        );
        break;

    case 'get_doctors':
        $response = $appointmentModel->getDoctors();
        break;

    case 'get_availability':
        $medico_id = $_POST['medico_id'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $response = $appointmentModel->getAvailability($medico_id, $fecha);
        break;

    case 'book_appointment':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'No autorizado. Por favor, inicia sesión.';
            break;
        }
        $paciente_id = $_SESSION['user_id'];
        $medico_id = $_POST['medico_id'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $response = $appointmentModel->bookAppointment($paciente_id, $medico_id, $fecha, $hora);
        break;

    case 'get_my_appointments':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'No autorizado. Por favor, inicia sesión.';
            break;
        }
        $paciente_id = $_SESSION['user_id'];
        $response = $appointmentModel->getMyAppointments($paciente_id);
        break;

    default:
        $response["message"] = "Acción no válida.";
        break;
}

echo json_encode($response);