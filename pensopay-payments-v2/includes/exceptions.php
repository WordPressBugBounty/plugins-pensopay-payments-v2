<?php

class Pensopay_Payments_V2_Exception extends Exception {

	public function __construct( $message, $code = 0, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->write_to_logs();
	}

	public function write_to_logs(): void {
		$log_data = [
			'Exception file'    => $this->getFile() . ':' . $this->getLine(),
			'Exception message' => $this->getMessage()
		];

		foreach ( $log_data as $title => $line ) {
			Pensopay_Payments_V2_Logger::log( sprintf( '%s: %s', $title, $line ) );
		}
	}
}