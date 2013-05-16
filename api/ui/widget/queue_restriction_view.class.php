<?php
/**
 * queue_restriction_view.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace beartooth\ui\widget;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * widget queue_restriction view
 */
class queue_restriction_view extends \cenozo\ui\widget\base_view
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
    parent::__construct( 'queue_restriction', 'view', $args );
  }

  /**
   * Processes arguments, preparing them for the operation.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @throws exception\notice
   * @access protected
   */
  protected function prepare()
  {
    parent::prepare();

    // define all columns defining this record

    $type = lib::create( 'business\session' )->get_role()->all_sites ? 'enum' : 'hidden';
    $this->add_item( 'site_id', $type, 'Site' );
    $this->add_item( 'city', 'string', 'City' );
    $this->add_item( 'region_id', 'enum', 'Region' );
    $this->add_item( 'postcode', 'string', 'Postcode' );
  }

  /**
   * Sets up the operation with any pre-execution instructions that may be necessary.
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access protected
   */
  protected function setup()
  {
    parent::setup();

    $site_class_name = lib::get_class_name( 'database\site' );
    $region_class_name = lib::get_class_name( 'database\region' );

    $session = lib::create( 'business\session' );
    $all_sites = $session->get_role()->all_sites;

    // create enum arrays
    if( $all_sites )
    {
      $site_mod = lib::create( 'database\modifier' );
      $site_mod->order( 'name' );
      $sites = array();
      foreach( $class_name::select( $site_mod ) as $db_site )
        $sites[$db_site->id] = $db_site->name;
    }

    $region_mod = lib::create( 'database\modifier' );
    $region_mod->order( 'country' );
    $region_mod->order( 'name' );
    $regions = array();
    foreach( $region_class_name::select( $region_mod ) as $db_region )
      $regions[$db_region->id] = $db_region->name;

    // set the view's items
    $this->set_item(
      'site_id', $this->get_record()->site_id, false, $all_sites ? $sites : NULL );
    $this->set_item( 'city', $this->get_record()->city, false );
    $this->set_item( 'region_id', $this->get_record()->region_id, false, $regions );
    $this->set_item( 'postcode', $this->get_record()->postcode, false );
  }
}
