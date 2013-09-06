<?php
	function csv_prepare($string)
	{
		$string = str_replace("\r", "", $string);
		$string = str_replace(array(BR, "\n"), "\r\n", $string);
		$string = strip_tags(html_entity_decode($string));

		// check if contains any characters to escape
		if (strpos($string,',') OR strpos($string,'"') OR strpos($string,"\r\n"))
		{
			$string = '"'.str_replace('"', '""', $string).'"';
		}
		$string .= ',';
		return $string;
	}

	foreach ($table_head as $column )
	{
		echo csv_prepare($column);
	}

	echo "\r\n";

	foreach ($table_data as $row)
	{
		foreach ($row as $entry)
		{
			if (is_array($entry))
			{
				echo csv_prepare($entry['data']);
				if (isset($entry['colspan']))
				{
					$colspan = (int)$entry['colspan'];
					$i=1;
					for ($i; $i < $colspan; $i++)
					{
						echo ',';
					}
				}
			}
			else
			{
				echo csv_prepare($entry);
			}
		}

		echo "\r\n";
	}
?>