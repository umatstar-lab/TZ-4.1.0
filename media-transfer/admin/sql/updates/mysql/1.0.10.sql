CREATE TABLE IF NOT EXISTS `#__tz_media_transfer_menus` (
 `id` char(32) NOT NULL, `user_id` int unsigned NOT NULL, `created` datetime NOT NULL,
 `status` varchar(24) NOT NULL, `data` longtext NOT NULL,
 PRIMARY KEY (`id`), KEY `menu_owner` (`user_id`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
