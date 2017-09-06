<?php

namespace CLU;

class Checker {

  /**
   * Check that Git, Composer, and Hub are available on the filesystem.
   */
  public static function check_executables() {
    $execs = array( 'git', 'composer', 'hub' );
    foreach( $execs as $exec ) {
      exec( 'type ' . escapeshellarg( $exec ), $_, $return_code );
      if ( 0 !== $return_code ) {
        Logger::error( "Missing {$exec} on the system." );
      }
    }
    Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );
  }

}
