CREATE TABLE `company` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `company_name` varchar(255),
  `company_address` varchar(255),
  `company_email` varchar(255),
  `company_telephone` integer,
  `created_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `profile_picture` varchar(255)
);

CREATE TABLE `stuffs` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `type_id` integer,
  `secondtype_id` integer,
  `brand_id` integer,
  `model_id` integer,
  `manufacture_date` integer,
  `favourite` boolean,
  `stuff_status_id` integer,
  `qr_code` varchar(255),
  `company_id` integer
);

CREATE TABLE `user` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `firstname` varchar(255),
  `lastname` varchar(255),
  `email` varchar(255),
  `telephone` integer,
  `password` varchar(255),
  `profile_pic` varchar(255),
  `company_id` integer,
  `cookie_id` int,
  `current_status_id` int,
  `created_date` datetime,
  `connect_date` datetime,
  `is_email_verified` boolean DEFAULT false,
  `language` varchar(10) DEFAULT 'hu',
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
);

CREATE TABLE `roles` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `role_name` varchar(255)
);

CREATE TABLE `user_to_roles` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `role_id` integer,
  `user_id` int
);

CREATE TABLE `status_history` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer,
  `status_id` int,
  `status_startdate` timestamp,
  `status_enddate` timestamp
);

CREATE TABLE `status` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `project_type` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `project` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255),
  `type_id` integer,
  `project_startdate` DATETIME,
  `project_enddate` DATETIME,
  `picture` VARCHAR(255),
  `company_id` integer,
  `country_id` integer,
  `county_id` integer,
  `city_id` integer,
  `district_id` integer
);

CREATE TABLE `event_status` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `work` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `deliver_id` integer,
  `project_id` integer,
  `work_start_date` timestamp,
  `work_end_date` timestamp,
  `company_id` integer,
  FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
);

CREATE TABLE `deliver` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `stuff_status` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `stuff_history` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `stuffs_id` integer,
  `work_id` integer,
  `user_id` integer,
  `stuff_status_id` integer,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`stuffs_id`) REFERENCES `stuffs` (`id`),
  FOREIGN KEY (`work_id`) REFERENCES `work` (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`stuff_status_id`) REFERENCES `stuff_status` (`id`)
);

CREATE TABLE `stuff_type` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `stuff_secondtype` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255),
  `stuff_type_id` int
);

CREATE TABLE `stuff_brand` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255),
  `stuff_secondtype_id` integer
);

CREATE TABLE `stuff_model` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255),
  `brand_id` integer
);

CREATE TABLE `stuff_manufacture_date` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `year` int,
  `stuff_model_id` int
);

CREATE TABLE `maintenance` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `stuffs_id` integer,
  `user_id` integer,
  `servis_startdate` timestamp,
  `servis_planenddate` timestamp,
  `servis_currectenddate` timestamp,
  `description` text,
  `maintenance_status_id` integer,
  `company_id` integer
);

CREATE TABLE `maintenance_status` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255)
);

CREATE TABLE `notifications` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `sender_user_id` integer,
  `receiver_user_id` integer,
  `work_id` integer,
  `notification_text` text,
  `notification_time` timestamp
);

CREATE TABLE `invitations` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `email` varchar(255),
  `company_id` integer,
  `role_id` integer,
  `invitation_token` varchar(255),
  `is_used` boolean DEFAULT false,
  `expiration_date` timestamp,
  `created_by_user_id` integer,
  `created_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `cookies` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `acceptedornot` boolean
);

CREATE TABLE `calendar_events` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `title` varchar(255),
  `description` text,
  `start_date` timestamp,
  `end_date` timestamp,
  `status_id` integer,
  `user_id` integer,
  `work_id` integer,
  `company_id` integer,
  `is_accepted` boolean DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`work_id`) REFERENCES `work` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`),
  FOREIGN KEY (`status_id`) REFERENCES `status` (`id`)
);

CREATE TABLE `leave_requests` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `sender_user_id` integer NOT NULL,
  `receiver_user_id` integer NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `notification_text` text NOT NULL,
  `status_id` integer NOT NULL,
  `notification_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  `response_time` timestamp NULL,
  `response_message` text,
  `is_accepted` boolean DEFAULT NULL,
  FOREIGN KEY (sender_user_id) REFERENCES user (id),
  FOREIGN KEY (receiver_user_id) REFERENCES user (id),
  FOREIGN KEY (status_id) REFERENCES status (id)
);

CREATE TABLE `notes` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer,
  `company_id` integer,
  `title` varchar(255),
  `content` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
);

CREATE TABLE `countries` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
);

CREATE TABLE `counties` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `country_id` integer NOT NULL,
  `name` varchar(255) NOT NULL,
  FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
);

CREATE TABLE `districts` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `county_id` integer NOT NULL,
  `name` varchar(255) NOT NULL,
  FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`)
);

CREATE TABLE `cities` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `county_id` integer,
  `district_id` integer,
  `name` varchar(255) NOT NULL,
  FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`),
  FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
);

