CREATE TABLE IF NOT EXISTS `#__tz_media_transfer_jobs` (
 `id` char(32) NOT NULL, `user_id` int unsigned NOT NULL, `status` varchar(24) NOT NULL,
 `state` longtext NOT NULL, `created` datetime NOT NULL, `updated` datetime NOT NULL,
 PRIMARY KEY (`id`), KEY `owner_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__tz_media_transfer_changes` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `job_id` char(32) NOT NULL,
 `status` varchar(24) NOT NULL, `cleaned` tinyint NOT NULL DEFAULT 0, `data` longtext NOT NULL,
 PRIMARY KEY (`id`), KEY `job_order` (`job_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
