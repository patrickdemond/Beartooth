<?php
/**
 * error_codes.inc.php
 * 
 * This file is where all error codes are defined.
 * All error code are named after the class and function they occur in.
 */

/**
 * Error number category defines.
 */
define( 'ARGUMENT_BEARTOOTH_BASE_ERRNO',   160000 );
define( 'DATABASE_BEARTOOTH_BASE_ERRNO',   260000 );
define( 'NOTICE_BEARTOOTH_BASE_ERRNO',     460000 );
define( 'PERMISSION_BEARTOOTH_BASE_ERRNO', 560000 );
define( 'RUNTIME_BEARTOOTH_BASE_ERRNO',    660000 );
define( 'SYSTEM_BEARTOOTH_BASE_ERRNO',     760000 );
define( 'VOIP_BEARTOOTH_BASE_ERRNO',       960000 );

/**
 * "argument" error codes
 */
define( 'ARGUMENT__BEARTOOTH_BUSINESS_DATA_MANAGER__GET_PARTICIPANT_VALUE__ERRNO',
        ARGUMENT_BEARTOOTH_BASE_ERRNO + 1 );
define( 'ARGUMENT__BEARTOOTH_DATABASE_QUEUE__PREPARE_QUEUE_QUERY__ERRNO',
        ARGUMENT_BEARTOOTH_BASE_ERRNO + 2 );

/**
 * "database" error codes
 * 
 * Since database errors already have codes this list is likely to stay empty.
 */

/**
 * "notice" error codes
 */
define( 'NOTICE__BEARTOOTH_BUSINESS_CANTAB_MANAGER__ON_RESPONSE_ERROR__ERRNO',
        NOTICE_BEARTOOTH_BASE_ERRNO + 1 );
define( 'NOTICE__BEARTOOTH_BUSINESS_REPORT_APPOINTMENT__BUILD__ERRNO',
        NOTICE_BEARTOOTH_BASE_ERRNO + 2 );
define( 'NOTICE__BEARTOOTH_DATABASE_APPOINTMENT__SAVE__ERRNO',
        NOTICE_BEARTOOTH_BASE_ERRNO + 3 );
define( 'NOTICE__BEARTOOTH_SERVICE_INTERVIEW_APPOINTMENT_POST__EXECUTE__ERRNO',
        NOTICE_BEARTOOTH_BASE_ERRNO + 4 );
define( 'NOTICE__BEARTOOTH_SERVICE_INTERVIEWING_INSTANCE_POST__FINISH__ERRNO',
        NOTICE_BEARTOOTH_BASE_ERRNO + 5 );

/**
 * "permission" error codes
 */

/**
 * "runtime" error codes
 */
define( 'RUNTIME__BEARTOOTH_BUSINESS_CANTAB_MANAGER____CONSTRUCT__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 1 );
define( 'RUNTIME__BEARTOOTH_BUSINESS_CANTAB_MANAGER__ADD_PARTICIPANT__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 2 );
define( 'RUNTIME__BEARTOOTH_DATABASE_QUEUE__REPOPULATE_TIME__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 3 );
define( 'RUNTIME__BEARTOOTH_DATABASE_QUEUE__PREPARE_QUEUE_QUERY__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 4 );
define( 'RUNTIME__BEARTOOTH_SERVICE_APPOINTMENT_MODULE__PREPARE_READ__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 5 );
define( 'RUNTIME__BEARTOOTH_SERVICE_ONYX_POST__PROCESS_CONSENT__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 6 );
define( 'RUNTIME__BEARTOOTH_SERVICE_ONYX_POST__PROCESS_HIN__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 7 );
define( 'RUNTIME__BEARTOOTH_SERVICE_ONYX_POST__PROCESS_EXTENDED_HIN__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 8 );
define( 'RUNTIME__BEARTOOTH_SERVICE_ONYX_POST__PROCESS_PARTICIPANT__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 9 );
define( 'RUNTIME__BEARTOOTH_SERVICE_PINE_POST__EXECUTE__ERRNO',
        RUNTIME_BEARTOOTH_BASE_ERRNO + 10 );

/**
 * "system" error codes
 * 
 * Since system errors already have codes this list is likely to stay empty.
 * Note the following PHP error codes:
 *      1: error,
 *      2: warning,
 *      4: parse,
 *      8: notice,
 *     16: core error,
 *     32: core warning,
 *     64: compile error,
 *    128: compile warning,
 *    256: user error,
 *    512: user warning,
 *   1024: user notice
 */

/**
 * "voip" error codes
 */

