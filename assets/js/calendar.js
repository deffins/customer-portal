/**
 * Booking Calendar JavaScript
 * Shared component for both admin and client views
 */

(function() {
    'use strict';

    const CONFIG = window.bcConfig || {
        ajaxUrl: '',
        nonce: '',
        isAdmin: false
    };

    const HOURS = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
    const DAYS_OF_WEEK = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    let currentWeekOffset = 0; // 0 = this week, 1 = next week, etc.
    let slotsData = {}; // Store slot data: { "2025-12-02_8": "free", ... }

    /**
     * Initialize calendar
     */
    function init() {
        const container = document.getElementById('bc-calendar-container');
        if (!container) return;

        renderCalendar();
        loadSlotsForCurrentWeek();
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
                const status = slotsData[slotKey] || 'busy';
                const clickable = CONFIG.isAdmin && !isPast;

                html += `<div class="bc-cell bc-slot-cell bc-status-${status} ${isPast ? 'bc-past' : ''} ${clickable ? 'bc-clickable' : ''}"
                              data-date="${dateStr}"
                              data-hour="${hour}"
                              data-status="${status}">
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
        formData.append('action', 'bc_get_slots');
        formData.append('nonce', CONFIG.nonce);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

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
                    slotsData[key] = slot.status;
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
        const formData = new FormData();
        formData.append('action', 'bc_toggle_slot');
        formData.append('nonce', CONFIG.nonce);
        formData.append('date', date);
        formData.append('hour', hour);

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const key = `${date}_${hour}`;
                slotsData[key] = data.data.status;
                renderCalendar();
            } else {
                alert('Error: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(error => {
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
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
