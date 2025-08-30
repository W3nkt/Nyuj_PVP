// Bull PVP Platform - Main JavaScript

// Get base path for API calls
function getBasePath() {
    const path = window.location.pathname;
    if (path.includes('Bull_PVP')) {
        const parts = path.split('/');
        const index = parts.indexOf('Bull_PVP');
        return parts.slice(0, index + 1).join('/');
    }
    return '/Bull_PVP';
}

document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    initializeTooltips();
    initializeModals();
    initializeFormValidation();
    initializeLiveUpdates();
    initializeEventHandlers();
}

function initializeTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function initializeModals() {
    // Handle modal events
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            // Reset form if exists
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                clearFormErrors(form);
            }
        });
    });
}

function initializeFormValidation() {
    // Add validation to all forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    clearFormErrors(form);
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'This field is required');
            isValid = false;
        } else if (input.type === 'email' && !isValidEmail(input.value)) {
            showFieldError(input, 'Please enter a valid email address');
            isValid = false;
        } else if (input.type === 'number' && parseFloat(input.value) <= 0) {
            showFieldError(input, 'Please enter a valid amount');
            isValid = false;
        }
    });
    
    return isValid;
}

function clearFormErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.textContent = message;
    field.parentNode.appendChild(feedback);
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function initializeLiveUpdates() {
    // Update event statuses and wallet balances periodically
    setInterval(updateLiveData, 30000); // Every 30 seconds
}

function updateLiveData() {
    updateEventStatuses();
    updateWalletBalance();
    updateNotifications();
}

function updateEventStatuses() {
    fetch(getBasePath() + '/api/events_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.events.forEach(event => {
                    const statusElement = document.querySelector(`[data-event-id="${event.id}"] .event-status`);
                    if (statusElement) {
                        statusElement.textContent = event.status.toUpperCase();
                        statusElement.className = `event-status status-${event.status.replace('_', '-')}`;
                    }
                });
            }
        })
        .catch(error => console.error('Error updating event statuses:', error));
}

function updateWalletBalance() {
    const balanceElement = document.querySelector('.balance-amount');
    if (balanceElement) {
        fetch(getBasePath() + '/api/wallet_balance.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    balanceElement.textContent = '$' + parseFloat(data.balance).toFixed(2);
                }
            })
            .catch(error => console.error('Error updating wallet balance:', error));
    }
}

function updateNotifications() {
    fetch(getBasePath() + '/api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                showNotification(data.notifications[0]);
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

function initializeEventHandlers() {
    // Join event button
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('join-event-btn')) {
            const eventId = e.target.dataset.eventId;
            const stakeAmount = e.target.dataset.stake;
            showJoinEventModal(eventId, stakeAmount);
        }
    });

    // Vote buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('vote-btn')) {
            const eventId = e.target.dataset.eventId;
            const winnerId = e.target.dataset.winnerId;
            submitVote(eventId, winnerId);
        }
    });

    // Deposit/Withdrawal buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('deposit-btn')) {
            showDepositModal();
        } else if (e.target.classList.contains('withdraw-btn')) {
            showWithdrawModal();
        }
    });
}

function showJoinEventModal(eventId, stakeAmount) {
    const modal = document.getElementById('joinEventModal');
    if (modal) {
        document.getElementById('modalEventId').value = eventId;
        document.getElementById('modalStakeAmount').textContent = '$' + stakeAmount;
        new bootstrap.Modal(modal).show();
    }
}

function joinEvent(eventId) {
    showLoading();
    
    fetch(getBasePath() + '/api/join_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ event_id: eventId })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Successfully joined the event!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to join event', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while joining the event', 'error');
        console.error('Error:', error);
    });
}

function submitVote(eventId, winnerId) {
    if (!confirm('Are you sure you want to submit this vote? This action cannot be undone.')) {
        return;
    }
    
    showLoading();
    
    fetch(getBasePath() + '/api/submit_vote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            event_id: eventId, 
            winner_id: winnerId 
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Vote submitted successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to submit vote', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while submitting vote', 'error');
        console.error('Error:', error);
    });
}

function showDepositModal() {
    const modal = document.getElementById('depositModal');
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function showWithdrawModal() {
    const modal = document.getElementById('withdrawModal');
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function processDeposit(amount) {
    showLoading();
    
    fetch(getBasePath() + '/api/deposit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ amount: amount })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Deposit successful!', 'success');
            updateWalletBalance();
            bootstrap.Modal.getInstance(document.getElementById('depositModal')).hide();
        } else {
            showNotification(data.message || 'Deposit failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred during deposit', 'error');
        console.error('Error:', error);
    });
}

function processWithdrawal(amount) {
    showLoading();
    
    fetch(getBasePath() + '/api/withdraw.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ amount: amount })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Withdrawal successful!', 'success');
            updateWalletBalance();
            bootstrap.Modal.getInstance(document.getElementById('withdrawModal')).hide();
        } else {
            showNotification(data.message || 'Withdrawal failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred during withdrawal', 'error');
        console.error('Error:', error);
    });
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function showLoading() {
    const loading = document.createElement('div');
    loading.id = 'loadingOverlay';
    loading.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center';
    loading.style.cssText = 'background: rgba(255,255,255,0.8); z-index: 9999;';
    loading.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.remove();
    }
}

// Utility functions
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Description toggle function for event cards
function toggleDescription(eventId) {
    const descElement = document.getElementById('desc-' + eventId);
    const toggleLink = descElement.querySelector('.description-toggle');
    
    if (descElement.classList.contains('description-collapsed')) {
        descElement.classList.remove('description-collapsed');
        descElement.classList.add('description-expanded');
        toggleLink.textContent = 'Show less';
    } else {
        descElement.classList.remove('description-expanded');
        descElement.classList.add('description-collapsed');
        toggleLink.textContent = 'Show more';
    }
}