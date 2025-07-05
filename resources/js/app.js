import './bootstrap';
import Alpine from 'alpinejs';

// Make Alpine available globally
window.Alpine = Alpine;

// Start Alpine
Alpine.start();

// Custom JavaScript for the Golf Referee System
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages after 5 seconds
    const flashMessages = document.querySelectorAll('.alert-success, .alert-error, .alert-warning');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 5000);
    });

    // Confirm dialogs for delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = button.getAttribute('data-confirm') || 'Sei sicuro di voler procedere?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
