<?php
/**
 * Database operations
 */

if (!defined('ABSPATH')) exit;

class BC_Database {

    /**
     * Plugin activation - create tables
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Time slots table
        $table = $wpdb->prefix . 'booking_calendar_slots';
        $sql = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_date date NOT NULL,
            slot_hour tinyint NOT NULL,
            status varchar(20) DEFAULT 'busy',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_hour (slot_date, slot_hour),
            KEY slot_date (slot_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Initialize all slots as busy for next 4 weeks
        $this->initialize_default_slots();
    }

    /**
     * Initialize all time slots as busy for next 4 weeks
     */
    private function initialize_default_slots() {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Check if already initialized
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) return;

        // Create slots for next 4 weeks (today + 28 days)
        $start_date = new DateTime('today');
        $end_date = new DateTime('+4 weeks');

        $slots = array();
        $current_date = clone $start_date;

        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');

            // Create slots for each hour (8-20)
            for ($hour = 8; $hour <= 20; $hour++) {
                $slots[] = $wpdb->prepare(
                    "(%s, %d, 'busy')",
                    $date_str,
                    $hour
                );
            }

            $current_date->modify('+1 day');
        }

        // Batch insert
        if (!empty($slots)) {
            $values = implode(',', $slots);
            $wpdb->query("INSERT INTO {$table} (slot_date, slot_hour, status) VALUES {$values}");
        }
    }

    /**
     * Get slots for date range
     */
    public function get_slots($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT slot_date, slot_hour, status
             FROM {$table}
             WHERE slot_date >= %s AND slot_date <= %s
             ORDER BY slot_date ASC, slot_hour ASC",
            $start_date,
            $end_date
        ), ARRAY_A);
    }

    /**
     * Toggle slot status
     */
    public function toggle_slot($date, $hour) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        // Get current status
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table} WHERE slot_date = %s AND slot_hour = %d",
            $date,
            $hour
        ));

        $new_status = ($current === 'free') ? 'busy' : 'free';

        // Update or insert
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE slot_date = %s AND slot_hour = %d",
            $date,
            $hour
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                array('status' => $new_status),
                array('slot_date' => $date, 'slot_hour' => $hour)
            );
        } else {
            $wpdb->insert($table, array(
                'slot_date' => $date,
                'slot_hour' => $hour,
                'status' => $new_status
            ));
        }

        return $new_status;
    }

    /**
     * Get slot status (default to busy if not exists)
     */
    public function get_slot_status($date, $hour) {
        global $wpdb;
        $table = $wpdb->prefix . 'booking_calendar_slots';

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table} WHERE slot_date = %s AND slot_hour = %d",
            $date,
            $hour
        ));

        return $status ? $status : 'busy';
    }
}
