<?php
// Archivo: Model/AppointmentModel.php

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
        $sql = "SELECT u.user_id, u.nombres, u.apellidos, d.especialidad FROM doctors d JOIN users u ON d.user_id = u.user_id";
        $result = $this->conn->query($sql);
        $doctors = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $doctors[] = $row;
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

        $horarioAtencion = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
        
        $sql = "SELECT hora_cita FROM citas WHERE medico_id = ? AND fecha_cita = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $medico_id, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();

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

        if ($result_check->num_rows > 0) {
            return ["success" => false, "message" => "Esta franja horaria ya está ocupada."];
        }
        
        $stmt_paciente_id = $this->conn->prepare("SELECT paciente_id FROM patients WHERE user_id = ?");
        $stmt_paciente_id->bind_param("i", $paciente_id);
        $stmt_paciente_id->execute();
        $result_paciente = $stmt_paciente_id->get_result();
        
        if ($result_paciente->num_rows == 0) {
            return ["success" => false, "message" => "No se encontró el ID de paciente."];
        }
        $paciente_row = $result_paciente->fetch_assoc();
        $paciente_db_id = $paciente_row['paciente_id'];

        $sql_insert = "INSERT INTO citas (paciente_id, medico_id, fecha_cita, hora_cita, estado) VALUES (?, ?, ?, ?, 'agendada')";
        $stmt = $this->conn->prepare($sql_insert);
        
        if ($stmt) {
            $stmt->bind_param("iiss", $paciente_db_id, $medico_id, $fecha, $hora);
            if ($stmt->execute()) {
                return ["success" => true, "message" => "Cita agendada con éxito."];
            } else {
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
                JOIN doctors d ON c.medico_id = d.medico_id
                JOIN users u ON d.user_id = u.user_id
                WHERE c.paciente_id = (SELECT paciente_id FROM patients WHERE user_id = ?)";
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
        return ["success" => false, "message" => "No tienes citas agendadas."];
    }
}