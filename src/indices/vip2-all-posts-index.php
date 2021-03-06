<?php

/*
 * VIP Index containing posts of all statuses and all CPTs (including attachments).
 * Post attachments are indexed using Apache Tikka
 * Does not include revisions.
 */

class VIP2_All_Posts_Index_Builder extends WPES_Abstract_Index_Builder {

	public function get_config( $args ) {
		$defaults = array(
			'lang' => 'en',
		);
		$args = wp_parse_args( $args, $defaults );

		$config = $this->_build_standard_index_config( $args );

		return $config;
	}

	public function get_settings( $args ) {
		$defaults = array(
			'lang' => 'en',
		);
		$args = wp_parse_args( $args, $defaults );

		$analyzer_builder = new WPES_Analyzer_Builder();
		$analyzers = $analyzer_builder->build_analyzers( array( $args['lang'], 'lowercase' ) );

		//use the lang analyzer as the default analyzer for this index
		$analyzer_name = $analyzer_builder->get_analyzer_name( $args['lang'] );
		$analyzers['analyzer']['default'] = $analyzers['analyzer'][$analyzer_name];

		//optimized for replication across a three data center cluster
		//almost never need more than one shard
		$global_settings = array(
			'number_of_shards' => 1,
			'number_of_replicas' => 2,
			'analysis' => $analyzers,
		);

		return $global_settings;
	}

