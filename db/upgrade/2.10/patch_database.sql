-- Patch to upgrade database to version 2.10

SET AUTOCOMMIT=0;

SOURCE appointment_type.sql

SOURCE update_version_number.sql

COMMIT;
