<?php
	spl_autoload_register(function ($className)
	{
		$file = __DIR__ . '/../classes/' . str_replace('\\', '/', $className) . '.php';

		if (file_exists($file)) {
			require $file;
		}
	});