	public function get_mappings( $args ) {
		$defaults = array(
		);
		$args = wp_parse_args( $args, $defaults );

		$mappings = new WPES_WPCOM_Mappings();

		$dynamic_post_templates = array(
			array(
				"tax_template_name" => array(
					"path_match" => "taxonomy.*.name",
					"mapping" => $mappings->text_lcase_raw( 'name' ),
			) ),
			array(
				"tax_template_slug" => array(
					"path_match" => "taxonomy.*.slug",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"tax_template_term_id" => array(
					"path_match" => "taxonomy.*.term_id",
					"mapping" => $mappings->primitive( 'long' ),
			) ),
			array(
				"has_template" => array(
					"path_match" => "has.*",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
			array(
				"shortcode_args_template" => array(
					"path_match" => "shortcode.*.id",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"shortcode_count_template" => array(
					"path_match" => "shortcode.*.count",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
		);

		$dynamic_post_templates[] = array(
			"meta_str_template" => array(
				"path_match" => "meta.*.value",
				"mapping" => $mappings->text_lcase_raw( 'value' ),
			) );
		$dynamic_post_templates[] = array(
			"meta_long_template" => array(
				"path_match" => "meta.*.long",
				"mapping" => $mappings->primitive( 'long' ),
			) );
		$dynamic_post_templates[] = array(
			"meta_bool_template" => array(
				"path_match" => "meta.*.boolean",
				"mapping" => $mappings->primitive( 'boolean' ),
			) );
		$dynamic_post_templates[] = array(
			"meta_float_template" => array(
				"path_match" => "meta.*.double",
				"mapping" => $mappings->primitive( 'double' ),
			) );

		//same mapping for both pages, posts, all custom post types
		$post_mapping = array(
			'dynamic_templates' => $dynamic_post_templates,
			'_all' => array( 'enabled' => false ),
			'properties' => array(

				//////////////////////////////////
				//Blog/Post meta fields

				'post_id'               => $mappings->primitive_stored( 'long' ),
				'blog_id'               => $mappings->primitive_stored( 'integer' ),
				'site_id'               => $mappings->primitive( 'short' ),
				'post_type'             => $mappings->keyword(),
				'post_format'           => $mappings->keyword(),
				'post_mime_type'        => $mappings->text_raw(),
				'post_status'           => $mappings->keyword(),
				'public'                => $mappings->primitive( 'boolean' ),
				'has_password'          => $mappings->primitive( 'boolean' ),

				'parent_post_id'        => $mappings->primitive( 'long' ),
				'ancestor_post_ids'     => $mappings->primitive( 'long' ),

				'menu_order'            => $mappings->primitive( 'integer' ),

				'lang'                  => $mappings->keyword(),

				'url'                   => $mappings->text_raw( 'url' ),
				'slug'                  => $mappings->keyword(),

				'date'                  => $mappings->datetime(),
				'date_token'            => $mappings->datetimetoken(),
				'date_gmt'              => $mappings->datetime_stored(),
				'date_gmt_token'        => $mappings->datetimetoken(),
				'modified'              => $mappings->datetime(),
				'modified_token'        => $mappings->datetimetoken(),
				'modified_gmt'          => $mappings->datetime(),
				'modified_gmt_token'    => $mappings->datetimetoken(),

				'sticky'                => $mappings->primitive( 'boolean' ),

				//////////////////////////////////
				//Post Content fields

				'author'                => $mappings->text_raw( 'author' ),
				'author_login'          => $mappings->keyword(),
				'author_id'             => $mappings->primitive( 'integer' ),
				'title'                 => $mappings->text_count( 'title' ),
				'content'               => $mappings->text_count( 'content' ),
				'excerpt'               => $mappings->text_count( 'excerpt' ),
				'tag_cat_count'         => $mappings->primitive( 'short' ),
				'tag'                   => $mappings->tagcat( 'tag' ),
				'category'              => $mappings->tagcat( 'category' ),
				'mlt_content'           => $mappings->text(),

				'file'                  => $mappings->file_attachment(),

				//taxonomy.*.* added as dynamic template

				//////////////////////////////////
				//Embedded Media/Shortcodes/etc

				//has.* added as dynamic template

				'link'                  => $mappings->url_analyzed(),
				'image'                 => $mappings->url(),
				'shortcode_types'       => $mappings->keyword(),
				'embed'                 => $mappings->url(),
				'featured_image'        => $mappings->keyword(),
				'featured_image_url'    => $mappings->url(),
				'hashtag' => array(
					'type' => 'object',
					'properties' => array(
						'name'          => $mappings->keyword(),
					),
				),
				'mention' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name'  => $mappings->keyword(),
								'lc'    => $mappings->keyword_lcase(),
							),
						),
					),
				),

				//////////////////////////////////
				//Comments

				'commenter_ids'         => $mappings->primitive( 'integer' ),
				'comment_count'         => $mappings->primitive( 'integer' ),

				//////////////////////////////////
				//WP.com extras
				'date_added'                => $mappings->datetime(),
				'liker_ids'                 => $mappings->primitive( 'integer' ),
				'like_count'                => $mappings->primitive( 'short' ),
				'is_reblogged'              => $mappings->primitive( 'boolean' ),
				'reblogger_ids'             => $mappings->primitive( 'integer' ),
				'reblog_count'              => $mappings->primitive( 'short' ),
				'location'                  => $mappings->geo(),

			)
		);

		//TODO correct urls with the latest stuff from global2 index

		return array(
			'post' => $post_mapping,
		);
	}

	public function get_doc_callbacks() {
		return array(
			'post' => 'VIP2_Post_All_Posts_Doc_Builder',
		);
	}

}

class VIP2_Post_All_Posts_Doc_Builder extends WPES_Abstract_Document_Builder {
	protected $_statii_blacklist = array(
		'auto-draft', //revisions
	);

	public function get_id( $args ) {
		return $args['blog_id'] . '-p-' . $args['id'];
	}

	public function get_type( $args ) {
		return 'post';
	}

	public function doc( $args ) {
		//use the extended version of WPES_WP_Post_Field_Builder() so it
		// also handles Jetpack sites and custom WP.com stuff such as likes
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		$post_fld_bldr->index_media = true;

		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['id'] );
		if ( !$post ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $post_fld_bldr->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $post_fld_bldr->clean_short( $blog->site_id, 'site_id' ),
		);
		$lang_data = $post_fld_bldr->post_lang( $args['blog_id'], $post );
		$post_data = $post_fld_bldr->post_fields( $post, $lang_data['lang'] );
		$post_data['public'] = $post_fld_bldr->is_post_public( $args['blog_id'], $post->ID );
		$tax_data = $post_fld_bldr->taxonomy( $post );
		$added_on_data = $post_fld_bldr->added_on( $post );

		$commenters_data = $post_fld_bldr->commenters( $args['blog_id'], $post );
		$reblog_data = $post_fld_bldr->reblogs( $args['blog_id'], $post );
		$likers_data = $post_fld_bldr->likers( $args['blog_id'], $post );
		$geo_data = $post_fld_bldr->geo( $post );

		$feat_img_data = $post_fld_bldr->featured_image( $post );
		$media_data = $post_fld_bldr->extract_media( $args['blog_id'], $post );
		$meta_data = $post_fld_bldr->meta(
			$post,
			apply_filters( 'wpcom_elasticsearch_meta_blacklist_filter', array() ),
			apply_filters( 'wpcom_elasticsearch_meta_whitelist_filter', array( '_thumbnail_id', '_wp_old_slug' ) )
		);

		$mlt_content_data = $post_fld_bldr->mlt_content( array(
			'content' => $post_data['content'],
			'title' => $post_data['title'],
			'excerpt' => $post_data['excerpt'],
			'tags' => isset( $tax_data['tag'] ) ? wp_list_pluck( $tax_data['tag'], 'name' ) : array(),
			'cats' => isset( $tax_data['category'] ) ? wp_list_pluck( $tax_data['category'], 'name' ) : array(),
		) );

		$data['post_mime_type'] = $post_fld_bldr->clean_string( $post->post_mime_type );
		$data['file'] = $post_fld_bldr->attached_files( $args['blog_id'], $post );
		if ( empty( $data['file'] ) )
			unset( $data['file'] );

		$data = array_merge(
			$data,
			$lang_data,
			$post_data,
			$tax_data,
			$added_on_data,
			$commenters_data,
			$reblog_data,
			$likers_data,
			$geo_data,
			$media_data,
			$feat_img_data,
			$meta_data,
			$mlt_content_data
		);

		restore_current_blog();
		return $data;
	}

	public function get_coupled_docs( $args ) {
		switch_to_blog( $args['blog_id'] );
		$children = get_children( array(
			'post_parent' => $args['id'],
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
		) );
		restore_current_blog();
		$children = array_keys( $children );

		if ( !empty( $children ) )
			return array( 'post' => $children );
		return false;
	}

	public function is_indexable( $args ) {
		if ( ! $this->is_indexing_enabled( $args ) )
			return false;

		//We index all post types and most all post statii
		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['id'] );
		if ( ! $post ) {
			restore_current_blog();
			return false;
		}

		if ( 'revision' == $post->post_type ) {
			restore_current_blog();
			return false;
		}

		$post_statii = $this->_get_disallowed_statii();
		$post_status = get_post_status( $post->ID );
		if ( in_array( $post_status, $post_statii ) ) {
			restore_current_blog();
			return false;
		}

		restore_current_blog();
		return true;
	}

	public function update( $args ) {
		$post_fld_bldr = new WPES_WPCOM_Post_Field_Builder();
		return $post_fld_bldr->get_update_script( $args );
	}

	public function get_doc_iterator( $args ) {
		$it = new WPES_WP_Post_Iterator();
		$it->init( array(
			'blog_id' => $args['blog_id'],
			'sql_where' => $this->_get_disallowed_statii( true ),
			'doc_builder' => $this,
			'start' => $args['start'],
		) );
		return $it;
	}

	protected function _get_disallowed_statii( $as_sql_in = false ) {
		if ( $as_sql_in ) {
			// Live dangerously
			return " post_status NOT IN ( '" . implode( "','", $this->_statii_blacklist ) .  "' ) ";
		} else {
			return $this->_statii_blacklist;
		}
	}


}
