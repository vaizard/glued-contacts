-- migrate:up

ALTER TABLE `t_contacts_objects`
  DROP COLUMN `c_private`,
  DROP COLUMN `c_rev`;

ALTER TABLE `t_contacts_objects`
  ADD COLUMN `c_private` bit(1) AS (COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`c_data`, '$._.private'))+0, 0)) STORED COMMENT 'Exclude private data from ft search' AFTER `c_data`;


CREATE TABLE `t_contacts_objects_audit` (
  `c_uuid` binary(16) NOT NULL COMMENT 'Object UUID',
  `c_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(concat(`c_data`)))) STORED,
  `c_data` json DEFAULT NULL COMMENT 'Object data as JSON',
  `c_ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp column'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE OR REPLACE VIEW v_contacts_objects_audit AS SELECT
  t.*,
  ROW_NUMBER() OVER (PARTITION BY t.c_uuid ORDER BY t.c_ts) AS c_rev,
  COUNT(*) OVER (PARTITION BY t.c_uuid) AS c_rev_count
FROM t_contacts_objects_audit t;


-- migrate:down

DROP VIEW IF EXISTS `v_contacts_objects_audit`;
DROP TABLE IF EXISTS `t_contacts_objects_audit`;

ALTER TABLE `t_contacts_objects`
  DROP COLUMN `c_private`;

ALTER TABLE `t_contacts_objects`
  ADD COLUMN `c_rev` int unsigned NOT NULL DEFAULT '0' AFTER `c_uuid`,
  ADD COLUMN `c_private` bit(1) NOT NULL DEFAULT b'0' AFTER `c_data`;


