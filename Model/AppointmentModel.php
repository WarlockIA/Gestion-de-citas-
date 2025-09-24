<?php
// Archivo: Model/AppointmentModel.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../Config/Database.php';

class AppointmentModel
{
    private $conn;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->conn;
    }

    public function getDoctors()
    {
        $sql = "SELECT u.usuario_id, u.nombres, u.apellidos, m.especialidad FROM medicos m JOIN usuarios u ON m.usuario_id = u.usuario_id";
        $result = $this->conn->query($sql);
        $doctors = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $doctors[] = ['user_id' => $row['usuario_id'], 'nombres' => $row['nombres'], 'apellidos' => $row['apellidos'], 'especialidad' => $row['especialidad']];
            }
            return ["success" => true, "doctors" => $doctors];
        }
        return ["success" => false, "message" => "No se encontraron médicos."];
    }

    public function getAvailability($medico_id, $fecha)
    {
        if (empty($medico_id) || empty($fecha)) {
            return ["success" => false, "message" => "Faltan datos para la consulta de disponibilidad."];
        }

        $horarioAtencion = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
        
        $sql = "SELECT hora_cita FROM citas WHERE medico_id = ? AND fecha_cita = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $medico_id, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $occupiedSlots = [];
        while ($row = $result->fetch_assoc()) {
            $occupiedSlots[] = $row['hora_cita'];
        }

        $availableSlots = array_diff($horarioAtencion, $occupiedSlots);
        
        return ["success" => true, "availability" => array_values($availableSlots)];
    }

    public function bookAppointment($paciente_id, $medico_id, $fecha, $hora)
    {
        if (empty($paciente_id) || empty($medico_id) || empty($fecha) || empty($hora)) {
            return ["success" => false, "message" => "Faltan datos para agendar la cita."];
        }
        
        $sql_check = "SELECT * FROM citas WHERE medico_id = ? AND fecha_cita = ? AND hora_cita = ?";
        $stmt_check = $this->conn->prepare($sql_check);
        $stmt_check->bind_param("iss", $medico_id, $fecha, $hora);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $stmt_check->close();

        if ($result_check->num_rows > 0) {
            return ["success" => false, "message" => "Esta franja horaria ya está ocupada."];
        }
        
        $stmt_paciente_id = $this->conn->prepare("SELECT paciente_id FROM pacientes WHERE usuario_id = ?");
        $stmt_paciente_id->bind_param("i", $paciente_id);
        $stmt_paciente_id->execute();
        $result_paciente = $stmt_paciente_id->get_result();
        
        if ($result_paciente->num_rows == 0) {
            $stmt_paciente_id->close();
            return ["success" => false, "message" => "No se encontró el ID de paciente."];
        }
        $paciente_row = $result_paciente->fetch_assoc();
        $paciente_db_id = $paciente_row['paciente_id'];
        $stmt_paciente_id->close();

        $sql_insert = "INSERT INTO citas (paciente_id, medico_id, fecha_cita, hora_cita, estado) VALUES (?, ?, ?, ?, 'agendada')";
        $stmt = $this->conn->prepare($sql_insert);
        
        if ($stmt) {
            $stmt->bind_param("iiss", $paciente_db_id, $medico_id, $fecha, $hora);
            if ($stmt->execute()) {
                $this->sendConfirmationEmail($paciente_db_id, $medico_id, $fecha, $hora);
                $stmt->close();
                return ["success" => true, "message" => "Cita agendada con éxito."];
            } else {
                $stmt->close();
                return ["success" => false, "message" => "Error al agendar la cita: " . $stmt->error];
            }
        } else {
            return ["success" => false, "message" => "Error en la preparación de la consulta: " . $this->conn->error];
        }
    }

    public function getMyAppointments($paciente_id)
    {
        $sql = "SELECT c.fecha_cita, c.hora_cita, u.nombres, u.apellidos, u.ci 
                FROM citas c
                JOIN pacientes p ON c.paciente_id = p.paciente_id
                JOIN medicos d ON c.medico_id = d.medico_id
                JOIN usuarios u ON d.usuario_id = u.usuario_id
                WHERE p.usuario_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $paciente_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $appointments = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
            return ["success" => true, "appointments" => $appointments];
        }
        $stmt->close();
        return ["success" => false, "message" => "No tienes citas agendadas."];
    }
    
    private function sendConfirmationEmail($paciente_db_id, $medico_id, $fecha, $hora)
    {
        $stmt = $this->conn->prepare("
            SELECT 
                u_p.email AS paciente_email, 
                u_p.telefono AS paciente_telefono,
                u_m.nombres AS medico_nombres, 
                u_m.apellidos AS medico_apellidos, 
                m.especialidad
            FROM citas c
            JOIN pacientes p ON c.paciente_id = p.paciente_id
            JOIN usuarios u_p ON p.usuario_id = u_p.usuario_id
            JOIN medicos m ON c.medico_id = m.medico_id
            JOIN usuarios u_m ON m.usuario_id = u_m.usuario_id
            WHERE c.paciente_id = ? AND c.medico_id = ? AND c.fecha_cita = ? AND c.hora_cita = ?
        ");
        
        $stmt->bind_param("iiss", $paciente_db_id, $medico_id, $fecha, $hora);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            $paciente_email = $data['paciente_email'];
            $medico_nombre = $data['medico_nombres'] . ' ' . $data['medico_apellidos'];

            $subject = "Confirmación de Cita Médica";
            $body = "Hola,\n\n";
            $body .= "Tu cita ha sido agendada con éxito.\n\n";
            $body .= "Detalles de la cita:\n";
            $body .= "- Médico: " . $medico_nombre . "\n";
            $body .= "- Fecha: " . $fecha . "\n";
            $body .= "- Hora: " . $hora . "\n";
            $body .= "Por favor, llega 15 minutos antes de tu cita.\n\n";
            $body .= "Gracias,\nSistema de Gestión de Citas Médicas";
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.example.com'; // REEMPLAZA
                $mail->SMTPAuth   = true;
                $mail->Username   = 'user@example.com'; // REEMPLAZA
                $mail->Password   = 'your_password'; // REEMPLAZA
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('no-reply@citasmedicas.com', 'Citas Médicas');
                $mail->addAddress($paciente_email);

                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body    = $body;

                $mail->send();
            } catch (Exception $e) {
                $errorMessage = date("Y-m-d H:i:s") . " - Error al enviar correo de confirmación a {$paciente_email}. Error: {$mail->ErrorInfo}\n";
                file_put_contents(__DIR__ . '/../logs/mail_error.log', $errorMessage, FILE_APPEND);
            }
        }
    }
}