-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2026 at 09:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jemmerific_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_event_plans`
--

CREATE TABLE `ai_event_plans` (
  `plan_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `suggested_layout_desc` text DEFAULT NULL,
  `suggested_menu_summary` text DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_event_plans`
--

INSERT INTO `ai_event_plans` (`plan_id`, `booking_id`, `suggested_layout_desc`, `suggested_menu_summary`, `estimated_cost`, `generated_at`) VALUES
(1, NULL, '1. Buffet setup along one side of the court to maintain open space\n2. Stage positioned at the far end of the court for optimal viewing\n3. Guest tables arranged in a linear, alternating pattern to facilitate conversation and movement', NULL, 37000.00, '2026-07-25 07:17:09'),
(2, NULL, '1. Buffet setup along one side of the court\n2. Stage centered at the far end of the court\n3. Guest tables arranged in a systematic, grid-like pattern to maximize space and facilitate easy movement', NULL, 60000.00, '2026-07-26 19:36:11'),
(3, NULL, '1. Buffet setup along the perimeter to keep the central area clear and focused on the mountain view.\n2. A small, elegantly designed stage centered to draw attention without obstructing the view.\n3. Guest tables arranged systematically to allow for comfortable seating and unobstructed views of the surrounding landscape.', NULL, 50000.00, '2026-07-26 23:21:43'),
(4, NULL, '1. Buffet setup along the perimeter of the venue to maximize space and reduce congestion.\n2. A centered stage for presentations or entertainment, ensuring all guests have a clear view.\n3. Guest tables arranged systematically to facilitate easy movement and conversation.', NULL, 35000.00, '2026-07-26 23:32:14'),
(5, NULL, '1. Buffet setup along one side of the court\n2. Stage centered at the far end of the court\n3. Guest tables arranged in a systematic pattern, leaving ample space for guest flow and catering staff', NULL, 0.00, '2026-08-11 04:43:17'),
(6, NULL, '1. Buffet setup along perimeter, with a red carpet leading to the buffet area.\n2. Stage centered, with a simple yet elegant backdrop.\n3. Guest tables arranged systematically, with ample space for guest flow.', NULL, 70000.00, '2026-08-11 04:44:44'),
(7, NULL, '1. Buffet setup along the perimeter of the venue to take advantage of the mountain view\n2. Stage centered for optimal viewing and minimal obstruction\n3. Guest tables arranged systematically to ensure smooth flow and conversation', NULL, 65000.00, '2026-08-14 08:12:17'),
(8, NULL, '1. Entrance & Registration Table: Positioned 3m from main doorway with welcome signage.\n2. Buffet Lineup: Dual-sided 16ft buffet table along left perimeter with heating chafing dishes.\n3. Guest Seating: 10 round tables evenly spaced with 1.5m clearance aisle.\n4. Stage & Backdrop: Centered at front wall with ambient spotlighting and sound system setup.\n5. Beverage & Dessert Bar: Corner station near entrance for continuous refreshment access.', NULL, 45000.00, '2026-10-22 11:42:50');

-- --------------------------------------------------------

--
-- Table structure for table `ai_user_limits`
--

CREATE TABLE `ai_user_limits` (
  `user_id` int(11) NOT NULL,
  `message_count` int(11) DEFAULT 0,
  `last_reset` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_user_limits`
--

INSERT INTO `ai_user_limits` (`user_id`, `message_count`, `last_reset`) VALUES
(1, 4, '2026-10-22 11:42:50');

-- --------------------------------------------------------

--
-- Table structure for table `availability_schedule`
--

CREATE TABLE `availability_schedule` (
  `id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `status` enum('Available','Limited Slots','Fully Booked','Closed','Holiday') DEFAULT 'Available',
  `max_events` int(11) DEFAULT 3,
  `current_events` int(11) DEFAULT 0,
  `remaining_slots` int(11) DEFAULT 3,
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `availability_schedule`
--

INSERT INTO `availability_schedule` (`id`, `schedule_date`, `status`, `max_events`, `current_events`, `remaining_slots`, `reason`, `notes`, `created_at`, `updated_at`) VALUES
(1, '2026-08-07', 'Available', 3, 0, 3, 'Regular Operations', '', '2026-08-03 13:24:08', '2026-08-03 13:24:08'),
(2, '2026-08-14', 'Available', 3, 1, 2, NULL, NULL, '2026-08-03 13:51:56', '2026-08-03 13:51:56'),
(5, '2026-08-21', 'Fully Booked', 3, 3, 0, NULL, NULL, '2026-08-10 11:31:15', '2026-08-11 05:13:08'),
(11, '2026-10-14', 'Fully Booked', 3, 0, 3, 'Regular Operations', '', '2026-10-14 13:07:36', '2026-10-14 13:07:36'),
(12, '0000-00-00', 'Available', 3, 1, 2, NULL, NULL, '2026-10-14 13:08:15', '2026-10-14 13:08:15'),
(13, '2026-10-15', 'Holiday', 3, 1, 2, 'Weekend Closure', 'IWAS MAGING TAMAD', '2026-10-14 13:18:44', '2026-10-18 10:21:35'),
(16, '2026-10-21', 'Holiday', 3, 0, 3, 'Weekend Closure', 'sarado tayo ah', '2026-10-18 10:23:15', '2026-10-18 10:23:15');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_name` varchar(150) NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `event_end_time` time DEFAULT NULL,
  `venue_location` varchar(255) DEFAULT NULL,
  `venue_address` text NOT NULL,
  `pax_count` int(11) NOT NULL,
  `table_count` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `venue_image` varchar(255) DEFAULT NULL,
  `ai_venue_analysis` text DEFAULT NULL,
  `ai_conceptual_image` varchar(255) DEFAULT NULL,
  `layout_approval_status` varchar(40) NOT NULL DEFAULT 'none',
  `layout_generated_by` varchar(100) DEFAULT NULL,
  `layout_generated_at` datetime DEFAULT NULL,
  `layout_approved_by` varchar(100) DEFAULT NULL,
  `layout_approved_at` datetime DEFAULT NULL,
  `layout_admin_notes` text DEFAULT NULL,
  `status` enum('pending','on_progress','confirmed','completed','cancelled') DEFAULT 'pending',
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('unpaid','partially_paid','fully_paid','refunded') DEFAULT 'unpaid',
  `reference_no` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `booking_status` enum('draft','confirmed','completed','cancelled') DEFAULT 'draft',
  `payment_proof` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `admin_archived_chat` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `event_name`, `event_type`, `event_date`, `event_time`, `event_end_time`, `venue_location`, `venue_address`, `pax_count`, `table_count`, `special_requests`, `venue_image`, `ai_venue_analysis`, `ai_conceptual_image`, `layout_approval_status`, `layout_generated_by`, `layout_generated_at`, `layout_approved_by`, `layout_approved_at`, `layout_admin_notes`, `status`, `total_price`, `payment_method`, `created_at`, `payment_status`, `reference_no`, `total_amount`, `booking_status`, `payment_proof`, `admin_note`, `cancel_reason`, `refund_amount`, `admin_archived_chat`) VALUES
