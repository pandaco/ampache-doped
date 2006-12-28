<?php
/*

 Copyright (c) 2001 - 2006 Ampache.org
 All Rights Reserved

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License v2
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. 

*/

/**
 * Album Class
 * This is the class responsible for handling the Album object
 * it is related to the album table in the database.
 */
class Album {

	/* Variables from DB */
	var $id;
	var $name;
	var $year;
	var $prefix;

	/* Art Related Fields */
	var $art;
	var $art_mime; 

	/*!
		@function Album
		@discussion Album class, for modifing a song.
		@param $album_id 	The ID of the song
	 */
	function Album($album_id = 0) {

		if (!$album_id) { return false; } 

		/* Assign id for use in get_info() */
		$this->id = $album_id;

		/* Get the information from the db */
		if ($info = $this->_get_info()) {
			$this->name 		= trim($info['prefix'] . " " . $info['album_name']);
			$this->songs		= $info['song_count'];
			$this->artist_count	= $info['artist_count'];
			$this->year		= $info['year'];
			$this->artist		= trim($info['artist_prefix'] . " " . $info['artist_name']);
			$this->artist_id	= $info['art_id'];
			$this->album		= $info['album_name'];
			$this->has_art		= $info['has_art'];
			$this->prefix 		= $info['prefix'];
		} // if info

		return true; 

	} //constructor

	/*!
		@function get_info
		@discussion get's the vars for $this out of the database 
		@param $this->id	Taken from the object
	*/
	function _get_info() {

		$this->id = intval($this->id); 
	
		/* Grab the basic information from the catalog and return it */
		$sql = "SELECT COUNT(DISTINCT(song.artist)) as artist_count,album.prefix,album.year,album.name AS album_name,COUNT(song.id) AS song_count," .
			"artist.name AS artist_name,artist.id AS art_id,artist.prefix AS artist_prefix,album.art AS has_art ".
			"FROM song,artist,album WHERE album.id='$this->id' AND song.album=album.id AND song.artist=artist.id GROUP BY song.album";

		$db_results = mysql_query($sql, dbh());

		$results = mysql_fetch_assoc($db_results);

		// If there is art then set it to 1, if not set it to 0, we don't want to cary
		// around the full blob with every object because it can be pretty big
		$results['has_art'] = strlen($results['has_art']) ? '1' : '0'; 

		return $results;

	} // _get_info

	/*!
		@function get_songs
		@discussion gets the songs for this album
	*/
	function get_songs($limit = 0) { 

		$results = array();

		$sql = "SELECT id FROM song WHERE album='$this->id' ORDER BY track, title";
		if ($limit) { $sql .= " LIMIT $limit"; }
		$db_results = mysql_query($sql, dbh());

		while ($r = mysql_fetch_object($db_results)) { 
			$results[] = new Song($r->id);
		}

		return $results;

	} // get_songs

	/**
	 * get_song_ids
	 * This returns an array of the song id's that are on this album. This is used by the
	 * show_songs function and can be pased and artist if you so desire to limit it to that
	 */
	function get_song_ids($artist='') { 

		/* If they pass an artist then constrain it based on the artist as well */
		if ($artist) { 
			$artist_sql = " AND artist='" . sql_escape($artist) . "'";
		}
		
		$sql = "SELECT id FROM song WHERE album='" . sql_escape($this->id) . "' $artist_sql ORDER BY track";
		$db_results = mysql_query($sql, dbh());

		$results = array();

		while ($r = mysql_fetch_assoc($db_results)) { 
			$results[] = $r['id'];
		}

		return $results;

	} // get_song_ids

	/**
	 * format
	 * This is the format function for this object. It sets cleaned up
	 * album information with the base required
	 * f_link, f_name
	 */
	function format() { 

	        $web_path = conf('web_path');

		/* Truncate the string if it's to long */
		$name 		= scrub_out(truncate_with_ellipse($this->name,conf('ellipse_threshold_album')));
		$artist		= scrub_out($this->artist);
	        $this->f_name	= "<a href=\"$web_path/albums.php?action=show&amp;album=" . $this->id . "\" title=\"" . scrub_out($this->name) . "\">" . $name . "</a>";
		$this->f_link	= "<a href=\"$web_path/albums.php?action=show&amp;album=" . scrub_out($this->id) . "\" title=\"" . scrub_out($this->name) . "\">" . $name . "</a>";
	        $this->f_songs	= "<div align=\"center\">" . $this->songs . "</div>";
		if ($this->artist_count == '1') { 
		        $this->f_artist	= "<a href=\"$web_path/artists.php?action=show&amp;artist=" . $this->artist_id . "\">" . $artist . "</a>";
		}
		else {
			$this->f_artist = _("Various");
		}

		if ($this->year == '0') { 
			$this->year = "N/A";
		}

	} // format

	/**
	 * format_album
	 * DEPRECIATED DO NOT USE!
	 */
	function format_album() { 
	
		// Call the real function 
		$this->format(); 

	} // format_album

