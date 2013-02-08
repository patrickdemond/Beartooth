<?php
/**
 * cohort.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace beartooth\database;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * cohort: record
 */
class cohort extends \cenozo\database\cohort
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
    // make sure to only include cohorts belonging to this application
    if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'service_has_cohort.service_id', '=',
                      lib::create( 'business\session' )->get_service()->id );
    return parent::select( $modifier, $count );
  }

  /**
   * Make sure to only include cohorts which this service has access to.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param database\modifier $modifier Modifications to the list.
   * @return array( database\cohort )
   * @access public
   */
  public function get_record_list( 
    $record_type, $modifier = NULL, $inverted = false, $count = false )
  { 
    if( 'service' == $record_type )
    {
      if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'service_has_cohort.service_id', '=',
                        lib::create( 'business\session' )->get_service()->id );
    }                   
    return parent::get_record_list( $record_type, $modifier, $inverted, $count );
  }
}
