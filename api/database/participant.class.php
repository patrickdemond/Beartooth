<?php
/**
 * participant.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace beartooth\database;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * participant: record
 */
class participant extends \cenozo\database\participant
{
  /**
   * Extend parent method by restricting selection to records belonging to this service only
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the selection.
   * @param boolean $count If true the total number of records instead of a list
   * @access public
   * @static
   */
  public static function select( $modifier = NULL, $count = false )
  {
    // make sure to only include sites belonging to this application
    if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'service_has_participant.service_id', '=',
                      lib::create( 'business\session' )->get_service()->id );
    $modifier->where( 'service_has_participant.datetime', '!=', NULL );
    return parent::select( $modifier, $count );
  }

  /**
   * Override parent method by restricting returned records to those belonging to this service only
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string|array $column A column with the unique key property (or array of columns)
   * @param string|array $value The value of the column to match (or array of values)
   * @return database\record
   * @static
   * @access public
   */
  public static function get_unique_record( $column, $value )
  {
    $db_participant = parent::get_unique_record( $column, $value );

    if( !is_null( $db_participant ) )
    { // make sure the participant has been released
      $db_service = lib::create( 'business\session' )->get_service();

      $participant_mod = lib::create( 'database\modifier' );
      $participant_mod->where( 'participant.id', '=', $db_participant->id );
      $participant_mod->where( 'service_has_participant.datetime', '!=', NULL );
      if( 0 == $db_service->get_participant_count( $participant_mod ) ) $db_participant = NULL;
    }

    return $db_participant;
  }

  /**
   * Get the participant's most recent, closed assignment.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return assignment
   * @access public
   */
  public function get_last_finished_assignment()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query participant with no id.' );
      return NULL;
    }

    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'interview.participant_id', '=', $this->id );
    $modifier->where( 'end_datetime', '!=', NULL );
    $modifier->order_desc( 'start_datetime' );
    $modifier->limit( 1 );
    $assignment_class_name = lib::get_class_name( 'database\assignment' );
    $assignment_list = $assignment_class_name::select( $modifier );

    return 0 == count( $assignment_list ) ? NULL : current( $assignment_list );
  }

  /**
   * Get the participant's current assignment (or null if none is found)
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return assignment
   * @access public
   */
  public function get_current_assignment()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query participant with no id.' );
      return NULL;
    }

    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'interview.participant_id', '=', $this->id );
    $modifier->where( 'end_datetime', '=', NULL );
    $modifier->order_desc( 'start_datetime' );
    $modifier->limit( 1 );
    $assignment_class_name = lib::get_class_name( 'database\assignment' );
    $assignment_list = $assignment_class_name::select( $modifier );

    return 0 == count( $assignment_list ) ? NULL : current( $assignment_list );
  }

  /**
   * Override parent's magic get method so that supplementary data can be retrieved
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param string $column_name The name of the column or table being fetched from the database
   * @return mixed
   * @access public
   */
  public function __get( $column_name )
  {
    if( 'current_qnaire_id' == $column_name ||
        'current_qnaire_type' == $column_name ||
        'start_qnaire_date' == $column_name )
    {
      $this->get_queue_data();
      return $this->$column_name;
    }

    return parent::__get( $column_name );
  }

  /**
   * Fills in the current qnaire id and start qnaire date
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access private
   */
  private function get_queue_data()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query participant with no id.' );
      return NULL;
    }

    if( is_null( $this->current_qnaire_id ) &&
        is_null( $this->current_qnaire_type ) &&
        is_null( $this->start_qnaire_date ) )
    {
      $database_class_name = lib::get_class_name( 'database\database' );
      // special sql to get the current qnaire id and start date
      // NOTE: when updating this query database\queue::get_query_parts()
      //       should also be updated as it performs a very similar query
      $sql = sprintf(
        $this->participant_additional_sql,
        $database_class_name::format_string( $this->id ) );
      $row = static::db()->get_row( $sql );
      $this->current_qnaire_id = $row['current_qnaire_id'];
      $this->current_qnaire_type = $row['current_qnaire_type'];
      $this->start_qnaire_date = $row['start_qnaire_date'];
    }
  }

  /**
   * A convenience function to get this participant's next of kin.
   * This method is necessary since there is a 1 to N relationship between participant and
   * next_of_kin, however, the next_of_kin.participant_id column is unique.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return database\next_of_kin
   * @access public
   */
  public function get_next_of_kin()
  {
    $next_of_kin_class_name = lib::get_class_name( 'database\next_of_kin' );
    return $next_of_kin_class_name::get_unique_record( 'participant_id', $this->id );
  }

  /**
   * A convenience function to get this participant's next of kin.
   * This method is necessary since there is a 1 to N relationship between participant and
   * data_collection, however, the data_collection.participant_id column is unique.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @return database\data_collection
   * @access public
   */
  public function get_data_collection()
  {
    $data_collection_class_name = lib::get_class_name( 'database\data_collection' );
    return $data_collection_class_name::get_unique_record( 'participant_id', $this->id );
  }

  /**
   * The participant's current questionnaire id (from a custom query)
   * @var int
   * @access private
   */
  private $current_qnaire_id = NULL;

  /**
   * The participant's current questionnaire type (from a custom query)
   * @var string
   * @access private
   */
  private $current_qnaire_type = NULL;

  /**
   * The date that the current questionnaire is to begin (from a custom query)
   * @var int
   * @access private
   */
  private $start_qnaire_date = NULL;

  /**
   * A string containing the SQL used to get the additional participant information used by
   * get_queue_data()
   * @var string
   * @access private
   */
  private $participant_additional_sql = <<<'SQL'
