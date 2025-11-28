// Enhanced booking functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date fields
    initializeDates();
    
    // Room card booking functionality
    initializeRoomCardBooking();
    
    // Quick book buttons
    initializeQuickBook();
    
    // Show any messages from URL parameters
    showURLMessages();
});

function initializeDates() {
    // Set minimum dates for booking form
    const today = new Date().toISOString().split('T')[0];
    const checkinInput = document.getElementById('checkin');
    const checkoutInput = document.getElementById('checkout');
    
    if (checkinInput) {
        checkinInput.min = today;
        
        // Set default check-in to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];
        
        if (!checkinInput.value) {
            checkinInput.value = tomorrowStr;
        }
        
        // Update checkout min date when checkin changes
        checkinInput.addEventListener('change', function() {
            checkoutInput.min = this.value;
            
            // Auto-set checkout to check-in + 1 day if not set or before check-in
            if (!checkoutInput.value || new Date(checkoutInput.value) <= new Date(this.value)) {
                const nextDay = new Date(this.value);
                nextDay.setDate(nextDay.getDate() + 1);
                checkoutInput.value = nextDay.toISOString().split('T')[0];
            }
        });
        
        // Initialize checkout date
        if (!checkoutInput.value) {
            const nextDay = new Date(checkinInput.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkoutInput.value = nextDay.toISOString().split('T')[0];
        }
        checkoutInput.min = checkinInput.value;
    }
}

function initializeRoomCardBooking() {
    // Room card booking buttons
    const bookNowButtons = document.querySelectorAll('.book-now-btn');
    bookNowButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const roomType = this.getAttribute('data-room-type');
            const roomId = this.getAttribute('data-room-id');
            const roomNumber = this.getAttribute('data-room-number');
            const roomPrice = this.getAttribute('data-room-price');
            
            // Scroll to booking form
            document.getElementById('bookingForm').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
            
            // Preselect the room
            preselectRoom(roomType, roomId, roomNumber, roomPrice);
            
            // Show success message
            showNotification('Room selected! Please choose your dates and click "Check Availability & Book Now".', 'success');
        });
    });
}

function initializeQuickBook() {
    // Quick book buttons
    const quickBookButtons = document.querySelectorAll('.quick-book-btn');
    quickBookButtons.forEach(button => {
        button.addEventListener('click', function() {
            const days = parseInt(this.getAttribute('data-days'));
            setQuickDates(days);
            showNotification(`Dates set for ${days} nights! Click "Check Availability & Book Now" to proceed.`, 'success');
        });
    });
}

function showURLMessages() {
    // Show success/error messages from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showNotification(decodeURIComponent(urlParams.get('success')), 'success');
    }
    if (urlParams.has('error')) {
        showNotification(decodeURIComponent(urlParams.get('error')), 'error');
    }
    
    // Show session messages if any
    if (typeof sessionMessages !== 'undefined') {
        sessionMessages.forEach(message => {
            showNotification(message.text, message.type);
        });
    }
}

function preselectRoom(roomType, roomId, roomNumber, roomPrice) {
    const roomSelect = document.getElementById('room-type');
    const roomIdInput = document.getElementById('selected_room_id');
    
    if (roomSelect && roomIdInput) {
        // Set room type in dropdown
        roomSelect.value = roomType;
        
        // Set selected room ID in hidden field
        roomIdInput.value = roomId;
        
        // Highlight the selected room
        highlightSelectedRoom(roomType);
        
        // Update room details display
        updateRoomDetails(roomType, roomNumber, roomPrice);
    }
}

function highlightSelectedRoom(roomType) {
    const roomCards = document.querySelectorAll('.room-card');
    roomCards.forEach(card => {
        card.classList.remove('selected');
        const cardRoomType = card.querySelector('.room-details h3').textContent.toLowerCase();
        if (cardRoomType.includes(roomType)) {
            card.classList.add('selected');
            
            // Scroll the selected card into view if needed
            const rect = card.getBoundingClientRect();
            if (rect.top < 0 || rect.bottom > window.innerHeight) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
}

function updateRoomDetails(roomType, roomNumber, roomPrice) {
    const roomDetails = document.getElementById('roomDetails');
    if (!roomDetails) {
        // Create room details element if it doesn't exist
        const bookingForm = document.getElementById('bookingForm');
        const roomDetailsHTML = `
            <div id="roomDetails" class="room-details">
                <div class="room-info-card">
                    <h5>Selected Room Details</h5>
                    <div class="room-info">
                        <span>${ucfirst(roomType)} Room</span>
                        <span>Room ${roomNumber}</span>
                        <span>₦${parseFloat(roomPrice).toLocaleString('en-US', {minimumFractionDigits: 2})}/night</span>
                    </div>
                </div>
            </div>
        `;
        bookingForm.insertAdjacentHTML('afterbegin', roomDetailsHTML);
    } else {
        // Update existing room details
        document.getElementById('roomDetails').innerHTML = `
            <div class="room-info-card">
                <h5>Selected Room Details</h5>
                <div class="room-info">
                    <span>${ucfirst(roomType)} Room</span>
                    <span>Room ${roomNumber}</span>
                    <span>₦${parseFloat(roomPrice).toLocaleString('en-US', {minimumFractionDigits: 2})}/night</span>
                </div>
            </div>
        `;
    }
}

function setQuickDates(days) {
    const checkinInput = document.getElementById('checkin');
    const checkoutInput = document.getElementById('checkout');
    
    if (checkinInput && checkoutInput) {
        // Set check-in to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        checkinInput.value = tomorrow.toISOString().split('T')[0];
        
        // Set check-out based on number of days
        const checkout = new Date(tomorrow);
        checkout.setDate(checkout.getDate() + days);
        checkoutInput.value = checkout.toISOString().split('T')[0];
        checkoutInput.min = checkinInput.value;
    }
}

// Helper function to capitalize first letter
function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Notification function
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => {
        notification.remove();
    });
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Add CSS for notifications and room selection
const additionalStyles = `
    .room-card.selected {
        border: 2px solid var(--primary) !important;
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(37, 99, 235, 0.15) !important;
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        color: var(--dark);
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
        border-left: 4px solid var(--primary);
    }
    
    .notification.success {
        border-left-color: var(--success);
    }
    
    .notification.error {
        border-left-color: var(--error);
    }
    
    .notification.warning {
        border-left-color: var(--warning);
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }
    
    .notification-content i {
        font-size: 1.2rem;
    }
    
    .notification.success .notification-content i {
        color: var(--success);
    }
    
    .notification.error .notification-content i {
        color: var(--error);
    }
    
    .notification.warning .notification-content i {
        color: var(--warning);
    }
    
    .notification.info .notification-content i {
        color: var(--primary);
    }
    
    .notification-close {
        background: none;
        border: none;
        color: var(--gray);
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: background-color 0.3s;
    }
    
    .notification-close:hover {
        background: rgba(0,0,0,0.1);
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .quick-book-actions {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--gray-light);
    }
    
    .quick-book-actions h4 {
        margin-bottom: 10px;
        color: var(--dark);
        font-size: 1rem;
    }
    
    .quick-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .room-details {
        margin-bottom: 20px;
    }
    
    .room-info-card {
        background: var(--light);
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid var(--primary);
    }
    
    .room-info-card h5 {
        margin-bottom: 10px;
        color: var(--dark);
    }
    
    .room-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .room-info span {
        padding: 8px 12px;
        background: white;
        border-radius: 6px;
        font-size: 0.9rem;
        text-align: center;
        border: 1px solid var(--gray-light);
    }
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);