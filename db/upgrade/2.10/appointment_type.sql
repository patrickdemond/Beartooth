DROP PROCEDURE IF EXISTS patch_appointment_type;
DELIMITER //
CREATE PROCEDURE patch_appointment_type()
  BEGIN

    SELECT "Adding use_participant_timezone column to appointment_type table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "appointment_type"
    AND column_name = "use_participant_timezone";

    IF @test = 0 THEN
      ALTER TABLE appointment_type
      ADD COLUMN use_participant_timezone TINYINT(1) NOT NULL DEFAULT 1 AFTER qnaire_id;
    END IF;

  END //
DELIMITER ;

CALL patch_appointment_type();
DROP PROCEDURE IF EXISTS patch_appointment_type;