SELECT IF
(
  current_interview.id IS NULL,
  ( SELECT id FROM qnaire WHERE rank = 1 ),
  IF( current_interview.completed, next_qnaire.id, current_qnaire.id )
) AS current_qnaire_id,
IF
(
  current_interview.id IS NULL,
  ( SELECT type FROM qnaire WHERE rank = 1 ),
  IF( current_interview.completed, next_qnaire.type, current_qnaire.type )
) AS current_qnaire_type,
IF
(
  current_interview.id IS NULL,
  NULL,
  IF
  (
    current_interview.completed,
    IF
    (
      next_qnaire.id IS NULL,
      NULL,
      next_prev_assignment.end_datetime + INTERVAL next_qnaire.delay WEEK
    ),
    NULL
  )
) AS start_qnaire_date
FROM participant
LEFT JOIN interview AS current_interview
ON current_interview.participant_id = participant.id
LEFT JOIN interview_last_assignment
ON current_interview.id = interview_last_assignment.interview_id
LEFT JOIN assignment
ON interview_last_assignment.assignment_id = assignment.id
LEFT JOIN qnaire AS current_qnaire
ON current_qnaire.id = current_interview.qnaire_id
LEFT JOIN qnaire AS next_qnaire
ON next_qnaire.rank = ( current_qnaire.rank + 1 )
LEFT JOIN qnaire AS next_prev_qnaire
ON next_prev_qnaire.id = next_qnaire.prev_qnaire_id
LEFT JOIN interview AS next_prev_interview
ON next_prev_interview.qnaire_id = next_prev_qnaire.id
AND next_prev_interview.participant_id = participant.id
LEFT JOIN assignment next_prev_assignment
ON next_prev_assignment.interview_id = next_prev_interview.id
WHERE
(
  current_qnaire.rank IS NULL OR
  current_qnaire.rank =
  (
    SELECT MAX( qnaire.rank )
    FROM interview, qnaire
    WHERE qnaire.id = interview.qnaire_id
    AND current_interview.participant_id = interview.participant_id
    GROUP BY current_interview.participant_id
  )
)
AND
(
  next_prev_assignment.end_datetime IS NULL OR
  next_prev_assignment.end_datetime =
  (
    SELECT MAX( assignment.end_datetime )
    FROM interview, assignment
    WHERE interview.qnaire_id = next_prev_qnaire.id
    AND interview.id = assignment.interview_id
    AND next_prev_assignment.id = assignment.id
    GROUP BY next_prev_assignment.interview_id
  )
)
AND participant.id = %s
SQL;
}
