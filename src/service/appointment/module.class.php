<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace beartooth\service\appointment;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\base_calendar_module
{
  /**
   * Extend parent method
   */
  protected function get_argument( $name, $default = NULL )
  {
    $session = lib::create( 'business\session' );
    $db_site = $session->get_site();
    $db_role = $session->get_role();

    // return specific values for min_date and max_date for the interviewing role
    if( 'min_date' == $name && 'machine' == $db_role->name )
    {
      return util::get_datetime_object()->format( 'Y-m-d' );
    }
    else if( 'max_date' == $name && 'machine' == $db_role->name )
    {
      $db_setting = $db_site->get_setting();
      $date_obj = util::get_datetime_object();
      $date_obj->add( new \DateInterval( sprintf( 'P%dD', $db_setting->appointment_update_span ) ) );
      return $date_obj->format( 'Y-m-d' );
    }

    return parent::get_argument( $name, $default );
  }

  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->service->may_continue() )
    {
      $service_class_name = lib::get_class_name( 'service\service' );
      $db_appointment = $this->get_resource();
      $db_interview = is_null( $db_appointment ) ? $this->get_parent_resource() : $db_appointment->get_interview();
      $method = $this->get_method();

      $db_application = lib::create( 'business\session' )->get_application();

      // make sure the application has access to the participant
      if( !is_null( $db_appointment ) )
      {
        $db_participant = $db_interview->get_participant();
        if( $db_application->release_based )
        {
          $modifier = lib::create( 'database\modifier' );
          $modifier->where( 'participant_id', '=', $db_participant->id );
          if( 0 == $db_application->get_participant_count( $modifier ) )
          {
            $this->get_status()->set_code( 404 );
            return;
          }
        }

        // restrict by site
        $db_restrict_site = $this->get_restricted_site();
        if( !is_null( $db_restrict_site ) )
        {
          $db_effective_site = $db_participant->get_effective_site();
          if( is_null( $db_effective_site ) || $db_restrict_site->id != $db_effective_site->id )
          {
            $this->get_status()->set_code( 403 );
            return;
          }
        }

        // we don't restrict by role here since tier-1 roles need to see other people's appointments
      }

      if( $service_class_name::is_write_method( $method ) )
      {
        // no writing of appointments if interview is completed
        if( !is_null( $db_interview ) && !is_null( $db_interview->end_datetime ) )
        {
          $this->set_data( 'Appointments cannot be changed after an interview is complete.' );
          $this->get_status()->set_code( 306 );
        }
        // no writing passed appointments (except to cancel them)
        else if( !is_null( $db_appointment ) && $db_appointment->datetime < util::get_datetime_object() )
        {
          $allow = false;
          if( 'PATCH' == $method )
          {
            $data = $this->get_file_as_array();

            $allow =
              'passed' == $db_appointment->get_state() &&
              1 == count( $data ) && (
                // We may be cancelling a passed appointment
                array_key_exists( 'outcome', $data ) &&
                'cancelled' == $data['outcome']
              ) || (
                // Or we may be rescheduling a passed appointment by changing its datetime
                // (note that in this case the patch service will cancel this appointment and create a new one)
                array_key_exists( 'datetime', $data )
              );
          }

          if( !$allow )
          {
            $this->set_data( 'Appointments cannot be changed after they have passed.' );
            $this->get_status()->set_code( 306 );
          }
        }
        else
        {
          // make sure mandatory scripts have been submitted before allowing a new appointment
          if( 'POST' == $method && !$db_appointment->are_scripts_complete() )
          {
            $this->set_data(
              'An appointment cannot be made for this participant until '.
              'all mandatory scripts have been submitted.' );
            $this->get_status()->set_code( 306 );
          }
          // validate if we are changing the datetime
          if( 'POST' == $method ||
              ( 'PATCH' == $method && array_key_exists( 'datetime', $this->get_file_as_array() ) ) )
          {
            if( !$db_appointment->validate_date() )
            {
              $this->set_data( 'An appointment cannot currently be made for this participant.' );
              $this->get_status()->set_code( 306 );
            }
          }
        }
      }
    }
  }

  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    $session = lib::create( 'business\session' );
    $db_application = $session->get_application();
    $db_user = $session->get_user();
    $db_site = $session->get_site();
    $db_role = $session->get_role();

    // make sure to define the lower and upper date before calling the parent method
    $date_string = sprintf( 'DATE( CONVERT_TZ( appointment.datetime, "UTC", "%s" ) )', $db_user->timezone );
    $this->lower_date = array( 'null' => false, 'column' => $date_string );
    $this->upper_date = array( 'null' => false, 'column' => $date_string );

    parent::prepare_read( $select, $modifier );

    $modifier->left_join( 'appointment_type', 'appointment.appointment_type_id', 'appointment_type.id' );
    $modifier->join( 'interview', 'appointment.interview_id', 'interview.id' );
    $modifier->join( 'participant', 'interview.participant_id', 'participant.id' );
    $modifier->join( 'qnaire', 'interview.qnaire_id', 'qnaire.id' );

    $participant_site_join_mod = lib::create( 'database\modifier' );
    $participant_site_join_mod->where(
      'interview.participant_id', '=', 'participant_site.participant_id', false );
    $participant_site_join_mod->where(
      'participant_site.application_id', '=', $db_application->id );
    $modifier->join_modifier( 'participant_site', $participant_site_join_mod, 'left' );

    if( $select->has_table_columns( 'effective_site' ) )
      $modifier->join( 'site', 'participant_site.site_id', 'effective_site.id', 'left', 'effective_site' );

    if( $select->has_column( 'disable_mail' ) ) $select->add_constant( false, 'disable_mail', 'boolean' );
    $modifier->group( 'appointment.id' );

    // interviewing roles need to be treated specially
    if( 'machine' == $db_role->name )
    {
      // always include all appointments
      $modifier->limit( 10000000 );

      // never include appointments with an outcome
      $modifier->where( 'appointment.outcome', '=', NULL );

      $interviewing_instance_class_name = lib::create( 'database\interviewing_instance' );
      $appointment_type_class_name = lib::create( 'database\appointment_type' );
      $form_type_class_name = lib::create( 'database\form_type' );
      $identifier_class_name = lib::create( 'database\identifier' );
      $study_class_name = lib::create( 'database\study' );
      $stratum_class_name = lib::create( 'database\stratum' );

      $db_interviewing_instance = $interviewing_instance_class_name::get_unique_record( 'user_id', $db_user->id );
      if( is_null( $db_interviewing_instance ) )
        throw lib::create( 'exception\runtime',
          sprintf( 'Tried to get appointment list for interviewing instance user "%s" that has no interviewing instance record.',
                   $db_user->name ),
          __METHOD__ );
      $db_interviewer_user = $db_interviewing_instance->get_interviewer_user();

      // add specific columns
      $select->remove_column();
      $select->add_table_column( 'participant', 'uid' );
      $select->add_table_column( 'appointment', 'datetime' );
      $select->add_table_column( 'cohort', 'name', 'cohort' );
      $select->add_table_column( 'language', 'code', 'language' );
      $select->add_table_column( 'participant', 'honorific' );
      $select->add_table_column( 'participant', 'first_name' );
      $select->add_column(
        'IFNULL( participant.other_name, "" )',
        'onyx' == $db_interviewing_instance->type ? 'otherName' : 'other_name',
        false
      );
      $select->add_table_column( 'participant', 'last_name' );
      $select->add_column(
        'IFNULL( participant.date_of_birth, "" )',
        'onyx' == $db_interviewing_instance->type ? 'dob' : 'date_of_birth',
        false
      );
      $select->add_table_column( 'participant', 'sex', 'onyx' == $db_interviewing_instance->type ? 'gender' : 'sex' );
      if( 'onyx' != $db_interviewing_instance->type ) $select->add_table_column( 'participant', 'current_sex' );
      $select->add_column( 'datetime' );
      $select->add_table_column( 'address', 'address1', 'onyx' == $db_interviewing_instance->type ? 'street' : 'address1' );
      if( 'onyx' != $db_interviewing_instance->type ) $select->add_table_column( 'address', 'address2' );
      $select->add_table_column( 'address', 'city' );
      $select->add_table_column( 'region', 'name', 'onyx' == $db_interviewing_instance->type ? 'province' : 'region' );
      $select->add_table_column( 'address', 'postcode' );
      if( 'onyx' != $db_interviewing_instance->type )
      {
        $select->add_table_column( 'address', 'international' );
        $select->add_table_column( 'address', 'international_region' );
        $select->add_table_column( 'address', 'international_country_id' );
        $select->add_table_column( 'address', 'timezone_offset' );
        $select->add_table_column( 'address', 'daylight_savings' );
        $select->add_table_column( 'address', 'january' );
        $select->add_table_column( 'address', 'february' );
        $select->add_table_column( 'address', 'march' );
        $select->add_table_column( 'address', 'april' );
        $select->add_table_column( 'address', 'may' );
        $select->add_table_column( 'address', 'june' );
        $select->add_table_column( 'address', 'july' );
        $select->add_table_column( 'address', 'august' );
        $select->add_table_column( 'address', 'september' );
        $select->add_table_column( 'address', 'october' );
        $select->add_table_column( 'address', 'november' );
        $select->add_table_column( 'address', 'december' );
        $select->add_table_column( 'address', 'note', 'address_note' );
      }

      $select->add_table_column( 'participant', 'IFNULL( email, "" )', 'email', false );
      if( 'onyx' == $db_interviewing_instance->type )
      {
        $select->add_column(
          'IF( 70 <= TIMESTAMPDIFF( YEAR, date_of_birth, CURDATE() ) AND proxy_form.total = 0, 1, 0 )',
          'ask_proxy',
          false
        );
      }
      else
      {
        $select->add_table_column( 'participant', 'override_stratum' );
        $select->add_table_column( 'participant', 'mass_email' );
        $select->add_table_column( 'participant', 'delink' );
        $select->add_table_column( 'participant', 'withdraw_third_party' );
        $select->add_table_column( 'participant', 'out_of_area' );
        $select->add_table_column( 'participant', 'low_education' );
      }

      $modifier->join( 'cohort', 'participant.cohort_id', 'cohort.id' );
      $modifier->join( 'language', 'participant.language_id', 'language.id' );

      // make sure the participant has consented to participate
      $modifier->join( 'participant_last_consent', 'participant.id', 'participant_last_consent.participant_id' );
      $modifier->join( 'consent_type', 'participant_last_consent.consent_type_id', 'consent_type.id' );
      $modifier->join( 'consent', 'participant_last_consent.consent_id', 'consent.id' );
      $modifier->where( 'consent_type.name', '=', 'participation' );
      $modifier->where( 'consent.accept', '=', true );

      // link to the primary address
      $modifier->join(
        'participant_primary_address', 'participant.id', 'participant_primary_address.participant_id' );
      $modifier->left_join( 'address', 'participant_primary_address.address_id', 'address.id' );
      $modifier->left_join( 'region', 'address.region_id', 'region.id' );
      $modifier->where( 'participant_site.site_id', '=', $db_site->id );

      // link to the number of proxy forms the participant has (which may be zero)
      $proxy_form_type_id_list = array();
      $form_type_sel = lib::create( 'database\select' );
      $form_type_sel->add_column( 'id' );
      $form_type_mod = lib::create( 'database\modifier' );
      $form_type_mod->where( 'name', 'IN', array( 'proxy', 'general_proxy' ) );
      foreach( $form_type_class_name::select( $form_type_sel, $form_type_mod ) as $form_type )
        $proxy_form_type_id_list[] = $form_type['id'];

      $proxy_form_sel = lib::create( 'database\select' );
      $proxy_form_sel->add_column( 'id', 'participant_id' );
      $proxy_form_sel->add_column( 'IF( form.id IS NULL, 0, COUNT(*) )', 'total', false );
      $proxy_form_sel->from( 'participant' );

      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'participant.id', '=', 'form.participant_id', false );
      $join_mod->where( 'form.form_type_id', 'IN', $proxy_form_type_id_list );
      $proxy_form_mod = lib::create( 'database\modifier' );
      $proxy_form_mod->join_modifier( 'form', $join_mod, 'left' );
      $proxy_form_mod->group( 'participant.id' );

      $modifier->join(
        sprintf( '( %s %s ) AS proxy_form', $proxy_form_sel->get_sql(), $proxy_form_mod->get_sql() ),
        'participant.id',
        'proxy_form.participant_id'
      );

      if( !is_null( $db_interviewer_user ) )
      {
        // home interview
        $modifier->where( 'appointment.user_id', '=', $db_interviewer_user->id );
      }
      else
      {
        // site interview
        $modifier->where( 'appointment.user_id', '=', NULL );
        $modifier->where( 'qnaire.type', '=', 'site' );

        // restrict by home instance type (if NULL then send to onyx)
        $modifier->join( 'interview', 'participant.id', 'home_interview.participant_id', '', 'home_interview' );
        $modifier->join( 'qnaire', 'home_interview.qnaire_id', 'home_qnaire.id', '', 'home_qnaire' );
        $modifier->left_join(
          'interviewing_instance',
          'home_interview.interviewing_instance_id',
          'interviewing_instance.id'
        );
        $modifier->where( 'home_qnaire.type', '=', 'home' );
        $modifier->where( 'IFNULL( interviewing_instance.type, "onyx" )', '=', $db_interviewing_instance->type );

        // consent status is passed to onyx in a customized way (pine consent is done below)
        if( 'onyx' == $db_interviewing_instance->type )
        {
          $select->add_column(
            'IF( IFNULL( hin_consent.accept, false ), "YES", "NO" )',
            'consentToHIN',
            false
          );
          $select->add_column(
            'IF( IFNULL( blood_consent.accept, true ), "YES", "NO" )',
            'consentToDrawBlood',
            false
          );
          $select->add_column(
            'IF( IFNULL( urine_consent.accept, true ), "YES", "NO" )',
            'consentToTakeUrine',
            false
          );

          // provide HIN access consent
          $modifier->join(
            'participant_last_consent',
            'participant.id',
            'participant_last_hin_consent.participant_id',
            '', // regular join type
            'participant_last_hin_consent'
          );
          $modifier->join(
            'consent_type',
            'participant_last_hin_consent.consent_type_id',
            'hin_consent_type.id',
            '', // regular join type
            'hin_consent_type'
          );
          $modifier->left_join(
            'consent',
            'participant_last_hin_consent.consent_id',
            'hin_consent.id',
            'hin_consent'
          );
          $modifier->where( 'hin_consent_type.name', '=', 'HIN access' );

          // provide blood consent
          $modifier->join(
            'participant_last_consent',
            'participant.id',
            'participant_last_blood_consent.participant_id',
            '', // regular join type
            'participant_last_blood_consent'
          );
          $modifier->join(
            'consent_type',
            'participant_last_blood_consent.consent_type_id',
            'blood_consent_type.id',
            '', // regular join type
            'blood_consent_type'
          );
          $modifier->left_join(
            'consent',
            'participant_last_blood_consent.consent_id',
            'blood_consent.id',
            'blood_consent'
          );
          $modifier->where( 'blood_consent_type.name', '=', 'draw blood' );

          // provide urine consent
          $modifier->join(
            'participant_last_consent',
            'participant.id',
            'participant_last_urine_consent.participant_id',
            '', // regular join type
            'participant_last_urine_consent'
          );
          $modifier->join(
            'consent_type',
            'participant_last_urine_consent.consent_type_id',
            'urine_consent_type.id',
            '', // regular join type
            'urine_consent_type'
          );
          $modifier->left_join(
            'consent',
            'participant_last_urine_consent.consent_id',
            'urine_consent.id',
            'urine_consent'
          );
          $modifier->where( 'urine_consent_type.name', '=', 'take urine' );
        }
      }

      // send a list of all eligible studies
      $study_sel = lib::create( 'database\select' );
      $study_sel->from( 'participant' );
      $study_sel->add_column( 'id', 'participant_id' );
      $study_sel->add_column(
        sprintf(
          'GROUP_CONCAT( study.name ORDER BY study.name SEPARATOR "%s" )',
          'onyx' == $db_interviewing_instance->type ? ',' : ';'
        ),
        'list',
        false
      );
      $study_mod = lib::create( 'database\modifier' );
      $study_mod->left_join( 'study_has_participant', 'participant.id', 'study_has_participant.participant_id' );
      $study_mod->left_join( 'study', 'study_has_participant.study_id', 'study.id' );
      $study_mod->group( 'participant.id' );

      $sql = sprintf(
        'CREATE TEMPORARY TABLE study_list %s %s',
        $study_sel->get_sql(),
        $study_mod->get_sql()
      );

      $study_class_name::db()->execute( 'DROP TABLE IF EXISTS study_list' );
      $study_class_name::db()->execute( $sql );
      $study_class_name::db()->execute( 'ALTER TABLE study_list ADD PRIMARY KEY (participant_id)' );

      $modifier->join( 'study_list', 'participant.id', 'study_list.participant_id' );
      $select->add_table_column( 'study_list', 'list', 'study_list' );

      if( 'onyx' == $db_interviewing_instance->type )
      {
        // send a list of all participant identifiers
        foreach( $identifier_class_name::select_objects() as $db_identifier )
        {
          $join_mod = lib::create( 'database\modifier' );
          $join_mod->where( 'participant.id', '=', 'participant_identifier.participant_id', false );
          $join_mod->where( 'participant_identifier.identifier_id', '=', $db_identifier->id );
          $modifier->join_modifier( 'participant_identifier', $join_mod, 'left' );
          $modifier->left_join( 'identifier', 'participant_identifier.identifier_id', 'identifier.id' );
          $select->add_table_column(
            'participant_identifier',
            'value',
            sprintf( 'identifier %s', $db_identifier->name )
          );
        }
      }

      // send pine a list of all participant identifier and consent records
      if( 'pine' == $db_interviewing_instance->type )
      {
        // send a list of all strata
        $stratum_sel = lib::create( 'database\select' );
        $stratum_sel->from( 'participant' );
        $stratum_sel->add_column( 'id', 'participant_id' );
        $stratum_sel->add_column(
          'GROUP_CONCAT( '.
            'CONCAT_WS( "||", study.name, stratum.name ) '.
            'ORDER BY study.name, stratum.name '.
            'SEPARATOR ";" '.
          ')',
          'list',
          false
        );
        $stratum_mod = lib::create( 'database\modifier' );
        $stratum_mod->left_join(
          'stratum_has_participant',
          'participant.id',
          'stratum_has_participant.participant_id'
        );
        $stratum_mod->left_join( 'stratum', 'stratum_has_participant.stratum_id', 'stratum.id' );
        $stratum_mod->left_join( 'study', 'stratum.study_id', 'study.id' );
        $stratum_mod->group( 'participant.id' );

        $sql = sprintf(
          'CREATE TEMPORARY TABLE stratum_list %s %s',
          $stratum_sel->get_sql(),
          $stratum_mod->get_sql()
        );

        $stratum_class_name::db()->execute( 'DROP TABLE IF EXISTS stratum_list' );
        $stratum_class_name::db()->execute( $sql );
        $stratum_class_name::db()->execute( 'ALTER TABLE stratum_list ADD PRIMARY KEY (participant_id)' );

        $modifier->join( 'stratum_list', 'participant.id', 'stratum_list.participant_id' );
        $select->add_table_column( 'stratum_list', 'list', 'stratum_list' );

        // send a list of all identifiers
        $identifier_sel = lib::create( 'database\select' );
        $identifier_sel->from( 'participant' );
        $identifier_sel->add_column( 'id', 'participant_id' );
        $identifier_sel->add_column(
          'GROUP_CONCAT( '.
            'CONCAT_WS( "$", identifier.name, participant_identifier.value ) '.
            'ORDER BY identifier.name '.
            'SEPARATOR ";" '.
          ')',
          'list',
          false
        );
        $identifier_mod = lib::create( 'database\modifier' );
        $identifier_mod->left_join(
          'participant_identifier',
          'participant.id',
          'participant_identifier.participant_id'
        );
        $identifier_mod->left_join( 'identifier', 'participant_identifier.identifier_id', 'identifier.id' );
        $identifier_mod->group( 'participant.id' );

        $sql = sprintf(
          'CREATE TEMPORARY TABLE identifier_list %s %s',
          $identifier_sel->get_sql(),
          $identifier_mod->get_sql()
        );

        $identifier_class_name::db()->execute( 'DROP TABLE IF EXISTS identifier_list' );
        $identifier_class_name::db()->execute( $sql );
        $identifier_class_name::db()->execute( 'ALTER TABLE identifier_list ADD PRIMARY KEY (participant_id)' );

        $modifier->join( 'identifier_list', 'participant.id', 'identifier_list.participant_id' );
        $select->add_table_column( 'identifier_list', 'list', 'participant_identifier_list' );

        // send a list of all eligible studies
        $collection_sel = lib::create( 'database\select' );
        $collection_sel->from( 'participant' );
        $collection_sel->add_column( 'id', 'participant_id' );
        $collection_sel->add_column(
          'GROUP_CONCAT( collection.name ORDER BY collection.name SEPARATOR ";" )',
          'list',
          false
        );
        $collection_mod = lib::create( 'database\modifier' );
        $collection_mod->join(
          'collection_has_participant',
          'participant.id',
          'collection_has_participant.participant_id'
        );
        $collection_mod->join( 'collection', 'collection_has_participant.collection_id', 'collection.id' );
        $collection_mod->group( 'participant.id' );

        $sql = sprintf(
          'CREATE TEMPORARY TABLE collection_list %s %s',
          $collection_sel->get_sql(),
          $collection_mod->get_sql()
        );

        $identifier_class_name::db()->execute( 'DROP TABLE IF EXISTS collection_list' );
        $identifier_class_name::db()->execute( $sql );
        $identifier_class_name::db()->execute( 'ALTER TABLE collection_list ADD PRIMARY KEY (participant_id)' );

        $modifier->left_join( 'collection_list', 'participant.id', 'collection_list.participant_id' );
        $select->add_table_column( 'collection_list', 'list', 'collection_list' );

        // send a list of all consent records
        $consent_sel = lib::create( 'database\select' );
        $consent_sel->from( 'participant' );
        $consent_sel->add_column( 'id', 'participant_id' );
        $consent_sel->add_column(
          'GROUP_CONCAT( '.
            'CONCAT_WS( "$", consent_type.name, consent.accept, consent.datetime ) '.
            'ORDER BY consent.id '.
            'SEPARATOR ";" '.
          ')',
          'list',
          false
        );
        $consent_mod = lib::create( 'database\modifier' );
        $consent_mod->join( 'consent', 'participant.id', 'consent.participant_id' );
        $consent_mod->join( 'consent_type', 'consent.consent_type_id', 'consent_type.id' );
        $consent_mod->group( 'participant.id' );

        $sql = sprintf(
          'CREATE TEMPORARY TABLE consent_list %s %s',
          $consent_sel->get_sql(),
          $consent_mod->get_sql()
        );

        $identifier_class_name::db()->execute( 'DROP TABLE IF EXISTS consent_list' );
        $identifier_class_name::db()->execute( $sql );
        $identifier_class_name::db()->execute( 'ALTER TABLE consent_list ADD PRIMARY KEY (participant_id)' );

        $modifier->left_join( 'consent_list', 'participant.id', 'consent_list.participant_id' );
        $select->add_table_column( 'consent_list', 'list', 'consent_list' );

        // send a list of all event records
        $event_sel = lib::create( 'database\select' );
        $event_sel->from( 'participant' );
        $event_sel->add_column( 'id', 'participant_id' );
        $event_sel->add_column(
          'GROUP_CONCAT( '.
            'CONCAT_WS( "$", event_type.name, event.datetime ) '.
            'ORDER BY event.id '.
            'SEPARATOR ";" '.
          ')',
          'list',
          false
        );
        $event_mod = lib::create( 'database\modifier' );
        $event_mod->join( 'event', 'participant.id', 'event.participant_id' );
        $event_mod->join( 'event_type', 'event.event_type_id', 'event_type.id' );
        $event_mod->group( 'participant.id' );

        $sql = sprintf(
          'CREATE TEMPORARY TABLE event_list %s %s',
          $event_sel->get_sql(),
          $event_mod->get_sql()
        );

        $identifier_class_name::db()->execute( 'DROP TABLE IF EXISTS event_list' );
        $identifier_class_name::db()->execute( $sql );
        $identifier_class_name::db()->execute( 'ALTER TABLE event_list ADD PRIMARY KEY (participant_id)' );

        $modifier->left_join( 'event_list', 'participant.id', 'event_list.participant_id' );
        $select->add_table_column( 'event_list', 'list', 'event_list' );
      }

      // restrict appointment for onyx by appointment type
      $appointment_type = $this->get_argument( 'type', false );
      if( !$appointment_type )
      {
        $modifier->where( 'appointment_type_id', '=', NULL );
      }
      else
      {
        $default = false;
        $appointment_type_id_list = array();

        foreach( explode( ';', $appointment_type ) as $type )
        {
          $type = trim( $type );
          if( 'default' == $type )
          {
            $default = true;
          }
          else
          {
            $db_appointment_type = $appointment_type_class_name::get_unique_record( 'name', $type );
            if( is_null( $db_appointment_type ) )
            {
              log::warning( sprintf(
                'Tried to get interviewing_instance appointment list by undefined appointment type "%s".', $type ) );
            }
            else
            {
              $appointment_type_id_list[] = $db_appointment_type->id;
            }
          }
        }

        if( $default && 0 < count( $appointment_type_id_list ) )
        {
          $modifier->where_bracket( true );
          $modifier->where( 'appointment_type_id', '=', NULL );
          $modifier->or_where( 'appointment_type_id', 'IN', $appointment_type_id_list );
          $modifier->where_bracket( false );
        }
        else if( $default )
        {
          $modifier->where( 'appointment_type_id', '=', NULL );
        }
        else if( 0 < count( $appointment_type_id_list ) )
        {
          $modifier->where( 'appointment_type_id', 'IN', $appointment_type_id_list );
        }
      }

      if( 'pine' == $db_interviewing_instance->type )
      {
        // specify the appointment type for pine
        $select->add_table_column( 'appointment_type', 'name', 'appointment_type' );
      }
    }
    else
    {
      // explicitely join to the home interviewing instance
      $modifier->join( 'interview', 'participant.id', 'home_interview.participant_id', '', 'home_interview' );
      $modifier->join( 'qnaire', 'home_interview.qnaire_id', 'home_qnaire.id', '', 'home_qnaire' );
      $modifier->where( 'home_qnaire.type', '=', 'home' );
      $modifier->left_join(
        'interviewing_instance',
        'home_interview.interviewing_instance_id',
        'home_interviewing_instance.id',
        'home_interviewing_instance'
      );

      // add the appointment's duration
      $modifier->left_join( 'setting', 'participant_site.site_id', 'setting.site_id' );
      $select->add_column(
        "IF(\n".
        "    appointment.user_id IS NULL,\n".
        "    IF(\n".
        "      setting.id IS NOT NULL,\n".
        "      setting.appointment_site_duration,\n".
        "      ( SELECT DEFAULT( appointment_site_duration ) FROM setting LIMIT 1 )\n".
        "    ),\n".
        "    IF(\n".
        "      setting.id IS NOT NULL,\n".
        "      setting.appointment_home_duration,\n".
        "      ( SELECT DEFAULT( appointment_home_duration ) FROM setting LIMIT 1 )\n".
        "    )\n".
        "  )",
        'duration',
        false );

      $modifier->left_join( 'user', 'appointment.user_id', 'user.id' );
      // include the user first/last/name as supplemental data (for both get and query)
      $select->add_column(
        'CONCAT( user.first_name, " ", user.last_name, " (", user.name, ")" )',
        'formatted_user_id',
        false );

      // include the participant uid and interviewer name as supplemental data
      $modifier->left_join( 'user', 'appointment.user_id', 'user.id' );
      $select->add_table_column( 'participant', 'uid' );
      $select->add_table_column( 'user', 'name', 'username' );

      // add the address "summary" column if needed
      $modifier->left_join( 'address', 'appointment.address_id', 'address.id' );
      if( $select->has_column( 'address_summary' ) )
      {
        $modifier->left_join( 'region', 'address.region_id', 'region.id' );
        $select->add_column(
          'CONCAT_WS( ", ", address1, address2, city, region.name )', 'address_summary', false );
      }

      // add help text (for calendar events)
      $modifier->join( 'language', 'participant.language_id', 'language.id' );
      $modifier->left_join( 'phone', 'participant.id', 'phone.participant_id' );
      $modifier->where( 'IFNULL( phone.rank, 1 )', '=', 1 );
      $select->add_column(
        'CONCAT( '.
          'participant.first_name, " ", participant.last_name, " (", language.name, ")", '.
          'IF( '.
            'appointment.user_id IS NULL AND home_interviewing_instance.id IS NOT NULL, '.
            'CONCAT( "\n", home_interviewing_instance.type, " interview" ), '.
            '"" '.
          '), '.
          'IF( phone.number IS NOT NULL, CONCAT( "\n", phone.number ), "" ), '.
          'IF( '.
            'qnaire_has_consent_type.consent_type_id IS NOT NULL, '.
            'CONCAT( '.
              '"\nConsent of Interest: ", '.
              'IFNULL( GROUP_CONCAT( DISTINCT consent_type.name ORDER BY consent_type.name ), "(none)" ) '.
            '), '.
            '"" '.
          '), '.
          'IF( '.
            'qnaire_has_event_type.event_type_id IS NOT NULL, '.
            'CONCAT( '.
              '"\nEvent of Interest: ", '.
              'IFNULL( GROUP_CONCAT( DISTINCT event_type.name ORDER BY event_type.name ), "(none)" ) '.
            '), '.
            '"" '.
          '), '.
          'IF( '.
            'qnaire_has_study.study_id IS NOT NULL, '.
            'CONCAT( '.
              '"\nStudy of Interest: ", '.
              'IFNULL( GROUP_CONCAT( DISTINCT study.name ORDER BY study.name ), "(none)" ) '.
            '), '.
            '"" '.
          '), '.
          'IF( participant.global_note IS NOT NULL, CONCAT( "\n", participant.global_note ), "" ) '.
        ')',
        'help',
        false
      );

      // restrict by site
      $db_restricted_site = $this->get_restricted_site();
      if( !is_null( $db_restricted_site ) )
        $modifier->where( 'participant_site.site_id', '=', $db_restricted_site->id );

      // restrict by user
      if( 'interviewer' == $db_role->name )
        $modifier->where( sprintf( 'IFNULL( appointment.user_id, %s )', $db_user->id ), '=', $db_user->id );

      if( $select->has_table_columns( 'appointment_type' ) )
        $modifier->left_join( 'appointment_type', 'appointment.appointment_type_id', 'appointment_type.id' );

      if( $select->has_column( 'state' ) )
      {
        // specialized sql used to determine the appointment's current state
        $sql =
          'IF( appointment.outcome IS NOT NULL, '.
            'outcome, '.
            'IF( '.
              // make sure the appointment is in the next minute (compare to the next minute of UTC time)
              'UTC_TIMESTAMP() + INTERVAL 60-SECOND(UTC_TIMESTAMP()) SECOND <= appointment.datetime, '.
              '"upcoming", '.
              '"passed" '.
            ') '.
          ')';

        $select->add_column( $sql, 'state', false );
      }

      // add the list of consents of interest
      $modifier->left_join( 'qnaire_has_consent_type', 'qnaire.id', 'qnaire_has_consent_type.qnaire_id' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where(
        'qnaire_has_consent_type.consent_type_id',
        '=',
        'participant_last_consent.consent_type_id',
        false
      );
      $join_mod->where( 'participant.id', '=', 'participant_last_consent.participant_id', false );
      $modifier->join_modifier( 'participant_last_consent', $join_mod, 'left' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'participant_last_consent.consent_id', '=', 'consent.id', false );
      $join_mod->where( 'consent.accept', '=', true );
      $modifier->join_modifier( 'consent', $join_mod, 'left' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'consent.consent_type_id', '=', 'consent.consent_type_id', false );
      $join_mod->where( 'qnaire_has_consent_type.consent_type_id', '=', 'consent_type.id', false );
      $modifier->join_modifier( 'consent_type', $join_mod, 'left' );

      // add the list of events of interest
      $modifier->left_join( 'qnaire_has_event_type', 'qnaire.id', 'qnaire_has_event_type.qnaire_id' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where(
        'qnaire_has_event_type.event_type_id',
        '=',
        'participant_last_event.event_type_id',
        false
      );
      $join_mod->where( 'participant.id', '=', 'participant_last_event.participant_id', false );
      $modifier->join_modifier( 'participant_last_event', $join_mod, 'left' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'participant_last_event.event_id', '=', 'event.id', false );
      $modifier->join_modifier( 'event', $join_mod, 'left' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'event.event_type_id', '=', 'event.event_type_id', false );
      $join_mod->where( 'qnaire_has_event_type.event_type_id', '=', 'event_type.id', false );
      $modifier->join_modifier( 'event_type', $join_mod, 'left' );

      // add the list of studies of interest
      $modifier->left_join( 'qnaire_has_study', 'qnaire.id', 'qnaire_has_study.qnaire_id' );
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where(
        'qnaire_has_study.study_id',
        '=',
        'study_has_participant.study_id',
        false
      );
      $join_mod->where( 'participant.id', '=', 'study_has_participant.participant_id', false );
      $modifier->join_modifier( 'study_has_participant', $join_mod, 'left' );
      $modifier->left_join( 'study', 'study_has_participant.study_id', 'study.id' );

      // restrict by qnaire type
      $qnaire_type = $this->get_argument( 'qnaire_type', NULL );
      if( !is_null( $qnaire_type ) ) $modifier->where( 'qnaire.type', '=', $qnaire_type );
    }
  }
}
