//
// Archivo: script.js
// Lógica del frontend para el registro, el login y la gestión de perfiles.
//

// URL de tu script PHP en el servidor local
const API_URL = 'http://localhost/api.php'; // Cambia esto si es necesario

const appointmentManagementView = document.getElementById('appointment-management-view');
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

let selectedTime = null;
let selectedDoctorId = null;

// Obtener elementos del DOM
const loginView = document.getElementById('login-view');
const registerView = document.getElementById('register-view');
const dashboardView = document.getElementById('dashboard-view');
const formTitle = document.getElementById('form-title');
const messageArea = document.getElementById('message-area');

const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const logoutBtn = document.getElementById('logout-btn');

const profileCompletionView = document.getElementById('profile-completion-view');
const patientProfileForm = document.getElementById('patient-profile-form');
const doctorProfileForm = document.getElementById('doctor-profile-form');
const userInfoView = document.getElementById('user-info');
const patientInfoView = document.getElementById('patient-info');
const doctorInfoView = document.getElementById('doctor-info');

const changePasswordView = document.getElementById('change-password-view');
const changePasswordForm = document.getElementById('change-password-form');


// Funciones para manejar la visibilidad de las vistas
function showView(viewId) {
    loginView.classList.add('hidden');
    registerView.classList.add('hidden');
    dashboardView.classList.add('hidden');
    // Asegúrate de que todas las sub-vistas del dashboard también se ocultan
    profileCompletionView.classList.add('hidden');
    userInfoView.classList.add('hidden');
    appointmentManagementView.classList.add('hidden'); // Añadir esta línea
    if (changePasswordView) {
        changePasswordView.classList.add('hidden');
    }
    document.getElementById(viewId).classList.remove('hidden');
}

// Funciones para mostrar mensajes
function showMessage(message, type = 'error') {
    messageArea.textContent = message;
    messageArea.className = `message-area ${type}`;
}

// Manejar el cambio de vista entre login y registro
document.getElementById('show-register').addEventListener('click', () => {
    showView('register-view');
    formTitle.textContent = 'Registro de Usuarios';
    showMessage('');
});
document.getElementById('show-login').addEventListener('click', () => {
    showView('login-view');
    formTitle.textContent = 'Iniciar Sesión';
    showMessage('');
});

// Manejar el registro del usuario
registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Validación de la contraseña en el cliente
    const password = document.getElementById('register-password').value;
    if (password.length < 8) {
        showMessage("La contraseña debe tener al menos 8 caracteres.");
        return;
    }

    const formData = new FormData(registerForm);
    formData.append('action', 'register');

    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });

    const result = await response.json();
    if (result.success) {
        showMessage(result.message, 'success');
        // Redirigir al formulario de login después de un registro exitoso
        setTimeout(() => {
            showView('login-view');
            formTitle.textContent = 'Iniciar Sesión';
            showMessage('');
        }, 2000);
    } else {
        showMessage(result.message);
    }
});

// Manejar el inicio de sesión
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(loginForm);
    formData.append('action', 'login');
    // El formulario ya tiene los campos 'ci' y 'password' con los nombres correctos
    // No es necesario modificar formData.append() aquí

    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });

    const result = await response.json();
    if (result.success) {
        // Almacenar datos del usuario en la sesión del navegador
        for (const key in result.user) {
            sessionStorage.setItem(`user_${key}`, result.user[key]);
        }
        showMessage(result.message, 'success');
        checkProfileStatus(result.user);
    } else {
        showMessage(result.message);
    }
});

// Revisa si el perfil está completo y muestra la vista correcta
function checkProfileStatus(user) {
    if (user.role === 'medico' && user.is_temp_password == 1) {
        showChangePasswordForm();
    } else if (user.role === 'paciente' && user.genero === null) {
        showProfileCompletionForm(user.role);
    } else if (user.role === 'medico' && user.especialidad === null) {
        showProfileCompletionForm(user.role);
    } else {
        showDashboard(user);
    }
}

// Muestra el formulario para cambiar la contraseña
function showChangePasswordForm() {
    formTitle.textContent = 'Cambiar Contraseña Temporal';
    showView('change-password-view');
    showMessage("¡Es tu primer inicio de sesión! Por favor, cambia tu contraseña.", 'info');
}