CREATE TABLE `work_to_stuffs` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `work_id` integer,
  `stuffs_id` integer,
  `is_packed` boolean DEFAULT 0,
  `packed_date` datetime,
  `packed_by` integer,
  FOREIGN KEY (`work_id`) REFERENCES `work` (`id`),
  FOREIGN KEY (`stuffs_id`) REFERENCES `stuffs` (`id`),
  FOREIGN KEY (packed_by) REFERENCES user(id)
);

CREATE TABLE `user_to_work` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `work_id` integer,
  `user_id` integer,
  FOREIGN KEY (`work_id`) REFERENCES `work` (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `billing_intervals` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
);

CREATE TABLE `subscription_statuses` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
);

CREATE TABLE `payment_statuses` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL
);

CREATE TABLE `subscription_plans` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `billing_interval_id` integer NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`billing_interval_id`) REFERENCES `billing_intervals` (`id`)
);

CREATE TABLE `payment_methods` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer NOT NULL,
  `card_holder_name` varchar(255) NOT NULL,
  `CVC` varchar(3) NOT NULL,
  `card_expiry_month` varchar(2) NOT NULL,
  `card_expiry_year` varchar(4) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_default` boolean DEFAULT false,
  `last_used` timestamp NULL,
  `card_type` varchar(50),
  `last_four_digits` varchar(4),
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `subscriptions` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer NOT NULL,
  `company_id` integer NOT NULL,
  `subscription_plan_id` integer NOT NULL,
  `payment_method_id` integer NOT NULL,
  `subscription_status_id` integer NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp,
  `next_billing_date` timestamp,
  `auto_renewal` boolean DEFAULT true,
  `cancellation_reason` text,
  `cancelled_at` timestamp NULL,
  `trial_end_date` timestamp NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`),
  FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`),
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  FOREIGN KEY (`subscription_status_id`) REFERENCES `subscription_statuses` (`id`)
);

CREATE TABLE `payment_history` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `subscription_id` integer NOT NULL,
  `payment_method_id` integer NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status_id` integer NOT NULL,
  `transaction_id` varchar(255),
  `payment_date` timestamp NOT NULL,
  `payment_method_type` varchar(50),
  `transaction_status` varchar(50),
  `error_message` text,
  `refund_amount` decimal(10,2),
  `refund_reason` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  FOREIGN KEY (`payment_status_id`) REFERENCES `payment_statuses` (`id`)
);

CREATE TABLE `maintenance_logs` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `maintenance_id` integer NOT NULL,
  `user_id` integer NOT NULL,
  `old_status_id` integer,
  `new_status_id` integer,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance` (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`old_status_id`) REFERENCES `maintenance_status` (`id`),
  FOREIGN KEY (`new_status_id`) REFERENCES `maintenance_status` (`id`)
);

CREATE TABLE `admin_users` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(255) NOT NULL UNIQUE,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `admin_permissions` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text
);

CREATE TABLE `admin_role_permissions` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `role_id` integer NOT NULL,
  `permission_id` integer NOT NULL,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions` (`id`)
);

CREATE TABLE `admin_logs` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `admin_id` integer NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(255),
  `record_id` integer,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `admin_settings` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(255),
  `is_public` boolean DEFAULT false,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `password_reset_tokens` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `work_history` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `original_work_id` integer,
  `user_id` int,
  `deliver_id` integer,
  `project_id` integer,
  `work_start_date` timestamp,
  `work_end_date` timestamp,
  `company_id` integer,
  `archived_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `archived_by` integer,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  FOREIGN KEY (`deliver_id`) REFERENCES `deliver` (`id`),
  FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`),
  FOREIGN KEY (`archived_by`) REFERENCES `user` (`id`)
);

CREATE TABLE `project_history` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `original_project_id` integer,
  `name` varchar(255),
  `type_id` integer,
  `project_startdate` DATETIME,
  `project_enddate` DATETIME,
  `picture` VARCHAR(255),
  `company_id` integer,
  `country_id` integer,
  `county_id` integer,
  `city_id` integer,
  `district_id` integer,
  `archived_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `archived_by` integer,
  FOREIGN KEY (`type_id`) REFERENCES `project_type` (`id`),
  FOREIGN KEY (`company_id`) REFERENCES `company` (`id`),
  FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`),
  FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  FOREIGN KEY (`archived_by`) REFERENCES `user` (`id`)
);

