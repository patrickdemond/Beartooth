<?php
/**
 * appointment_mail.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace beartooth\database;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * appointment_mail: record
 */
class appointment_mail extends \cenozo\database\record
{
  /**
   * Tests the subject and body of an email to make sure the template is valid
   * @return boolean
   * @access public
   */
  public function validate()
  {
    // test with any participant
    $db_participant = lib::create( 'database\participant', 1 );
    $datetime = util::get_datetime_object();

    $errors = array();
    try
    {
      $this->compile_text( $this->subject, $db_participant, $datetime );
    }
    catch( \cenozo\exception\argument $e )
    {
      preg_match( '/"key" with value "[^"]+"([^"]+)"/', $e->get_raw_message(), $matches );
      $errors['subject'] = $matches[1];
    }

    try
    {
      $this->compile_text( $this->body, $db_participant, $datetime );
    }
    catch( \cenozo\exception\argument $e )
    {
      preg_match( '/"key" with value "[^"]+"([^"]+)"/', $e->get_raw_message(), $matches );
      $errors['body'] = $matches[1];
    }

    return 0 < count( $errors ) ? util::json_encode( $errors ) : null;
  }

  /**
   * Adds a mail reminder for the given appointment
   * @param database\appointment $db_appointment
   * @access public
   */
  public function add_mail( $db_appointment )
  {
    $mail_class_name = lib::get_class_name( 'database\mail' );

    $db_appointment_type = $db_appointment->get_appointment_type();
    $db_participant = $db_appointment->get_interview()->get_participant();
    $db_address = $db_appointment->get_address();
    if( is_null( $db_address ) ) $db_address = $db_participant->get_first_address();
    $db_site = $db_participant->get_effective_site();
    $datetime = clone $db_appointment->datetime;

    // if the appointment type is setup to use the participant's TZ then use it, otherwise use the site's TZ
    $datetime->setTimezone(
      !is_null( $db_appointment_type ) &&
      $db_appointment_type->use_participant_timezone &&
      !is_null( $db_address ) ?
      $db_address->get_timezone_object() :
      $db_site->get_timezone_object()
    );

    if( !is_null( $db_participant->email ) )
    {
      $schedule_datetime = util::get_datetime_object();

      if( 'immediately' != $this->delay_unit )
      {
        $schedule_datetime = clone $db_appointment->datetime;
        $schedule_datetime->sub( new \DateInterval( sprintf( 'P%dD', $this->delay_offset ) ) );
        // don't send future reminders that have already passed
        if( util::get_datetime_object() >= $schedule_datetime ) return;
      }

      // work on the existing mail if one already exists
      $db_mail = $mail_class_name::get_unique_record(
        array( 'participant_id', 'schedule_datetime' ),
        array( $db_participant->id, $schedule_datetime->format( 'Y-m-d H:i:s' ) )
      );

      // or create a new one of none exists yet
      if( is_null( $db_mail ) ) $db_mail = lib::create( 'database\mail' );

      $db_mail->participant_id = $db_participant->id;
      $db_mail->from_name = $this->from_name;
      $db_mail->from_address = $this->from_address;
      $db_mail->to_name = $db_participant->get_full_name();
      $db_mail->to_address = $db_participant->email;
      $db_mail->cc_address = $this->cc_address;
      $db_mail->bcc_address = $this->bcc_address;
      $db_mail->schedule_datetime = $schedule_datetime;
      $db_mail->subject = $this->compile_text( $this->subject, $db_participant, $datetime );
      $db_mail->body = $this->compile_text( $this->body, $db_participant, $datetime );
      $db_mail->note = 'Automatically added from an appointment mail template.';
      $db_mail->save();

      // link the mail record to the appointment
      static::db()->execute( sprintf(
        'INSERT IGNORE INTO appointment_has_mail SET appointment_id = %d, mail_id = %d',
        $db_appointment->id,
        $db_mail->id
      ) );
    }
  }

  /**
   * Removes all mail reminder for the given appointment
   * @param database\appointment $db_appointment
   * @access public
   */
  public function remove_mail( $db_appointment )
  {
    $mail_class_name = lib::get_class_name( 'database\mail' );

    $db_participant = $db_appointment->get_interview()->get_participant();

    $schedule_datetime = 'immediately' == $this->delay_unit
                       ? util::get_datetime_object()
                       : clone $db_appointment->datetime;

    if( 'immediately' != $this->delay_unit )
      $schedule_datetime->sub( new \DateInterval( sprintf( 'P%dD', $this->delay_offset ) ) );

    // work on the existing mail if one already exists
    $db_mail = $mail_class_name::get_unique_record(
      array( 'participant_id', 'schedule_datetime' ),
      array( $db_participant->id, $schedule_datetime->format( 'Y-m-d H:i:s' ) )
    );

    // don't remove mail that has already been sent
    if( !is_null( $db_mail ) && is_null( $db_mail->sent_datetime ) ) $db_mail->delete();
  }

  /**
   * Compiles appointment_mail text, replacing coded variables with actual values
   * @access private
   */
  private function compile_text( $text, $db_participant, $datetime )
  {
    $data_manager = lib::create( 'business\data_manager' );
    $db_language = $db_participant->get_language();
    $date_format = 'en' == $db_language->code ? 'l, F jS' : 'l j F';
    $time_format = 'en' == $db_language->code ? 'g:i a' : 'H:i';

    $matches = array();
    preg_match_all( '/\$[^$\s]+\$/', $text, $matches ); // get anything enclosed by $ with no whitespace
    foreach( $matches[0] as $match )
    {
      $value = substr( $match, 1, -1 );
      $replace = '';
      if( 'appointment.date' == $value )
      {
        $replace = util::convert_datetime_language( $datetime->format( $date_format ), $db_language );
      }
      else if( 'appointment.datetime' == $value )
      {
        $replace = util::convert_datetime_language(
          $datetime->format( sprintf( '%s \a\t %s', $date_format, $time_format ) ),
          $db_language
        );
      }
      else if( 'appointment.time' == $value )
      {
        $replace = util::convert_datetime_language( $datetime->format( $time_format ), $db_language );
      }
      else if( preg_match( '/^interviewer\.*/', $value ) )
      {
        $db_interview = $db_participant->get_effective_interview();
        $db_appointment = is_null( $db_interview ) ? NULL : $db_interview->get_last_appointment();
        $db_user = is_null( $db_appointment ) ? NULL : $db_appointment->get_user();

        if( is_null( $db_user ) ) log::warning( 'Appointment mail text references appointment user but no user exists.' );
        if( 'interviewer.first_name' == $value )
        {
          $replace = is_null( $db_user ) ? '' : $db_user->first_name;
        }
        else if( 'interviewer.last_name' == $value )
        {
          $replace = is_null( $db_user ) ? '' : $db_user->last_name;
        }
        else if( 'interviewer.full_name' == $value )
        {
          $replace = is_null( $db_user ) ? '' : sprintf( '%s %s', $db_user->first_name, $db_user->last_name );
        }
        else log::error( sprintf( 'Invalid variable in appointment mail template: "%s"', $value ) );
      }
      else
      {
        $replace = 0 === strpos( $value, 'participant.' )
                 ? $data_manager->get_participant_value( $db_participant, $value )
                 : $data_manager->get_value( $value );
      }

      $text = str_replace( $match, $replace, $text );
    }

    return $text;
  }
}
