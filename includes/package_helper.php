<?php
// includes/package_helper.php

if (!function_exists('getPackageById')) {
    /**
     * Retrieve package details dynamically from the database.
     *
     * @param string|int $package_id Package letter ('A', 'B', 'C', 'D') or Booking ID
     * @param mysqli $conn Database connection
     * @return array Standardized package information
     */
    function getPackageById($package_id, $conn) {
        $package_letter = 'A';
        $pax = 100;
        $event_type = 'Wedding';
        $booking_id = 0;
        
        // If numeric, attempt to load booking context
        if (is_numeric($package_id)) {
            $booking_id = intval($package_id);
            $stmt = $conn->prepare("SELECT event_type, pax_count FROM bookings WHERE booking_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $booking_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if ($res) {
                    $pax = intval($res['pax_count']) > 0 ? intval($res['pax_count']) : 100;
                    $event_type = $res['event_type'] ?? 'Wedding';
                    
                    // Extract Package letter from event_type e.g. "Birthday (Package B)"
                    if (preg_match('/Package\s+([A-D])/i', $event_type, $matches)) {
                        $package_letter = strtoupper($matches[1]);
                    }
                }
            }
        } else if (is_string($package_id) && strlen(trim($package_id)) === 1) {
            $package_letter = strtoupper(trim($package_id));
        }
        
        // Query packages table
        $stmt_pkg = $conn->prepare("SELECT * FROM packages WHERE package_id = ?");
        $pkg_data = null;
        if ($stmt_pkg) {
            $stmt_pkg->bind_param("s", $package_letter);
            $stmt_pkg->execute();
            $pkg_data = $stmt_pkg->get_result()->fetch_assoc();
        }
        
        // Fetch selected menu items if booking_id is provided
        $menu_items = [];
        if ($booking_id > 0) {
            $stmt_menu = $conn->prepare("SELECT mi.item_name, mc.category_name 
                                         FROM booking_items bi 
                                         JOIN menu_items mi ON bi.item_id = mi.item_id 
                                         LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
                                         WHERE bi.booking_id = ?");
            if ($stmt_menu) {
                $stmt_menu->bind_param("i", $booking_id);
                $stmt_menu->execute();
                $menu_res = $stmt_menu->get_result();
                while ($row = $menu_res->fetch_assoc()) {
                    $menu_items[] = $row['item_name'];
                }
            }
        }
        
        $package_name = $pkg_data['package_name'] ?? ("Package " . $package_letter);
        $menu_str = !empty($menu_items) ? implode(', ', $menu_items) : ($pkg_data['description'] ?? 'Full Course Catering Buffet');
        
        // Theme selection based on event type
        $theme = 'Elegant White and Gold';
        if (stripos($event_type, 'Birthday') !== false) {
            $theme = 'Vibrant Celebration with Warm Ambient Glow';
        } else if (stripos($event_type, 'Corporate') !== false) {
            $theme = 'Modern Executive & Sophisticated Neutral';
        } else if (stripos($event_type, 'Wedding') !== false) {
            $theme = 'Romantic Luxe White & Warm Gold Accents';
        }
        
        return [
            'package_id'    => $package_letter,
            'package_name'  => $package_name,
            'pax'           => $pax,
            'theme'         => $theme,
            'table_type'    => 'Round Guest Tables with Premium Linens & Centerpieces',
            'menu'          => $menu_str,
            'buffet'        => 'Central Buffet Setup with Elegant Chafing Dishes & Floral Accent',
            'decor'         => 'Matching Linen Skirting, Floral Table Centerpieces, & Stage Backdrop',
            'raw_package'   => $pkg_data
        ];
    }
}
?>