CREATE TABLE `email_verification_codes` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_verified` boolean DEFAULT false,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `subscription_modifications` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `subscription_id` integer NOT NULL,
  `original_plan_id` integer NOT NULL,
  `modified_plan_id` integer NOT NULL,
  `modification_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `modified_by_user_id` integer NOT NULL,
  `price_difference` decimal(10,2),
  `modification_reason` text,
  FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
  FOREIGN KEY (`original_plan_id`) REFERENCES `subscription_plans` (`id`),
  FOREIGN KEY (`modified_plan_id`) REFERENCES `subscription_plans` (`id`),
  FOREIGN KEY (`modified_by_user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `subscription_analytics` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `subscription_plan_id` integer NOT NULL,
  `total_subscriptions` integer DEFAULT 0,
  `active_subscriptions` integer DEFAULT 0,
  `cancelled_subscriptions` integer DEFAULT 0,
  `average_duration` integer,
  `total_revenue` decimal(10,2),
  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`)
);

CREATE TABLE `subscription_feedback` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `subscription_id` integer NOT NULL,
  `user_id` integer NOT NULL,
  `rating` integer CHECK (rating >= 1 AND rating <= 5),
  `feedback_text` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

ALTER TABLE `stuffs` ADD FOREIGN KEY (`type_id`) REFERENCES `stuff_type` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`secondtype_id`) REFERENCES `stuff_secondtype` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`brand_id`) REFERENCES `stuff_brand` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`model_id`) REFERENCES `stuff_model` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`manufacture_date`) REFERENCES `stuff_manufacture_date` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`stuff_status_id`) REFERENCES `stuff_status` (`id`);
ALTER TABLE `stuffs` ADD FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);

ALTER TABLE `maintenance` ADD FOREIGN KEY (`maintenance_status_id`) REFERENCES `maintenance_status` (`id`);
ALTER TABLE `maintenance` ADD FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);

ALTER TABLE `notifications` ADD FOREIGN KEY (`receiver_user_id`) REFERENCES `user` (`id`);

ALTER TABLE `stuff_manufacture_date` ADD FOREIGN KEY (`stuff_model_id`) REFERENCES `stuff_model` (`id`);

ALTER TABLE `status_history` ADD FOREIGN KEY (`status_id`) REFERENCES `status` (`id`);

ALTER TABLE `user` ADD FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);
ALTER TABLE `user_to_roles` ADD FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
ALTER TABLE `user_to_roles` ADD FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `status_history` ADD FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
ALTER TABLE `user` ADD FOREIGN KEY (`current_status_id`) REFERENCES `status` (`id`);

ALTER TABLE `notifications` ADD FOREIGN KEY (`sender_user_id`) REFERENCES `user` (`id`);

ALTER TABLE `work` ADD FOREIGN KEY (`deliver_id`) REFERENCES `deliver` (`id`);
ALTER TABLE `work` ADD FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);

ALTER TABLE `project` ADD FOREIGN KEY (`type_id`) REFERENCES `project_type` (`id`);
ALTER TABLE `project` ADD FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);

ALTER TABLE `stuff_history` ADD FOREIGN KEY (`stuffs_id`) REFERENCES `stuffs` (`id`);

ALTER TABLE `stuff_secondtype` ADD FOREIGN KEY (`stuff_type_id`) REFERENCES `stuff_type` (`id`);

ALTER TABLE `stuff_brand` ADD FOREIGN KEY (`stuff_secondtype_id`) REFERENCES `stuff_secondtype` (`id`);

ALTER TABLE `stuff_model` ADD FOREIGN KEY (`brand_id`) REFERENCES `stuff_brand` (`id`);

ALTER TABLE `maintenance` ADD FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
ALTER TABLE `maintenance` ADD FOREIGN KEY (`stuffs_id`) REFERENCES `stuffs` (`id`);

ALTER TABLE `user` ADD FOREIGN KEY (`cookie_id`) REFERENCES `cookies` (`id`);

ALTER TABLE `project` ADD FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);
ALTER TABLE `project` ADD FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`);
ALTER TABLE `project` ADD FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);
ALTER TABLE `project` ADD FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`);

ALTER TABLE `notifications` ADD FOREIGN KEY (`work_id`) REFERENCES `work` (`id`);

-- Add a constraint to ensure either county_id or district_id is filled based on country
ALTER TABLE `cities` ADD CONSTRAINT `check_administrative_division` 
CHECK (
  (county_id IS NOT NULL AND district_id IS NULL) OR 
  (county_id IS NULL AND district_id IS NOT NULL)
);

-- Admin logs tábla kezelése - korábbi procedúra helyett közvetlenül hozzuk létre
-- Először ellenőrizzük, hogy létezik-e már, ha igen, akkor töröljük
DROP TABLE IF EXISTS `admin_logs`;

-- Létrehozzuk a táblát a megfelelő szerkezettel
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(255) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Teszt adat beszúrása
INSERT INTO `admin_logs` (
    `admin_id`, 
    `action_type`, 
    `table_name`, 
    `record_id`, 
    `old_values`, 
    `new_values`, 
    `ip_address`
) VALUES (
    1, 
    'TEST', 
    'company', 
    1, 
    '{"name":"Teszt Cég"}', 
    '{"name":"Módosított Teszt Cég"}', 
    '127.0.0.1'
);