// Manejar el envío del formulario de cambio de contraseña
if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(changePasswordForm);
        formData.append('action', 'change_password');
        formData.append('user_id', sessionStorage.getItem('user_user_id'));

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();
        if (result.success) {
            showMessage(result.message, 'success');
            // Almacenar el nuevo estado de la contraseña en la sesión
            sessionStorage.setItem('user_is_temp_password', 0);
            // Volver a cargar el dashboard con los datos actualizados
            const updatedUser = {
                user_id: sessionStorage.getItem('user_user_id'),
                nombres: sessionStorage.getItem('user_nombres'),
                apellidos: sessionStorage.getItem('user_apellidos'),
                role: sessionStorage.getItem('user_role'),
                ci: sessionStorage.getItem('user_ci'),
                telefono: sessionStorage.getItem('user_telefono'),
                fecha_nacimiento: sessionStorage.getItem('user_fecha_nacimiento'),
                email: sessionStorage.getItem('user_email'),
                is_temp_password: 0
            };
            checkProfileStatus(updatedUser);
        } else {
            showMessage(result.message);
        }
    });
}

// Muestra el formulario para completar el perfil
function showProfileCompletionForm(role) {
    formTitle.textContent = 'Completa tu Perfil';
    showView('dashboard-view');
    profileCompletionView.classList.remove('hidden');
    userInfoView.classList.add('hidden');
    
    if (role === 'paciente') {
        patientProfileForm.classList.remove('hidden');
        doctorProfileForm.classList.add('hidden');
    } else {
        patientProfileForm.classList.add('hidden');
        doctorProfileForm.classList.remove('hidden');
    }
}

// Manejar el envío del formulario de perfil de paciente
patientProfileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(patientProfileForm);
    formData.append('action', 'complete_profile');
    formData.append('user_id', sessionStorage.getItem('user_user_id'));
    formData.append('role', 'paciente');
    
    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });

    const result = await response.json();
    if (result.success) {
        showMessage(result.message, 'success');
        // Actualizar el valor del género en la sesión
        sessionStorage.setItem('user_genero', formData.get('genero'));
        // Volver a cargar el dashboard con los datos completos
        const updatedUser = {
            user_id: sessionStorage.getItem('user_user_id'),
            nombres: sessionStorage.getItem('user_nombres'),
            apellidos: sessionStorage.getItem('user_apellidos'),
            role: sessionStorage.getItem('user_role'),
            ci: sessionStorage.getItem('user_ci'),
            telefono: sessionStorage.getItem('user_telefono'),
            fecha_nacimiento: sessionStorage.getItem('user_fecha_nacimiento'),
            email: sessionStorage.getItem('user_email'),
            genero: sessionStorage.getItem('user_genero')
        };
        showDashboard(updatedUser);
    } else {
        showMessage(result.message);
    }
});

// Manejar el envío del formulario de perfil de médico
doctorProfileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(doctorProfileForm);
    formData.append('action', 'complete_profile');
    formData.append('user_id', sessionStorage.getItem('user_user_id'));
    formData.append('role', 'medico');

    const response = await fetch(API_URL, {
        method: 'POST',
        body: formData,
    });

    const result = await response.json();
    if (result.success) {
        showMessage(result.message, 'success');
        // Actualizar los valores en la sesión
        sessionStorage.setItem('user_especialidad', formData.get('especialidad'));
        sessionStorage.setItem('user_numero_licencia', formData.get('numero_licencia'));
        sessionStorage.setItem('user_horario_atencion', formData.get('horario_atencion'));

        const updatedUser = {
            user_id: sessionStorage.getItem('user_user_id'),
            nombres: sessionStorage.getItem('user_nombres'),
            apellidos: sessionStorage.getItem('user_apellidos'),
            role: sessionStorage.getItem('user_role'),
            ci: sessionStorage.getItem('user_ci'),
            telefono: sessionStorage.getItem('user_telefono'),
            fecha_nacimiento: sessionStorage.getItem('user_fecha_nacimiento'),
            email: sessionStorage.getItem('user_email'),
            especialidad: sessionStorage.getItem('user_especialidad'),
            numero_licencia: sessionStorage.getItem('user_numero_licencia'),
            horario_atencion: sessionStorage.getItem('user_horario_atencion'),
        };
        showDashboard(updatedUser);
    } else {
        showMessage(result.message);
    }
});

// Función para mostrar el dashboard con todos los datos
function showDashboard(user) {
    formTitle.textContent = 'Dashboard';
    showView('dashboard-view');
    profileCompletionView.classList.add('hidden');
    userInfoView.classList.remove('hidden');

    document.getElementById('user-name').textContent = user.nombres;
    document.getElementById('user-role').textContent = user.role;
    document.getElementById('user-id').textContent = user.user_id;
    document.getElementById('user-ci').textContent = user.ci;
    document.getElementById('user-nombres').textContent = user.nombres;
    document.getElementById('user-apellidos').textContent = user.apellidos;
    document.getElementById('user-telefono').textContent = user.telefono;
    document.getElementById('user-dob').textContent = user.fecha_nacimiento;
    document.getElementById('user-email').textContent = user.email;

    if (user.role === 'paciente') {
        patientInfoView.classList.remove('hidden');
        doctorInfoView.classList.add('hidden');
        document.getElementById('patient-genero-info').textContent = user.genero || 'No especificado';

        // Lógica para el paciente: muestra el calendario y carga los médicos
        appointmentManagementView.classList.remove('hidden');
        loadDoctors();
    } else { // medico
        patientInfoView.classList.add('hidden');
        doctorInfoView.classList.remove('hidden');
        document.getElementById('doctor-especialidad-info').textContent = user.especialidad || 'No especificado';
        document.getElementById('doctor-licencia-info').textContent = user.numero_licencia || 'No especificado';
        document.getElementById('doctor-horario-info').textContent = user.horario_atencion || 'No especificado';

        // Ocultar la vista de citas si el usuario no es un paciente
        appointmentManagementView.classList.add('hidden'); 
    }
}