(11, 1, 'hhahahahhaaah', 'Wedding', '2026-04-30', '10:32:00', NULL, 'awa', '', 8, 1, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-01 02:26:52', 'fully_paid', NULL, 300.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(14, 11, 'Debut', 'Birthday', '2026-04-05', '17:00:00', NULL, 'Area 1 Covered Court Paranaque', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-01 05:23:09', 'fully_paid', NULL, 420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(15, 11, 'Mama 50th Birthday Celebration', 'Birthday', '2026-04-11', '15:20:00', NULL, 'Complex Valley 1 near City Hall of Paranaque', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-01 05:57:30', 'fully_paid', NULL, 520.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(20, 1, 'test', 'Others (Package A)', '2026-04-09', '16:18:00', NULL, 'yeye', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-06 04:15:58', 'fully_paid', NULL, 45000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(24, 11, 'test777', 'Wedding (Package A)', '2026-04-08', '12:30:00', NULL, 'test77', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-07 01:30:01', 'fully_paid', NULL, 65000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(25, 1, 'test12345', 'Others (Package A)', '2026-04-09', '09:57:00', NULL, 'waaw', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'cancelled', NULL, 'Cash', '2026-04-07 01:56:01', 'fully_paid', NULL, 45000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(26, 11, 'test999', 'Birthday (Package A)', '2026-04-10', '10:49:00', NULL, 'test999', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-07 02:45:59', 'fully_paid', NULL, 45420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(28, 11, 'kaka', 'Birthday (Package A)', '2026-04-10', '22:00:00', NULL, 'test', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-07 02:59:01', 'fully_paid', NULL, 45000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(31, 10, 'test', 'Others (Package A)', '2026-04-09', '07:28:00', NULL, 'haaha', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-07 23:28:10', 'fully_paid', NULL, 45420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(35, 10, 'test for wawa', 'Wedding (Package A)', '2026-04-08', '16:58:00', NULL, 'yeyeye', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-08 00:58:54', 'fully_paid', NULL, 45420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(36, 1, 'MAMO BIRTHDAY', 'Birthday (Package A)', '2026-04-09', '14:58:00', NULL, 'HAHAHAHAHA', '', 50, 5, NULL, 'uploads/venue_36_1792307644.jpg', '{\"venue_type\":\"Outdoor Open-Air Concrete Driveway and Carport Area with Modern Residential Facade\",\"indoor\":false,\"estimated_capacity\":\"30 - 50 Pax\",\"wall_characteristics\":\"Modern two-story residential building facade with white and gray exterior walls, dark window frames, and black metal gates\",\"ceiling_type\":\"Open Sky with Carport Overhang\",\"floor_type\":\"Smooth concrete driveway and ground surface\",\"lighting\":\"Natural daylight and open sky illumination\",\"architectural_features\":\"Modern house facade, covered carport with flat roof, concrete driveway, and black metal perimeter gates\",\"spatial_layout\":\"Open driveway and carport area suitable for setting up round banquet tables and a compact perimeter catering buffet line\",\"accurate_staging_prompt\":\"Keep the current image intact, preserving the exact driveway layout background, architecture, and concrete floor. Stage round 10-seater banquet tables draped in crisp white linens, matching white chairs, elegant floral centerpieces, and a compact perimeter buffet line with mirror-polished silver chafing dishes under the carport.\",\"admin_prompt\":\"Keep the current image intact, simple setup on my garage with long table and 10 chairs\",\"ai_summary\":{\"package_name\":\"Package A\",\"event_name\":\"MAMO BIRTHDAY\",\"event_type\":\"Birthday (Package A)\",\"pax_count\":50,\"total_amount\":45420,\"formatted_amount\":\"\\u20b145,420.00\",\"theme\":\"Vibrant Celebration with Warm Ambient Glow\",\"venue_location\":\"HAHAHAHAHA\",\"suggested_tables\":{\"guest_tables\":\"5 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\",\"guest_table_count\":5,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"1 Centralized Dual-Sided Buffet Station with Roll-Top Stainless Chafing Dishes\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Veggies\":[\"Chopsuey\"]},\"menu_list\":[\"Chopsuey\"],\"staging_notes\":\"Custom Admin Staging Concept: Keep the current image intact, simple setup on my garage with long table and 10 chairs\",\"admin_prompt_used\":\"Keep the current image intact, simple setup on my garage with long table and 10 chairs\",\"executive_narrative\":\"Here is your professional catering summary, tailored to your specifications:\\n\\n\\u2022 **Package & Guest Capacity:** Mamo\'s Birthday is officially secured under Birthday Package A, accommodating exactly 50 guests for a total investment of \\u20b145,420.00.\\n\\n\\u2022 **Menu Highlights & Table Staging:** The setup features 5 round tables (configured with 10 chairs each, maintaining the requested simple garage layout) alongside 1 head table, complemented by a centralized dual-sided buffet station equipped with roll-top stainless chafing dishes serving our signature, farm-fresh Chopsuey.\\n\\n\\u2022 **Venue Concept & Ambience:** The open-air concrete driveway and carport area are seamlessly integrated with the modern residential facade, creating a clean, welcoming, and accessible al fresco dining experience.\"}}', 'uploads/ai_conceptual/ai_concept_36_1792307976.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, 'Cash', '2026-04-08 01:58:48', 'unpaid', NULL, 45420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(37, 10, 'mamo', 'Corporate (Package A)', '2026-04-10', '14:20:00', NULL, 'haahah', '', 50, 5, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-08 02:20:41', 'fully_paid', NULL, 45420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(38, 13, 'Joshua 21st Debut Birthday', 'Birthday (Package B)', '2026-05-02', '16:00:00', NULL, 'Area 1 Covered Court Paranaque', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-08 05:13:22', 'fully_paid', NULL, 87420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(39, 14, 'Melvin 18th Birthday Celebration', 'Birthday (Package A)', '2026-06-06', '16:10:00', NULL, 'Bf Homes Covered Court', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-08 06:10:07', 'fully_paid', NULL, 65420.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(41, 13, 'Mama Veron 60th Birthday Celeb', 'Birthday (Package B)', '2026-05-03', '16:00:00', NULL, 'Sunrise Area 4', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-04-09 03:21:28', 'fully_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(43, 13, 'Papa 80th Corporate Celeb', 'Corporate (Package A)', '2026-04-12', '16:00:00', NULL, 'Talon 3 Las Pinas', '', 100, 10, NULL, 'uploads/venue_43_1792678091.jpg', '{\"venue_type\":\"Indoor Multi-Purpose Gymnasium\",\"indoor\":true,\"estimated_capacity\":\"200 - 300 Pax\",\"wall_characteristics\":\"Light gray painted concrete walls with large floor-to-ceiling glass windows along the right perimeter, and an interior mezzanine balcony with glass railings overlooking the main floor.\",\"ceiling_type\":\"Modern suspended metal mesh ceiling panels integrated with recessed circular LED lighting fixtures and black structural beams.\",\"floor_type\":\"Varnished indoor hardwood sports flooring with painted basketball court markings.\",\"lighting\":\"Bright recessed circular LED ceiling lights providing even ambient indoor illumination, supplemented by natural daylight from large side windows.\",\"architectural_features\":\"Basketball hoops, electronic scoreboard mounted on the upper wall, interior second-floor mezzanine gallery with glass railings, and glass-walled rooms along the left perimeter.\",\"spatial_layout\":\"Open floor layout with round banquet tables placed on main floor and perimeter buffet stations\",\"accurate_staging_prompt\":\"Photorealistic 16:9 wide interior view of the modern indoor gymnasium preserving the exact varnished hardwood sports flooring, basketball court markings, high metal mesh ceiling with recessed LED lights, and upper mezzanine balcony. The main floor is professionally staged for a gala event with multiple elegant round 10-seater banquet tables draped in champagne floor-length linens, matching Chiavari chairs, lush tall floral centerpieces, and fine glassware. Along the perimeter glass walls, sophisticated buffet lines are set up featuring mirror-polished silver chafing dishes and decorative food displays.\",\"admin_prompt\":\"Photorealistic 16:9 wide interior view of the modern indoor gymnasium preserving the exact varnished hardwood sports flooring, basketball court markings, high metal mesh ceiling with recessed LED lights, and upper mezzanine balcony. The main floor is professionally staged for a gala event with multiple elegant round 10-seater banquet tables draped in champagne floor-length linens, matching Chiavari chairs, lush tall floral centerpieces, and fine glassware. Along the perimeter glass walls, sophisticated buffet lines are set up featuring mirror-polished silver chafing dishes and decorative food displays.\",\"color_scheme\":\"Champagne Gold & Warm White\",\"enriched_staging_prompt\":\"Wide-angle 16:9 architectural photograph of a luxury Modern Executive & Sophisticated Neutral Corporate (Package A) catering staging inside a Indoor Multi-Purpose Gymnasium. Featuring 10 impeccably draped 10-seater round banquet tables with Champagne Gold & Warm White premium linens, elegant Chiavari chairs, and lush tall floral centerpieces. Dual-sided perimeter buffet line with mirror-polished roll-top silver chafing dishes, warm ambient lighting, crystal stemware, and photorealistic catering presentation.\",\"ai_summary\":{\"package_name\":\"Package A\",\"event_name\":\"Papa 80th Corporate Celeb\",\"event_type\":\"Corporate (Package A)\",\"pax_count\":100,\"total_amount\":65000,\"formatted_amount\":\"\\u20b165,000.00\",\"theme\":\"Modern Executive & Sophisticated Neutral\",\"venue_location\":\"Talon 3 Las Pinas\",\"suggested_tables\":{\"guest_tables\":\"10 Round Guest Tables (10 guests per table with premium Modern Executive & Sophisticated Neutral linens & centerpieces)\",\"guest_table_count\":10,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Dessert\":[\"Mango Tapioca\"],\"Main Course\":[\"Beef Salisbury Steak\"],\"Pasta\":[\"Pancit Malabon\"],\"Veggies\":[\"Chopsuey\"]},\"menu_list\":[\"Mango Tapioca\",\"Beef Salisbury Steak\",\"Pancit Malabon\",\"Chopsuey\"],\"staging_notes\":\"Custom Staging Concept: Photorealistic 16:9 wide interior view of the modern indoor gymnasium preserving the exact varnished hardwood sports flooring, basketball court markings, high metal mesh ceiling with recessed LED lights, and upper mezzanine balcony. The main floor is professionally staged for a gala event with multiple elegant round 10-seater banquet tables draped in champagne floor-length linens, matching Chiavari chairs, lush tall floral centerpieces, and fine glassware. Along the perimeter glass walls, sophisticated buffet lines are set up featuring mirror-polished silver chafing dishes and decorative food displays.\",\"admin_prompt_used\":\"Photorealistic 16:9 wide interior view of the modern indoor gymnasium preserving the exact varnished hardwood sports flooring, basketball court markings, high metal mesh ceiling with recessed LED lights, and upper mezzanine balcony. The main floor is professionally staged for a gala event with multiple elegant round 10-seater banquet tables draped in champagne floor-length linens, matching Chiavari chairs, lush tall floral centerpieces, and fine glassware. Along the perimeter glass walls, sophisticated buffet lines are set up featuring mirror-polished silver chafing dishes and decorative food displays.\",\"executive_narrative\":\"\\u2022 Package & Guest Capacity: Customer availed Package A for 100 guests (\\u20b165,000.00) styled in Modern Executive & Sophisticated Neutral.\\n\\u2022 Table Staging & Menu: Staging includes 10 round 10-seater banquet tables, 1 VIP presidential table, and 2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue). Serving 4 dishes including Mango Tapioca, Beef Salisbury Steak, Pancit Malabon, Chopsuey.\\n\\u2022 Venue Concept: Custom Staging Concept: Photorealistic 16:9 wide interior view of the modern indoor gymnasium preserving the exact varnished hardwood sports flooring, basketball court markings, high metal mesh ceiling with recessed LED lights, and upper mezzanine balcony. The main floor is professionally staged for a gala event with multiple elegant round 10-seater banquet tables draped in champagne floor-length linens, matching Chiavari chairs, lush tall floral centerpieces, and fine glassware. Along the perimeter glass walls, sophisticated buffet lines are set up featuring mirror-polished silver chafing dishes and decorative food displays.\"},\"generation_engine\":\"Pollinations.ai (Flux.1 Schnell \\u2022 16:9)\"}', 'uploads/ai_conceptual/ai_concept_43_1792678136.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, 'Cash', '2026-04-09 03:39:13', 'unpaid', NULL, 65000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(46, 10, 'Adrian College Graduation', 'Others (Package B)', '2026-08-05', '19:00:00', NULL, '#55 Hockey st. Fourth Estate Lopena Compound', '', 100, 10, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-07-26 16:14:17', 'fully_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(47, 15, 'My First Birthday Celeb', 'Birthday (Package A)', '2026-07-30', '18:00:00', NULL, '#55 Hockey st. Fourth Estate Lopena Compound', '', 150, 15, 'no nuts and no salty food', NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-07-26 16:31:57', 'fully_paid', NULL, 85000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(48, 16, 'My 70TH Birthday', 'Birthday (Package B)', '2026-07-29', '19:00:00', NULL, 'Calamba LAGUNA', '', 100, 10, 'no salty food', NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-07-26 22:18:25', 'fully_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(51, 20, 'Binyag ng  Anak ko', 'Others (Package B)', '2026-08-14', '10:00:00', NULL, 'Area 4 Lopena Compound Tennis Street', '', 100, 10, 'test', 'uploads/venue_51_1785838957.jpg', '{\"venue_type\":\"Outdoor Garden Park with Waterfront View\",\"indoor\":false,\"estimated_capacity\":\"80 - 120 Pax\",\"wall_characteristics\":\"Natural open-air environment bounded by lush green foliage, mature deciduous trees, and landscaped garden beds filled with colorful flowering shrubs.\",\"ceiling_type\":\"Open sky with natural daylight filtering through tree branches\",\"floor_type\":\"Combination of manicured green lawn turf and a curving concrete pathway\",\"lighting\":\"Bright natural daylight with soft ambient sunlight filtering through the canopy of trees\",\"architectural_features\":\"Scenic pond with calm reflective water in the background, rolling grassy hills, paved winding pathways, and dense natural tree lines\",\"spatial_layout\":\"Ideal placement for round guest banquet tables is on the wide grassy lawn area adjacent to the concrete pathway, with the buffet station positioned near the firmer pathway ground for easy catering access\",\"existing_tables\":\"No existing furniture; completely open outdoor park landscape\",\"existing_chairs\":\"No existing seating\",\"decor_style\":\"Natural outdoor landscape with vibrant flowerbeds and mature trees\",\"accurate_staging_prompt\":\"A photorealistic architectural and event staging capture of this exact outdoor park venue featuring a serene pond, winding concrete pathways, and lush green lawns surrounded by mature trees and colorful flowerbeds. Neatly arrange multiple elegant round banquet tables draped in white linens with floral centerpieces on the grassy lawn area. Position a long, sophisticated catering buffet station with polished silver chafing dishes along the edge of the concrete pathway. Maintain the exact natural background, trees, pond, and bright daylight without altering the original landscape structure.\"}', 'uploads/ai_conceptual/ai_concept_51_1791989331.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-08-03 13:48:00', 'partially_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(52, 20, 'Jona Making Best Bday', 'Birthday (Package B)', '2026-08-21', '20:00:00', NULL, 'Honteveros Compound', '', 150, 15, 'No salty food please balance lang', 'uploads/venue_52_1787791047.jpg', '{\"venue_type\":\"Indoor Event Hall (Honteveros Compound)\",\"estimated_capacity\":\"100 - 150 Pax\",\"existing_tables\":\"Perimeter banquet tables\",\"existing_chairs\":\"Standard event seating\",\"has_stage\":true,\"indoor\":true,\"lighting\":\"Warm ceiling fixtures and ambient lighting\",\"decor_style\":\"Elegant neutral aesthetic\",\"available_space\":\"Open central space for guest tables & buffet\",\"confidence\":\"High (Analyzed)\"}', 'uploads/ai_conceptual/ai_concept_52_1787791057.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-08-10 10:29:00', 'partially_paid', NULL, 118000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(53, 21, 'Zian More Memorable Bday', 'Birthday (Package A)', '2026-08-21', '20:27:00', NULL, 'test test', '', 100, 10, 'test', NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-08-10 11:27:24', 'fully_paid', NULL, 65000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(54, 20, 'Jona Happiest bday', 'Birthday (Package B)', '2026-08-21', '18:39:00', NULL, 'test test test', '', 100, 10, 'test test', NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-08-11 04:38:52', 'fully_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(59, 20, '', 'Others', '0000-00-00', '00:00:00', NULL, 'Parañaque City, National Capital Region (NCR - Metro Manila)', 'Parañaque City, National Capital Region (NCR - Metro Manila)', 0, 0, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'Cash', '2026-10-14 12:54:31', 'fully_paid', NULL, 0.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(60, 22, 'My 21st Birthday Party', 'Birthday (Package A)', '2026-10-15', '10:00:00', '16:30:00', 'Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Parañaque City, National Capital Region (NCR - Metro Manila)', 'Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Parañaque City, National Capital Region (NCR - Metro Manila)', 50, 5, NULL, 'uploads/venue_60_1791986676.jpg', '{\"venue_type\":\"Modern Indoor Sports Hall and Multipurpose Arena\",\"indoor\":true,\"estimated_capacity\":\"250 - 350 Pax\",\"wall_characteristics\":\"Off-white and light gray painted walls with extensive floor-to-ceiling glass windows on the right side and rear mezzanine level, featuring a scoreboard and a second-story balcony with metal railings.\",\"ceiling_type\":\"High sloped architectural ceiling composed of white perforated metal panels arranged in angled sections with embedded circular recessed LED lighting fixtures and thin black structural support beams.\",\"floor_type\":\"Polished high-gloss light brown hardwood sports flooring with visible painted game lines (green and white key area).\",\"lighting\":\"Bright overhead circular recessed LED ceiling lights combined with abundant natural daylight streaming in from large perimeter windows.\",\"architectural_features\":\"Mezzanine second-floor balcony overlooking the main floor, retractable basketball hoops, digital scoreboard, glass partitions, and large open floor plate.\",\"spatial_layout\":\"Central open hardwood floor area ideal for placing round guest banquet tables, with the perimeter side zones suited for an elegant buffet station setup.\",\"existing_tables\":\"None visible on the main floor, only open hardwood space.\",\"existing_chairs\":\"Rows of blue plastic waiting chairs positioned along the side glass partitions.\",\"decor_style\":\"Contemporary, athletic, clean industrial-modern aesthetic with glass and steel elements.\",\"accurate_staging_prompt\":\"A hyper-detailed, photorealistic architectural photograph of a modern indoor sports hall and multipurpose arena featuring a high sloped ceiling with white perforated metal panels and recessed circular LED lights. The space has polished light brown hardwood flooring with game lines, off-white walls, and extensive floor-to-ceiling windows along the right side providing bright natural daylight. The venue includes a second-story mezzanine balcony with glass railings and a digital scoreboard mounted on the wall. Neatly arranged across the polished wooden floor are elegant round banquet guest tables covered in crisp white linens, surrounded by Chiavari chairs and adorned with tall floral centerpieces. Along the perimeter wall, a sophisticated catering buffet station is set up with polished stainless steel chafing dishes, glassware, and floral runners. The shot captures the entire architectural volume of the hall with crisp focus and realistic reflections on the glossy floor.\",\"admin_prompt\":\"Festive celebratory birthday catering setup inside Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila), colorful modern table runners on round guest tables, cheerful floral balloon accents, dessert table station, clean dual-sided buffet queue, bright celebratory ambient illumination matching venue architecture.\",\"ai_summary\":{\"package_name\":\"Package A\",\"event_name\":\"My 21st Birthday Party\",\"event_type\":\"Birthday (Package A)\",\"pax_count\":50,\"total_amount\":45000,\"formatted_amount\":\"\\u20b145,000.00\",\"theme\":\"Vibrant Celebration with Warm Ambient Glow\",\"venue_location\":\"Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila)\",\"suggested_tables\":{\"guest_tables\":\"5 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\",\"guest_table_count\":5,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"1 Centralized Dual-Sided Buffet Station with Roll-Top Stainless Chafing Dishes\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Dessert\":[\"Buko Fruit Salad\"],\"Main Course\":[\"Beef Salisbury Steak\"],\"Pasta\":[\"Biang Biang Noodles\"],\"Veggies\":[\"Chopsuey\"]},\"menu_list\":[\"Buko Fruit Salad\",\"Beef Salisbury Steak\",\"Biang Biang Noodles\",\"Chopsuey\"],\"staging_notes\":\"Custom Admin Staging Concept: Festive celebratory birthday catering setup inside Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila), colorful modern table runners on round guest tables, cheerful floral balloon accents, dessert table station, clean dual-sided buffet queue, bright celebratory ambient illumination matching venue architecture.\",\"admin_prompt_used\":\"Festive celebratory birthday catering setup inside Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila), colorful modern table runners on round guest tables, cheerful floral balloon accents, dessert table station, clean dual-sided buffet queue, bright celebratory ambient illumination matching venue architecture.\",\"executive_narrative\":\"\\u2022 Package & Guest Capacity: Customer availed Package A for 50 guests (\\u20b145,000.00) styled in Vibrant Celebration with Warm Ambient Glow.\\n\\u2022 Table Staging & Menu: Staging includes 5 round 10-seater banquet tables, 1 VIP presidential table, and 1 Centralized Dual-Sided Buffet Station with Roll-Top Stainless Chafing Dishes. Serving 4 dishes including Buko Fruit Salad, Beef Salisbury Steak, Biang Biang Noodles, Chopsuey.\\n\\u2022 Venue Concept: Custom Admin Staging Concept: Festive celebratory birthday catering setup inside Area 1  Covered Court Fourth Estate, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila), colorful modern table runners on round guest tables, cheerful floral balloon accents, dessert table station, clean dual-sided buffet queue, bright celebratory ambient illumination matching venue architecture.\"}}', 'uploads/ai_conceptual/ai_concept_60_1792248453.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'completed', NULL, 'GCash', '2026-10-14 13:10:51', 'fully_paid', NULL, 45000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(61, 11, 'My Greatest 19 birthday Ever', 'Birthday (Package B)', '2026-10-23', '16:00:00', '19:00:00', 'Banner Reyes Covered Court, Brgy. San Antonio, Parañaque City, National Capital Region (NCR - Metro Manila), Brgy. San Antonio, Parañaque City', 'Parañaque City, National Capital Region (NCR - Metro Manila)', 100, 10, '• Chicken Fajita Pasta: 🧂 Low Sodium (Less Salty)\n• Pancit Malabon: 🥣 Sauce Separated', 'uploads/venue_61_1792307035.jpg', '{\"venue_type\":\"Luxury Covered Grand Pavilion\",\"indoor\":false,\"estimated_capacity\":\"200 - 300 Pax\",\"wall_characteristics\":\"Open-air historic village perimeter featuring rustic wooden and colonial-style buildings\",\"ceiling_type\":\"Open Sky with bright natural daylight and scattered clouds\",\"floor_type\":\"Natural grass lawn and open field terrain\",\"lighting\":\"Bright natural daylight and ambient sun\",\"architectural_features\":\"Historic wooden houses, colonial administrative buildings, and pitched roofs lining the background\",\"spatial_layout\":\"Vast open grassy lawn layout suitable for arranging round banquet tables and perimeter catering buffet stations across the field\",\"accurate_staging_prompt\":\"Keep the current image intact, preserving the exact historic village background, architecture, and natural grass lawn floor. Stage round 10-seater banquet tables draped in crisp white linens, gold Chiavari chairs, elegant tall floral centerpieces, and perimeter buffet lines with mirror-polished silver chafing dishes.\",\"admin_prompt\":\"Keep the current image intact, preserving the exact historic village background, architecture, and natural grass lawn floor. Stage round 10-seater banquet tables draped in crisp white linens, gold Chiavari chairs, elegant tall floral centerpieces, and perimeter buffet lines with mirror-polished silver chafing dishes.\",\"ai_summary\":{\"package_name\":\"Package B\",\"event_name\":\"My Greatest 19 birthday Ever\",\"event_type\":\"Birthday (Package B)\",\"pax_count\":100,\"total_amount\":87000,\"formatted_amount\":\"\\u20b187,000.00\",\"theme\":\"Vibrant Celebration with Warm Ambient Glow\",\"venue_location\":\"Banner Reyes Covered Court, Brgy. San Antonio, Para\\u00f1aque City, National Capital Region (NCR - Metro Manila), Brgy. San Antonio, Para\\u00f1aque City\",\"suggested_tables\":{\"guest_tables\":\"10 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\",\"guest_table_count\":10,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Dessert\":[\"Buko Fruit Salad\"],\"Main Course\":[\"Beef Salisbury Steak\",\"Deconstructed kare kare\"],\"Pasta\":[\"Chicken Fajita Pasta\",\"Pancit Malabon\"],\"Veggies\":[\"Chopsuey\"]},\"menu_list\":[\"Buko Fruit Salad\",\"Beef Salisbury Steak\",\"Deconstructed kare kare\",\"Chicken Fajita Pasta\",\"Pancit Malabon\",\"Chopsuey\"],\"staging_notes\":\"Tailored to Luxury Covered Grand Pavilion: perimeter buffet lines preserve floor area for 10 guest tables, center aisle, and clear stage visibility with Bright natural daylight and ambient sun.\",\"admin_prompt_used\":\"\",\"executive_narrative\":\"\\u2022 Package & Guest Capacity: Executed under JEMmerific Catering\\u2019s \\u20b187,000.00 Birthday Package B, this grand celebration is meticulously planned to seamlessly accommodate 100 distinguished guests at the Banner Reyes Covered Court.\\n\\n\\u2022 Menu Highlights & Table Staging: The dining floor is elegantly arranged with 10 ten-pax round tables and a premier Head Table, complemented by two perimeter-winged twin-line buffet stations ensuring fluid, efficient queueing for a rich menu featuring Beef Salisbury Steak, Deconstructed Kare-Kare, Chicken Fajita Pasta, Pancit Malabon, Chopsuey, and Buko Fruit Salad.\\n\\n\\u2022 Venue Concept & Ambience: The spacious luxury covered grand pavilion is transformed into an upscale celebration space that perfectly balances industrial grandeur with intimate, festive sophistication.\"},\"color_scheme\":\"Emerald Green, Champagne Gold, and Ivory\",\"enriched_staging_prompt\":\"Wide-angle 16:9 architectural photograph of a luxury Emerald & Champagne Gold Elegance Birthday (Package B) catering staging inside a Luxury Covered Grand Pavilion. Featuring 12 impeccably draped 10-seater round banquet tables with Emerald Green, Champagne Gold, and Ivory premium linens, elegant Chiavari chairs, and lush tall floral centerpieces. Dual-sided perimeter buffet line with mirror-polished roll-top silver chafing dishes, warm ambient lighting, crystal stemware, and photorealistic catering presentation.\",\"generation_engine\":\"Google AI Catering Studio (16:9 Visualizer)\"}', 'uploads/ai_conceptual/ai_concept_61_1792669583.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, 'GCash', '2026-10-17 15:31:59', 'partially_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(62, 23, 'Arjay 21st Birthday Celeb', 'Birthday (Package B)', '2026-10-23', '17:30:00', '19:50:00', 'Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Piñas City, National Capital Region (NCR - Metro Manila)', 'Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Piñas City, National Capital Region (NCR - Metro Manila)', 100, 10, '• Chicken Fajita Pasta: no  heavy cream', 'uploads/venue_62_1792676505.jpg', '{\"venue_type\":\"Event Venue (Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Pi\\u00f1as City, National Capital Region (NCR - Metro Manila))\",\"indoor\":true,\"estimated_capacity\":\"100 - 150 Pax\",\"wall_characteristics\":\"Neutral walls with ambient fixtures\",\"ceiling_type\":\"High ceiling with ambient fixtures\",\"floor_type\":\"Polished event flooring\",\"lighting\":\"Warm celebration lighting\",\"architectural_features\":\"Spacious event floor\",\"spatial_layout\":\"Round banquet tables with perimeter buffet stations\",\"accurate_staging_prompt\":\"Keep the current image intact, preserving the exact background, architecture, and flooring. Stage round 10-seater banquet tables draped in champagne linens, gold Chiavari chairs, elegant tall floral centerpieces, and perimeter buffet lines with silver chafing dishes.\",\"admin_prompt\":\"A wide-angle 16:9 panoramic photograph taken with a 35mm lens of a luxurious high-end birthday reception staging inside the event venue with its exact existing white and blue draped tent ceiling architecture, light gray walls, and polished reflective concrete floor completely intact. Seamlessly staged on the venue floor are ten round 10-seater banquet tables and one VIP long presidential table, all dressed in floor-length champagne gold satin linens and warm white lace overlays. The seating features elegant gold Chiavari chairs with plush white cushions. The tables are meticulously set with fine white porcelain dinnerware, polished silverware, sparkling crystal glassware, neatly folded cloth napkins, and sophisticated champagne gold table runners. Towering cascading floral centerpieces composed of cascading white orchids, cream roses, and lush greenery rise from sleek gold stands on every table. Along the perimeter walls, dual-sided gourmet buffet lines are set up, featuring immaculate white skirting, gourmet culinary platters, and mirror-polished roll-top silver chafing dishes catching the warm ambient glow of professional overhead and uplighting. The entire venue radiates a sophisticated warm celebration atmosphere with soft golden illumination reflecting off the glossy floor.\",\"color_scheme\":\"Champagne Gold & Warm White\",\"enriched_staging_prompt\":\"A wide-angle 16:9 architectural and interior event staging photograph captured with a 35mm lens, showcasing a luxurious high-end birthday reception styled with a luxury wedding theme inside the event venue, keeping the exact existing white and blue draped tent ceiling architecture, light gray walls, and polished reflective concrete floor completely intact. Seamlessly staged across the glossy venue floor are ten round 10-seater banquet tables and one prominent VIP long presidential table, all draped in floor-length champagne gold satin linens with delicate warm white lace overlays and sophisticated champagne gold table runners. The seating features elegant gold Chiavari chairs with plush white cushions, neatly arranged around each table. The place settings are meticulously detailed with fine white porcelain dinnerware, polished silverware, sparkling crystal glassware, and neatly folded cloth napkins. Rising from the center of every table are towering cascading floral centerpieces composed of overflowing white orchids, cream roses, and lush greenery in sleek gold stands. Along the perimeter walls, dual-sided gourmet buffet lines are set up with immaculate white skirting, showcasing gourmet culinary platters and mirror-polished roll-top silver chafing dishes that catch the warm ambient glow of professional overhead lighting and soft golden uplighting. The entire venue radiates a sophisticated, high-end warm celebration atmosphere with soft golden illumination creating mirror-like reflections on the polished concrete floor, captured with professional interior photography lighting, exceptional clarity, and photorealistic detail.\",\"ai_summary\":{\"package_name\":\"Package B\",\"event_name\":\"Arjay 21st Birthday Celeb\",\"event_type\":\"Birthday (Package B)\",\"pax_count\":100,\"total_amount\":87000,\"formatted_amount\":\"\\u20b187,000.00\",\"theme\":\"Vibrant Celebration with Warm Ambient Glow\",\"venue_location\":\"Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Pi\\u00f1as City, National Capital Region (NCR - Metro Manila)\",\"suggested_tables\":{\"guest_tables\":\"10 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\",\"guest_table_count\":10,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Dessert\":[\"Mango Tapioca\"],\"Main Course\":[\"Beef Salisbury Steak\",\"Pork Adobo\"],\"Pasta\":[\"Chicken Fajita Pasta\",\"Pancit Malabon\"],\"Veggies\":[\"Chopsuey\"]},\"menu_list\":[\"Mango Tapioca\",\"Beef Salisbury Steak\",\"Pork Adobo\",\"Chicken Fajita Pasta\",\"Pancit Malabon\",\"Chopsuey\"],\"staging_notes\":\"Custom Staging Concept: A wide-angle 16:9 panoramic photograph taken with a 35mm lens of a luxurious high-end birthday reception staging inside the event venue with its exact existing white and blue draped tent ceiling architecture, light gray walls, and polished reflective concrete floor completely intact. Seamlessly staged on the venue floor are ten round 10-seater banquet tables and one VIP long presidential table, all dressed in floor-length champagne gold satin linens and warm white lace overlays. The seating features elegant gold Chiavari chairs with plush white cushions. The tables are meticulously set with fine white porcelain dinnerware, polished silverware, sparkling crystal glassware, neatly folded cloth napkins, and sophisticated champagne gold table runners. Towering cascading floral centerpieces composed of cascading white orchids, cream roses, and lush greenery rise from sleek gold stands on every table. Along the perimeter walls, dual-sided gourmet buffet lines are set up, featuring immaculate white skirting, gourmet culinary platters, and mirror-polished roll-top silver chafing dishes catching the warm ambient glow of professional overhead and uplighting. The entire venue radiates a sophisticated warm celebration atmosphere with soft golden illumination reflecting off the glossy floor.\",\"admin_prompt_used\":\"A wide-angle 16:9 panoramic photograph taken with a 35mm lens of a luxurious high-end birthday reception staging inside the event venue with its exact existing white and blue draped tent ceiling architecture, light gray walls, and polished reflective concrete floor completely intact. Seamlessly staged on the venue floor are ten round 10-seater banquet tables and one VIP long presidential table, all dressed in floor-length champagne gold satin linens and warm white lace overlays. The seating features elegant gold Chiavari chairs with plush white cushions. The tables are meticulously set with fine white porcelain dinnerware, polished silverware, sparkling crystal glassware, neatly folded cloth napkins, and sophisticated champagne gold table runners. Towering cascading floral centerpieces composed of cascading white orchids, cream roses, and lush greenery rise from sleek gold stands on every table. Along the perimeter walls, dual-sided gourmet buffet lines are set up, featuring immaculate white skirting, gourmet culinary platters, and mirror-polished roll-top silver chafing dishes catching the warm ambient glow of professional overhead and uplighting. The entire venue radiates a sophisticated warm celebration atmosphere with soft golden illumination reflecting off the glossy floor.\",\"executive_narrative\":\"Here is the professional catering summary for the upcoming celebration:\\n\\n\\u2022 **Package & Guest Capacity:** JEMmerific Catering is delighted to manage Arjay\\u2019s 21st Birthday Celebration under Birthday Package B, accommodating 100 esteemed guests at Lot 4 Romeo Sagana, Las Pi\\u00f1as City.\\n\\n\\u2022 **Menu Highlights & Table Staging:** The dining experience features dual-sided perimeter buffet stations serving an exquisite menu\\u2014including Beef Salisbury Steak, Pork Adobo, Chicken Fajita Pasta, Pancit Malabon, Chopsuey, and Mango Tapioca\\u2014meticulously paired with ten 10-seater round banquet tables, a premier head table, champagne gold satin linens, and elegant gold Chiavari chairs.\\n\\n\\u2022 **Venue Concept & Ambience:** The venue is transformed into a sophisticated, warm celebration space that seamlessly integrates the existing white and blue draped tent architecture with luxurious high-end styling, opulent floral centerpieces, and professional warm ambient lighting.\"},\"generation_engine\":\"Pollinations.ai (Flux.1 Schnell \\u2022 16:9)\",\"layout_approval_status\":\"approved\",\"layout_generated_by\":\"System Administrator (Admin)\",\"layout_generated_at\":\"2026-10-30 09:09:13\",\"layout_approved_by\":\"System Administrator (Admin)\",\"layout_approved_at\":\"2026-10-30 09:09:13\"}', 'uploads/ai_conceptual/ai_concept_62_1793347753.jpg', 'approved', 'System Administrator (Admin)', '2026-10-30 09:09:13', 'System Administrator (Admin)', '2026-10-30 09:09:13', NULL, 'pending', NULL, 'GCash', '2026-10-18 09:57:45', 'partially_paid', NULL, 87000.00, 'draft', NULL, NULL, NULL, 0.00, 0),
(63, 23, '', 'Others', '0000-00-00', '00:00:00', NULL, 'Parañaque City, National Capital Region (NCR - Metro Manila)', 'Parañaque City, National Capital Region (NCR - Metro Manila)', 0, 0, NULL, NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL, NULL, 'cancelled', NULL, 'Cash', '2026-10-18 10:47:05', 'unpaid', NULL, 0.00, 'draft', NULL, NULL, 'Schedule fully booked / conflict on selected date.', 0.00, 0),
(64, 22, '', 'Others', '0000-00-00', '00:00:00', NULL, 'Parañaque City, National Capital Region (NCR - Metro Manila)', 'Parañaque City, National Capital Region (NCR - Metro Manila)', 0, 0, NULL, 'uploads/venue_64_1792919897.jpg', '{\"venue_type\":\"Outdoor Open-Air Baseball Field\",\"indoor\":false,\"estimated_capacity\":\"300 - 500 Pax\",\"wall_characteristics\":\"Open perimeter featuring outfield fences, distant tree lines, stadium light poles, and a clear blue sky background\",\"ceiling_type\":\"Open sky\",\"floor_type\":\"Natural turf grass outfield with a dirt infield and painted blue and yellow warning track surfaces\",\"lighting\":\"Bright natural daylight and stadium floodlight poles\",\"architectural_features\":\"Baseball diamond infield, outfield fence, scoreboard, and stadium lighting towers\",\"spatial_layout\":\"Vast open-air sports field layout, ideal for large festival-style event staging, round banquet tables arranged across the outfield grass, and perimeter catering tents\",\"accurate_staging_prompt\":\"Photorealistic 16:9 view preserving exact outdoor baseball field venue architecture, natural turf grass, and dirt infield. Staging multiple large round 10-seater banquet tables draped in crisp white linens, Chiavari chairs, elegant floral centerpieces, and perimeter professional catering buffet lines with mirror-polished silver chafing dishes under a bright blue daytime sky.\",\"admin_prompt\":\"Photorealistic 16:9 view preserving exact outdoor baseball field venue architecture, natural turf grass, and dirt infield. Staging multiple large round 10-seater banquet tables draped in crisp white linens, Chiavari chairs, elegant floral centerpieces, and perimeter professional catering buffet lines with mirror-polished silver chafing dishes under a bright blue daytime sky.\",\"color_scheme\":\"Vibrant Colorful Modern Accents\",\"enriched_staging_prompt\":\"A wide-angle 16:9 panoramic architectural photograph taken with a 35mm lens of an outdoor open-air baseball field seamlessly staged for an ultra-luxury daytime gala event, preserving the exact venue architecture, natural turf grass, and dirt infield from the background. Ten large round 10-seater banquet tables and one long VIP presidential table are elegantly arranged across the outfield grass, draped in floor-length crisp white linens and paired with sleek gold Chiavari chairs. Each table features elaborate tall cascading floral centerpieces bursting with vibrant colorful modern accents including hot pink peonies, orange ranunculus, and deep purple orchids, complemented by elegant table runners, fine crystal glassware, polished silverware, and pristine white porcelain dinnerware with folded cloth napkins. Along the perimeter of the field, dual-sided professional catering buffet lines gleam under the bright blue daytime sky, featuring mirror-polished silver roll-top chafing dishes showcasing gourmet culinary presentations. The scene is illuminated by natural sunlight, capturing a hyper-realistic, high-end luxury event staging atmosphere with pristine architectural fidelity.\",\"ai_summary\":{\"package_name\":\"Package A\",\"event_name\":\"\",\"event_type\":\"Others\",\"pax_count\":0,\"total_amount\":0,\"formatted_amount\":\"\\u20b10.00\",\"theme\":\"Elegant White and Gold\",\"venue_location\":\"Para\\u00f1aque City, National Capital Region (NCR - Metro Manila)\",\"suggested_tables\":{\"guest_tables\":\"1 Round Guest Tables (10 guests per table with premium Elegant White and Gold linens & centerpieces)\",\"guest_table_count\":1,\"head_table\":\"1 VIP \\/ Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\",\"buffet_station\":\"1 Centralized Dual-Sided Buffet Station with Roll-Top Stainless Chafing Dishes\",\"special_stations\":\"1 Cake \\/ Gift Display Station, 1 Registration \\/ Reception Welcome Desk\"},\"menu_by_category\":{\"Selected Menu\":[\"1 Main Course Like Beef or Pork\",\"1 Veggies s\",\"1 Pasta\",\"1 Dessert\"]},\"menu_list\":[\"1 Main Course Like Beef or Pork\",\"1 Veggies s\",\"1 Pasta\",\"1 Dessert\"],\"staging_notes\":\"Custom Staging Concept: Photorealistic 16:9 view preserving exact outdoor baseball field venue architecture, natural turf grass, and dirt infield. Staging multiple large round 10-seater banquet tables draped in crisp white linens, Chiavari chairs, elegant floral centerpieces, and perimeter professional catering buffet lines with mirror-polished silver chafing dishes under a bright blue daytime sky.\",\"admin_prompt_used\":\"Photorealistic 16:9 view preserving exact outdoor baseball field venue architecture, natural turf grass, and dirt infield. Staging multiple large round 10-seater banquet tables draped in crisp white linens, Chiavari chairs, elegant floral centerpieces, and perimeter professional catering buffet lines with mirror-polished silver chafing dishes under a bright blue daytime sky.\",\"executive_narrative\":\"\\u2022 **Package & Guest Capacity:** JEMmerific Catering is deployed for a customized event layout featuring Package A, expertly tailored to accommodate your designated guest count with precision staging.\\n\\u2022 **Menu Highlights & Table Staging:** The setup features one 10-seater round banquet table with crisp white linens and a head table, complemented by a centralized dual-sided buffet station equipped with roll-top stainless chafing dishes to serve a curated selection of one main course (beef or pork), fresh vegetables, pasta, and dessert. \\n\\u2022 **Venue Concept & Ambience:** The open-air baseball field venue in Para\\u00f1aque City is seamlessly integrated with professional catering infrastructure, harmonizing the natural turf grass and dirt infield under a bright daytime sky.\"},\"generation_engine\":\"Pollinations.ai (Flux.1 Schnell \\u2022 16:9)\"}', 'uploads/ai_conceptual/ai_concept_64_1792919948.jpg', 'none', NULL, NULL, NULL, NULL, NULL, 'cancelled', NULL, 'Cash', '2026-10-22 11:24:01', 'unpaid', NULL, 0.00, 'draft', NULL, NULL, 'Schedule fully booked / conflict on selected date.', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `dietary_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `item_id`, `quantity`, `unit_price`, `subtotal`, `dietary_notes`) VALUES
(10, 11, 1, 3, 100.00, 300.00, NULL),
(13, 14, 1, 1, 100.00, 100.00, NULL),
(14, 14, 3, 1, 170.00, 170.00, NULL),
(15, 14, 2, 1, 150.00, 150.00, NULL),
(16, 15, 1, 2, 100.00, 200.00, NULL),
(17, 15, 3, 1, 170.00, 170.00, NULL),
(18, 15, 2, 1, 150.00, 150.00, NULL),
(35, 20, 4, 1, 0.00, 0.00, NULL),
(36, 20, 3, 1, 0.00, 0.00, NULL),
(134, 24, 4, 1, 0.00, 0.00, NULL),
(135, 24, 8, 1, 0.00, 0.00, NULL),
(136, 24, 3, 1, 0.00, 0.00, NULL),
(137, 24, 2, 1, 0.00, 0.00, NULL),
(153, 25, 4, 1, 0.00, 0.00, NULL),
(154, 25, 8, 1, 0.00, 0.00, NULL),
(155, 25, 3, 1, 0.00, 0.00, NULL),
(156, 25, 9, 1, 0.00, 0.00, NULL),
(157, 25, 2, 1, 0.00, 0.00, NULL),
(178, 26, 4, 1, 100.00, 100.00, NULL),
(179, 26, 8, 1, 0.00, 0.00, NULL),
(180, 26, 3, 1, 170.00, 170.00, NULL),
(181, 26, 9, 1, 0.00, 0.00, NULL),
(182, 26, 2, 1, 150.00, 150.00, NULL),
(226, 31, 4, 1, 100.00, 100.00, NULL),
(227, 31, 8, 1, 0.00, 0.00, NULL),
(228, 31, 3, 1, 170.00, 170.00, NULL),
(229, 31, 2, 1, 150.00, 150.00, NULL),
(265, 35, 4, 1, 100.00, 100.00, NULL),
(266, 35, 8, 1, 0.00, 0.00, NULL),
(267, 35, 3, 1, 170.00, 170.00, NULL),
(268, 35, 2, 1, 150.00, 150.00, NULL),
(278, 36, 4, 1, 100.00, 100.00, NULL),
(279, 36, 8, 1, 0.00, 0.00, NULL),
(280, 36, 3, 1, 170.00, 170.00, NULL),
(281, 36, 2, 1, 150.00, 150.00, NULL),
(289, 37, 4, 1, 100.00, 100.00, NULL),
(290, 37, 8, 1, 0.00, 0.00, NULL),
(291, 37, 3, 1, 170.00, 170.00, NULL),
(292, 37, 2, 1, 150.00, 150.00, NULL),
(308, 38, 4, 1, 100.00, 100.00, NULL),
(309, 38, 8, 1, 0.00, 0.00, NULL),
(310, 38, 7, 1, 0.00, 0.00, NULL),
(311, 38, 3, 1, 170.00, 170.00, NULL),
(312, 38, 2, 1, 150.00, 150.00, NULL),
(323, 39, 4, 1, 100.00, 100.00, NULL),
(324, 39, 8, 1, 0.00, 0.00, NULL),
(325, 39, 3, 1, 170.00, 170.00, NULL),
(326, 39, 2, 1, 150.00, 150.00, NULL),
(357, 41, 12, 1, 0.00, 0.00, NULL),
(358, 41, 16, 1, 0.00, 0.00, NULL),
(359, 41, 15, 1, 0.00, 0.00, NULL),
(360, 41, 8, 1, 0.00, 0.00, NULL),
(361, 41, 7, 1, 0.00, 0.00, NULL),
(732, 43, 12, 1, 0.00, 0.00, NULL),
(733, 43, 8, 1, 0.00, 0.00, NULL),
(734, 43, 14, 1, 0.00, 0.00, NULL),
(735, 43, 19, 1, 0.00, 0.00, NULL),
(740, 46, 12, 1, 0.00, 0.00, NULL),
(741, 46, 16, 1, 0.00, 0.00, NULL),
(742, 46, 15, 1, 0.00, 0.00, NULL),
(743, 46, 7, 1, 0.00, 0.00, NULL),
(744, 46, 11, 1, 0.00, 0.00, NULL),
(745, 47, 12, 1, 0.00, 0.00, NULL),
(746, 47, 15, 1, 0.00, 0.00, NULL),
(747, 47, 8, 1, 0.00, 0.00, NULL),
(748, 47, 19, 1, 0.00, 0.00, NULL),
(749, 48, 12, 1, 0.00, 0.00, NULL),
(750, 48, 16, 1, 0.00, 0.00, NULL),
(751, 48, 8, 1, 0.00, 0.00, NULL),
(752, 48, 7, 1, 0.00, 0.00, NULL),
(753, 48, 14, 1, 0.00, 0.00, NULL),
(764, 51, 12, 1, 0.00, 0.00, NULL),
(765, 51, 15, 1, 0.00, 0.00, NULL),
(766, 51, 19, 1, 0.00, 0.00, NULL),
(767, 51, 11, 1, 0.00, 0.00, NULL),
(768, 51, 17, 1, 0.00, 0.00, NULL),
(773, 52, 12, 1, 0.00, 0.00, NULL),
(774, 52, 16, 1, 0.00, 0.00, NULL),
(775, 52, 20, 1, 0.00, 0.00, NULL),
(776, 52, 8, 1, 0.00, 0.00, NULL),
(777, 52, 18, 1, 0.00, 0.00, NULL),
(778, 53, 12, 1, 0.00, 0.00, NULL),
(779, 53, 16, 1, 0.00, 0.00, NULL),
(780, 53, 15, 1, 0.00, 0.00, NULL),
(781, 53, 8, 1, 0.00, 0.00, NULL),
(782, 54, 12, 1, 0.00, 0.00, NULL),
(783, 54, 16, 1, 0.00, 0.00, NULL),
(784, 54, 15, 1, 0.00, 0.00, NULL),
(785, 54, 8, 1, 0.00, 0.00, NULL),
(786, 54, 10, 1, 0.00, 0.00, NULL),
(799, 60, 12, 1, 0.00, 0.00, ''),
(800, 60, 16, 1, 0.00, 0.00, ''),
(801, 60, 8, 1, 0.00, 0.00, ''),
(802, 60, 15, 1, 0.00, 0.00, ''),
(922, 61, 12, 1, 0.00, 0.00, ''),
(923, 61, 7, 1, 0.00, 0.00, ''),
(924, 61, 21, 1, 0.00, 0.00, '🧂 Low Sodium (Less Salty)'),
(925, 61, 19, 1, 0.00, 0.00, '🥣 Sauce Separated'),
(926, 61, 8, 1, 0.00, 0.00, ''),
(927, 61, 15, 1, 0.00, 0.00, ''),
(951, 62, 12, 1, 0.00, 0.00, ''),
(952, 62, 17, 1, 0.00, 0.00, ''),
(953, 62, 21, 1, 0.00, 0.00, 'no  heavy cream'),
(954, 62, 19, 1, 0.00, 0.00, ''),
(955, 62, 8, 1, 0.00, 0.00, ''),
(956, 62, 14, 1, 0.00, 0.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_daily_usage`
--

CREATE TABLE `chatbot_daily_usage` (
  `id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `message_count` int(11) NOT NULL DEFAULT 0,
  `usage_date` date NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_daily_usage`
--

INSERT INTO `chatbot_daily_usage` (`id`, `identifier`, `message_count`, `usage_date`, `updated_at`) VALUES
(1, 'ip_::1', 1, '2026-10-30', '2026-10-30 08:18:04'),
(2, 'user_20', 1, '2026-10-14', '2026-10-14 13:06:15'),
(10, 'ip_127.0.0.1', 4, '2026-10-23', '2026-10-23 08:16:00');

-- --------------------------------------------------------

--
-- Table structure for table `dietary_restrictions`
--

CREATE TABLE `dietary_restrictions` (
  `restriction_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `restriction_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dietary_restrictions`
--

INSERT INTO `dietary_restrictions` (`restriction_id`, `booking_id`, `guest_name`, `restriction_type`, `notes`) VALUES
(5, 47, NULL, 'no nuts and no salty food', NULL),
(6, 48, NULL, 'no salty food', NULL),
(9, 51, NULL, 'test', NULL),
(10, 52, NULL, 'No salty food please balance lang', NULL),
(11, 53, NULL, 'test', NULL),
(12, 54, NULL, 'test test', NULL),
(13, 61, NULL, '• Chicken Fajita Pasta: 🧂 Low Sodium (Less Salty)\n• Pancit Malabon: 🥣 Sauce Separated', NULL),
(14, 62, NULL, '• Chicken Fajita Pasta: no  heavy cream', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`category_id`, `category_name`) VALUES
(1, 'Main Course'),
(2, 'Pasta'),
(4, 'Veggies'),
(5, 'Dessert');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `recipe` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `recipe`, `image_path`, `image_url`, `price`, `is_available`) VALUES
(7, 1, 'Deconstructed kare kare', 'lame', 'hahahaha', 'uploads/dish_69d32a017c45f.jpg', NULL, 0.00, 1),
(8, 4, 'Chopsuey', 'SOLID TO MAN', 'HAHA', 'uploads/dish_69d340ce1fff6.jpg', NULL, 0.00, 1),
(10, 1, 'Pork Imbutido', 'Pork', 'SOLED', 'uploads/dish_69d49ce94eb7d.jpg', NULL, 0.00, 1),
(11, 4, 'Pinakbet', 'healthy', 'hahahaha', 'uploads/dish_69d5f7e593d24.jpg', NULL, 0.00, 1),
(12, 1, 'Beef Salisbury Steak', 'Beef', 'yeye', 'uploads/dish_69d5f80a2f23d.jpg', NULL, 0.00, 1),
(14, 5, 'Mango Tapioca', 'Solid di ganon ka tamis', 'huhu', 'uploads/dish_69d5f86ced615.jpg', NULL, 0.00, 1),
(15, 5, 'Buko Fruit Salad', 'soled man to', 'hehe', 'uploads/dish_69d5f890193d6.jpg', NULL, 0.00, 1),
(16, 2, 'Biang Biang Noodles', 'lame to par', 'nge', 'uploads/dish_69d5f8f7d3050.jpg', NULL, 0.00, 1),
(17, 1, 'Pork Adobo', 'LAME TO PRAMIS', 'KUKU', 'uploads/dish_69d5f947c59e1.jpg', NULL, 0.00, 1),
(18, 1, 'Pork Hamonado', 'Pork', 'kkk', 'uploads/dish_69d5f97e010c9.jpg', NULL, 0.00, 1),
(19, 2, 'Pancit Malabon', 'WOW sa sarap', 'haha', 'uploads/dish_69d74050c4546.jpg', NULL, 0.00, 1),
(20, 5, 'Brownies', 'solid to promise', 'bleh', 'uploads/dish_6a7057c30f750.jpg', NULL, 0.00, 1),
(21, 2, 'Chicken Fajita Pasta', 'Chicken Fajita Pasta is a fusion comfort-food dish that combines the smoky, spiced flavors of traditional Mexican chicken fajitas with a rich, cheesy pasta', '• 1 lb (450g) boneless, skinless chicken breasts, sliced into thin strips\r\n• 8 oz (225g) dry penne or rotini pasta\r\n• 2 tbsp olive oil, divided\r\n• 2 tbsp fajita seasoning mix, divided\r\n• 1 large onion, sliced\r\n• 2-3 bell peppers (mix of red, yellow, and green), sliced\r\n• 4 cloves garlic, minced\r\n• 2 cups low-sodium chicken broth\r\n• 1 can (10 oz) diced tomatoes with green chilies (like Ro-Tel), undrained\r\n• ½ cup heavy cream\r\n• 1 cup shredded Mexican blend cheese or cheddar (optional)\r\n• For Garnish: Fresh chopped cilantro and lime wedges', 'uploads/dish_6ad38f6dec4df.jpg', NULL, 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `booking_id`, `sender_id`, `receiver_id`, `message`, `attachment`, `file_type`, `is_read`, `created_at`) VALUES
(1, 47, 1, 15, 'boss', NULL, NULL, 1, '2026-07-26 21:26:57'),
(2, 47, 15, 1, 'hello po', NULL, NULL, 1, '2026-07-26 21:27:56'),
(3, 47, 15, 1, '', 'uploads/chat_files/chat_47_1785101301_9535.jpg', 'image', 1, '2026-07-26 21:28:21'),
(4, 48, 16, 1, 'pwede poba ako tumawag for clarification?', NULL, NULL, 1, '2026-07-26 22:21:25'),
(5, 48, 1, 16, 'Greetings! Here is your detailed breakdown and Quotation for the requested event package.', NULL, NULL, 1, '2026-07-26 22:22:31'),
(6, 49, 1, 17, 'hello, thank you for trusting us', NULL, NULL, 1, '2026-07-26 23:15:02'),
(7, 49, 17, 1, 'hehe', NULL, NULL, 1, '2026-07-26 23:16:01'),
(8, 49, 1, 17, 'heeh', NULL, NULL, 0, '2026-07-26 23:20:49'),
(9, 50, 18, 1, 'hello po', NULL, NULL, 1, '2026-07-26 23:29:37'),
(10, 50, 18, 1, 'pwede po ako tumawag?', NULL, NULL, 1, '2026-07-26 23:29:47'),
(11, 50, 18, 1, 'for clarification', NULL, NULL, 1, '2026-07-26 23:29:57'),
(12, 50, 1, 18, 'sure', NULL, NULL, 1, '2026-07-26 23:31:22'),
(19, 50, 18, 1, '', 'uploads/chat_files/chat_50_1785111342_5714.png', 'image', 1, '2026-07-27 00:15:42'),
(25, 50, 1, 18, '✨ *AI CATERING & VENUE RECOMMENDATION PROPOSAL* ✨\n📌 Booking #BK-0050: My 70TH Birthday\n\n🏛️ *Venue Vision Analysis:*\n• Venue Type: Indoor Event Hall (Manila BGC)\n• Estimated Capacity: 100 - 150 Pax\n• Lighting & Ambience: Warm ceiling fixtures and ambient lighting\n• Decor Style: Elegant neutral aesthetic\n\n🍴 *Recommended Catering Package:* Package B\n• Guest Count: 100 Pax\n• Recommended Theme: Romantic Luxe White & Warm Gold Accents\n• Table Layout: Round Guest Tables with Premium Linens & Centerpieces\n• Buffet Setup: Central Buffet Setup with Elegant Chafing Dishes & Floral Accent\n• Selected Menu: Beef Salisbury Steak, Biang Biang Noodles, Chopsuey, Deconstructed kare kare, Mango Tapioca\n\n🎨 *Conceptual Visualization:* Attached below is the AI-generated conceptual layout showing how your catering package will look inside your venue while preserving structural features!', 'uploads/ai_conceptual/ai_concept_50_1785493900.jpg', 'image', 1, '2026-07-31 10:40:44'),
(26, 50, 1, 18, 'what do you thiink sir?', NULL, NULL, 1, '2026-07-31 10:53:10'),
(35, 51, 1, 20, 'hi', NULL, NULL, 1, '2026-08-10 10:17:14'),
(36, 51, 20, 1, 'low', NULL, NULL, 1, '2026-08-10 10:17:42'),
(37, 51, 1, 20, 'haha', NULL, NULL, 1, '2026-08-10 10:27:43'),
(38, 53, 21, 1, 'test', NULL, NULL, 1, '2026-08-10 11:29:34'),
(39, 53, 1, 21, 'hey\'', NULL, NULL, 1, '2026-08-10 11:30:06'),
(40, 53, 1, 21, 'may question po kayo?', NULL, NULL, 1, '2026-08-10 11:54:44'),
(41, 52, 20, 1, 'thanks', NULL, NULL, 1, '2026-08-11 04:38:29'),
(42, 52, 1, 20, 'your welcome', NULL, NULL, 1, '2026-08-11 04:49:38'),
(43, 52, 1, 20, 'm', NULL, NULL, 1, '2026-08-13 04:57:47'),
(44, 52, 1, 20, 'hey', NULL, NULL, 1, '2026-08-14 08:12:41'),
(45, 52, 20, 1, 'h', NULL, NULL, 1, '2026-08-14 11:12:02'),
(46, 52, 1, 20, 'k', NULL, NULL, 1, '2026-08-15 02:12:11'),
(47, 52, 1, 20, '✨ *AI CATERING & VENUE RECOMMENDATION PROPOSAL* ✨\n📌 Booking #BK-0052: Jona Making Best Bday\n\n🏛️ *Venue Vision Analysis:*\n• Venue Type: Indoor Event Hall (Honteveros Compound)\n• Estimated Capacity: 100 - 150 Pax\n• Lighting & Ambience: Warm ceiling fixtures and ambient lighting\n• Decor Style: Elegant neutral aesthetic\n\n🍴 *Recommended Catering Package:* Package B\n• Guest Count: 150 Pax\n• Recommended Theme: Vibrant Celebration with Warm Ambient Glow\n• Table Layout: Round Guest Tables with Premium Linens & Centerpieces\n• Buffet Setup: Central Buffet Setup with Elegant Chafing Dishes & Floral Accent\n• Selected Menu: Beef Salisbury Steak, Biang Biang Noodles, Brownies, Chopsuey, Pork Hamonado\n\n🎨 *Conceptual Visualization:* Attached below is the AI-generated conceptual layout showing how your catering package will look inside your venue while preserving structural features!', 'uploads/ai_conceptual/ai_concept_52_1787791057.jpg', 'image', 1, '2026-08-27 00:37:52'),
(48, 54, 20, 1, 'hi', NULL, NULL, 1, '2026-09-22 10:30:24'),
(49, 59, 1, 20, 'h', NULL, NULL, 1, '2026-10-14 13:07:14'),
(50, 60, 1, 22, 'hey', NULL, NULL, 1, '2026-10-17 14:18:38'),
(51, 61, 11, 1, 'hello po', NULL, NULL, 1, '2026-10-17 16:05:51'),
(52, 61, 11, 1, 'nareceived nyo napo yung dp namin?', NULL, NULL, 1, '2026-10-17 16:06:01'),
(53, 61, 11, 1, '', 'uploads/chat_files/chat_61_1792253172_6837.jpg', 'image', 1, '2026-10-17 16:06:12'),
(54, 62, 1, 23, '✨ *AI CATERING & VENUE RECOMMENDATION PROPOSAL* ✨\n📌 Booking #BK-0062: Arjay 21st Birthday Celeb\n\n🏛️ *Venue & Setup Staging:*\n• Venue: Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Piñas City, National Capital Region (NCR - Metro Manila)\n• Capacity / Pax: 100 Pax\n• Ambience: Bright indoor recessed LED ceiling downlights and natural daylight streaming through large perimeter windows\n\n🍴 *Package Availed:* Package B (₱87,000.00)\n• Theme: Vibrant Celebration with Warm Ambient Glow\n• Guest Seating: 10 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\n• VIP / Head Table: 1 VIP / Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\n• Buffet Station: 2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)\n• Selected Menu Dishes: Mango Tapioca, Beef Salisbury Steak, Pork Adobo, Chicken Fajita Pasta, Pancit Malabon, Chopsuey\n\n🎨 *Conceptual Staging Visualization:* Attached below is your venue\'s photorealistic AI conceptual setup preserving the venue structure!', 'uploads/ai_conceptual/ai_concept_62_1792318213.jpg', 'image', 1, '2026-10-18 10:11:22'),
(55, 62, 1, 23, '✨ *AI CATERING & VENUE RECOMMENDATION PROPOSAL* ✨\n📌 Booking #BK-0062: Arjay 21st Birthday Celeb\n\n🏛️ *Venue & Setup Staging:*\n• Venue: Lot 4 Romeo Sagana, Brgy. Pulang Lupa Uno, Las Piñas City, National Capital Region (NCR - Metro Manila)\n• Capacity / Pax: 100 Pax\n• Ambience: Bright indoor recessed LED ceiling downlights and natural daylight streaming through large perimeter windows\n\n🍴 *Package Availed:* Package B (₱87,000.00)\n• Theme: Vibrant Celebration with Warm Ambient Glow\n• Guest Seating: 10 Round Guest Tables (10 guests per table with premium Vibrant Celebration with Warm Ambient Glow linens & centerpieces)\n• VIP / Head Table: 1 VIP / Presidential Long Table (seats 10-12 guests, elevated or facing main stage)\n• Buffet Station: 2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)\n• Selected Menu Dishes: Mango Tapioca, Beef Salisbury Steak, Pork Adobo, Chicken Fajita Pasta, Pancit Malabon, Chopsuey\n\n🎨 *Conceptual Staging Visualization:* Attached below is your venue\'s photorealistic AI conceptual setup preserving the venue structure!', 'uploads/ai_conceptual/ai_concept_62_1792318213.jpg', 'image', 1, '2026-10-18 10:11:41'),
(56, 60, 22, 1, 'lo', NULL, NULL, 1, '2026-10-22 11:35:34'),
(57, 60, 1, 22, 'h', NULL, NULL, 1, '2026-10-22 16:03:17'),
(58, 11, 1, 1, 'Hello Admin, testing smooth InfinityFree chat at 18:47:09', NULL, NULL, 1, '2026-10-22 16:47:09'),
(59, 11, 1, 1, 'Hello Admin, testing smooth InfinityFree chat at 18:48:13', NULL, NULL, 1, '2026-10-22 16:48:13'),
(60, 60, 1, 22, 'h', NULL, NULL, 1, '2026-10-22 16:57:08'),
(61, 60, 1, 22, 'he', NULL, NULL, 1, '2026-10-23 08:01:12'),
(62, 60, 1, 22, 'dgagg', NULL, NULL, 1, '2026-10-23 08:29:40'),
(63, 60, 24, 22, 'hello po', NULL, NULL, 1, '2026-10-30 07:11:05'),
(64, 60, 22, 1, 'hi', NULL, NULL, 0, '2026-10-30 08:14:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 11, 'Booking Approved! Your payment for #BK-0014 is verified.', 1, '2026-04-01 05:55:10'),
(2, 11, 'Booking Approved! Your payment for #BK-0015 is verified.', 1, '2026-04-01 05:59:30'),
(3, 10, 'Booking Approved! Your payment for #BK-0013 is verified.', 1, '2026-04-01 06:09:30'),
(4, 10, 'Booking Approved! Your payment for #BK-0012 is verified.', 1, '2026-04-01 06:33:52'),
(5, 1, 'Your booking #BK-0007 has been cancelled.', 1, '2026-04-01 06:35:42'),
(6, 1, 'Booking Approved! Your payment for #BK-0020 is verified.', 1, '2026-04-06 04:37:12'),
(16, 11, 'Booking Approved! Your payment for #BK-0023 is verified.', 1, '2026-04-07 01:14:30'),
(17, 11, 'Booking Approved! Your downpayment for #BK-0024 has been verified.', 1, '2026-04-07 01:35:30'),
(18, 11, 'Booking Approved! Your downpayment for #BK-0016 has been verified.', 1, '2026-04-07 01:37:42'),
(19, 11, 'Booking Approved! Your downpayment for #BK-0016 has been verified.', 1, '2026-04-07 01:37:44'),
(20, 11, 'Booking Approved! Your downpayment for #BK-0026 has been verified.', 1, '2026-04-07 02:54:55'),
(21, 11, 'Your booking #BK-0016 has been cancelled.', 1, '2026-04-07 04:45:13'),
(22, 11, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0014', 1, '2026-04-07 04:47:52'),
(23, 11, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0023', 1, '2026-04-07 04:48:05'),
(24, 10, 'Booking Approved! Your downpayment for #BK-0029 has been verified.', 1, '2026-04-07 05:05:17'),
(25, 10, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0012', 1, '2026-04-07 05:20:40'),
(26, 1, 'Payment Verified! Your payment for #BK-0025 is now cleared.', 1, '2026-04-07 05:26:07'),
(27, 1, 'Payment Verified! Your payment for #BK-0011 is now cleared.', 1, '2026-04-07 05:26:33'),
(28, 11, 'Payment Verified! Your payment for #BK-0027 is now cleared.', 1, '2026-04-07 05:26:57'),
(29, 11, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0028', 1, '2026-04-07 05:27:52'),
(30, 1, 'Your booking #BK-0025 has been cancelled.', 1, '2026-04-07 05:40:04'),
(31, 11, 'Payment received in full! Booking #BK-0024 marked complete & fully paid.', 1, '2026-04-07 06:07:01'),
(32, 11, 'Payment received in full! Booking #BK-0026 marked complete & fully paid.', 1, '2026-04-07 06:07:22'),
(34, 1, 'Booking Approved! Your downpayment for #BK-0019 has been verified.', 1, '2026-04-07 06:08:33'),
(35, 11, 'Booking Approved! Your downpayment for #BK-0016 has been verified.', 1, '2026-04-07 06:15:46'),
(36, 10, 'Event On Progress for #BK-0029. We\'re preparing your catering!', 1, '2026-04-07 06:16:41'),
(37, 10, 'Booking Confirmed! Your downpayment for #BK-0035 has been verified.', 1, '2026-04-08 01:01:18'),
(38, 10, 'Booking Confirmed! Your downpayment for #BK-0035 has been verified.', 1, '2026-04-08 01:02:56'),
(39, 10, 'Event On Progress for #BK-0035. We\'re preparing your catering!', 1, '2026-04-08 01:03:43'),
(40, 10, 'Payment received in full! Booking #BK-0035 marked complete & fully paid.', 1, '2026-04-08 01:03:56'),
(42, 10, 'Booking Confirmed! Your downpayment for #BK-0032 has been verified.', 1, '2026-04-08 01:06:45'),
(43, 10, 'Booking Confirmed! Your downpayment for #BK-0032 has been verified.', 1, '2026-04-08 01:07:06'),
(44, 10, 'Event On Progress for #BK-0032. We\'re preparing your catering!', 1, '2026-04-08 01:09:37'),
(45, 10, 'Booking Confirmed! Your downpayment for #BK-0032 has been verified.', 1, '2026-04-08 01:09:55'),
(46, 10, 'Booking Confirmed! Your downpayment for #BK-0032 has been verified.', 1, '2026-04-08 01:14:32'),
(47, 10, 'Booking Confirmed! Your downpayment for #BK-0032 has been verified.', 1, '2026-04-08 01:18:57'),
(48, 10, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0035', 1, '2026-04-08 01:20:56'),
(49, 1, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0020', 1, '2026-04-08 01:21:20'),
(50, 11, 'Event Completed! Thank you for choosing JEMmerific for booking #BK-0015', 1, '2026-04-08 01:22:04'),
(51, 10, 'Your booking #BK-0031 has been cancelled.', 1, '2026-04-08 01:22:34'),
(52, 10, 'Your booking #BK-0031 has been cancelled.', 1, '2026-04-08 01:22:36'),
(53, 10, 'Booking Confirmed! Your downpayment for #BK-0031 has been verified.', 1, '2026-04-08 01:51:40'),
(54, 10, 'Booking Confirmed! Your downpayment for #BK-0037 has been verified.', 1, '2026-04-08 02:21:56'),
(55, 10, 'Payment received in full! Booking #BK-0037 marked complete & fully paid.', 1, '2026-04-08 02:28:53'),
(56, 11, 'Booking #BK-0027 has been cancelled. Reason: no slot, were sorry about inconvenient. we send the refund to your provided gcash number.. Downpayment of ₱10,000.00 refunded.', 1, '2026-04-08 03:06:06'),
(57, 13, 'Booking Confirmed! Your downpayment for #BK-0038 has been verified.', 1, '2026-04-08 05:16:54'),
(58, 13, 'Event On Progress for #BK-0038. We\'re preparing your catering!', 1, '2026-04-08 05:18:25'),
(59, 13, 'Payment received in full! Booking #BK-0038 marked complete & fully paid.', 1, '2026-04-08 06:05:32'),
(60, 14, 'Booking Confirmed! Your downpayment for #BK-0039 has been verified.', 1, '2026-04-08 06:13:04'),
(61, 14, 'Event On Progress for #BK-0039. We\'re preparing your catering!', 1, '2026-04-08 06:14:32'),
(62, 14, 'Payment received in full! Booking #BK-0039 marked complete & fully paid.', 1, '2026-04-08 06:15:25'),
(63, 13, 'Booking Confirmed! Your downpayment for #BK-0041 has been verified.', 1, '2026-04-09 03:24:44'),
(64, 10, 'Booking Confirmed! Your downpayment for #BK-0045 has been verified.', 0, '2026-07-25 06:25:24'),
(65, 10, 'Event On Progress for #BK-0045. We\'re preparing your catering!', 0, '2026-07-25 07:06:09'),
(66, 15, 'Great news! Your booking request for \'My First Birthday Celeb\' (Ref #BK-0047) has been APPROVED by Admin and scheduled.', 0, '2026-07-26 16:36:07'),
(67, 10, 'Booking Confirmed! Your downpayment for #BK-0046 has been verified.', 0, '2026-07-26 16:36:24'),
(68, 10, 'Event On Progress for #BK-0046. We\'re preparing your catering!', 0, '2026-07-26 16:41:06'),
(69, 10, 'Event On Progress for #BK-0046. We\'re preparing your catering!', 0, '2026-07-26 16:51:23'),
(70, 15, 'Event On Progress for #BK-0047. We\'re preparing your catering!', 0, '2026-07-26 16:51:33'),
(71, 10, 'Payment received in full! Booking #BK-0045 marked complete & fully paid.', 0, '2026-07-26 17:05:52'),
(72, 16, 'Great news! Your booking request for \'My 70TH Birthday\' (Ref #BK-0048) has been APPROVED by Admin and scheduled.', 0, '2026-07-26 22:25:04'),
(73, 17, 'Great news! Your booking request for \'First Wedding The Most\' (Ref #BK-0049) has been APPROVED by Admin and scheduled.', 0, '2026-07-26 23:14:24'),
(74, 18, 'Great news! Your booking request for \'My 70TH Birthday\' (Ref #BK-0050) has been APPROVED by Admin and scheduled.', 1, '2026-07-26 23:30:41'),
(76, 1, 'New Message from Client: Uploaded a file attachment (IMAGE)', 0, '2026-07-27 00:09:38'),
(77, 10, 'New Message from Admin: 🤖 **JEMmerific AI Venue Analysis & Catering Proposal**\r\n\r\n📍 **Venue Type:** I...', 0, '2026-07-27 00:10:47'),
(78, 1, 'New Message from Client: Uploaded a file attachment (IMAGE)', 0, '2026-07-27 00:15:42'),
(79, 1, 'New Message from Client: wow', 0, '2026-07-27 00:27:56'),
(80, 1, 'New Message from Client: v', 0, '2026-07-27 01:01:39'),
(83, 1, 'New Message from Client: Uploaded a file attachment (IMAGE)', 0, '2026-07-31 10:21:38'),
(84, 13, 'AI Catering Proposal Ready! Our AI Event Planner has created a customized conceptual catering layout for your booking #BK-0043 (\'Papa 80th Corporate Celeb\'). Check your booking details to review the recommendation.', 0, '2026-07-31 10:27:24'),
(85, 13, 'AI Catering Proposal Ready! Our AI Event Planner has created a customized conceptual catering layout for your booking #BK-0043 (\'Papa 80th Corporate Celeb\'). Check your booking details to review the recommendation.', 0, '2026-07-31 10:27:33'),
(89, 18, 'New Message from Admin: what do you thiink sir?', 0, '2026-07-31 10:53:10'),
(90, 18, 'Payment received in full! Booking #BK-0050 marked complete & fully paid.', 0, '2026-07-31 11:12:05'),
(91, 10, 'New Message from Admin: what is that?', 0, '2026-08-03 11:34:15'),
(92, 1, 'New Message from Client: nothing hahaha', 0, '2026-08-03 11:46:07'),
(93, 10, 'New Message from Admin: ok', 0, '2026-08-03 12:01:39'),
(94, 1, 'New Message from Client: sorry', 0, '2026-08-03 12:05:09'),
(95, 10, 'New Message from Admin: no worries', 0, '2026-08-03 12:06:54'),
(96, 1, 'New Message from Client: k', 0, '2026-08-03 12:12:34'),
(98, 10, 'New Message from Admin: hey', 0, '2026-08-03 13:17:21'),
(99, 20, 'Great news! Your booking request for \'Binyag ng  Anak ko\' (Ref #BK-0051) has been APPROVED by Admin and scheduled.', 1, '2026-08-03 13:51:56'),
(100, 20, 'New Message from Admin: hi', 1, '2026-08-10 10:17:14'),
(101, 1, 'New Message from Client: low', 0, '2026-08-10 10:17:42'),
(102, 20, 'New Message from Admin: haha', 1, '2026-08-10 10:27:43'),
(103, 20, 'Thank you! Your event \'Binyag ng  Anak ko\' (#BK-0051) has been marked as COMPLETED. We hope you enjoyed JEMmerific Catering!', 1, '2026-08-10 10:27:57'),
(104, 1, 'New Message from Client: test', 0, '2026-08-10 11:29:34'),
(105, 21, 'New Message from Admin: hey\'', 0, '2026-08-10 11:30:06'),
(106, 21, 'Great news! Your booking request for \'Zian More Memorable Bday\' (Ref #BK-0053) has been APPROVED by Admin and scheduled.', 0, '2026-08-10 11:31:15'),
(107, 21, 'New Message from Admin: may question po kayo?', 0, '2026-08-10 11:54:44'),
(108, 20, 'Great news! Your booking request for \'Jona Making Best Bday\' (Ref #BK-0052) has been APPROVED by Admin and scheduled.', 1, '2026-08-11 04:33:58'),
(109, 20, 'Update: Your event \'Jona Making Best Bday\' (#BK-0052) is now IN PROGRESS! Our catering team is actively preparing.', 1, '2026-08-11 04:34:37'),
(110, 20, 'Thank you! Your event \'Jona Making Best Bday\' (#BK-0052) has been marked as COMPLETED. We hope you enjoyed JEMmerific Catering!', 1, '2026-08-11 04:34:47'),
(111, 1, 'New Message from Client: thanks', 0, '2026-08-11 04:38:29'),
(112, 20, 'New Message from Admin: your welcome', 1, '2026-08-11 04:49:38'),
(113, 20, 'Great news! Your booking request for \'Jona Happiest bday\' (Ref #BK-0054) has been APPROVED by Admin and scheduled.', 1, '2026-08-11 05:13:08'),
(114, 20, 'Thank you! Your event \'Jona Happiest bday\' (#BK-0054) has been marked as COMPLETED. We hope you enjoyed JEMmerific Catering!', 1, '2026-08-11 05:17:08'),
(115, 20, 'New Message from Admin: m', 1, '2026-08-13 04:57:47'),
(116, 20, 'New Message from Admin: hey', 0, '2026-08-14 08:12:41'),
(117, 1, 'New Message from Client: h', 0, '2026-08-14 11:12:02'),
(118, 20, 'New Message from Admin: k', 0, '2026-08-15 02:12:11'),
(119, 1, 'New Message from Client: hi', 0, '2026-09-22 10:30:24'),
(120, 20, 'New Message from Admin: h', 0, '2026-10-14 13:07:14'),
(121, 20, 'Great news! Your booking request for \'\' (Ref #BK-0059) has been APPROVED by Admin and scheduled.', 0, '2026-10-14 13:08:15'),
(122, 22, 'Update: Your event \'My 21st Birthday Party\' (#BK-0060) is now IN PROGRESS! Our catering team is actively preparing.', 0, '2026-10-14 13:18:44'),
(123, 22, 'New Message from Admin: hey', 0, '2026-10-17 14:18:38'),
(124, 1, 'New Message from Client: hello po', 0, '2026-10-17 16:05:51'),
(125, 1, 'New Message from Client: nareceived nyo napo yung dp namin?', 0, '2026-10-17 16:06:01'),
(126, 1, 'New Message from Client: Uploaded a file attachment (IMAGE)', 0, '2026-10-17 16:06:12'),
(127, 23, 'Booking Placed! Your reservation for \'Arjay 21st Birthday Celeb\' (#BK-0062) has been submitted successfully with Downpayment via GCash. Awaiting admin confirmation.', 0, '2026-10-18 10:05:33'),
(128, 1, 'New Booking: Arjay Morales submitted \'Arjay 21st Birthday Celeb\' (#BK-0062) with Downpayment via GCash. Please verify payment.', 0, '2026-10-18 10:05:33'),
(129, 1, 'New Message from Client: lo', 0, '2026-10-22 11:35:34'),
(130, 22, 'New Message from Admin: h', 0, '2026-10-22 16:03:17'),
(131, 1, 'New Message from Admin: Hello Admin, testing smooth InfinityFree chat at 18:47:09', 0, '2026-10-22 16:47:09'),
(132, 1, 'New Message from Admin: Hello Admin, testing smooth InfinityFree chat at 18:48:13', 0, '2026-10-22 16:48:13'),
(133, 22, 'New Message from Admin: h', 0, '2026-10-22 16:57:08'),
(134, 22, 'New Message from Admin: he', 0, '2026-10-23 08:01:12'),
(135, 22, 'New Message from Admin: dgagg', 0, '2026-10-23 08:29:40'),
(136, 22, 'Notice: Your booking request for \'\' (Ref #BK-0064) has been cancelled. Reason: Schedule fully booked / conflict on selected date.', 0, '2026-10-30 07:08:17'),
(137, 22, 'New Message from Admin: hello po', 0, '2026-10-30 07:11:05'),
(138, 23, 'Notice: Your booking request for \'\' (Ref #BK-0063) has been cancelled. Reason: Schedule fully booked / conflict on selected date.', 0, '2026-10-30 07:20:11'),
(139, 1, 'New Message from Client: hi', 0, '2026-10-30 08:14:00');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `package_id` char(1) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `price_per_pax` decimal(10,2) NOT NULL,
  `main_course_limit` int(11) NOT NULL DEFAULT 0,
  `dessert_limit` int(11) NOT NULL DEFAULT 0,
  `soup_limit` int(11) NOT NULL DEFAULT 0,
  `veggies_limit` int(11) DEFAULT 0,
  `pasta_limit` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`package_id`, `package_name`, `price_per_pax`, `main_course_limit`, `dessert_limit`, `soup_limit`, `veggies_limit`, `pasta_limit`, `description`, `inclusions`) VALUES
('A', 'Package A', 450.00, 1, 1, 0, 1, 1, '1 Main Course Like Beef or Pork, 1 Veggies s, 1 Pasta, 1 Dessert', '• Steamed Rice\r\n• Purified Drinking Water\r\n• Red Ice Tea'),
('B', 'Package B', 650.00, 2, 1, 0, 1, 2, '2 Main Course like Pork and Beef, 1 Veggies, 1 Pasta, 1 Dessert', '• Steamed Rice\r\n• Purified Drinking Water\r\n• Red Ice Tea'),
('C', 'Package C', 850.00, 3, 1, 0, 1, 1, '3 Main Course like Pork, Beef and Chicken or Fish, 1 Veggies, 1 Pasta, 1 Dessert', '• Steamed Rice\r\n• Purified Drinking Water\r\n• Red Ice Tea'),
('D', 'Package D', 1000.00, 4, 1, 0, 1, 1, 'Premium Full Course Buffet 4 Main Course Like Pork, Beef, Chicken and Fish, 1 Veggies, 1 Pasta, 1 Dessert', '• Steamed Rice\r\n• Purified Drinking Water\r\n• Red Ice Tea');

-- --------------------------------------------------------

--
-- Table structure for table `package_pricing_tiers`
--

CREATE TABLE `package_pricing_tiers` (
  `tier_id` int(11) NOT NULL,
  `package_id` char(1) NOT NULL,
  `pax_threshold` int(11) NOT NULL,
  `package_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `package_pricing_tiers`
--

INSERT INTO `package_pricing_tiers` (`tier_id`, `package_id`, `pax_threshold`, `package_price`) VALUES
(1, 'A', 50, 45000.00),
(2, 'A', 100, 65000.00),
(3, 'A', 150, 85000.00),
(4, 'A', 200, 105000.00),
(5, 'B', 50, 62000.00),
(6, 'B', 100, 87000.00),
(7, 'B', 150, 118000.00),
(8, 'B', 200, 139000.00),
(9, 'C', 50, 73000.00),
(10, 'C', 100, 107000.00),
(11, 'C', 150, 142000.00),
(12, 'C', 200, 169000.00),
(13, 'D', 50, 81000.00),
(14, 'D', 100, 120000.00),
(15, 'D', 150, 154000.00),
(16, 'D', 200, 186000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected','Refunded') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `user_id`, `payment_method`, `account_name`, `account_number`, `reference_no`, `screenshot_path`, `status`, `created_at`) VALUES
(2, 11, 1, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_11_1775011898.jpg', 'Verified', '2026-04-01 02:51:38'),
(4, 14, 11, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_14_1775021061.jpg', 'Verified', '2026-04-01 05:24:21'),
(5, 15, 11, 'GCash', 'Alec Cerenado', '09673961063', '0987654321098', 'pay_15_1775023119.jpg', 'Verified', '2026-04-01 05:58:39'),
(6, 20, 1, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_20_1775450192.jpg', 'Verified', '2026-04-06 04:36:32'),
(8, 24, 11, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_24_1775525693.jpg', 'Verified', '2026-04-07 01:34:53'),
(9, 25, 1, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_25_1775527017.jpg', 'Verified', '2026-04-07 01:56:57'),
(10, 26, 11, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_26_1775530190.jpg', 'Verified', '2026-04-07 02:49:50'),
(12, 31, 10, 'GCash', 'Adrian', '09932536547', '1234567890123', 'pay_31_1775604530.jpg', 'Verified', '2026-04-07 23:28:50'),
(13, 35, 10, 'GCash', 'Adrian Cerenado', '09932536547', '1234567890123', 'pay_35_1775610030.jpg', 'Verified', '2026-04-08 01:00:30'),
(15, 36, 1, 'GCash', 'Adrian Cerenado', '09932536547', '1234567890123', 'pay_36_1775613589.jpg', 'Pending', '2026-04-08 01:59:49'),
(16, 37, 10, 'GCash', 'Adrian Cerenado', '09932536547', '1234567890123', 'pay_37_1775614879.jpg', 'Verified', '2026-04-08 02:21:19'),
(17, 38, 13, 'GCash', 'Adrian Cerenado', '09932536547', '1234567890123', 'pay_38_1775625346.jpg', 'Verified', '2026-04-08 05:15:46'),
(18, 39, 14, 'GCash', 'Melvin Gunda', '09932536547', '1234567890123', 'pay_39_1775628729.jpg', 'Verified', '2026-04-08 06:12:09'),
(19, 41, 13, 'GCash', 'Adrian Cerenado', '09932536547', '1234567890123', 'pay_41_1775705026.jpg', 'Verified', '2026-04-09 03:23:46'),
(20, 43, 13, 'GCash', 'Jazon Garcia', '09932536547', '1234567890123', 'pay_43_1775714511.jpg', 'Pending', '2026-04-09 06:01:51'),
(22, 46, 10, 'GCash', 'Adrian Cerenado', '09932536437', '748364086130', 'pay_46_1785082700.jpg', 'Verified', '2026-07-26 16:18:20'),
(23, 47, 15, 'GCash', 'Harold Viray', '09673961063', '75872389570530', 'pay_47_1785083655.jpg', 'Verified', '2026-07-26 16:34:15'),
(24, 48, 16, 'GCash', 'Vhong Cerenado', '09932536437', '7221272723445', 'pay_48_1785104451.jpg', 'Verified', '2026-07-26 22:20:51'),
(25, 49, 17, 'GCash', 'nasha vella', '09673961063', '75872389570530', 'pay_49_1785107605.jpg', '', '2026-07-26 23:13:25'),
(26, 50, 18, 'GCash', 'Duterte Roa', '09673961063', '722127272344563', 'pay_50_1785108549.jpg', 'Verified', '2026-07-26 23:29:09'),
(27, 51, 20, 'GCash', 'Jona Mari', '09932536437', '7221272723445', 'pay_51_1785765057.jpg', '', '2026-08-03 13:50:57'),
(28, 52, 20, 'GCash', 'Jona Mari', '0993265414', '4781647917417671', 'pay_52_1786358257.jpg', '', '2026-08-10 10:37:37'),
(29, 53, 21, 'GCash', 'Ziah Dela Cruz', '09477811434', '4781647917417671', 'pay_53_1786361355.jpg', 'Verified', '2026-08-10 11:29:15'),
(30, 54, 20, 'GCash', 'Jona Mari', '0993265414', '4781647917417671', 'pay_54_1786423255.jpg', 'Verified', '2026-08-11 04:40:55'),
(31, 60, 22, 'GCash', 'John Cena', '09932536437', '4371451447194791', 'pay_60_1791983764.jpg', 'Verified', '2026-10-14 13:16:04'),
(32, 61, 11, 'GCash', 'Alec Cerenaado', '09673961063', '8163810568016561', 'pay_61_1792253004.jpg', 'Pending', '2026-10-17 16:03:24'),
(33, 62, 23, 'GCash', 'ARJAY MORALES', '09932536437', '6498168456184686', 'pay_62_1792317932.jpg', 'Pending', '2026-10-18 10:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `plain_password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'client',
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_active` datetime DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `plain_password`, `role`, `permissions`, `created_at`, `last_active`, `is_online`) VALUES
(1, 'System Administrator', 'admin@jemmerific.com', NULL, 'admin123', NULL, 'admin', NULL, '2026-03-31 05:40:05', '2026-10-30 16:11:59', 0),
(10, 'Adrian Cerenado', 'adriancerenado2@gmail.com', NULL, '$2y$10$9xmrwJfCt2LM6UAlCIw80.jcePqpNdkanifx6SUZM8GfC8LXt5iJ2', NULL, NULL, NULL, '2026-03-31 08:08:17', NULL, 0),
(11, 'alec cerenado', 'aleccerenado2@gmail.com', NULL, '$2y$10$uuQKST8i331w4EcaMLwJOu9Xd.BywxG1gsRATL5obXhuCpaBTFyEu', NULL, '', NULL, '2026-04-01 03:23:44', NULL, 0),
(12, 'Romulo', 'romulonablea2@gmail.com', NULL, '$2y$10$QWx.XezRjPLFlRKjExkMy.OXROgnVpqJLQkzpY1J279NqA1s.Vevu', NULL, '', NULL, '2026-04-07 06:00:50', NULL, 0),
(13, 'Jazon Garcia', 'jazongarcia2@gmail.com', NULL, '$2y$10$NtYA4TO63TcsXwsm9m.KT.JLrh6eyt.Evzv70TMKGYWpPuouwdEK6', NULL, '', NULL, '2026-04-08 03:47:54', NULL, 0),
(14, 'Melvin Gunda', 'Melvingunda2@gmail.com', NULL, '$2y$10$fYZOFO.WPP6.fGnUyXPaiOuOhEL05jan1nR5VlJNeHxLPYUEd1kYW', NULL, '', NULL, '2026-04-08 06:09:30', NULL, 0),
(15, 'Harold Viray', 'haroldviray2@gmail.com', NULL, '$2y$10$/jTZzvkXdV7HlRjV/xPw8ueKifdmuf6NYGxC3cL3HuH1GpvlM0Asa', NULL, '', NULL, '2026-07-26 16:31:29', NULL, 0),
(16, 'Vhong Cerenado', 'vhongcerenado2@gmail.com', NULL, '$2y$10$65eNLVUjEHvm1ZPKHSXi6.GQRHgZBei9OJa9mMAM9c95X5y4pVHny', NULL, '', NULL, '2026-07-26 22:17:30', NULL, 0),
(19, 'Sarah Duterte', 'sarahduterte@gmail.com', NULL, '$2y$10$wRaIY1b7ObLG/58gTxqf3e1yDGu.ASvdPvbNjxgLnoe6xIM0d24p6', NULL, '', NULL, '2026-07-31 12:06:39', NULL, 0),
(20, 'Jona Mari', 'jonamari@gmail.com', NULL, '$2y$10$SLPmH69brIypi7swoqiXaOJ8UWDiFzaQpeFWK/1x1Oy1Ly4dlJL/q', NULL, '', NULL, '2026-08-03 13:43:40', NULL, 0),
(21, 'Ziah Dela Cruz', 'ziahdelacruz@gmail.com', NULL, '$2y$10$J25ZIjaciiCFG53GIamLq.d6F5FGd2gyALsy/1VMgy3oFUcCaUqi2', NULL, '', NULL, '2026-08-10 11:21:40', NULL, 0),
(22, 'John Cena', 'johncena@gmail.com', NULL, '$2y$10$4iqFLVDxYO08sXgVbFsYLuv3RVO2OCGxPaZlOhse03JiAppwLl9AW', NULL, '', NULL, '2026-10-14 13:10:06', '2026-10-30 16:13:44', 0),
(23, 'Arjay Morales', 'arjaymorales@gmail.com', NULL, '$2y$10$wM4WIp4Xdar7Wb4EtsdS3Oy9HYCeVVHMyCFLmezJPYuXEFFhv.YFq', NULL, '', NULL, '2026-10-18 09:53:46', NULL, 0),
(24, 'Wakin Manado', 'wakin@gmail.com', '09932536437', '$2y$10$JnZbmlBO9j9Zb2qeGgkSl.tfdhlsBMR15IcWioOphauiiLQ/loJJe', 'wakin123', 'staff', '[\"customer_messages\",\"booking_requests\"]', '2026-10-30 07:05:37', '2026-10-30 16:03:11', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_event_plans`
--
ALTER TABLE `ai_event_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `ai_user_limits`
--
ALTER TABLE `ai_user_limits`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `availability_schedule`
--
ALTER TABLE `availability_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `schedule_date_idx` (`schedule_date`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_link` (`booking_id`);

--
-- Indexes for table `chatbot_daily_usage`
--
ALTER TABLE `chatbot_daily_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identifier` (`identifier`);

--
-- Indexes for table `dietary_restrictions`
--
ALTER TABLE `dietary_restrictions`
  ADD PRIMARY KEY (`restriction_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_booking_msg_id` (`booking_id`,`id`),
  ADD KEY `idx_receiver_unread` (`receiver_id`,`is_read`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`package_id`);

--
-- Indexes for table `package_pricing_tiers`
--
ALTER TABLE `package_pricing_tiers`
  ADD PRIMARY KEY (`tier_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_event_plans`
--
ALTER TABLE `ai_event_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `availability_schedule`
--
ALTER TABLE `availability_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=957;

--
-- AUTO_INCREMENT for table `chatbot_daily_usage`
--
ALTER TABLE `chatbot_daily_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `dietary_restrictions`
--
ALTER TABLE `dietary_restrictions`
  MODIFY `restriction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `package_pricing_tiers`
--
ALTER TABLE `package_pricing_tiers`
  MODIFY `tier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_event_plans`
--
ALTER TABLE `ai_event_plans`
  ADD CONSTRAINT `ai_event_plans_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `fk_booking_link` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dietary_restrictions`
--
ALTER TABLE `dietary_restrictions`
  ADD CONSTRAINT `dietary_restrictions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
