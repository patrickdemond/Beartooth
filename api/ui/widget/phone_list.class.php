<?php
/**
 * phone_list.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package beartooth\ui
 * @filesource
 */

namespace beartooth\ui\widget;
use cenozo\lib, cenozo\log;
use beartooth\business as bus;
use beartooth\database as db;
use beartooth\exception as exc;

/**
 * widget phone list
 * 
 * @package beartooth\ui
 */
class phone_list extends base_list
{
  /**
   * Constructor
   * 
   * Defines all variables required by the phone list.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'phone', $args );
    
    $this->add_column( 'active', 'boolean', 'Active', true );
    $this->add_column( 'rank', 'number', 'Rank', true );
    $this->add_column( 'type', 'string', 'Type', true );
    $this->add_column( 'number', 'string', 'Number', false );
  }
  
  /**
   * Set the rows array needed by the template.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    
    // only allow higher than first tier roles to make direct calls
    $this->set_variable( 'allow_connect',
                         1 < lib::create( 'business\session' )->get_role()->tier );
    $this->set_variable( 'sip_enabled',
      lib::create( 'business\voip_manager' )->get_sip_enabled() );

    foreach( $this->get_record_list() as $record )
    {
      $this->add_row( $record->id,
        array( 'active' => $record->active,
               'rank' => $record->rank,
               'type' => $record->type,
               'number' => $record->number ) );
    }

    $this->finish_setting_rows();
  }
}
?>
