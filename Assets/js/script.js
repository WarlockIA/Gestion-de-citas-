//
// Archivo: script.js
// Lógica del frontend para el registro, el login y la gestión de perfiles.
//

// URL de tu script PHP en el servidor local
const API_URL = 'http://localhost/api.php'; // Cambia esto si es necesario

// Obtener elementos del DOM
const loginView = document.getElementById('login-view');
const registerView = document.getElementById('register-view');
const dashboardView = document.getElementById('dashboard-view');
const formTitle = document.getElementById('form-title');
const messageArea = document.getElementById('message-area');
const logoutBtn = document.getElementById('logout-btn');

// Elementos del dashboard para agendar citas
const patientDashboardView = document.getElementById('patient-dashboard-view');
const doctorSelect = document.getElementById('doctor-select');
const dateSelect = document.getElementById('date-select');
const checkAvailabilityBtn = document.getElementById('check-availability-btn');
const availabilityArea = document.getElementById('availability-area');
const timeSlotsContainer = document.getElementById('time-slots-container');
const bookAppointmentBtn = document.getElementById('book-appointment-btn');
const appointmentSummary = document.getElementById('appointment-summary');
const summaryDoctor = document.getElementById('summary-doctor');
const summaryDate = document.getElementById('summary-date');
const summaryTime = document.getElementById('summary-time');

// Elementos de la nueva sección de citas agendadas
const myAppointmentsView = document.getElementById('my-appointments-view');
const appointmentsListContainer = document.getElementById('appointments-list-container');
const noAppointmentsMessage = document.getElementById('no-appointments-message');

let selectedTime = null;
let selectedDoctorId = null;

// Manejar el clic en el botón de logout
logoutBtn.addEventListener('click', () => {
    sessionStorage.removeItem('user');
    window.location.href = '../index.php';
});

// Función para mostrar mensajes
function showMessage(message, type = 'error') {
    messageArea.textContent = message;
    messageArea.className = `message-area ${type}`;
}

// Lógica para obtener médicos y agendar cita
document.addEventListener('DOMContentLoaded', () => {
    // Verificar si el usuario está autenticado
    const user = JSON.parse(sessionStorage.getItem('user'));
    if (!user || user.role !== 'paciente') {
        window.location.href = '../index.php';
        return;
    }
    
    // Si el usuario es paciente, mostrar el dashboard de paciente
    patientDashboardView.classList.remove('hidden');
    myAppointmentsView.classList.remove('hidden');
    
    fetchDoctors();
    fetchPatientAppointments(user.user_id);
});

// Función para obtener la lista de médicos
async function fetchDoctors() {
    const response = await fetch(`${API_URL}?action=get_doctors`);
    const result = await response.json();
    if (result.success) {
        doctorSelect.innerHTML = '<option value="">Selecciona un médico</option>';
        result.doctors.forEach(doctor => {
            const option = document.createElement('option');
            option.value = doctor.user_id;
            option.textContent = `${doctor.nombres} ${doctor.apellidos} - ${doctor.especialidad}`;
            doctorSelect.appendChild(option);
        });
    } else {
        doctorSelect.innerHTML = '<option value="">No se encontraron médicos</option>';
        showMessage(result.message);
    }
}

// Manejar el clic en el botón "Ver Disponibilidad"
checkAvailabilityBtn.addEventListener('click', async () => {
    const medicoId = doctorSelect.value;
    const fecha = dateSelect.value;
    if (!medicoId || !fecha) {
        showMessage('Por favor, selecciona un médico y una fecha.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'get_availability');
    formData.append('medico_id', medicoId);
    formData.append('fecha', fecha);
    
    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });
    const result = await response.json();
    
    if (result.success) {
        availabilityArea.classList.remove('hidden');
        renderTimeSlots(result.availability);
        const selectedDoctorName = doctorSelect.options[doctorSelect.selectedIndex].textContent;
        document.getElementById('selected-doctor-name').textContent = selectedDoctorName;
    } else {
        showMessage(result.message);
        availabilityArea.classList.add('hidden');
    }
});

// Renderizar los horarios disponibles
function renderTimeSlots(timeSlots) {
    timeSlotsContainer.innerHTML = '';
    bookAppointmentBtn.classList.add('hidden');
    
    if (timeSlots.length === 0) {
        timeSlotsContainer.innerHTML = '<p>No hay horarios disponibles para esta fecha.</p>';
        return;
    }
    
    timeSlots.forEach(time => {
        const timeSlot = document.createElement('button');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = time;
        timeSlot.addEventListener('click', () => {
            // Eliminar la selección anterior
            document.querySelectorAll('.time-slot').forEach(btn => btn.classList.remove('selected'));
            // Seleccionar el nuevo
            timeSlot.classList.add('selected');
            selectedTime = time;
            
            // Actualizar resumen de cita
            const selectedDoctorName = doctorSelect.options[doctorSelect.selectedIndex].textContent;
            const fecha = dateSelect.value;
            summaryDoctor.textContent = selectedDoctorName;
            summaryDate.textContent = fecha;
            summaryTime.textContent = time;
            appointmentSummary.classList.remove('hidden');
            bookAppointmentBtn.classList.remove('hidden');
        });
        timeSlotsContainer.appendChild(timeSlot);
    });
}

// Manejar el clic en el botón "Agendar Cita"
bookAppointmentBtn.addEventListener('click', async () => {
    if (!selectedTime || !selectedDoctorId) {
        showMessage('Por favor, seleccione un horario para agendar la cita.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'book_appointment');
    formData.append('paciente_id', JSON.parse(sessionStorage.getItem('user')).user_id);
    formData.append('medico_id', selectedDoctorId);
    formData.append('fecha', dateSelect.value);
    formData.append('hora', selectedTime);
    
    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });
    
    const result = await response.json();
    
    if (result.success) {
        showMessage(result.message, 'success');
        // Limpiar la interfaz después de agendar
        selectedTime = null;
        selectedDoctorId = null;
        availabilityArea.classList.add('hidden');
        bookAppointmentBtn.classList.add('hidden');
        appointmentSummary.classList.add('hidden');
        dateSelect.value = '';
        
        // **Actualizar la lista de citas del paciente**
        fetchPatientAppointments(JSON.parse(sessionStorage.getItem('user')).user_id);
    } else {
        showMessage(result.message);
    }
});

// **NUEVA FUNCIÓN: Obtener y mostrar las citas del paciente**
async function fetchPatientAppointments(userId) {
    const formData = new FormData();
    formData.append('action', 'get_patient_appointments');
    formData.append('user_id', userId);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();

        appointmentsListContainer.innerHTML = ''; // Limpiar el contenedor antes de mostrar

        if (result.success && result.appointments.length > 0) {
            noAppointmentsMessage.classList.add('hidden');
            result.appointments.forEach(cita => {
                const appointmentItem = document.createElement('div');
                appointmentItem.className = 'appointment-item';
                appointmentItem.innerHTML = `
                    <p><strong>Médico:</strong> ${cita.medico_nombres} ${cita.medico_apellidos}</p>
                    <p><strong>Fecha:</strong> ${cita.fecha_cita}</p>
                    <p><strong>Hora:</strong> ${cita.hora_cita}</p>
                    <hr>
                `;
                appointmentsListContainer.appendChild(appointmentItem);
            });
        } else {
            noAppointmentsMessage.classList.remove('hidden');
        }

    } catch (error) {
        showMessage('Error al cargar las citas: ' + error.message);
    }
}