<?php

/*
 * Developer: Eslam Mahmoud contact@eslam.me
 * URL: http://eslam.me
 * Discription: PHP class to scan files/project and create or update .po file, used for localization. Could be used to scan any type of files, It will extract all strings like __('Hello World') Or _e("Hello again.")
 */

class gettext {

	//Default scan the curnt directory, accept string as directory path or array or directories
	//Directory path mast end with '/'
	var $directory = './';
	//Pattern to match
	//(__('pattern should get me :)'),'pattern should not get me !!') and if there another __('text need translation') in the same line it will be there 
	// __('pattern gets %s','me') too
	private $pattern = '/(__|_e)\((\'|\")(.+?)(\'|\")(,.*)?\)/';
	//Files extensions to scan, accept Array()
	var $file_extensions = false;
	//Default output file name will
	var $file_name = 'default.po';

	//Scan the directory and sub directories
	//Try to match every line in each file with the pattern
	function scan_dir($directory = false) {
		if (!$directory)
			$directory = $this->directory;

		$lines = array();

		if (is_array($directory)) {
			foreach ($directory as $k => $dir) {
				$sub_lines = $this->scan_dir($dir);
				$lines = array_merge($lines, $sub_lines);
			}

			return $lines;
		}

		if (!is_dir($directory))
			return false;
		
		$handle = opendir($directory);
		if ($handle) {
			//Get every file or sub directory in the defined directory
			while (false !== ($file = readdir($handle))) {
				if ($file == "." || $file == "..")
					continue;

				//echo "<br><br>" . $file . "<br>";
				$file = $directory . $file;
				//If sub directory call this function recursively
				if (is_dir($file)) {
					$sub_lines = $this->scan_dir($file . '/');
					$lines = array_merge($lines, $sub_lines);
				} else {
					$file_lines = $this->parse_file($file);

					if ($file_lines)
						$lines = array_merge($lines, $file_lines);
				}
			}
			closedir($handle);
		}

		//Removes duplicate values from an array
		return array_unique($lines);
	}

	//Create the .po file if not exists
	//If file exist will be updated with the new lines only
	function create_po($lines = array()) {
		if (count($lines) < 1)
			return false;

		//Get the old content
		$old_content = '';
		if (file_exists($this->file_name))
			$old_content = file_get_contents($this->file_name);

		//Open the file and append on it or create it if not there
		$file = fopen($this->file_name, 'a+') or die('Could bot open file ' . $this->file_name);
		foreach ($lines as $k => $line) {
			//Check to see if the line was in the file
			if (preg_match('/' . $line . '/', $old_content, $matches))
				continue;

			fwrite($file, 'msgid "' . $line . '"' . "\n" . 'msgstr ""' . "\n\n");
		}
		fclose($file);

		return true;
	}

	//parse file to get lines
	function parse_file($file = false) {
		if (!$file || !is_file($file))
			return false;

		//check the file extension, if there and not the same as file extension skip the file
		if ($this->file_extensions && is_array($this->file_extensions)) {
			$pathinfo = pathinfo($file);
			if (!in_array($pathinfo['extension'], $this->file_extensions))
				return false;
		}

		$lines = array();
		//Open the file
		$fh = fopen($file, 'r') or die('Could not open file ' . $file);
		$i = 1;
		while (!feof($fh)) {
			// read each line and trim off leading/trailing whitespace
			if ($s = trim(fgets($fh, 16384))) {
				// match the line to the pattern

				if (preg_match_all($this->pattern, $s, $matches)) {
					//$matches[0] -> full pattern
					//$matches[1] -> method __ OR _e
					//$matches[2] -> ' OR "
					//$matches[3] -> array ('text1', 'text2')
					//$matches[4] -> ' OR "
					if (!isset($matches[3]))
						continue;

					//Add the lines without duplicate values
					foreach ($matches[3] as $k => $text) {
						if (!in_array($text, $lines))
							$lines[] = $text;
					}
				} else {
					// complain if the line didn't match the pattern 
					error_log("Can't parse $file line $i: $s");
				}
			}
			$i++;
		}
		fclose($fh) or die('Could not close file ' . $file);

		return $lines;
	}

}

?>