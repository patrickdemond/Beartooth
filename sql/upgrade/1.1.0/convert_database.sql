-- 
-- Cross-application database amalgamation redesign
-- This script converts pre version 1.2 databases to the new amalgamated design
-- 

-- change the cohort column to a varchar
DROP PROCEDURE IF EXISTS convert_database;
DELIMITER //
CREATE PROCEDURE convert_database()
  BEGIN
    SET @test = (
      SELECT COUNT( * )
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ( SELECT DATABASE() )
      AND TABLE_NAME = "participant" );
    IF @test = 1 THEN

      SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
      SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
      SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='';

      -- determine the @cenozo database name
      SET @cenozo = CONCAT( SUBSTRING( DATABASE(), 1, LOCATE( 'beartooth', DATABASE() ) - 1 ),
                            'cenozo' );

      -- qnaire ------------------------------------------------------------------------------------
      ALTER TABLE qnaire DROP FOREIGN KEY fk_qnaire_prev_qnaire;
      ALTER TABLE qnaire
      ADD CONSTRAINT fk_qnaire_prev_qnaire_id
      FOREIGN KEY ( prev_qnaire_id ) REFERENCES qnaire ( id )
      ON DELETE NO ACTION ON UPDATE NO ACTION;

      -- phase -------------------------------------------------------------------------------------
      ALTER TABLE phase DROP FOREIGN KEY fk_phase_qnaire;
      ALTER TABLE phase
      ADD CONSTRAINT fk_phase_qnaire_id
      FOREIGN KEY ( qnaire_id ) REFERENCES qnaire ( id )
      ON DELETE NO ACTION ON UPDATE NO ACTION;

      -- interview ---------------------------------------------------------------------------------
      ALTER TABLE interview RENAME interview_old;
      ALTER TABLE interview_old
      DROP FOREIGN KEY fk_interiew_duplicate_qnaire_id,
      DROP FOREIGN KEY fk_interview_participant,
      DROP FOREIGN KEY fk_interview_qnaire_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS interview ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "qnaire_id INT UNSIGNED NOT NULL, ",
          "participant_id INT UNSIGNED NOT NULL, ",
          "require_supervisor TINYINT( 1 ) NOT NULL DEFAULT 0, ",
          "completed TINYINT( 1 ) NOT NULL DEFAULT 0, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_participant_id ( participant_id ASC ), ",
          "INDEX fk_qnaire_id ( qnaire_id ASC ), ",
          "INDEX dk_completed ( completed ASC ), ",
          "UNIQUE INDEX uq_participant_id_qnaire_id ( participant_id ASC, qnaire_id ASC ), ",
          "CONSTRAINT fk_interview_participant_id ",
            "FOREIGN KEY ( participant_id ) ",
            "REFERENCES ", @cenozo, ".participant ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_interview_qnaire_id ",
            "FOREIGN KEY ( qnaire_id ) ",
            "REFERENCES qnaire ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB ",
        "COMMENT = 'aka: qnaire_has_participant'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO interview ( id, update_timestamp, create_timestamp, qnaire_id, ",
                                "participant_id, completed ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, old.qnaire_id, ",
               "cparticipant.id, old.completed ",
        "FROM interview_old old ",
        "JOIN participant ON old.participant_id = participant.id ",
        "JOIN ", @cenozo, ".participant cparticipant ON participant.uid = cparticipant.uid" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE interview_old;

      -- assignment --------------------------------------------------------------------------------
      ALTER TABLE assignment RENAME assignment_old;
      ALTER TABLE assignment_old
      DROP FOREIGN KEY fk_assignment_interview_id,
      DROP FOREIGN KEY fk_assignment_queue_id,
      DROP FOREIGN KEY fk_assignment_site_id,
      DROP FOREIGN KEY fk_assignment_user_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS assignment ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "user_id INT UNSIGNED NOT NULL, ",
          "site_id INT UNSIGNED NOT NULL COMMENT 'The site from which the user was assigned.', ",
          "interview_id INT UNSIGNED NOT NULL, ",
          "queue_id INT UNSIGNED DEFAULT NULL COMMENT 'The queue that the assignment came from.', ",
          "start_datetime DATETIME NOT NULL, ",
          "end_datetime DATETIME NULL DEFAULT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_interview_id ( interview_id ASC ), ",
          "INDEX fk_queue_id ( queue_id ASC ), ",
          "INDEX dk_start_datetime ( start_datetime ASC ), ",
          "INDEX dk_end_datetime ( end_datetime ASC ), ",
          "INDEX fk_user_id ( user_id ASC ), ",
          "INDEX fk_site_id ( site_id ASC ), ",
          "CONSTRAINT fk_assignment_interview_id ",
            "FOREIGN KEY ( interview_id ) ",
            "REFERENCES interview ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_assignment_queue_id ",
            "FOREIGN KEY ( queue_id ) ",
            "REFERENCES queue ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_assignment_user_id ",
            "FOREIGN KEY ( user_id ) ",
            "REFERENCES ", @cenozo, ".user ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_assignment_site_id ",
            "FOREIGN KEY ( site_id ) ",
            "REFERENCES ", @cenozo, ".site ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO assignment ( id, update_timestamp, create_timestamp, user_id, site_id, ",
                                 "interview_id, queue_id, start_datetime, end_datetime ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, cuser.id, csite.id, ",
               "old.interview_id, old.queue_id, old.start_datetime, old.end_datetime ",
        "FROM assignment_old old ",
        "JOIN user ON old.user_id = user.id ",
        "JOIN ", @cenozo, ".user cuser ON user.name = cuser.name ",
        "JOIN site ON old.site_id = site.id ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE assignment_old;

      -- phone_call --------------------------------------------------------------------------------
      ALTER TABLE phone_call RENAME phone_call_old;
      ALTER TABLE phone_call_old
      DROP FOREIGN KEY fk_phone_call_assignment,
      DROP FOREIGN KEY fk_phone_call_phone_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS phone_call ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "assignment_id INT UNSIGNED NOT NULL, ",
          "phone_id INT UNSIGNED NOT NULL, ",
          "start_datetime DATETIME NOT NULL COMMENT 'The time the call started.', ",
          "end_datetime DATETIME NULL DEFAULT NULL COMMENT 'The time the call endede.', ",
          "status ENUM( 'contacted','busy','no answer','machine message','machine no message',",
                       "'fax','disconnected','wrong number','not reached','hang up','soft refusal' )",
                       " NULL DEFAULT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_assignment_id ( assignment_id ASC ), ",
          "INDEX dk_status ( status ASC ), ",
          "INDEX fk_phone_id ( phone_id ASC ), ",
          "INDEX dk_start_datetime ( start_datetime ASC ), ",
          "INDEX dk_end_datetime ( end_datetime ASC ), ",
          "CONSTRAINT fk_phone_call_assignment_id ",
            "FOREIGN KEY ( assignment_id ) ",
            "REFERENCES assignment ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_phone_call_phone_id ",
            "FOREIGN KEY ( phone_id ) ",
            "REFERENCES ", @cenozo, ".phone ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO phone_call ( id, update_timestamp, create_timestamp, assignment_id, ",
                                 "phone_id, start_datetime, end_datetime, status ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, old.assignment_id, ",
               "cphone.id, old.start_datetime, old.end_datetime, old.status ",
        "FROM phone_call_old old ",
        "JOIN phone ON old.phone_id = phone.id ",
        "JOIN participant ON phone.participant_id = participant.id ",
        "JOIN ", @cenozo, ".participant cparticipant ON participant.uid = cparticipant.uid ",
        "JOIN ", @cenozo, ".phone cphone ON phone.rank = cphone.rank ",
        "AND cparticipant.person_id = cphone.person_id" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE phone_call_old;

      -- assignment_note ---------------------------------------------------------------------------
      ALTER TABLE assignment_note RENAME assignment_note_old;
      ALTER TABLE assignment_note_old
      DROP FOREIGN KEY fk_assignment_note_assignment,
      DROP FOREIGN KEY fk_assignment_note_user;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS assignment_note ( ",
          "id INT NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "user_id INT UNSIGNED NOT NULL, ",
          "assignment_id INT UNSIGNED NOT NULL, ",
          "sticky TINYINT( 1 ) NOT NULL DEFAULT 0, ",
          "datetime DATETIME NOT NULL, ",
          "note TEXT NOT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_assignment_id ( assignment_id ASC ), ",
          "INDEX fk_user_id ( user_id ASC ), ",
          "INDEX dk_sticky_datetime ( sticky ASC, datetime ASC ), ",
          "CONSTRAINT fk_assignment_note_assignment_id ",
            "FOREIGN KEY ( assignment_id ) ",
            "REFERENCES assignment ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_assignment_note_user_id ",
            "FOREIGN KEY ( user_id ) ",
            "REFERENCES ", @cenozo, ".user ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO assignment_note( id, update_timestamp, create_timestamp, user_id, ",
                                     "assignment_id, sticky, datetime, note ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, cuser.id, ",
               "old.assignment_id, old.sticky, old.datetime, old.note ",
        "FROM assignment_note_old old ",
        "JOIN user ON old.user_id = user.id ",
        "JOIN ", @cenozo, ".user cuser ON user.name = cuser.name" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE assignment_note_old;

      -- appointment -------------------------------------------------------------------------------
      ALTER TABLE appointment RENAME appointment_old;
      ALTER TABLE appointment_old
      DROP FOREIGN KEY fk_appointment_address_id,
      DROP FOREIGN KEY fk_appointment_participant_id,
      DROP FOREIGN KEY fk_appointment_user_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS appointment ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "participant_id INT UNSIGNED NOT NULL, ",
          "user_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL for site appointments', ",
          "address_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL for site appointments', ",
          "datetime DATETIME NOT NULL, ",
          "completed TINYINT( 1 ) NOT NULL DEFAULT 0, ",
          "PRIMARY KEY ( id ), ",
          "INDEX dk_reached ( completed ASC ), ",
          "INDEX fk_address_id ( address_id ASC ), ",
          "INDEX fk_participant_id ( participant_id ASC ), ",
          "INDEX dk_datetime ( datetime ASC ), ",
          "INDEX fk_user_id ( user_id ASC ), ",
          "CONSTRAINT fk_appointment_address_id ",
            "FOREIGN KEY ( address_id ) ",
            "REFERENCES ", @cenozo, ".address ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_appointment_participant_id ",
            "FOREIGN KEY ( participant_id ) ",
            "REFERENCES ", @cenozo, ".participant ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_appointment_user_id ",
            "FOREIGN KEY ( user_id ) ",
            "REFERENCES ", @cenozo, ".user ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO appointment( id, update_timestamp, create_timestamp, participant_id, ",
                                 "user_id, address_id, datetime, completed ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, cparticipant.id, ",
               "cuser.id, caddress.id, old.datetime, old.completed ",
        "FROM appointment_old old ",
        "JOIN participant ON old.participant_id = participant.id ",
        "JOIN ", @cenozo, ".participant cparticipant ON participant.uid = cparticipant.uid ",
        "JOIN user ON old.user_id = user.id ",
        "JOIN ", @cenozo, ".user cuser ON user.id = cuser.id ",
        "JOIN address ON old.address_id = address.id ",
        "JOIN ", @cenozo, ".address caddress ON address.rank = caddress.rank ",
        "AND cparticipant.person_id = caddress.person_id" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE appointment_old;

      -- queue_restriction --------------------------------------------------------------------------------
      ALTER TABLE queue_restriction RENAME queue_restriction_old;
      ALTER TABLE queue_restriction_old
      DROP FOREIGN KEY fk_queue_restriction_region,
      DROP FOREIGN KEY fk_queue_restriction_site;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS queue_restriction ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "site_id INT UNSIGNED NULL DEFAULT NULL, ",
          "city VARCHAR( 100 ) NULL DEFAULT NULL, ",
          "region_id INT UNSIGNED NULL DEFAULT NULL, ",
          "postcode VARCHAR( 10 ) NULL DEFAULT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_region_id ( region_id ASC ), ",
          "INDEX fk_site_id ( site_id ASC ), ",
          "INDEX dk_city ( city ASC ), ",
          "INDEX dk_postcode ( postcode ASC ), ",
          "CONSTRAINT fk_queue_restriction_region_id ",
            "FOREIGN KEY ( region_id ) ",
            "REFERENCES ", @cenozo, ".region ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_queue_restriction_site_id ",
            "FOREIGN KEY ( site_id ) ",
            "REFERENCES ", @cenozo, ".site ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO queue_restriction( id, update_timestamp, create_timestamp, ",
                                       "site_id, city, region_id, postcode ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, ",
               "csite.id, old.city, cregion.id, old.postcode ",
        "FROM queue_restriction_old old ",
        "JOIN region ON old.region_id = region.id ",
        "JOIN ", @cenozo, ".region cregion ON region.name = cregion.name ",
        "JOIN site ON old.site_id = site.id ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE queue_restriction_old;

      -- onyx_instance -----------------------------------------------------------------------------
      ALTER TABLE onyx_instance RENAME onyx_instance_old;
      ALTER TABLE onyx_instance_old
      DROP FOREIGN KEY fk_onyx_instance_interview_user_id,
      DROP FOREIGN KEY fk_onyx_instance_site_id,
      DROP FOREIGN KEY fk_onyx_instance_user_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS onyx_instance ( ",
          "id INT NOT NULL , ",
          "update_timestamp TIMESTAMP NOT NULL , ",
          "create_timestamp TIMESTAMP NOT NULL , ",
          "site_id INT UNSIGNED NOT NULL , ",
          "user_id INT UNSIGNED NOT NULL , ",
          "interviewer_user_id INT UNSIGNED NULL DEFAULT NULL , ",
          "PRIMARY KEY (id) , ",
          "INDEX fk_site_id (site_id ASC) , ",
          "INDEX fk_user_id (user_id ASC) , ",
          "INDEX fk_interviewer_user_id (interviewer_user_id ASC) , ",
          "UNIQUE INDEX uq_user_id (user_id ASC) , ",
          "CONSTRAINT fk_onyx_instance_site_id ",
            "FOREIGN KEY (site_id ) ",
            "REFERENCES ", @cenozo, ".site (id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_onyx_instance_user_id ",
            "FOREIGN KEY (user_id ) ",
            "REFERENCES ", @cenozo, ".user (id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_onyx_instance_interviewer_user_id ",
            "FOREIGN KEY (interviewer_user_id ) ",
            "REFERENCES ", @cenozo, ".user (id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO onyx_instance( id, update_timestamp, create_timestamp, ",
                                   "site_id, user_id, interviewer_user_id ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, ",
               "csite.id, cuser.id, cinterviewer_user.id ",
        "FROM onyx_instance_old old ",
        "JOIN site ON old.site_id = site.id ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' ) ",
        "JOIN user ON old.user_id = user.id ",
        "JOIN ", @cenozo, ".user cuser ON user.name = cuser.name ",
        "JOIN user interviewer_user ON old.interviewer_user_id = interviewer_user.id ",
        "JOIN ", @cenozo, ".user cinterviewer_user ON interviewer_user.name = cinterviewer_user.name" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE onyx_instance_old;

      -- callback ----------------------------------------------------------------------------------
      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS callback ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "participant_id INT UNSIGNED NOT NULL, ",
          "phone_id INT UNSIGNED NULL DEFAULT NULL, ",
          "assignment_id INT UNSIGNED NULL DEFAULT NULL, ",
          "datetime DATETIME NOT NULL, ",
          "reached TINYINT( 1 ) NULL DEFAULT NULL ",
          "COMMENT 'If the callback was met, whether the participant was reached.', ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_participant_id ( participant_id ASC ), ",
          "INDEX fk_assignment_id ( assignment_id ASC ), ",
          "INDEX dk_reached ( reached ASC ), ",
          "INDEX fk_phone_id ( phone_id ASC ), ",
          "INDEX dk_datetime ( datetime ASC ), ",
          "CONSTRAINT fk_callback_participant_id ",
            "FOREIGN KEY ( participant_id ) ",
            "REFERENCES ", @cenozo, ".participant ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_callback_assignment_id ",
            "FOREIGN KEY ( assignment_id ) ",
            "REFERENCES assignment ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_callback_phone_id ",
            "FOREIGN KEY ( phone_id ) ",
            "REFERENCES ", @cenozo, ".phone ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- convert participant.defer_until into a callback -------------------------------------------
      SET @sql = CONCAT(
        "INSERT INTO callback ( create_timestamp, participant_id, datetime ) ",
        "SELECT NULL, cparticipant.id, CONCAT( participant.defer_until, ' 14:00:00' ) ",
        "FROM participant ",
        "JOIN ", @cenozo, ".participant cparticipant ON cparticipant.uid = participant.uid ",
        "WHERE participant.defer_until >= DATE( NOW() )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- setting_value -----------------------------------------------------------------------------
      ALTER TABLE setting_value RENAME setting_value_old;
      ALTER TABLE setting_value_old
      DROP FOREIGN KEY fk_setting_value_setting_id,
      DROP FOREIGN KEY fk_setting_value_site_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS setting_value ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "setting_id INT UNSIGNED NOT NULL, ",
          "site_id INT UNSIGNED NOT NULL, ",
          "value VARCHAR( 45 ) NOT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_site_id ( site_id ASC ), ",
          "UNIQUE INDEX uq_setting_id_site_id ( setting_id ASC, site_id ASC ), ",
          "INDEX fk_setting_id ( setting_id ASC ), ",
          "CONSTRAINT fk_setting_value_site_id ",
            "FOREIGN KEY ( site_id ) ",
            "REFERENCES ", @cenozo, ".site ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_setting_value_setting_id ",
            "FOREIGN KEY ( setting_id ) ",
            "REFERENCES setting ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB ",
        "COMMENT = 'Site-specific setting overriding the default.'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO setting_value( id, update_timestamp, create_timestamp, setting_id, site_id, value ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, old.setting_id, csite.id, old.value ",
        "FROM setting_value_old old ",
        "JOIN site ON old.site_id = site.id ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE setting_value_old;
      
      -- operation ---------------------------------------------------------------------------------
      ALTER TABLE operation MODIFY COLUMN type ENUM( 'push','pull','widget' ) NOT NULL;
      
      -- activity ----------------------------------------------------------------------------------
      ALTER TABLE activity RENAME activity_old;
      ALTER TABLE activity_old
      DROP FOREIGN KEY fk_activity_operation_id,
      DROP FOREIGN KEY fk_activity_role_id,
      DROP FOREIGN KEY fk_activity_site_id,
      DROP FOREIGN KEY fk_activity_user_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS activity ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "user_id INT UNSIGNED NOT NULL, ",
          "site_id INT UNSIGNED NOT NULL, ",
          "role_id INT UNSIGNED NOT NULL, ",
          "operation_id INT UNSIGNED NOT NULL, ",
          "query VARCHAR( 511 ) NOT NULL, ",
          "elapsed FLOAT NOT NULL DEFAULT 0 ",
          "COMMENT 'The total time to perform the operation in seconds.', ",
          "error_code VARCHAR( 20 ) NULL DEFAULT '(incomplete)' ",
          "COMMENT 'NULL if no error occurred.', ",
          "datetime DATETIME NOT NULL, ",
          "PRIMARY KEY ( id ), ",
          "INDEX fk_user_id ( user_id ASC ), ",
          "INDEX fk_role_id ( role_id ASC ), ",
          "INDEX fk_site_id ( site_id ASC ), ",
          "INDEX fk_operation_id ( operation_id ASC ), ",
          "INDEX dk_datetime ( datetime ASC ), ",
          "CONSTRAINT fk_activity_user_id ",
            "FOREIGN KEY ( user_id ) ",
            "REFERENCES ", @cenozo, ".user ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_activity_role_id ",
            "FOREIGN KEY ( role_id ) ",
            "REFERENCES ", @cenozo, ".role ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_activity_site_id ",
            "FOREIGN KEY ( site_id ) ",
            "REFERENCES ", @cenozo, ".site ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_activity_operation_id ",
            "FOREIGN KEY ( operation_id ) ",
            "REFERENCES operation ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO activity( id, update_timestamp, create_timestamp, user_id, site_id, role_id, ",
                              "operation_id, query, elapsed, error_code, datetime ) ",
        "SELECT old.id, old.update_timestamp, old.create_timestamp, cuser.id, csite.id, crole.id, ",
               "old.operation_id, old.query, old.elapsed, old.error_code, old.datetime ",
        "FROM activity_old old ",
        "JOIN user ON old.user_id = user.id ",
        "JOIN ", @cenozo, ".user cuser ON user.name = cuser.name ",
        "JOIN role ON old.role_id = role.id ",
        "JOIN ", @cenozo, ".role crole ON role.name = crole.name ",
        "JOIN site ON old.site_id = site.id ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE activity_old;
      
      -- role_has_operation ------------------------------------------------------------------------
      ALTER TABLE role_has_operation RENAME role_has_operation_old;
      ALTER TABLE role_has_operation_old
      DROP FOREIGN KEY fk_role_has_operation_operation_id,
      DROP FOREIGN KEY fk_role_has_operation_role_id;

      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS role_has_operation ( ",
          "role_id INT UNSIGNED NOT NULL, ",
          "operation_id INT UNSIGNED NOT NULL, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "PRIMARY KEY ( role_id, operation_id ), ",
          "INDEX fk_operation_id ( operation_id ASC ), ",
          "INDEX fk_role_id ( role_id ASC ), ",
          "CONSTRAINT fk_role_has_operation_role_id ",
            "FOREIGN KEY ( role_id ) ",
            "REFERENCES ", @cenozo, ".role ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_role_has_operation_operation_id ",
            "FOREIGN KEY ( operation_id ) ",
            "REFERENCES operation ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id, update_timestamp, create_timestamp ) ",
        "SELECT crole.id, old.operation_id, old.update_timestamp, old.create_timestamp ",
        "FROM role_has_operation_old old ",
        "JOIN role ON old.role_id = role.id ",
        "JOIN ", @cenozo, ".role crole ON role.name = crole.name" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      DROP TABLE role_has_operation_old;

      -- site_voip ---------------------------------------------------------------------------------
      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS site_voip ( ",
          "site_id INT UNSIGNED NOT NULL , ",
          "host VARCHAR( 45 ) NULL DEFAULT NULL , ",
          "xor_key VARCHAR( 45 ) NULL DEFAULT NULL , ",
          "PRIMARY KEY ( site_id ) , ",
          "INDEX fk_site_id ( site_id ASC ) , ",
          "CONSTRAINT fk_site_voip_site_id ",
            "FOREIGN KEY ( site_id ) ",
            "REFERENCES ", @cenozo, ".site ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB " );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      SET @sql = CONCAT(
        "INSERT INTO site_voip( site_id, host, xor_key ) ",
        "SELECT csite.id, site.voip_host, site.voip_xor_key ",
        "FROM site ",
        "JOIN ", @cenozo, ".site csite ON site.name = csite.name ",
        "AND csite.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Beartooth' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- next_of_kin -------------------------------------------------------------------------------
      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS next_of_kin ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT , ",
          "update_timestamp VARCHAR( 45 ) NULL , ",
          "create_timestamp VARCHAR( 45 ) NULL , ",
          "participant_id INT UNSIGNED NOT NULL , ",
          "first_name VARCHAR( 45 ) NULL , ",
          "last_name VARCHAR( 45 ) NULL , ",
          "gender VARCHAR( 10 ) NULL , ",
          "phone VARCHAR( 100 ) NULL , ",
          "street VARCHAR( 255 ) NULL , ",
          "city VARCHAR( 100 ) NULL , ",
          "province VARCHAR( 45 ) NULL , ",
          "postal_code VARCHAR( 45 ) NULL , ",
          "PRIMARY KEY ( id ) , ",
          "INDEX fk_participant_id ( participant_id ASC ) , ",
          "UNIQUE INDEX uq_participant_id ( participant_id ASC ) , ",
          "CONSTRAINT fk_next_of_kin_participant_id ",
            "FOREIGN KEY ( participant_id ) ",
            "REFERENCES ", @cenozo, ".participant ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- copy data from participant table to next_of_kin -------------------------------------------
      SET @sql = CONCAT(
        "INSERT INTO next_of_kin ( create_timestamp, participant_id, first_name, last_name, ",
                                  "gender, phone, street, city, province, postal_code ) ",
        "SELECT NULL, cparticipant.id, participant.next_of_kin_first_name, ",
               "participant.next_of_kin_last_name, participant.next_of_kin_gender, ",
               "participant.next_of_kin_phone, participant.next_of_kin_street, ",
               "participant.next_of_kin_city, participant.next_of_kin_province, ".
               "participant.next_of_kin_postal_code ",
        "FROM participant ",
        "JOIN ", @cenozo, ".participant cparticipant ON cparticipant.uid = participant.uid ",
        "WHERE participant.next_of_kin_first_name IS NOT NULL" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- data_collection ---------------------------------------------------------------------------
      SET @sql = CONCAT(
        "CREATE  TABLE IF NOT EXISTS data_collection ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT , ",
          "update_timestamp TIMESTAMP NULL , ",
          "create_timestamp TIMESTAMP NULL , ",
          "participant_id INT UNSIGNED NOT NULL , ",
          "draw_blood TINYINT( 1 ) NULL DEFAULT NULL , ",
          "draw_blood_continue TINYINT( 1 ) NULL DEFAULT NULL , ",
          "physical_tests_continue TINYINT( 1 ) NULL DEFAULT NULL , ",
          "PRIMARY KEY ( id ) , ",
          "INDEX fk_participant_id ( participant_id ASC) , ",
          "UNIQUE INDEX uq_participant_id ( participant_id ASC) , ",
          "CONSTRAINT fk_data_collection_participant_id ",
            "FOREIGN KEY ( participant_id ) ",
            "REFERENCES ", @cenozo, ".participant ( id ) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION ) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- copy data from participant table to data_collection ---------------------------------------
      SET @sql = CONCAT(
        "INSERT INTO data_collection ( create_timestamp, participant_id, draw_blood, ",
                                      "draw_blood_continue, physical_tests_continue ) ",
        "SELECT NULL, cparticipant.id, "
               "IF( participant.consent_to_draw_blood IS NULL, NULL, participant.consent_to_draw_blood = 1 ), ",
               "participant.consent_to_draw_blood_continue, participant.physical_tests_continue ",
        "FROM participant ",
        "JOIN ", @cenozo, ".participant cparticipant ON cparticipant.uid = participant.uid ",
        "WHERE participant.consent_to_draw_blood IS NOT NULL ",
        "OR participant.consent_to_draw_blood_continue IS NOT NULL ",
        "OR participant.physical_tests_continue IS NOT NULL" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- participant_last_appointment --------------------------------------------------------------
      DROP VIEW participant_last_appointment;
      SET @sql = CONCAT(
        "CREATE VIEW participant_last_appointment AS ",
        "SELECT participant.id AS participant_id, t1.id AS appointment_id, t1.completed ",
        "FROM ", @cenozo, ".participant ",
        "LEFT JOIN appointment t1 ",
        "ON participant.id = t1.participant_id ",
        "AND t1.datetime = ( ",
          "SELECT MAX( t2.datetime ) FROM appointment t2 ",
          "WHERE t1.participant_id = t2.participant_id ) ",
        "GROUP BY participant.id" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      -- drop tables which have been moved to the @cenozo database
      DROP TABLE access;
      DROP TABLE phone;
      DROP VIEW participant_first_address;
      DROP VIEW participant_primary_address;
      DROP TABLE address;
      DROP TABLE availability;
      DROP TABLE consent;
      DROP VIEW participant_last_consent;
      DROP VIEW participant_last_written_consent;
      DROP VIEW participant_site;
      DROP TABLE participant;
      DROP TABLE quota;
      DROP TABLE age_group;
      DROP TABLE participant_note;
      DROP TABLE postcode;
      DROP TABLE region;
      DROP TABLE source;
      DROP TABLE system_message;
      DROP TABLE jurisdiction;
      DROP TABLE user;
      DROP TABLE role;
      DROP TABLE site;

      -- add the new operations --------------------------------------------------------------------
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "alternate", "delete", true, "Removes an alternate contact person from the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "alternate", "edit", true, "Edits an alternate contact person's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "alternate", "new", true, "Add a new alternate contact person to the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "alternate", "add", true, "View a form for creating a new alternate contact person." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "alternate", "view", true, "View an alternate contact person's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "alternate", "list", true, "List alternate contact persons in the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "alternate", "add_address", true, "A form to create a new address entry to add to an alternate contact person." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "alternate", "delete_address", true, "Remove an alternate contact person's address entry." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "alternate", "add_phone", true, "A form to create a new phone entry to add to an alternate contact person." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "alternate", "delete_phone", true, "Remove an alternate contact person's phone entry." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "pull", "alternate", "primary", true, "Retrieves base alternate contact person information." );

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id ) ",
        "SELECT crole.id, operation.id ",
        "FROM operation, ", @cenozo, ".role crole ",
        "JOIN ", @cenozo, ".service_has_role cservice_has_role ON crole.id = cservice_has_role.role_id ",
        "AND cservice_has_role.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Sabretooth' )",
        "WHERE operation.subject = 'alternate'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "cohort", "delete", true, "Removes a cohort from the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "cohort", "edit", true, "Edits a cohort's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "cohort", "new", true, "Add a new cohort to the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "cohort", "add", true, "View a form for creating a new cohort." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "cohort", "view", true, "View a cohort's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "cohort", "list", true, "List cohorts in the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "pull", "cohort", "primary", true, "Retrieves base cohort information." );

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id ) ",
        "SELECT crole.id, operation.id ",
        "FROM operation, ", @cenozo, ".role crole ",
        "WHERE crole.name = 'Administrator' ",
        "AND operation.subject = 'cohort'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "participant", "add_alternate", true, "A form to create a new alternate contact to add to a participant." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "participant", "delete_alternate", true, "Remove a participant's alternate contact." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "pull", "participant", "list_alternate", true, "Retrieves a list of a participant's alternates." );

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id ) ",
        "SELECT crole.id, operation.id ",
        "FROM operation, ", @cenozo, ".role crole ",
        "JOIN ", @cenozo, ".service_has_role cservice_has_role ON crole.id = cservice_has_role.role_id ",
        "AND cservice_has_role.service_id = ( SELECT id FROM ", @cenozo, ".service WHERE name = 'Sabretooth' )",
        "WHERE operation.subject = 'participant' ",
        "AND operation.name LIKE '%_alternate'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "participant", "site_reassign", true, "A form to mass reassign the preferred site of multiple participants at once." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "participant", "site_reassign", true, "Updates the preferred site of a group of participants." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "participant", "multinote", true, "A form to add a note to multiple participants at once." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "participant", "multinote", true, "Adds a note to a group of participants." );

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id ) ",
        "SELECT crole.id, operation.id ",
        "FROM operation, ", @cenozo, ".role crole ",
        "WHERE crole.name = 'Administrator' ",
        "AND operation.subject = 'participant' ",
        "AND operation.name IN ( 'site_reassign', 'multinote' )" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "service", "delete", true, "Removes a service from the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "service", "edit", true, "Edits a service's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "service", "new", true, "Add a new service to the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "service", "add", true, "View a form for creating a new service." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "service", "view", true, "View a service's details." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "service", "list", true, "List services in the system." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "pull", "service", "primary", true, "Retrieves base service information." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "widget", "service", "add_cohort", true, "A form to create a new cohort to add to a service." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "service", "new_cohort", true, "Add a cohort to a service." );
      INSERT INTO operation( type, subject, name, restricted, description )
      VALUES( "push", "service", "delete_cohort", true, "Remove a service's cohort." );

      SET @sql = CONCAT(
        "INSERT INTO role_has_operation( role_id, operation_id ) ",
        "SELECT crole.id, operation.id ",
        "FROM operation, ", @cenozo, ".role crole ",
        "WHERE crole.name = 'Administrator' ",
        "AND operation.subject = 'service'" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

    END IF;
  END //
DELIMITER ;

-- now call the procedure and remove the procedure
CALL convert_database();
DROP PROCEDURE IF EXISTS convert_database;
