<?php
// Archivo: Model/UserModel.php
require_once __DIR__ . '/../Config/Database.php';

class UserModel
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->conn;
    }

    public function registerUser($ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $password, $role)
    {
        $response = ["success" => false, "message" => "Error al registrar."];

        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($telefono) || empty($fecha_nacimiento) || empty($email) || empty($password) || empty($role)) {
            $response["message"] = "Todos los campos son requeridos.";
            return $response;
        }

        if (strlen($password) < 8) {
            $response["message"] = "La contraseña debe tener al menos 8 caracteres.";
            return $response;
        }

        $stmt_check_email = $this->conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $response["message"] = "El correo electrónico ya está registrado.";
            $stmt_check_email->close();
            return $response;
        }
        $stmt_check_email->close();

        $stmt_check_ci = $this->conn->prepare("SELECT ci FROM users WHERE ci = ?");
        $stmt_check_ci->bind_param("s", $ci);
        $stmt_check_ci->execute();
        $stmt_check_ci->store_result();
        if ($stmt_check_ci->num_rows > 0) {
            $response["message"] = "El número de CI ya está registrado.";
            $stmt_check_ci->close();
            return $response;
        }
        $stmt_check_ci->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("INSERT INTO users (ci, nombres, apellidos, telefono, fecha_nacimiento, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("ssssssss", $ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                $last_id = $stmt->insert_id;
                if ($role === 'paciente') {
                    $stmt_profile = $this->conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                } else {
                    $stmt_profile = $this->conn->prepare("INSERT INTO doctors (user_id) VALUES (?)");
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
            $response["message"] = "Error de preparación de la consulta: " . $this->conn->error;
        }
        return $response;
    }

    public function loginUser($ci, $password)
    {
        $response = ["success" => false, "message" => "Usuario o contraseña incorrectos."];
        $stmt = $this->conn->prepare("SELECT u.user_id, u.password, u.role, u.nombres, u.ci, u.apellidos, u.telefono, u.fecha_nacimiento, u.email, u.is_temp_password,
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
            $response["message"] = "Error de preparación de la consulta: " . $this->conn->error;
        }
        return $response;
    }

    public function completeProfile($userId, $role, $data)
    {
        $response = ["success" => false, "message" => "Error al completar perfil."];
        if (empty($userId) || empty($role) || empty($data)) {
            $response["message"] = "Datos incompletos.";
            return $response;
        }

        if ($role === 'paciente') {
            $genero = $data['genero'] ?? '';
            if (empty($genero)) {
                $response["message"] = "El campo 'género' es requerido.";
                return $response;
            }
            $stmt = $this->conn->prepare("UPDATE patients SET genero = ? WHERE user_id = ?");
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
                $response["message"] = "Error de preparación de la consulta: " . $this->conn->error;
            }
        } elseif ($role === 'medico') {
            $especialidad = $data['especialidad'] ?? '';
            $numero_licencia = $data['numero_licencia'] ?? '';
            $horario_atencion = $data['horario_atencion'] ?? '';

            if (empty($especialidad) || empty($numero_licencia) || empty($horario_atencion)) {
                $response["message"] = "Todos los campos de médico son requeridos.";
                return $response;
            }

            $stmt_check_licencia = $this->conn->prepare("SELECT numero_licencia FROM doctors WHERE numero_licencia = ? AND user_id != ?");
            $stmt_check_licencia->bind_param("si", $numero_licencia, $userId);
            $stmt_check_licencia->execute();
            $stmt_check_licencia->store_result();
            if ($stmt_check_licencia->num_rows > 0) {
                $response["message"] = "El número de licencia ya está en uso.";
                $stmt_check_licencia->close();
                return $response;
            }
            $stmt_check_licencia->close();

            $stmt = $this->conn->prepare("UPDATE doctors SET especialidad = ?, numero_licencia = ?, horario_atencion = ? WHERE user_id = ?");
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
                $response["message"] = "Error de preparación de la consulta: " . $this->conn->error;
            }
        } else {
            $response["message"] = "Rol no válido.";
        }
        return $response;
    }

    public function changePassword($userId, $newPassword, $confirmPassword)
    {
        $response = ["success" => false, "message" => "Error al cambiar la contraseña."];

        if (empty($userId) || empty($newPassword) || empty($confirmPassword)) {
            $response["message"] = "Todos los campos son requeridos.";
            return $response;
        }

        if ($newPassword !== $confirmPassword) {
            $response["message"] = "Las contraseñas no coinciden.";
            return $response;
        }

        if (strlen($newPassword) < 8) {
            $response["message"] = "La contraseña debe tener al menos 8 caracteres.";
            return $response;
        }

        $hashed_password = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("UPDATE users SET password = ?, is_temp_password = 0 WHERE user_id = ?");
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
            $response["message"] = "Error de preparación de la consulta: " . $this->conn->error;
        }
        return $response;
    }

    public function createDoctor($ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $especialidad, $numero_licencia, $horario_atencion, $temp_password)
    {
        $response = ["success" => false, "message" => "Error en el registro."];

        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($telefono) || empty($fecha_nacimiento) || empty($email) || empty($especialidad) || empty($numero_licencia) || empty($horario_atencion)) {
            $response["message"] = "Todos los campos son requeridos.";
            return $response;
        }

        $stmt_check = $this->conn->prepare("SELECT email FROM users WHERE email = ? OR ci = ?");
        $stmt_check->bind_param("ss", $email, $ci);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $response["message"] = "El correo o CI ya están registrados.";
            $stmt_check->close();
            return $response;
        }
        $stmt_check->close();

        $stmt_check_licencia = $this->conn->prepare("SELECT numero_licencia FROM doctors WHERE numero_licencia = ?");
        $stmt_check_licencia->bind_param("s", $numero_licencia);
        $stmt_check_licencia->execute();
        $stmt_check_licencia->store_result();
        if ($stmt_check_licencia->num_rows > 0) {
            $response["message"] = "El número de licencia ya está en uso.";
            $stmt_check_licencia->close();
            return $response;
        }
        $stmt_check_licencia->close();

        $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);

        $this->conn->begin_transaction();

        try {
            $role = 'medico';
            $is_temp_password = 1;
            $stmt_user = $this->conn->prepare("INSERT INTO users (ci, nombres, apellidos, telefono, fecha_nacimiento, email, password, role, is_temp_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_user->bind_param("ssssssssi", $ci, $nombres, $apellidos, $telefono, $fecha_nacimiento, $email, $hashed_password, $role, $is_temp_password);

            if (!$stmt_user->execute()) {
                throw new Exception("Error al insertar usuario: " . $stmt_user->error);
            }
            $last_id = $stmt_user->insert_id;
            $stmt_user->close();

            $stmt_doctor = $this->conn->prepare("INSERT INTO doctors (user_id, especialidad, numero_licencia, horario_atencion) VALUES (?, ?, ?, ?)");
            $stmt_doctor->bind_param("isss", $last_id, $especialidad, $numero_licencia, $horario_atencion);

            if (!$stmt_doctor->execute()) {
                throw new Exception("Error al insertar médico: " . $stmt_doctor->error);
            }
            $stmt_doctor->close();

            $this->conn->commit();
            $response["success"] = true;
            $response["message"] = "Cuenta de médico creada con éxito.";
            $response["temp_password"] = $temp_password;
        } catch (Exception $e) {
            $this->conn->rollback();
            $response["message"] = "Error en el registro: " . $e->getMessage();
        }
        return $response;
    }
}