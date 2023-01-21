-- migrate:up

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `t_contacts_atoms`;
CREATE TABLE `t_contacts_atoms` (
  `c_uuid` binary(16) GENERATED ALWAYS AS (uuid_to_bin(json_unquote(json_extract(`c_data`,_utf8mb4'$.uuid')),true)) STORED COMMENT 'Atom UUID',
  `c_sub` binary(16) GENERATED ALWAYS AS (uuid_to_bin(json_unquote(json_extract(`c_data`,_utf8mb4'$._sub')),true)) STORED COMMENT 'Contact atom subject',
  `c_data` json DEFAULT NULL COMMENT 'Atom data as json',
  `c_fulltext` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`c_data`,_utf8mb4'$.value'))) STORED COMMENT 'Fulltext searchable data',
  `c_public` binary(1) GENERATED ALWAYS AS (json_unquote(json_extract(`c_data`,_utf8mb4'$._public'))) STORED COMMENT 'Atom is publicly searchable',
  `c_scheme` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`c_data`,_utf8mb4'$._s'))) STORED,
  `c_kind` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`c_data`,_utf8mb4'$.kind'))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- migrate:down

DROP TABLE `t_contacts_atoms`