<?php
/**
 * onyx_instance_view.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package beartooth\ui
 * @filesource
 */

namespace beartooth\ui\widget;
use beartooth\log, beartooth\util;
use beartooth\business as bus;
use beartooth\database as db;
use beartooth\exception as exc;

/**
 * widget onyx_instance view
 * 
 * @package beartooth\ui
 */
class onyx_instance_view extends base_view
{
  /**
   * Constructor
   * 
   * Defines all variables which need to be set for the associated template.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args An associative array of arguments to be processed by the widget
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'onyx_instance', 'view', $args );

    // define all columns defining this record

    $type = 'administrator' == bus\session::self()->get_role()->name ? 'enum' : 'hidden';
    $this->add_item( 'site_id', $type, 'Site' );
    $this->add_item( 'interviewer_user_id', 'enum', 'Instance' );

    try
    {
      $this->user_view = new user_view( $args );
      $this->user_view->set_parent( $this );
      $this->user_view->set_heading( '' );
    }
    catch( exc\permission $e )
    {
      $this->user_view = NULL;
    }
  }

  /**
   * Finish setting the variables in a widget.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function finish()
  {
    parent::finish();
    $session = bus\session::self();
    $is_administrator = 'administrator' == $session->get_role()->name;

    // create enum arrays
    if( $is_administrator )
    {
      $sites = array();
      foreach( db\site::select() as $db_site ) $sites[$db_site->id] = $db_site->name;
    }

    $interviewers = array();
    foreach( db\user::select( $modifier ) as $db_user )
      $interviewers[$db_user->id] = $db_user->name;

    // set the view's items
    $this->set_item(
      'site_id', $this->get_record()->site_id, false, $is_administrator ? $sites : NULL );
    $this->set_item(
      'interviewer_user_id', $this->get_record()->interviewer_user_id, true, $interviewers );

    $this->finish_setting_items();
  }
}
?>