	/**
	 * get_art
	 * This function should be called for gathering and returning Album Art
	 * By default it ignores the DB and looks at the current gathering preferences
	 * as defined by the config file and attempts to find the album art. If the 
	 * param FAST is passed then it will only check the database, if no art is
	 * found it return false. This only return the first art found and should
	 * not be used for the advanced album art finding functions, but for the 
	 * catalog
	 */
	function get_art($fast = 0) { 

		// If we are doing fast then only return
		// what's in the database 
		if ($fast) { 
			return $this->get_db_art(); 
		} 

		/* Create Base Vars */
		$album_art_order = array();

		/* Attempt to retrive the album art order */
		$config_value = conf('album_art_order');
                $class_methods = get_class_methods('Album');		
		
		/* If it's not set */
		if (empty($config_value)) { 
			$album_art_order = array('id3','folder','amazon');
		}
		elseif (!is_array($config_value)) { 
			array_push($album_art_order,$config_value);
		}
		else { 
			$album_art_order = array_merge($album_art_order, conf('album_art_order'));
		}
		
		foreach ($album_art_order AS $method) { 
		
			$method_name = "get_" . $method . "_art";
			
			if (in_array($method_name,$class_methods)) { 
				if ($this->{$method_name}()) {
					return $this->get_db_art();
				} // if method finds the art
			} // if the method exists

		} // end foreach

		return false;
		
	} // get_art

	/*!
		@function get_id3_art
		@discussion looks for art from the id3 tags
	*/
	function get_id3_art() { 

		$songs = $this->get_songs();

		// Foreach songs in this album
		foreach ($songs as $song) {
			// If we find a good one, stop looking
		        $getID3 = new getID3();
		        $id3 = $getID3->analyze($song->file);

			if ($id3['format_name'] == "WMA") { 
				$image = $id3['asf']['extended_content_description_object']['content_descriptors']['13'];
			}
			else {
				$image = $id3['id3v2']['APIC']['0'];
			}
		        if ($image) {
		                $art = $image['data'];
		                $mime = $image['mime'];

		                // Stick it in the db for next time
				$this->insert_art($art,$mime);
	
				return true;
			} // end if image
		} // end foreach

		return false;

	} // get_id3_art

	/*!
		@function get_folder_art()
		@discussion returns the album art from the folder of the mp3s
	*/
	function get_folder_art() { 

		$songs = $this->get_songs();

		/* See if we are looking for a specific filename */
		$preferred_filename = conf('album_art_preferred_filename');
		
		/* Thanks to dromio for origional code */
		/* Added search for any .jpg, png or .gif - Vollmer */
		foreach($songs as $song) { 
			$dir = dirname($song->file);

			/* Open up the directory */
	                $handle = @opendir($dir);

                	if (!is_resource($handle)) {
	                        echo "<font class=\"error\">" . _("Error: Unable to open") . " $dir</font><br />\n";
				debug_event('read',"Error: Unable to open $dir for album art read",'2');
	                }

	                /* Recurse through this dir and create the files array */
	                while ( FALSE !== ($file = @readdir($handle)) ) {
				$extension = substr($file,strlen($file)-3,4);
				
				/* If it's an image file */
				if ($extension == "jpg" || $extension == "gif" || $extension == "png" || $extension == "jp2") { 
				
					if ($file == $preferred_filename) { 
						$found = 1;
						$album_art_filename = array('file' => $file, 'ext' => $extension);
						break;
					}
					elseif (!$preferred_filename) { 
						$found = 1;
						$album_art_filename = array('file' => $file, 'ext' => $extension);
						break;
					}
					else {
						$found = 1;
						$album_art_filename = array('file' => $file, 'ext' => $extension);
					}
				
				} // end if it's an image
				
			} // end while reading dir
			@closedir($handle);

			if ($found) { 
				$handle = fopen($dir."/".$album_art_filename['file'], "rb");
				$mime = "image/" . $album_art_filename['ext'];
				$art = '';
	               		while(!feof($handle)) {
					$art .= fread($handle, 1024);
				}
				fclose($handle);
				$this->insert_art($art,$mime);
	                	return true; 
			} // if found
		} // end foreach songs

		return false;

	} // get_folder_art()

	/*!
		@function get_db_art()
		@discussion returns the album art from the db
	*/
	function get_db_art() {

		$sql = "SELECT art,art_mime FROM album WHERE id='$this->id' AND art_mime IS NOT NULL";
		$db_results = mysql_query($sql, dbh());

		$results = mysql_fetch_object($db_results);
		
		return $results;

	} // get_db_art
	

	/*!
		@function get_amazon_art
		@discussion searches amazon for the 
			album art
	*/
	function get_amazon_art() { 

		$results = $this->find_art();

		if (count($results) < 1) { return false; }

		$snoopy = new Snoopy();
		$snoopy->fetch($results['0']['url']);
		$data = $snoopy->results;

		$this->insert_art($data,$results['0']['mime']);

		return true;

	} // get_amazon_art

