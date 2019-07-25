<?php
use PHPUnit\Framework\TestCase;

class CheckerTest extends TestCase {

	public function testClassExists() {
		$this->assertTrue( class_exists( 'CLU\Checker' ) );
	}

}
