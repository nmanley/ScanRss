<?php
	$start = microtime(true);
	
	@ini_set('display_errors', '1');
	@ini_set('max_execution_time', 300); // Set 5minutes for slower connections, or unexpected lag.
	error_reporting(E_ALL);
		
	class ScanRss {
		
		// Declaring Vars
		var $feeds    = array();
		var $settings = array();
		var $feedData = array();
		var $history  = array();
		var $parsed   = array();
		var $torCount = 0;
		var $endTime  = 0;
		
		function construct($startTime) {

		// Set Feeds
			$this->feeds[] = "http://showrss.info/rss.php?user_id=123456&hd=null&proper=0&magnets=false";
			
			// Settings
			$this->settings['log_file_name'] = __DIR__ . "/\downloaded.log";
			$this->settings['file_indexes']  = __DIR__ . "/\indexes.log";
			$this->settings['history_log']   = __DIR__ . "/\history.log";
			$this->settings['file_location'] = __DIR__ . "/shares/";
						
			// Checking if a Few Things
			if(!file_exists($this->settings['file_location']))
				mkdir($this->settings['file_location'], 0766, true);
			
			
			if(!is_writeable($this->settings['file_location'])) {
				$this->write_history(3);
				exit;
			}
				
			// Starting to work
			$this->feedData = $this->read_feed();
			$this->history  = $this->get_history();
			$this->parsed   = $this->parse_feed();
			
			// Getting Torrents
			$status = $this->get_torrents();
			
			$this->endTime = microtime(true) - $startTime;
			
			$this->write_history($status);
			
		}
		
		
		function write_history($status) {
			
			Switch($status) {
				case 0: $msg = "Failed To Run Script."; break;
				case 1: $msg = "Script Ran Successfully.[ " . (string) $this->torCount . " ] New. [ Time ] . " . $this->endTime . " seconds."; break;
				case 2: $msg = "Script Ran Successfully, but no new torrents were downloaded. [ Time ] . " . $this->endTime . " seconds." ; break;
				case 3: $msg = "Unable to write to directory : " .  $this->settings['file_location']; break;
				default: 
					(string) $msg = "Unable to determine the Pass/Fail status of the Script, plese fire you're programmer. Status: " . $status;
					break;
			}
			
			(string) $date = date('Y-m-d H:i:s', time());
			(string) $form = "[ " . $date . " ] - " . $msg . "\r\n";
			
			file_put_contents($this->settings['history_log'], $form, FILE_APPEND);
			return;
		}
		
		function read_feed($url = NULL) {
			
			$ret = array();
			
			if(!$url) {
				$url = $this->feeds;					
			}
			else {
				if(!is_array($url))
					return 0;
			}
			
			foreach($url as $f) {
							
				$ch = curl_init();
				if ($ch) {
				
					curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
					curl_setopt($ch, CURLOPT_URL, $f);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					$ret[] = curl_exec($ch);
					curl_close($ch);
				}	
			}			
			return $ret;
			
		}
		
		
		function parse_feed($data = NULL) {
			
			$ret = array();
			
			if(!$data) {
				$data = $this->feedData;
				
			}
			else {
				if(!is_array($data))
					return 0;
			}
			foreach ( $data as $set )
			{
				if($rss = simplexml_load_string($set)) {
					foreach($rss->channel->item as $i) {
						$id = md5($i->link);
											
						if(!in_array($id, $this->history))
						{
							$this->torCount++; // Add 1 To the Download Count for record purposes.
							
							// Escaping Illegal Chars
							$i->title = preg_replace('/[^A-Za-z0-9_\-.\s]/', '_', $i->title);
							
							
							$ret[ (string) $i->guid ] = array(
								'link' => $i->link,
								'file' => $i->title . " - " . $id . ".torrent",
								'hash' => $id);
						}
							
					}
				}
			}
			return $ret;
		
		}

		
		function get_history() {
			
			if (is_file($this->settings['file_indexes']) && file_exists($this->settings['file_indexes']) && is_readable($this->settings['file_indexes'])) 
				return file($this->settings['file_indexes'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			else
				return array(); // Empty array to please
		}
		
		
		function save_history( $data ) {
		
			if(is_array($data)) {
				(string) $date = date('Y-m-d H:i:s', time());
				(string) $form = "[ " . $date . " ] - " . $data['file'] . "\r\n";
				
				// Writing To File
				file_put_contents($this->settings['log_file_name'], $form, FILE_APPEND);
				return 1;
			}
			else {
				file_put_contents($this->settings['file_indexes'], $data . "\r\n", FILE_APPEND);
				return 1;
			}
		
		}
		
		
		function get_torrents() {
			
			if(isset($this->parsed) && is_array($this->parsed) && sizeof($this->parsed) > 0) {
				foreach($this->parsed as $item) {
					$ch = curl_init($item['link']);
					if($ch) {
						curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						
						// cURL Getting File
						$ret = curl_exec($ch);
						curl_close($ch);
						
						if(!$ret)
							return 0;
						else {
								$file = fopen($this->settings['file_location'] . $item['file'], "w");
								fputs($file, $ret);
								fclose($file);
								
								// Check if the file size is > 0
								if (@filesize((string) $this->settings['file_location'] . $item['file']) <= 0)
									unlink($this->settings['file_location'] . $item['file']);
									
								$this->save_history(array('file' => $item['file']));
								$this->save_history((string) $item['hash']);

				
						}
						
					}
					
				}
				return 1;
			}
			elseif($this->parsed > 0)
				return 2;
			else
				return 0;
		}
			
	
	}
$scan = new ScanRss();
$scan->construct($start);
?>
