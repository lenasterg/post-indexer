<?php

// A class that contains the database functions used within the post indexer plugin

if(!class_exists('postindexermodel')) {

	class postindexermodel {

		var $build = 1;

		var $db;

		// tables list
		var $oldtables =  array( 'site_posts', 'term_counts', 'site_terms', 'site_term_relationships' );
		var $tables = array( 'network_posts', 'network_rebuildqueue', 'network_postmeta', 'network_terms', 'network_term_taxonomy', 'network_term_relationships' );

		// old table variables
		var $site_posts;
		var $term_counts;
		var $site_terms;
		var $site_term_relationships;

		// new table variables
		var $network_posts;
		var $network_rebuildqueue;
		var $network_postmeta;
		var $network_terms;
		var $network_term_taxonomy;
		var $network_term_relationships;

		function postindexermodel() {
			$this->__construct();
		}

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach($this->tables as $table) {
				$this->$table = $this->db->base_prefix . $table;
			}

			if(defined('PI_LOAD_OLD_TABLES') && PI_LOAD_OLD_TABLES === true ) {
				foreach($this->oldtables as $table) {
					$this->$table = $this->db->base_prefix . $table;
				}
			}

			$version = get_site_option('postindexer_version', false);
			if($version === false || $version < $this->build) {
				update_site_option('postindexer_version', $this->build);
				$this->build_indexer_tables( $version );
			}

		}

		function build_indexer_tables( $old_version ) {

			switch( $old_version ) {

				case 1:
							break;

				default:	$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_posts . "` (
							  `BLOG_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `ID` bigint(20) unsigned NOT NULL,
							  `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_content` longtext NOT NULL,
							  `post_title` text NOT NULL,
							  `post_excerpt` text NOT NULL,
							  `post_status` varchar(20) NOT NULL DEFAULT 'publish',
							  `comment_status` varchar(20) NOT NULL DEFAULT 'open',
							  `ping_status` varchar(20) NOT NULL DEFAULT 'open',
							  `post_password` varchar(20) NOT NULL DEFAULT '',
							  `post_name` varchar(200) NOT NULL DEFAULT '',
							  `to_ping` text NOT NULL,
							  `pinged` text NOT NULL,
							  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_content_filtered` longtext NOT NULL,
							  `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `guid` varchar(255) NOT NULL DEFAULT '',
							  `menu_order` int(11) NOT NULL DEFAULT '0',
							  `post_type` varchar(20) NOT NULL DEFAULT 'post',
							  `post_mime_type` varchar(100) NOT NULL DEFAULT '',
							  `comment_count` bigint(20) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`BLOG_ID`,`ID`),
							  KEY `post_name` (`post_name`),
							  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
							  KEY `post_parent` (`post_parent`),
							  KEY `post_author` (`post_author`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_rebuildqueue . "` (
							  `blog_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `rebuild_updatedate` timestamp NULL DEFAULT NULL,
							  `rebuild_progress` bigint(20) unsigned DEFAULT NULL,
							  PRIMARY KEY (`blog_id`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_postmeta . "` (
							  `blog_id` bigint(20) unsigned NOT NULL,
							  `meta_id` bigint(20) unsigned NOT NULL,
							  `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `meta_key` varchar(255) DEFAULT NULL,
							  `meta_value` longtext,
							  PRIMARY KEY (`blog_id`,`meta_id`),
							  KEY `post_id` (`post_id`),
							  KEY `meta_key` (`meta_key`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_terms . "` (
							  `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `name` varchar(200) NOT NULL DEFAULT '',
							  `slug` varchar(200) NOT NULL DEFAULT '',
							  `term_group` bigint(10) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`term_id`),
							  UNIQUE KEY `slug` (`slug`),
							  KEY `name` (`name`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_term_taxonomy . "` (
							  `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `taxonomy` varchar(32) NOT NULL DEFAULT '',
							  `description` longtext NOT NULL,
							  `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `count` bigint(20) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`term_taxonomy_id`),
							  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
							  KEY `taxonomy` (`taxonomy`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_term_relationships . "` (
							  `blog_id` bigint(20) unsigned NOT NULL,
							  `object_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `term_order` int(11) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`blog_id`,`object_id`,`term_taxonomy_id`),
							  KEY `term_taxonomy_id` (`term_taxonomy_id`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							break;
			}

		}

		function get_justqueued_blogs( $limit = 25 ) {

			$sql = $this->db->prepare("SELECT * FROM {$this->network_rebuildqueue} WHERE rebuild_progress = 0 ORDER BY rebuild_updatedate ASC LIMIT %d", $limit );
			$queue = $this->db->get_results( $sql );

			return $queue;
		}

		function get_rebuilding_blogs( $limit = 5 ) {

			$sql = $this->db->prepare("SELECT * FROM {$this->network_rebuildqueue} WHERE rebuild_progress > 0 ORDER BY rebuild_updatedate ASC LIMIT %d", $limit );
			$queue = $this->db->get_results( $sql );

			return $queue;

		}

		function blogs_for_rebuilding() {
			$sql = $this->db->prepare( "SELECT count(*) as rebuildblogs FROM {$this->network_rebuildqueue}" );

			$var = $this->db->get_var( $sql );

			if(empty($var) || $var == 0) {
				return false;
			} else {
				return true;
			}
		}

		// Rebuild blogs

		function rebuild_blog( $blog_id, $progress = 0 ) {

			$this->insert_or_update( $this->network_rebuildqueue, array( 'blog_id' => $blog_id, 'rebuild_updatedate' => current_time('mysql'), 'rebuild_progress' => $progress ) );

		}

		function rebuild_all_blogs() {

			$sql = $this->db->prepare( "DELETE FROM {$this->network_rebuildqueue}");
			$this->db->query( $sql );

			$sql = $this->db->prepare( "INSERT INTO {$this->network_rebuildqueue} SELECT blog_id, timestamp(now()), 0 FROM {$this->db->blogs}");
			$this->db->query( $sql );

		}

		function is_in_rebuild_queue( $blog_id ) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $blog_id );

			$row = $this->db->get_row( $sql );

			if( !empty($row) && $row->blog_id == $blog_id ) {
				return true;
			} else {
				return false;
			}

		}

		function disable_indexing_for_blog( $blog_id ) {

			// Switch off indexing
			update_blog_option( $blog_id, 'postindexer_active', 'no' );

			// Remove any entry from the rebuild queue
			$this->remove_blog_from_queue( $blog_id );

			// Remove the existing entries
			$this->remove_indexed_entries_for_blog( $blog_id );

		}

		function enable_indexing_for_blog( $blog_id ) {

			// Switch on the indexing
			update_blog_option( $blog_id, 'postindexer_active', 'yes' );

			// Queue the site for rebuilding
			$this->rebuild_blog( $blog_id );
		}

		function remove_indexed_entries_for_blog( $blog_id ) {

			// Remove all the networked posts for the blog id
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_posts} WHERE BLOG_ID = %d", $blog_id ) );

			// Remove all the networked postmeta for the blog id
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_postmeta} WHERE blog_id = %d", $blog_id ) );

			// Remove all the networked term relationship information for the blog_id
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_term_relationships} WHERE blog_id = %d", $blog_id ) );

		}

		function remove_blog_from_queue( $blog_id ) {

			// Remove the blog from the queue
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $blog_id ) );

		}

		// Insert on duplicate update function
		function insert_or_update( $table, $query ) {

				$fields = array_keys($query);
				$formatted_fields = array();
				foreach ( $fields as $field ) {
					$form = '%s';
					$formatted_fields[] = $form;
				}
				$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
				$sql .= " ON DUPLICATE KEY UPDATE ";

				$dup = array();
				foreach($fields as $field) {
					$dup[] = "`" . $field . "` = VALUES(`" . $field . "`)";
				}

				$sql .= implode(',', $dup);

				return $this->db->query( $this->db->prepare( $sql, $query ) );

		}


	}

}

?>