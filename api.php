<?php
// Archivo: api.php

// Permitir peticiones desde el frontend (ajustar en producción)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Si la petición es OPTIONS, responder con 200 OK para el preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de la base de datos
$servername = "localhost"; // La dirección de tu servidor MySQL
$username = "root";        // Tu nombre de usuario de MySQL
$password = "";            // Tu contraseña de MySQL
$dbname = "citas_medicas";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Error de conexión: " . $conn->connect_error]));
}

// Inicializar la respuesta
$response = ["success" => false, "message" => "Solicitud inválida."];

// Función para generar una contraseña temporal segura
function generateTemporaryPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[mt_rand(0, $max)];
    }
    return $password;
}

// Determinar la acción y procesar la solicitud POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $ci = $_POST['ci'] ?? '';
        $nombres = $_POST['nombres'] ?? '';
        $apellidos = $_POST['apellidos'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        // Validaciones del lado del servidor
        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($telefono) || empty($fecha_nacimiento) || empty($email) || empty($password) || empty($role)) {
            $response["message"] = "Todos los campos son requeridos.";
            break;
        }

        if (strlen($password) < 8) {
            $response["message"] = "La contraseña debe tener al menos 8 caracteres.";
            break;
        }

        // Manejar caso de correo duplicado
        $stmt_check_email = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $response["message"] = "El correo electrónico ya está registrado.";
            $stmt_check_email->close();
            break;
        }
        $stmt_check_email->close();
        
        // Manejar caso de CI duplicado
        $stmt_check_ci = $conn->prepare("SELECT ci FROM users WHERE ci = ?");
        $stmt_check_ci->bind_param("s", $ci);
        $stmt_check_ci->execute();
        $stmt_check_ci->store_result();
        if ($stmt_check_ci->num_rows > 0) {
            $response["message"] = "El número de CI ya está registrado.";
            $stmt_check_ci->close();
            break;
        }
        $stmt_check_ci->close();

        // Encriptar la contraseña con bcrypt
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Guardar el nuevo usuario en la tabla `users`
        $stmt = $conn->prepare("INSERT INTO users (ci, nombres, apellidos, telefono, fecha_nacimiento, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssssss", $ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                $last_id = $stmt->insert_id;
                
                // Guardar el rol en la base de datos
                if ($role === 'paciente') {
                    $stmt_profile = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                } else { // medico
                    $stmt_profile = $conn->prepare("INSERT INTO doctors (user_id) VALUES (?)");
                }
                
                if ($stmt_profile) {
                    $stmt_profile->bind_param("i", $last_id);
                    $stmt_profile->execute();
                    $stmt_profile->close();
                }

                $response["success"] = true;
                $response["message"] = "Registro exitoso. Ahora puedes iniciar sesión.";
            } else {
                $response["message"] = "Error al registrar: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $response["message"] = "Error de preparación de la consulta: " . $conn->error;
        }
        break;

    case 'create_doctor':
        $ci = $_POST['ci'] ?? '';
        $nombres = $_POST['nombres'] ?? '';
        $apellidos = $_POST['apellidos'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $email = $_POST['email'] ?? '';
        $especialidad = $_POST['especialidad'] ?? '';
        $numero_licencia = $_POST['numero_licencia'] ?? '';
        $horario_atencion = $_POST['horario_atencion'] ?? '';

        // Validaciones del lado del servidor
        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($telefono) || empty($fecha_nacimiento) || empty($email) || empty($especialidad) || empty($numero_licencia) || empty($horario_atencion)) {
            $response["message"] = "Todos los campos son requeridos.";
            break;
        }
        
        // Manejar caso de CI, correo y licencia duplicados
        $stmt_check = $conn->prepare("SELECT email FROM users WHERE email = ? OR ci = ?");
        $stmt_check->bind_param("ss", $email, $ci);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $response["message"] = "El correo o CI ya están registrados.";
            $stmt_check->close();
            break;
        }
        $stmt_check->close();

        $stmt_check_licencia = $conn->prepare("SELECT numero_licencia FROM doctors WHERE numero_licencia = ?");
        $stmt_check_licencia->bind_param("s", $numero_licencia);
        $stmt_check_licencia->execute();
        $stmt_check_licencia->store_result();
        if ($stmt_check_licencia->num_rows > 0) {
            $response["message"] = "El número de licencia ya está en uso.";
            $stmt_check_licencia->close();
            break;
        }
        $stmt_check_licencia->close();

        // Generar una contraseña temporal
        $temp_password = generateTemporaryPassword();
        $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);

        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Insertar en la tabla users
            $role = 'medico';
            $is_temp_password = 1;
            $stmt_user = $conn->prepare("INSERT INTO users (ci, nombres, apellidos, telefono, fecha_nacimiento, email, password, role, is_temp_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_user->bind_param("ssssssssi", $ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $hashed_password, $role, $is_temp_password);
            
            if (!$stmt_user->execute()) {
                throw new Exception("Error al insertar usuario: " . $stmt_user->error);
            }
            $last_id = $stmt_user->insert_id;
            $stmt_user->close();
            
            // Insertar en la tabla doctors
            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, especialidad, numero_licencia, horario_atencion) VALUES (?, ?, ?, ?)");
            $stmt_doctor->bind_param("isss", $last_id, $especialidad, $numero_licencia, $horario_atencion);
            
            if (!$stmt_doctor->execute()) {
                throw new Exception("Error al insertar médico: " . $stmt_doctor->error);
            }
            $stmt_doctor->close();
            
            // Si todo es correcto, confirmar la transacción
            $conn->commit();
            $response["success"] = true;
            $response["message"] = "Cuenta de médico creada con éxito.";
            $response["temp_password"] = $temp_password;
        } catch (Exception $e) {
            // Si hay un error, revertir la transacción
            $conn->rollback();
            $response["message"] = "Error en el registro: " . $e->getMessage();
        }
        break;

    case 'login':
        $ci = $_POST['ci'] ?? '';
        $password = $_POST['password'] ?? '';

        // Buscar el usuario por CI y obtener los datos completos del perfil
        $stmt = $conn->prepare("SELECT u.user_id, u.password, u.role, u.nombres, u.ci, u.apellidos, u.telefono, u.fecha_nacimiento, u.email, u.is_temp_password,
                                p.genero,
                                d.especialidad, d.numero_licencia, d.horario_atencion
                                FROM users u
                                LEFT JOIN patients p ON u.user_id = p.user_id
                                LEFT JOIN doctors d ON u.user_id = d.user_id
                                WHERE u.ci = ?");

        if ($stmt) {
            $stmt->bind_param("s", $ci);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $response["success"] = true;
                    $response["message"] = "Inicio de sesión exitoso.";
                    $response["user"] = [
                        "user_id" => $user['user_id'],
                        "nombres" => $user['nombres'],
                        "apellidos" => $user['apellidos'],
                        "role" => $user['role'],
                        "ci" => $user['ci'],
                        "telefono" => $user['telefono'],
                        "fecha_nacimiento" => $user['fecha_nacimiento'],
                        "email" => $user['email'],
                        "genero" => $user['genero'],
                        "especialidad" => $user['especialidad'],
                        "numero_licencia" => $user['numero_licencia'],
                        "horario_atencion" => $user['horario_atencion'],
                        "is_temp_password" => $user['is_temp_password']
                    ];
                } else {
                    $response["message"] = "Contraseña incorrecta.";
                }
            } else {
                $response["message"] = "Usuario no encontrado.";
            }
            $stmt->close();
        } else {
            $response["message"] = "Error de preparación de la consulta: " . $conn->error;
        }
        break;
    
    case 'change_password':
        $userId = $_POST['user_id'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($userId) || empty($newPassword) || empty($confirmPassword)) {
            $response["message"] = "Todos los campos son requeridos.";
            break;
        }

        if ($newPassword !== $confirmPassword) {
            $response["message"] = "Las contraseñas no coinciden.";
            break;
        }

        if (strlen($newPassword) < 8) {
            $response["message"] = "La contraseña debe tener al menos 8 caracteres.";
            break;
        }

        $hashed_password = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, is_temp_password = 0 WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $hashed_password, $userId);
            if ($stmt->execute()) {
                $response["success"] = true;
                $response["message"] = "Contraseña actualizada con éxito. Ahora puedes acceder a tu panel.";
            } else {
                $response["message"] = "Error al cambiar la contraseña: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $response["message"] = "Error de preparación de la consulta: " . $conn->error;
        }
        break;

    case 'complete_profile':
        $userId = $_POST['user_id'] ?? '';
        $role = $_POST['role'] ?? '';

        if (empty($userId) || empty($role)) {
            $response["message"] = "ID de usuario o rol no proporcionados.";
            break;
        }

        if ($role === 'paciente') {
            $genero = $_POST['genero'] ?? '';
            if (empty($genero)) {
                $response["message"] = "El campo 'género' es requerido.";
                break;
            }
            $stmt = $conn->prepare("UPDATE patients SET genero = ? WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $genero, $userId);
                if ($stmt->execute()) {
                    $response["success"] = true;
                    $response["message"] = "Perfil de paciente completado exitosamente.";
                } else {
                    $response["message"] = "Error al completar perfil: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response["message"] = "Error de preparación de la consulta: " . $conn->error;
            }
        } elseif ($role === 'medico') {
            $especialidad = $_POST['especialidad'] ?? '';
            $numero_licencia = $_POST['numero_licencia'] ?? '';
            $horario_atencion = $_POST['horario_atencion'] ?? '';

            if (empty($especialidad) || empty($numero_licencia) || empty($horario_atencion)) {
                $response["message"] = "Todos los campos de médico son requeridos.";
                break;
            }
            
            $stmt_check_licencia = $conn->prepare("SELECT numero_licencia FROM doctors WHERE numero_licencia = ? AND user_id != ?");
            $stmt_check_licencia->bind_param("si", $numero_licencia, $userId);
            $stmt_check_licencia->execute();
            $stmt_check_licencia->store_result();
            if ($stmt_check_licencia->num_rows > 0) {
                $response["message"] = "El número de licencia ya está en uso.";
                $stmt_check_licencia->close();
                break;
            }
            $stmt_check_licencia->close();
            
            $stmt = $conn->prepare("UPDATE doctors SET especialidad = ?, numero_licencia = ?, horario_atencion = ? WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $especialidad, $numero_licencia, $horario_atencion, $userId);
                if ($stmt->execute()) {
                    $response["success"] = true;
                    $response["message"] = "Perfil de médico completado exitosamente.";
                } else {
                    $response["message"] = "Error al completar perfil: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response["message"] = "Error de preparación de la consulta: " . $conn->error;
            }
        } else {
            $response["message"] = "Rol no válido.";
        }
        break;
    default:
        $response["message"] = "Acción no válida.";
        break;
}

$conn->close();
echo json_encode($response);