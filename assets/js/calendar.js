/**
 * Booking Calendar JavaScript
 * Shared component for both admin and client views
 */

(function() {
    'use strict';

    // Support both old (bcConfig) and new (cpCalendarConfig) configurations
    const CONFIG = window.cpCalendarConfig || window.bcConfig || {
        ajaxUrl: '',
        nonce: '',
        isAdmin: false,
        isCustomer: false
    };

    const HOURS = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
    const DAYS_OF_WEEK = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    let currentWeekOffset = 0; // 0 = this week, 1 = next week, etc.
    let slotsData = {}; // Store slot data: { "2025-12-02_8": {status: "free", is_mine: false, customer_name: "..."}, ... }
    let isUpdating = false; // Prevent multi-click
    let pendingUpdates = new Set(); // Track slots being updated

    /**
     * Initialize calendar
     */
    function init() {
        const container = document.getElementById('bc-calendar-container');
        if (!container) return;

        renderCalendar();
        loadSlotsForCurrentWeek();

        // Load appointments list for customer view
        if (CONFIG.isCustomer) {
            loadMyAppointments();
        }
    }

    /**
     * Load and display user's upcoming appointments
     */
    function loadMyAppointments() {
        const container = document.getElementById('my-appointments-list');
        if (!container) return;

        const telegramId = window.cpGetUserTelegramId ? window.cpGetUserTelegramId() : null;
        if (!telegramId) {
            container.innerHTML = '<p style="color: #999; font-style: italic;">No upcoming appointments</p>';
            return;
        }

        const formData = new FormData();
        formData.append('action', 'cp_get_my_appointments');
        formData.append('nonce', CONFIG.nonce);
        formData.append('telegram_id', telegramId);

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.appointments) {
                displayMyAppointments(data.data.appointments);
            } else {
                container.innerHTML = '<p style="color: #999; font-style: italic;">No upcoming appointments</p>';
            }
        })
        .catch(error => {
            console.error('Error loading appointments:', error);
            container.innerHTML = '<p style="color: #d66b41;">Error loading appointments</p>';
        });
    }

    /**
     * Display appointments list
     */
    function displayMyAppointments(appointments) {
        const container = document.getElementById('my-appointments-list');
        if (!container) return;

        if (!appointments || appointments.length === 0) {
            container.innerHTML = '<p style="color: #999; font-style: italic;">No upcoming appointments</p>';
            return;
        }

        let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';

        appointments.forEach(apt => {
            const date = new Date(apt.slot_date);
            const dateStr = date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            const timeStr = `${String(apt.slot_hour).padStart(2, '0')}:00`;

            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: #F5F5F5; border-radius: 8px; border-left: 4px solid #E87C52;">
                    <div>
                        <div style="font-weight: 600; color: #3B4F3D; margin-bottom: 4px;">${dateStr}</div>
                        <div style="color: #666; font-size: 14px;">Time: ${timeStr}</div>
                    </div>
                    <button class="button cancel-apt-btn" data-date="${apt.slot_date}" data-hour="${apt.slot_hour}" style="padding: 6px 14px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">Cancel</button>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Add event listeners to cancel buttons
        const cancelButtons = container.querySelectorAll('.cancel-apt-btn');
        cancelButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const date = this.getAttribute('data-date');
                const hour = parseInt(this.getAttribute('data-hour'));
                handleCancelAppointment(date, hour);
            });
        });
    }

    /**
     * Handle appointment cancellation from the list
     */
    function handleCancelAppointment(date, hour) {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'cp_cancel_booking');
        formData.append('nonce', CONFIG.nonce);
        formData.append('date', date);
        formData.append('hour', hour);

        if (CONFIG.isCustomer && window.cpGetUserTelegramId) {
            formData.append('telegram_id', window.cpGetUserTelegramId());
        }

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload appointments list and calendar
                loadMyAppointments();
                loadSlotsForCurrentWeek();
            } else {
                alert(data.data && data.data.message ? data.data.message : 'Failed to cancel appointment');
            }
        })
        .catch(error => {
            console.error('Error cancelling appointment:', error);
            alert('Error cancelling appointment');
        });
    }

    /**
     * Render calendar grid
     */
    function renderCalendar() {
        const container = document.getElementById('bc-calendar-container');
        const weekDates = getWeekDates(currentWeekOffset);

        let html = '<div class="bc-calendar">';

        // Navigation
        html += '<div class="bc-nav">';
        html += `<button class="bc-nav-btn" id="bc-prev-week" ${currentWeekOffset === 0 ? 'disabled' : ''}>← Previous Week</button>`;
        html += `<span class="bc-week-label">${formatWeekLabel(weekDates)}</span>`;
        html += `<button class="bc-nav-btn" id="bc-next-week" ${currentWeekOffset >= 3 ? 'disabled' : ''}>Next Week →</button>`;
        html += '</div>';

        // Calendar grid
        html += '<div class="bc-grid">';

        // Header row (days)
        html += '<div class="bc-row bc-header-row">';
        html += '<div class="bc-cell bc-time-cell">Time</div>';
        weekDates.forEach(date => {
            const dayName = DAYS_OF_WEEK[date.getDay()];
            const dayNum = date.getDate();
            const month = date.toLocaleString('default', { month: 'short' });
            const isPast = date < new Date().setHours(0, 0, 0, 0);
            html += `<div class="bc-cell bc-day-cell ${isPast ? 'bc-past' : ''}">
                        <div class="bc-day-name">${dayName}</div>
                        <div class="bc-day-date">${month} ${dayNum}</div>
                     </div>`;
        });
        html += '</div>';

        // Time slots rows
        HOURS.forEach(hour => {
            html += '<div class="bc-row">';
            html += `<div class="bc-cell bc-time-cell">${formatHour(hour)}</div>`;

            weekDates.forEach(date => {
                const dateStr = formatDate(date);
                const slotKey = `${dateStr}_${hour}`;
                const isPast = isPastSlot(date, hour);
                const slotData = slotsData[slotKey] || {status: 'blocked', is_mine: false};
                const status = slotData.status;

                // Determine clickability
                let clickable = false;
                let title = '';

                if (CONFIG.isAdmin && !isPast && status !== 'booked') {
                    clickable = true;
                } else if (status === 'booked') {
                    if (slotData.customer_name) {
                        title = `Booked by ${slotData.customer_name}`;
                    } else {
                        title = 'Booked';
                    }
                }

                // Additional classes for customer view
                let extraClass = '';
                if (status === 'booked' && slotData.is_mine) {
                    extraClass = 'bc-booked-mine';
                } else if (status === 'booked' && !slotData.is_mine) {
                    extraClass = 'bc-booked-other';
                }

                html += `<div class="bc-cell bc-slot-cell bc-status-${status} ${extraClass} ${isPast ? 'bc-past' : ''} ${clickable ? 'bc-clickable' : ''}"
                              data-date="${dateStr}"
                              data-hour="${hour}"
                              data-status="${status}"
                              ${title ? `title="${title}"` : ''}>
                         </div>`;
            });

            html += '</div>';
        });

        html += '</div>'; // bc-grid
        html += '</div>'; // bc-calendar

        container.innerHTML = html;

        // Attach event listeners
        attachEventListeners();
    }

    /**
     * Get dates for a specific week
     */
    function getWeekDates(weekOffset) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Start from Monday of current week + offset
        const startDate = new Date(today);
        const dayOfWeek = startDate.getDay();
        const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // Monday
        startDate.setDate(startDate.getDate() + diff + (weekOffset * 7));

        const dates = [];
        for (let i = 0; i < 7; i++) {
            const date = new Date(startDate);
            date.setDate(startDate.getDate() + i);
            dates.push(date);
        }

        return dates;
    }

    /**
     * Format week label
     */
    function formatWeekLabel(dates) {
        const start = dates[0];
        const end = dates[6];
        const startStr = `${start.toLocaleString('default', { month: 'short' })} ${start.getDate()}`;
        const endStr = `${end.toLocaleString('default', { month: 'short' })} ${end.getDate()}, ${end.getFullYear()}`;
        return `${startStr} - ${endStr}`;
    }

    /**
     * Format date as YYYY-MM-DD
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Format hour as HH:00
     */
    function formatHour(hour) {
        return `${String(hour).padStart(2, '0')}:00`;
    }

    /**
     * Check if slot is in the past
     */
    function isPastSlot(date, hour) {
        const slotTime = new Date(date);
        slotTime.setHours(hour, 0, 0, 0);
        return slotTime < new Date();
    }

    /**
     * Load slots from server
     */
    function loadSlotsForCurrentWeek() {
        const weekDates = getWeekDates(currentWeekOffset);
        const startDate = formatDate(weekDates[0]);
        const endDate = formatDate(weekDates[6]);

        const formData = new FormData();
        // Support both old (bc_get_slots) and new (cp_get_calendar_slots) actions
        const action = CONFIG.isAdmin && !CONFIG.isCustomer ? 'cp_get_calendar_slots' : (window.cpCalendarConfig ? 'cp_get_calendar_slots' : 'bc_get_slots');
        formData.append('action', action);
        formData.append('nonce', CONFIG.nonce);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        // Add telegram_id for customer view
        if (CONFIG.isCustomer && window.cpGetUserTelegramId) {
            const telegramId = window.cpGetUserTelegramId();
            if (telegramId) {
                formData.append('telegram_id', telegramId);
            }
        }

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update slotsData
                slotsData = {};
                data.data.slots.forEach(slot => {
                    const key = `${slot.slot_date}_${slot.slot_hour}`;
                    slotsData[key] = {
                        status: slot.status,
                        is_mine: slot.is_mine || false,
                        customer_name: slot.customer_name || null
                    };
                });
                renderCalendar();
            }
        })
        .catch(error => console.error('Error loading slots:', error));
    }

    /**
     * Toggle slot (admin only)
     */
    function toggleSlot(date, hour, currentStatus) {
        const key = `${date}_${hour}`;

        // Prevent multi-click on same slot
        if (pendingUpdates.has(key)) {
            return;
        }

        // Optimistic update - change UI immediately
        const slotData = slotsData[key] || {status: 'blocked', is_mine: false};
        const newStatus = slotData.status === 'free' ? 'blocked' : 'free';
        const oldStatus = slotData.status;

        // Update local data optimistically
        slotsData[key] = {
            status: newStatus,
            is_mine: false,
            customer_name: null
        };

        // Mark as updating
        pendingUpdates.add(key);

        // Update just the clicked cell immediately (optimistic UI update)
        const cell = document.querySelector(`.bc-slot-cell[data-date="${date}"][data-hour="${hour}"]`);
        if (cell) {
            // Remove old status class
            cell.classList.remove('bc-status-free', 'bc-status-blocked', 'bc-status-booked');
            // Add new status class
            cell.classList.add(`bc-status-${newStatus}`);
            // Update data attribute
            cell.setAttribute('data-status', newStatus);
            // Show loading state
            cell.style.opacity = '0.7';
        }

        const formData = new FormData();
        const action = window.cpCalendarConfig ? 'cp_toggle_slot_availability' : 'bc_toggle_slot';
        formData.append('action', action);
        formData.append('nonce', CONFIG.nonce);
        formData.append('date', date);
        formData.append('hour', hour);

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            pendingUpdates.delete(key);

            if (data.success) {
                // Confirm with server response
                slotsData[key] = {
                    status: data.data.status,
                    is_mine: false,
                    customer_name: null
                };
                // Restore opacity only
                if (cell) {
                    cell.style.opacity = '1';
                }
            } else {
                // Revert on error
                slotsData[key] = {
                    status: oldStatus,
                    is_mine: false,
                    customer_name: null
                };
                // Revert the cell visually
                if (cell) {
                    cell.classList.remove('bc-status-free', 'bc-status-blocked', 'bc-status-booked');
                    cell.classList.add(`bc-status-${oldStatus}`);
                    cell.setAttribute('data-status', oldStatus);
                    cell.style.opacity = '1';
                }
                alert('Error: ' + (data.data.message || data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            pendingUpdates.delete(key);
            // Revert on error
            slotsData[key] = {
                status: oldStatus,
                is_mine: false,
                customer_name: null
            };
            // Revert the cell visually
            if (cell) {
                cell.classList.remove('bc-status-free', 'bc-status-blocked', 'bc-status-booked');
                cell.classList.add(`bc-status-${oldStatus}`);
                cell.setAttribute('data-status', oldStatus);
                cell.style.opacity = '1';
            }
            console.error('Error toggling slot:', error);
            alert('Failed to update slot');
        });
    }

    /**
     * Attach event listeners
     */
    function attachEventListeners() {
        // Navigation buttons
        const prevBtn = document.getElementById('bc-prev-week');
        const nextBtn = document.getElementById('bc-next-week');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentWeekOffset > 0) {
                    currentWeekOffset--;
                    loadSlotsForCurrentWeek();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentWeekOffset < 3) {
                    currentWeekOffset++;
                    loadSlotsForCurrentWeek();
                }
            });
        }

        // Slot clicks (admin only)
        if (CONFIG.isAdmin) {
            document.querySelectorAll('.bc-slot-cell.bc-clickable').forEach(cell => {
                cell.addEventListener('click', function() {
                    const date = this.getAttribute('data-date');
                    const hour = parseInt(this.getAttribute('data-hour'));
                    const status = this.getAttribute('data-status');
                    toggleSlot(date, hour, status);
                });
            });
        }

        // Slot clicks (customer mode)
        if (CONFIG.isCustomer) {
            document.querySelectorAll('.bc-slot-cell').forEach(cell => {
                const status = cell.getAttribute('data-status');
                const slotKey = `${cell.getAttribute('data-date')}_${cell.getAttribute('data-hour')}`;
                const slotData = slotsData[slotKey] || {status: 'blocked', is_mine: false};

                // Skip if slot is being updated
                if (pendingUpdates.has(slotKey)) {
                    cell.style.opacity = '0.6';
                    cell.style.pointerEvents = 'none';
                    return;
                }

                // Free slots - can book
                if (status === 'free' && !cell.classList.contains('bc-past')) {
                    cell.classList.add('bc-clickable');
                    cell.addEventListener('click', function() {
                        // Prevent multi-click
                        if (pendingUpdates.has(slotKey)) return;

                        const date = this.getAttribute('data-date');
                        const hour = parseInt(this.getAttribute('data-hour'));
                        if (window.cpShowBookingModal) {
                            window.cpShowBookingModal('book', date, hour);
                        }
                    });
                }

                // My bookings - can cancel
                if (status === 'booked' && slotData.is_mine && !cell.classList.contains('bc-past')) {
                    cell.classList.add('bc-clickable');
                    cell.addEventListener('click', function() {
                        // Prevent multi-click
                        if (pendingUpdates.has(slotKey)) return;

                        const date = this.getAttribute('data-date');
                        const hour = parseInt(this.getAttribute('data-hour'));
                        if (window.cpShowBookingModal) {
                            window.cpShowBookingModal('cancel', date, hour);
                        }
                    });
                }
            });
        }
    }

    // Expose reload function for portal.js to call after booking/cancel
    window.cpReloadCalendar = function() {
        loadSlotsForCurrentWeek();
    };

    // Expose function to reload appointments list
    window.cpReloadMyAppointments = function() {
        loadMyAppointments();
    };

    // Expose function to mark slot as updating (for optimistic updates)
    window.cpMarkSlotUpdating = function(slotKey) {
        pendingUpdates.add(slotKey);
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
