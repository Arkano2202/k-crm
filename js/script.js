// Navegación entre secciones
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        // Remover clase active de todos los items
        document.querySelectorAll('.menu-item').forEach(i => {
            i.classList.remove('active');
        });
        
        // Agregar clase active al item clickeado
        this.classList.add('active');
        
        // Cambiar el título de la página
        const sectionName = this.getAttribute('data-section');
        const pageTitle = document.querySelector('.page-title');
        
        switch(sectionName) {
            case 'dashboard':
                pageTitle.textContent = 'Dashboard';
                // Aquí podrías hacer una petición AJAX para cargar el contenido
                break;
            case 'leads':
                pageTitle.textContent = 'Leads';
                // Cargar contenido de leads via AJAX
                break;
            case 'calendar':
                pageTitle.textContent = 'Calendario';
                break;
            case 'users':
                pageTitle.textContent = 'Usuarios';
                break;
            case 'reports':
                pageTitle.textContent = 'Informes';
                break;
        }
    });
});

// Simulación de notificaciones
document.querySelector('.notification-icon').addEventListener('click', function() {
    alert('Tienes 3 notificaciones nuevas');
});

