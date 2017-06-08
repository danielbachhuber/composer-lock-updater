<?php

namespace CLU;

class Logger {

	/**
	 * Write an informational message to STDOUT.
	 *
	 * @param string $message Message to write.
	 */
	public static function info( $message ) {
		self::write( STDOUT, $message . "\n" );
	}

	/**
	 * Write a success message, prefixed with "Success: ".
	 *
	 * @param string $message Message to write.
	 */
	public static function success( $message ) {
		self::line( $message, 'Success', '%G' );
	}

	/**
	 * Write a warning message to STDERR, prefixed with "Warning: ".
	 *
	 * @param string $message Message to write.
	 */
	public static function warning( $message ) {
		self::line( $message, 'Warning', '%C', STDERR );
	}

	/**
	 * Write an message to STDERR, prefixed with "Error: ".
	 *
	 * @param string $message Message to write.
	 */
	public static function error( $message ) {
		self::line( $message, 'Error', '%R', STDERR );
	}

	/**
	 * Write a string to a resource.
	 *
	 * @param resource $handle Commonly STDOUT or STDERR.
	 * @param string $str Message to write.
	 */
	protected static function write( $handle, $str ) {
		fwrite( $handle, $str );
	}

	/**
	 * Output one line of message to a resource.
	 *
	 * @param string $message Message to write.
	 * @param string $label Prefix message with a label.
	 * @param string $color Colorize label with a given color.
	 * @param resource $handle Resource to write to. Defaults to STDOUT.
	 */
	protected static function line( $message, $label, $color, $handle = STDOUT ) {
		if ( class_exists( 'cli\Colors' ) ) {
			$label = \cli\Colors::colorize( "$color$label:%n", true );
		} else {
			$label = "$label:";
		}
		self::write( $handle, "$label $message\n" );
	}

}
