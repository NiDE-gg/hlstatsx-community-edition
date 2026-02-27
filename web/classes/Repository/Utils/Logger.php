<?php
	namespace Utils;

	class Logger
	{
		public function __construct()
		{
			
		}

		public function error(string $text) : void
		{
			error_log($text); // PHP error log
		}
	}

?>