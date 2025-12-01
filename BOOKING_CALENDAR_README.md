# Booking Calendar Plugin - MVP

A simple WordPress plugin for managing booking time slots with a clean week-view calendar interface.

## Features

- **Week-view calendar** with 7-day grid
- **Time slots**: 08:00 - 20:00 (hourly intervals)
- **Date range**: Today to +4 weeks ahead
- **Two views**:
  - **Admin view**: Toggle slots between free/busy
  - **Client view**: Read-only display of availability
- **Smart features**:
  - Past slots are grayed out and unclickable
  - Week navigation (Previous/Next)
  - All slots default to "busy"
  - Color-coded: Green = Free, Red = Busy

## Installation

1. **Upload the plugin folder** to:
   ```
   /wp-content/plugins/booking-calendar/
   ```
   Or upload the ZIP through WordPress Admin → Plugins → Add New

2. **Activate the plugin**:
   - Go to WordPress Admin → Plugins
   - Find "Booking Calendar"
   - Click "Activate"

3. **Database tables will be created automatically** with all slots initialized as "busy"

## Usage

### Admin View

1. Go to WordPress Admin → **Calendar** (in sidebar menu)
2. You'll see the week-view calendar
3. **Click any slot** to toggle:
   - Red (busy) → Green (free)
   - Green (free) → Red (busy)
4. Use **Previous Week** / **Next Week** to navigate
5. Past slots cannot be modified

### Client View (Frontend)

1. Create or edit a WordPress **Page**
2. Add this shortcode:
   ```
   [booking_calendar]
   ```
3. Publish the page
4. Clients will see a read-only calendar showing availability
5. Green = available, Red = busy

## File Structure

```
booking-calendar/
├── booking-calendar.php          # Main plugin file
├── includes/
│   ├── class-calendar-database.php   # Database operations
│   ├── class-calendar-ajax.php       # AJAX handlers
│   ├── class-calendar-admin.php      # Admin interface
│   └── class-calendar-frontend.php   # Frontend shortcode
├── assets/
│   ├── css/
│   │   └── calendar.css          # Calendar styles
│   └── js/
│       └── calendar.js           # Calendar JavaScript (shared component)
└── BOOKING_CALENDAR_README.md
```

## Technical Details

### Database Schema

**Table**: `wp_booking_calendar_slots`

| Column       | Type      | Description                    |
|-------------|-----------|--------------------------------|
| id          | mediumint | Primary key                    |
| slot_date   | date      | Date (YYYY-MM-DD)             |
| slot_hour   | tinyint   | Hour (8-20)                   |
| status      | varchar   | 'free' or 'busy'              |
| created_at  | datetime  | Creation timestamp            |
| updated_at  | datetime  | Last update timestamp         |

**Unique constraint**: (slot_date, slot_hour)

### Data Model

Slots are stored as:
```json
{
  "slot_date": "2025-12-02",
  "slot_hour": 9,
  "status": "free"
}
```

If a slot doesn't exist in the database, it defaults to "busy".

### AJAX Endpoints

1. **bc_get_slots** (both admin & client):
   - Gets slots for a date range
   - Parameters: start_date, end_date, nonce

2. **bc_toggle_slot** (admin only):
   - Toggles a slot between free/busy
   - Parameters: date, hour, nonce
   - Requires `manage_options` capability

### JavaScript Component

The calendar uses a **shared JavaScript component** (`calendar.js`) that:
- Renders the week grid dynamically
- Handles week navigation
- Loads slot data via AJAX
- Toggles slots (admin only)
- Detects and disables past slots

The component behavior changes based on `bcConfig.isAdmin` flag.

## Customization

### Change Time Range

Edit `includes/class-calendar-database.php`:
```php
// Line ~97: Change hour range
for ($hour = 8; $hour <= 20; $hour++) { ... }
```

Edit `assets/js/calendar.js`:
```javascript
// Line 11: Update hours array
const HOURS = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
```

### Change Colors

Edit `assets/css/calendar.css`:
```css
.bc-status-free {
    background: #27ae60; /* Green */
}

.bc-status-busy {
    background: #e74c3c; /* Red */
}
```

### Extend Date Range

Edit `assets/js/calendar.js`:
```javascript
// Line 106 & 157: Change week limit
${currentWeekOffset >= 3 ? 'disabled' : ''}  // 3 = 4 weeks (0-3)
if (currentWeekOffset < 3) { ... }
```

Edit `includes/class-calendar-database.php`:
```php
// Line 58: Change initialization range
$end_date = new DateTime('+4 weeks');
```

## Security

- ✅ AJAX nonce verification
- ✅ Admin capability checks (`manage_options`)
- ✅ Input sanitization and validation
- ✅ SQL prepared statements
- ✅ Past slot modification prevention

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (responsive design)

## Future Enhancements (Out of Scope for MVP)

This MVP intentionally **does not** include:
- ❌ Booking/reservation system
- ❌ User details or client info
- ❌ Email notifications
- ❌ Payment integration
- ❌ Recurring availability patterns
- ❌ Multiple calendars/resources
- ❌ Export functionality

These can be added in future versions.

## Troubleshooting

### Calendar doesn't show
- Check if shortcode is correct: `[booking_calendar]`
- Clear browser cache (Ctrl+Shift+R)
- Check browser console for errors (F12)

### Slots don't toggle (admin)
- Verify you're logged in as admin
- Check browser console for AJAX errors
- Verify nonce is generated correctly

### Past slots are clickable
- This shouldn't happen - check JavaScript console
- Verify server time is correct

### Database not created
- Deactivate and reactivate the plugin
- Check file permissions
- Verify database user has CREATE TABLE permissions

## Support

For issues or questions, check the WordPress debug log:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `/wp-content/debug.log`

## Version

**1.0.0** - Initial MVP release
