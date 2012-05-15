<?php
/**
 * address_new.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @package beartooth\ui
 * @filesource
 */

namespace beartooth\ui\push;
use cenozo\lib, cenozo\log, beartooth\util;

/**
 * push: address new
 *
 * Create a new address.
 * @package beartooth\ui
 */
class address_new extends base_new
{
  /**
   * Constructor.
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @param array $args Push arguments
   * @access public
   */
  public function __construct( $args )
  {
    parent::__construct( 'address', $args );
  }

  // TODO: document
  protected function prepare()
  {
    parent::prepare();

    $this->set_machine_request_enabled( true );
    $this->set_machine_request_url( MASTODON_URL );
  }

  // TODO: document
  protected function validate()
  {
    parent::validate();

    $columns = $this->get_argument( 'columns' );

    // validate the postcode
    if( !preg_match( '/^[A-Z][0-9][A-Z] [0-9][A-Z][0-9]$/', $columns['postcode'] ) &&
        !preg_match( '/^[0-9]{5}$/', $columns['postcode'] ) )
      throw lib::create( 'exception\notice',
        'Postal codes must be in "A1A 1A1" format, zip codes in "01234" format.', __METHOD__ );

    $postcode_class_name = lib::get_class_name( 'database\postcode' );
    $db_postcode = $postcode_class_name::get_match( $columns['postcode'] );
    if( is_null( $db_postcode ) )
      throw lib::create( 'exception\notice',
        'The postcode is invalid and cannot be used.', __METHOD__ );
  }

  // TODO: document
  protected function execute()
  {
    $columns = $this->get_argument( 'columns' );
    $this->get_record()->postcode = $columns['postcode'];
    $this->get_record()->source_postcode();

    parent::execute();
  }
}
?>