	/*!
		@function get_random_songs
		@discussion gets a random number, and 
			a random assortment of songs from this 
			album
	*/
	function get_random_songs() { 

		$results = array();

		$sql = "SELECT id FROM song WHERE album='$this->id' ORDER BY RAND()";
		$db_results = mysql_query($sql, dbh());

		while ($r = mysql_fetch_array($db_results)) { 
			$results[] = $r[0];
		}

		return $results;

	} // get_random_songs

	/*!
		@function clear_art
		@discussion clears the album art from the DB
	*/
	function clear_art() { 
	
		$sql = "UPDATE album SET art=NULL, art_mime=NULL WHERE id='$this->id'";
		$db_results = mysql_query($sql, dbh());

	} // clear_art

	/*!
		@function insert_art
		@discussion this takes a string representation of an image
			and inserts it into the database. You must pass the
			mime type as well
	*/
	function insert_art($image, $mime) { 

		/* Have to disable this for Demo because people suck and try to
 		 * insert PORN :( 
		 */
		if (conf('demo_mode')) { return false; } 

                // Check for PHP:GD and if we have it make sure this image is of some size
        	if (function_exists('ImageCreateFromString')) {
			$im = @ImageCreateFromString($image);
			if (@imagesx($im) == 1 || @imagesy($im) == 1 && $im) {
	                	return false;
	               	}
		} // if we have PHP:GD

                // Push the image into the database
                $sql = "UPDATE album SET art = '" . sql_escape($image) . "'," .
                        " art_mime = '" . sql_escape($mime) . "'" .
        	        " WHERE id = '$this->id'";
	        $db_results = mysql_query($sql, dbh());

		return true;

	} // insert_art

	/*!
		@function find_art
		@discussion searches amazon or a url
			for the album art
		@patch Added Keyword Support (csammis)
		@patch Added Variable Root Amazon Search (nhorloc)
	*/
	function find_art($coverurl = '', $keywords = '') {

		$images 	= array();
		$final_results 	= array();
		$possible_keys = array("LargeImage","MediumImage","SmallImage");
	
		// Prevent the script from timing out
		set_time_limit(0);

		// No coverurl specified search amazon
	        if (empty($coverurl)) { 

		if (empty($keywords)) { 		
			$keywords = $this->name;
			/* If this isn't a various album combine with artist name */
			if ($this->artist_count == '1') { $keywords .= ' ' . $this->artist; }
		}
			
		/* Create Base Vars */
		$amazon_base_urls = array();

		/* Attempt to retrive the album art order */
		$config_value = conf('amazon_base_urls');
               
		/* If it's not set */
		if (empty($config_value)) { 
			$amazon_base_urls = array('http://webservices.amazon.com');
		}
		elseif (!is_array($config_value)) { 
	        	array_push($amazon_base_urls,$config_value);
		}
		else { 
			$amazon_base_urls = array_merge($amazon_base_urls, conf('amazon_base_urls'));
		}

	       /* Foreach through the base urls that we should check */
               foreach ($amazon_base_urls AS $amazon_base) { 

		    	// Create the Search Object
	        	$amazon = new AmazonSearch(conf('amazon_developer_key'), $amazon_base);
			$search_results = array();

			/* Setup the needed variables */
			$max_pages_to_search = max(conf('max_amazon_results_pages'),$amazon->_default_results_pages);
			$pages_to_search = $max_pages_to_search; //init to max until we know better.
			do {
				$search_results = array_merge($search_results, $amazon->search(array('artist' => $artist, 'album' => $albumname, 'keywords' => $keywords)));
				$pages_to_search = min($max_pages_to_search, $amazon->_maxPage);
				debug_event('amazon-xml', "Searched results page " . ($amazon->_currentPage+1) . "/" . $pages_to_search,'5');
				$amazon->_currentPage++;
			} while($amazon->_currentPage < $pages_to_search);
			
			// Only do the second search if the first actually returns something
			if (count($search_results)) { 
				$final_results = $amazon->lookup($search_results);
			}
		
			/* Log this if we're doin debug */
			debug_event('amazon-xml',"Searched using $keywords with " . conf('amazon_developer_key') . " as key " . count($final_results) . " results found",'5');
		} // end foreach
		} // if no cover
		
		// If we've specified a coverurl, create a fake Amazon array with it
		else {
			$final_results = array_merge($final_results, array(array('LargeImage' => $coverurl)));
		}
		
		/* Foreach through what we've found */
		foreach ($final_results as $result) { 

			/* Recurse through the images found */
			foreach ($possible_keys as $key) { 
				if (strlen($result[$key])) { 
					break;
				} 
			} // foreach

			// Rudimentary image type detection, only JPG and GIF allowed.
			if (substr($result[$key], -4 == ".jpg")) {
				$mime = "image/jpg";
			}
			elseif (substr($result[$key], -4 == ".gif")) { 
				$mime = "image/gif";
			}
			else {
				/* Just go to the next result */
				continue;
			}

	                $data['url'] 	= $result[$key];
			$data['mime']	= $mime;
			
			$images[] = $data;

                } // if we've got something
		
		/* Default to false */
		return $images;

	} // find_art 

} //end of album class

?>
