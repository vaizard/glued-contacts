-- migrate:up

CREATE TRIGGER `before_update_t_contacts_objects` BEFORE UPDATE ON `t_contacts_objects` FOR EACH ROW
BEGIN
  IF (OLD.c_hash <> NEW.c_hash) THEN
    INSERT INTO `t_contacts_objects_audit` (`c_uuid`, `c_data`, `c_ts`)
    VALUES (OLD.c_uuid, OLD.c_data, NOW());
  END IF;
END

-- migrate:down

DROP TRIGGER IF EXISTS `before_update_t_contacts_objects`;
