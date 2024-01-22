-- migrate:up

CREATE TABLE `t_contacts_objects` (
  `c_uuid` binary(16) NOT NULL COMMENT 'Object UUID',
  `c_rev` int unsigned NOT NULL DEFAULT '0' COMMENT 'Object revision',
  `c_data` json DEFAULT NULL COMMENT 'Object data as JSON',
  `c_private` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Exclude private data from ft search',
  `c_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp column',
  `c_ft` text COMMENT 'Fulltext search',
  `c_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`c_data`))) STORED,
  PRIMARY KEY (`c_uuid`),
  UNIQUE KEY `idx_c_hash` (`c_hash`),
  FULLTEXT KEY `idx_c_ft` (`c_ft`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `t_contacts_objects_refs` (
  `obj` binary(16) NOT NULL COMMENT 'Object UUID',
  `ref_label` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '_' COMMENT 'Reference label',
  `ref_val` binary(16) NOT NULL COMMENT 'Reference UUID',
  `ref_kind` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Reference kind',
  `ref_from` timestamp NULL DEFAULT NULL COMMENT 'Reference valid from',
  `ref_until` timestamp NULL DEFAULT NULL COMMENT 'Reference valid until',
  PRIMARY KEY (`obj`,`ref_label`,`ref_kind`,`ref_val`),
  KEY `obj` (`obj`),
  KEY `obj_obj_generation` (`obj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='References between objects';

-- migrate:down

DROP TABLE IF EXISTS `t_contacts_objects`;
DROP TABLE IF EXISTS `t_contacts_objects_refs`;