// Manejar el cierre de sesión
logoutBtn.addEventListener('click', () => {
    sessionStorage.clear(); // Limpiar datos de sesión
    showView('login-view');
    formTitle.textContent = 'Iniciar Sesión';
    showMessage('Sesión cerrada correctamente.', 'success');
});

// Cargar la vista inicial al cargar la página
window.onload = function() {
    // Comprobar si hay una sesión activa al cargar la página
    const userId = sessionStorage.getItem('user_user_id');
    if (userId) {
        const user = {
            user_id: userId,
            nombres: sessionStorage.getItem('user_nombres'),
            apellidos: sessionStorage.getItem('user_apellidos'),
            role: sessionStorage.getItem('user_role'),
            ci: sessionStorage.getItem('user_ci'),
            telefono: sessionStorage.getItem('user_telefono'),
            fecha_nacimiento: sessionStorage.getItem('user_fecha_nacimiento'),
            email: sessionStorage.getItem('user_email'),
            genero: sessionStorage.getItem('user_genero'),
            especialidad: sessionStorage.getItem('user_especialidad'),
            numero_licencia: sessionStorage.getItem('user_numero_licencia'),
            horario_atencion: sessionStorage.getItem('user_horario_atencion'),
            is_temp_password: sessionStorage.getItem('user_is_temp_password')
        };
        checkProfileStatus(user);
    } else {
        showView('login-view');
    }
}

// Cargar la lista de médicos en el selector
async function loadDoctors() {
    const response = await fetch(API_URL, {
        method: 'POST',
        body: new URLSearchParams('action=get_doctors')
    });
    const result = await response.json();

    if (result.success) {
        doctorSelect.innerHTML = '<option value="">Seleccione un médico</option>';
        result.doctors.forEach(doctor => {
            const option = document.createElement('option');
            option.value = doctor.user_id;
            option.textContent = `${doctor.nombres} ${doctor.apellidos} - ${doctor.especialidad}`;
            doctorSelect.appendChild(option);
        });
    } else {
        showMessage(result.message);
    }
}

// Deshabilitar fechas pasadas en el selector de fecha
const today = new Date().toISOString().split('T')[0];
dateSelect.min = today;

// Manejar el clic en el botón "Ver Disponibilidad"
checkAvailabilityBtn.addEventListener('click', async () => {
    const medicoId = doctorSelect.value;
    const fecha = dateSelect.value;

    if (!medicoId || !fecha) {
        showMessage('Por favor, seleccione un médico y una fecha.');
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
        displayAvailability(result.availability, medicoId, fecha);
    } else {
        showMessage(result.message);
    }
});

// Mostrar las franjas horarias disponibles
function displayAvailability(timeSlots, medicoId, fecha) {
    timeSlotsContainer.innerHTML = '';
    availabilityArea.classList.remove('hidden');
    selectedTime = null;
    bookAppointmentBtn.classList.add('hidden');
    appointmentSummary.classList.add('hidden');

    const doctorName = doctorSelect.options[doctorSelect.selectedIndex].text;
    document.getElementById('selected-doctor-name').textContent = doctorName;

    if (timeSlots.length === 0) {
        timeSlotsContainer.innerHTML = '<p>No hay horarios disponibles para esta fecha.</p>';
        return;
    }

    timeSlots.forEach(time => {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = time;
        timeSlot.addEventListener('click', () => {
            // Des-seleccionar la franja anterior
            const selected = document.querySelector('.time-slot.selected');
            if (selected) {
                selected.classList.remove('selected');
            }
            // Seleccionar la nueva franja
            timeSlot.classList.add('selected');
            selectedTime = time;
            selectedDoctorId = medicoId;

            // Mostrar el resumen y el botón de agendar
            summaryDoctor.textContent = doctorName;
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
    formData.append('paciente_id', sessionStorage.getItem('user_user_id'));
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
        doctorSelect.value = '';
    } else {
        showMessage(result.message);
    }
});