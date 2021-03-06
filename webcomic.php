<?php
/*
Text Domain: 2dwebcomic
Plugin Name: Webcomic-TwoDots
Plugin URI: http://mphys.com/
Description: Webcomic adds a collection of new features to WordPress designed specifically for publishing webcomics, developing webcomic themes, and managing webcomic sites.
Version: 2.1.1
Author: Michael Sisk (Maintained by: Andrew Naylor)
Author URI: http://maikeruon.com/

Copyright 2008 - 2009 Michael Sisk (email: mike@maikeruon.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//
// Base Loaders
//

/**
 * Loads the webcomic domain for translations.
 * 
 * @package Webcomic
 * @since 1.6.0
 */
function load_webcomic_domain() { load_plugin_textdomain( 'webcomic', PLUGINDIR . '/' . dirname( plugin_basename( __FILE__ ) ), dirname( plugin_basename( __FILE__ ) ) ); }

/**
 * Creates the default Webcomic settings.
 * 
 * This funciton should only run when the plugin is first activated.
 * It will attempt to create all of the default Webcomic settings and
 * standard comic directories, and upgrade older features as necessary.
 * 
 * @package Webcomic
 * @since 1.0.0
 */
if ( !get_option( 'webcomic_version' ) || '2.1.1' != get_option( 'webcomic_version' ) ) {
    function webcomic_install() {
        load_webcomic_domain();
        
        $default_category = get_option( 'default_category' );
        
        /** Make sure all of our default options have a value. */
        add_option( 'comic_category', array( $default_category ) );
        add_option( 'comic_directory', 'comics' );
        add_option( 'comic_current_chapter', array( $default_category => '-1' ) );
        add_option( 'comic_secure_paths', '' );
        add_option( 'comic_secure_names', '' );
        add_option( 'comic_transcripts_allowed', '' );
        add_option( 'comic_transcripts_required', '' );
        add_option( 'comic_transcripts_loggedin', '' );
        add_option( 'comic_feed', '1' );
        add_option( 'comic_feed_size', 'full' );
        add_option( 'comic_buffer', '1' );
        add_option( 'comic_buffer_alert', '7' );
        add_option( 'comic_keyboard_shortcuts', '' );
        add_option( 'comic_thumb_crop', '' );
        add_option( 'comic_large_size_w', get_option( 'large_size_w' ) );
        add_option( 'comic_large_size_h', get_option( 'large_size_h' ) );
        add_option( 'comic_medium_size_w', get_option( 'medium_size_w' ) );
        add_option( 'comic_medium_size_h', get_option( 'medium_size_h' ) );
        add_option( 'comic_thumb_size_w', get_option( 'thumbnail_size_w' ) );
        add_option( 'comic_thumb_size_h', get_option( 'thumbnail_size_h' ) );
        
        /** Make sure our default comic directories exist. */
        if ( file_exists( ABSPATH . 'wpmu-settings.php' ) && !file_exists( BLOGUPLOADDIR ) ) //WPMU Check
            mkdir( BLOGUPLOADDIR, 0775, true );
        
        if ( !file_exists( get_comic_directory( 'abs', true ) ) )
            if ( !mkdir( get_comic_directory( 'abs', true ), 0775, true ) )
                $mkdir_error = sprintf( __( 'Webcomic was not able to create the default comic directories. If this problem persists after <a href="%s">update your settings</a> you will need to create them yourself.', 'webcomic' ), 'admin.php?page=comic-settings' );
        
        /** Add or update the 'webcomic_version' setting and create the first series as necessary */
        if ( get_option( 'webcomic_version' ) ) {
            update_option( 'webcomic_version', '2.1.1' );
        } else {
            add_option( 'webcomic_version', '2.1.1' );
            
            if ( !get_the_collection( 'hide_empty=0&depth=1' ) ) {
                $first_series = get_term( $default_category, 'category' );
                wp_insert_term( $first_series->name, 'chapter' );
            }
        }
        
        /** Setup our buffer alert scheduled task hook */
        if ( !wp_next_scheduled( 'webcomic_buffer_alert' ) )
            wp_schedule_event( 0, 'daily', 'webcomic_buffer_alert' );
        
        echo '<div class="updated fade"><p>' . sprintf( __( 'Thanks for choosing Webcomic! Please <a href="%s">update your settings</a>.', 'webcomic' ), 'admin.php?page=comic-settings' ) . '</p></div>';
        
        if ( $mkdir_error )
            echo '<div id="message" class="error"><p>' . $mkdir_error . '</p></div>';
    } add_action( 'admin_notices', 'webcomic_install' );
}



//
// Data Retrieval
//

/**
 * Returns the appropriate include URL.
 * 
 * This is an internal utility function for determining the correct
 * path (absolute path, relative path, or url path) to a file in
 * Webcomic's "includes" directory based on it's installed location:
 * either a regular plugins folder or WordPress MU's special "mu-plugins" folder.
 * 
 * @package Webcomic
 * @since 2.0.0
 * 
 * @param str $file The file to include (required).
 * @param str $type The type of URL to return, one of 'abs', 'url', or 'rel'.
 */
function webcomic_include_url( $file = false, $type = false ) {
    if ( !$file )
        return; //Must specify a file
    
    $mu_check = pathinfo( __FILE__ );
    
    if ( strstr( $mu_check[ 'dirname' ], 'mu-plugins' ) ) {
        switch ( $type ) {
            case 'abs' : return WP_CONTENT_DIR . '/mu-plugins/includes/' . $file;
            case 'rel' : return 'includes/' . $file;
            case 'url' :
            default    : return WP_CONTENT_URL . '/mu-plugins/includes/' . $file;
        }
    } else {
        switch ( $type ) {
            case 'abs' : return WP_PLUGIN_DIR . '/2dwebcomic/includes/' . $file;
            case 'rel' : return '2dwebcomic/includes/' . $file;
            case 'url' :
            default    : return plugins_url( '2dwebcomic/includes/' . $file );
        }
    }
}

/**
 * Returns the specified comic category.
 * 
 * This is a utility funciton for retrieving a comic category. If no
 * 'id' is specified, the first comic category is returned. An array
 * containing all comic categories is returned if 'id' is set to 'all'.
 * 
 * @package Webcomic
 * @since 1.0.0
 * 
 * @param bool $all Return all comic categories as an array.
 * @param str $format The format multiple categories should be returned in.
 * @return int|arr ID of the first comic category or an array of all comic categories.
 */
function get_comic_category( $all = false, $format = false ) {
    $category = get_option( 'comic_category' );
    
    if ( $all ) {
        if ( 'include' == $format ) {
            return implode( ',', $category );
        } elseif ( 'exclude' == $format) {
            return '-' . implode( ',-', $category );
        } elseif ( 'random' == $format ) {
            $key = array_rand( $category );
            return $category[ $key ];
        } elseif ( 'post_link_exclude' == $format ) {
            return implode( ' and ', $category );
        } else {
            return $category;
        }
    } else {
        return ( int ) $category[ 0 ];
    }
}

/**
 * Returns the the specified comic directory.
 * 
 * This is a utility funciton for retrieving the specified comic
 * directory. This function can return either the absolute or url
 * path to the comic root or 'thumbs' directory.
 * 
 * @package Webcomic
 * @since 1.0.0
 * 
 * @param str $type The type of path to return, 'abs' or 'url'.
 * @param bool $thumbs Return the comic thumbnail directory.
 * @param int $category ID of a specified comic category.
 * @return str Path to the comic root or thumbnail directory.
 */
function get_comic_directory( $type = 'abs', $thumbs = false, $category = false ) {
    if ( 'root' == $type )
        return get_option( 'siteurl' ) . '/' . get_option( 'comic_directory' ) . '/';
    
    $prepend = ( 'abs' == $type ) ? ABSPATH : get_option( 'siteurl' ) . '/';
    $prepend = ( 'abs' == $type ) ? ABSPATH : ((!get_option('alternate_image_root')) ? get_option('siteurl') : get_option('alternate_image_root')).'/';
    
    if ( file_exists( ABSPATH . 'wpmu-settings.php' ) ) //WPMU Check
        $prepend = ( 'abs' == $type ) ? BLOGUPLOADDIR : get_option( 'siteurl' ) . '/files/';
    
    $catid  = ( $category ) ? $category : get_comic_category();
    $cat    = get_category( $catid );
    
    $catdir = '/' . $cat->slug;
    $catdir = '';
    
    if ( $thumbs )
        return $prepend . get_option( 'comic_directory' ) . $catdir . '/thumbs/';
    
    return $prepend . get_option( 'comic_directory' ) . $catdir . '/';
}

/**
 * Returns the the current comic chapter for the specified series.
 * 
 * This is a utility funciton for retrieving the current chapter for the
 * specified comic series, or all current chapters if $series is set
 * to 'all'.
 * 
 * @package Webcomic
 * @since 1.0.0
 * 
 * @param int|str $series ID of a specific comic category or -1.
 * @return int|array ID of the slected current comic chapter or all comic chapters.
 */
function get_comic_current_chapter( $series = false ) {
    $current_chapters = get_option( 'comic_current_chapter' );
    
    if ( !$series )
        return array_shift( array_values( $current_chapters ) );
    elseif ( true === $series )
        return $current_chapters;
    else
        return $current_chapters[ $series ];
}

/**
 * Retrieves the comic category for a given post.
 * 
 * This is a utility function to determine which (if any) comic category
 * a given post belongs to. If a match is found, the comic category ID
 * is returned immediately.
 * 
 * @package Webcomic
 * @since 1.8.0
 * 
 * @param int $id Post ID.
 * @return int Category ID.
 */
function get_post_comic_category( $id = false ) {
    global $post;
    
    $id = ( $id ) ? ( int ) $id : $post->ID;
    
    $post_cats  = wp_get_object_terms( $id, 'category', array( 'fields' => 'ids' ) );
    $comic_cats = get_comic_category( true );
    
    if ( $comic_cats )
        foreach ( $post_cats as $post_cat )
            foreach ( $comic_cats as $comic_cat )
                if ( $post_cat == $comic_cat )
                    return ( int ) $comic_cat;
}

/**
 * Retrieves the chapter objects for a given post.
 * 
 * This is a utility function used to generate an object of taxonomy
 * objects for the specified or current posts chapter, volume, and series.
 * 
 * @package Webcomic
 * @since 1.8.0
 * 
 * @param int $id A valid post ID.
 * @return obj Object containg chapter taxonomy objects.
 */
function get_post_comic_chapters( $id = false ) {
    global $post;
    
    $id = ( $id ) ? ( int ) $id : $post->ID;
    
    $chapters = wp_get_object_terms( $id, 'chapter' );
    
    if ( !$chapters )
        return; //The post does not beling to any chapters
    
    $post_chapters = new stdClass();
    
    foreach ( $chapters as $value ) {
        if ( !$value->parent )
            $post_chapters->series = $value;
        elseif ( !get_term_children( $value->term_id, 'chapter' ) )
            $post_chapters->chapter = $value;
        else
            $post_chapters->volume = $value;
    }
    
    return $post_chapters;
}

/**
 * Returns the current webcomic series ID based on the requested path.
 * 
 * This funciton checks the requrested URL for various parameters
 * in an attempt to find what comic series, if any, the requested
 * pages is associated with. If the page is associated witha comic
 * series the series ID is returned.
 * 
 * @package Webcomic
 * @since 2.0.0
 * 
 * @return Category ID.
 */
function get_series_by_path() {
    if ( get_option( 'permalink_structure' ) ) {
        if ( $_SERVER[ 'HTTPS' ] )
            $s = ( 'on' == $_SERVER[ 'HTTPS' ] ) ? 's' : '';
        
        $port = ( '80' == $_SERVER[ 'SERVER_PORT' ] ) ? '' : ':' . $_SERVER[ 'SERVER_PORT' ];
        $url  = "http$s://" . $_SERVER[ 'SERVER_NAME' ] . $port . $_SERVER[ 'REQUEST_URI' ];
        list( $url ) = explode( '?', $url );
        
        $pid = url_to_postid( $url );
        
        list( $url ) = explode( '/page/', $url );
        
        $cid = get_category_by_path( $url, false );
        $cid = $cid->cat_ID;
        
        $url  = rawurlencode( urldecode( $url ) );
        $url  = str_replace( '%2F', '/', $url );
        $url  = str_replace( '%20', ' ', $url );
        $urls = '/' . trim( $url, '/' );
        $slug = sanitize_title( basename( $urls ) );
        
        $chapter = get_term_by( 'slug', $slug, 'chapter' );
        
        if ( $chapter ) {
            if ( $chapter->parent ) {
                $chapter = get_term( $chapter->parent, 'chapter' );
                
                if ( $chapter->parent  )
                    $chapter = get_term( $chapter->parent, 'chapter' );
            }
            
            $sid = $chapter->term_id;
        }
    } else {
        $pid = ( $_GET[ 'p' ] ) ? $_GET[ 'p' ] : $_GET[ 'page_id' ];
        $cid = $_GET[ 'cat' ];
    }
    
    if ( get_post_meta( $pid, 'comic_series', true ) )
        $pid = get_post_meta( $pid, 'comic_series', true );
    else
        $pid = get_post_comic_category( $pid );
    
    if ( $pid )
        return get_category( $pid );
    elseif ( $cid )
        return get_category( $cid );
    elseif ( $sid )
        return get_category( $sid );
}



//
// Search Unification
//

/**
 * Removes duplicates from search results.
 * 
 * @package Webcomic
 * @since 1.5.0
 */
function webcomic_post_request( $query ) {
    global $wp_query;
    
    if ( $wp_query->is_search && false === strpos( $where, 'DISTINCT' ) )
        $query = str_replace( 'SELECT', 'SELECT DISTINCT', $query );
    
    return $query;
} add_filter( 'posts_request', 'webcomic_post_request' );

/**
 * Adds post meta data to the search query.
 * 
 * @package Webcomic
 * @since 1.5.0
 */
function webcomic_posts_join( $join ) {
    global $wp_query, $wpdb;
    
    if ( $wp_query->is_search )
        $join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
    
    return $join;
} add_filter( 'posts_join', 'webcomic_posts_join' );

/**
 * Adds specific checks for finding content based on the 'comic_description'
 * and 'comic_transcript' custom field matches.
 * 
 * @package Webcomic
 * @since 1.5.0
 */
function webcomic_posts_where( $where ) {
    global $wp_query, $wpdb;

    $i = 0;
    $query_terms = explode( ' ', isset($wp_query->query_vars[ 's' ]) ? $wp_query->query_vars[ 's' ] : "" );

    $or = '(';

    foreach ( $query_terms as $query_term ) {
        if ( $query_term !== '' ) {
            $or .= "(($wpdb->posts.post_title LIKE '%" . $wpdb->escape( $query_term ) . "%') OR ($wpdb->posts.post_content LIKE '%" . $wpdb->escape( $query_term ) . "%') OR (($wpdb->postmeta.meta_key = 'comic_transcript' OR $wpdb->postmeta.meta_key = 'comic_description') AND $wpdb->postmeta.meta_value LIKE '%" . $wpdb->escape( $query_term ) . "%')) OR ";
            $i++;
        }
    }

    if ( $i > 1 )
        $or .= "(($wpdb->posts.post_title LIKE '" . $wpdb->escape( $wp_query->query_vars[ 's' ] ) . "') OR ($wpdb->posts.post_content LIKE '" . $wpdb->escape( $wp_query->query_vars[ 's' ] ) . "') OR (($wpdb->postmeta.meta_key = 'comic_transcript' OR $wpdb->postmeta.meta_key = 'comic_description') AND $wpdb->postmeta.meta_value LIKE '%" . $wpdb->escape( $wp_query->query_vars[ 's' ] ) . "%')))";
    else
        $or = rtrim( $or, ' OR ') . ')';

    $where = preg_replace( "/\(\(\(.*\)\)/i", $or, $where, 1 );

    return $where;
} add_filter( 'posts_where', 'webcomic_posts_where' );



//
// Comic Templates
//

/**
 * Loads a comic-specific template and sets the $webcomic_series global.
 * 
 * This function checks the requested url to see if the
 * requested page is either directly related to a specific
 * comic category or has the 'comic_series' custom field set.
 * If either is true, it will check if a template exists in
 * "webcomic-cat-slug" and load that template instead, if one exists.
 * 
 * @package Webcomic
 * @since 2.0.0
 * 
 * @param str $template The current template string.
 * @return str New theme directory or $template
 */
function webcomic_series_template() {
    global $webcomic_series;
    return '';
    $webcomic_series = get_series_by_path();
    
    if ( $webcomic_series && is_dir( get_theme_root() . '/webcomic-' . $webcomic_series->slug ) ) {
        function load_series_template() {
            global $webcomic_series;
            
            return 'webcomic-' . $webcomic_series->slug;
        } add_filter( 'stylesheet', 'load_series_template' );
    }
} add_action( 'template_redirect', 'webcomic_series_template' );



//
// Other Stuff
//

/**
 * Registers the 'chapter' taxonomy.
 * 
 * @package Webcomic
 * @since 2.0.0
 */
function webcomic_init() {
    register_taxonomy( 'chapter', 'post', array( 'hierarchical' => true, 'update_count_callback' => '_update_post_term_count', 'label' => 'Chapter' ) );
} add_action( 'init', 'webcomic_init' );

/**
 * Displays the comic in the site feed.
 * 
 * @package Webcomic
 * @since 1.0.0
 */
if ( get_option( 'comic_feed' ) ) {
    function webcomic_feed( $content ) {
        if ( is_feed() ) $prepend = ( in_comic_category() ) ? '<p>' . get_comic_object( get_the_comic(), get_option( 'comic_feed_size' ) ) . '</p>' : '';
        
        return $prepend . $content; 
    } add_filter( 'the_excerpt_rss', 'webcomic_feed' );
}

/**
 * Sends buffer comic notifications on a daily basis.
 * 
 * @package Webcomic
 * @since 2.1.0
 */
if ( get_option( 'comic_buffer' ) ) {
    function webcomic_buffer_alert() {
        $cats = get_comic_category( true );
        $now  = time();
        
        foreach ( $cats as $cat ) {
            $buffer = get_comic_buffer( $cat );
            $info   = get_term( $cat, 'category' );
            $eta    = floor( ( $buffer->timestamp - $now ) / 86400 );
            
            if ( $buffer && $eta == get_option( 'comic_buffer_alert' ) )
                @wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Buffer Alert for %s', 'webcomic' ), html_entity_decode( get_option( 'blogname' ) ), html_entity_decode( $info->name ) ), sprintf( __( 'This is an automated reminder that your buffer for %s will run out on %s ($d days away).', 'webcomic' ), html_entity_decode( $info->name ), $buffer->datetime, get_option( 'comic_buffer_alert' ) - $eta ) );
        }
    } add_action( 'webcomic_buffer_alert', 'webcomic_buffer_alert' );
}

/**
 * Handles secure URL's, transcript.php form requests, and enqueue's necessary javascript.
 * 
 * @package Webcomic
 * @since 2.0.0
 */
function webcomic_template_redirect() {
    //Display the specified comic object from a secured URL
    if ( isset($_GET[ 'comic_object' ]) ) {
        $info  = explode( '/', $_GET[ 'comic_object' ] );
        $comic = get_the_comic( $info[ 0 ] );
            
        $headers = ( function_exists( 'getallheaders' ) ) ? getallheaders() : false;
        $size    = ( $info[ 1 ] ) ? $info[ 1 ] : 'full';
        $dir     = get_post_comic_category( $comic->ID );
        $fpath   = get_comic_directory( 'abs', false, $dir );
        $tpath   = get_comic_directory( 'abs', true, $dir );
        
        if ( 'full' == $size || $comic->flash )
            $size = $fpath . $comic->file_name;
        
        if ( 'large' == $size )
            $size = ( $comic->large ) ? $tpath . $comic->large_name : 'medium';
        
        if ( 'medium' == $size )
            $size = ( $comic->medium ) ? $tpath . $comic->medium_name : 'thumb';
        
        if ( 'thumb' == $size )
            $size = ( $comic->thumb ) ? $tpath . $comic->thumb_name : $fpath . $comic->file_name;
        
        if ( $headers && isset( $headers[ 'If-Modified-Since' ] ) && ( strtotime( $headers[ 'If-Modified-Since' ] ) == filemtime( $size ) ) ) {
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $size ) ) . ' GMT', true, 304 );
        } else {
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $size ) ) . ' GMT', true, 200 );
            header( 'Content-Length: ' . filesize( $size ) );
            header( 'Content-Type: ' . $comic->file_data[ 'mime' ] );
            
            die( readfile( $size ) );
        }
    }
    
    //Enqueue javascript required for various Webcomic features
    //wp_enqueue_script( 'jquery-cookie', webcomic_include_url( 'jquery.cookie.js' ), array( 'jquery' ) );
    //wp_enqueue_script( 'jquery-konami', webcomic_include_url( 'jquery.konami.js' ), array( 'jquery' ) );
    //wp_enqueue_script( 'webcomic-scripts', webcomic_include_url( 'scripts.js' ), array( 'jquery', 'jquery-hotkeys', 'jquery-form', 'jquery-cookie' ) );
    
    //Handle transcript.php form requests
    if ( isset($_POST[ 'comic_transcript_submit' ]) ) {
        global $transcript_response;
        
        $before  = '<span class="error">';
        $after   = '</span>';
        $captcha = ( $_POST[ 'trans_checksum' ] ) ? md5( $_POST[ 'trans_captcha' ] ) == $_POST[ 'trans_checksum' ] : true;
        $human   = ( 1 < $_POST[ 'comic_transcript_submit' ] ) ? $_POST[ 'trans_human' ] : true;
        
        if ( !get_post_meta( $_POST[ 'trans_id' ], 'comic_transcript_draft', true ) && !get_post_meta( $_POST[ 'trans_id' ], 'comic_transcript', true ) && ( $human && $captcha ) ) {
            if ( !$_POST[ 'transcript' ] || ( get_option( 'comic_transcripts_required' ) && ( !$_POST[ 'trans_author' ] || !$_POST[ 'trans_email' ] ) ) ) {
                $error = 1;
                $message = ( get_option( 'comic_transcripts_required' ) && ( !$_POST[ 'trans_author' ] || !$_POST[ 'trans_email' ] ) ) ? __( 'Error: all fields are required.', 'webcomic' ) : __( 'Error: please type a transcript.', 'webcomic' ) ;
            } elseif ( get_option( 'comic_transcripts_required' ) && !filter_var( $_POST[ 'trans_email' ], FILTER_VALIDATE_EMAIL ) ) {
                $error = 1;
                $message = __( 'Error: invalid e-mail address.', 'webcomic' );
            } else {
                $email      = ( $_POST[ 'trans_email' ] ) ? ' (' . $_POST[ 'trans_email' ] . ')' : '';
                $from       = ( $_POST[ 'trans_author' ] ) ? stripslashes( $_POST[ 'trans_author' ] ) . $email : __( 'Anonymous', 'webcomc' );
                $title      = stripslashes( $_POST[ 'trans_title' ] );
                $transcript = "\n\n" . wp_filter_kses( $_POST[ 'transcript' ] ) . "\n\n";
                $message    = sprintf( __( '%1$s has submitted a new transcript for %2$s.%3$sYou can approve, edit, or delete this transcript by visiting: %4$s', 'webcomic' ), $from, $title, $transcript, admin_url( 'post.php?action=edit&post=' . $_POST[ 'trans_id' ] ) );
                $postmeta   = sprintf( __( '{ Submitted by %1$s on %2$s }', 'webcomic' ), $from, date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . rtrim( $transcript );
                
                add_post_meta( $_POST[ 'trans_id' ], 'comic_transcript_draft', $postmeta, true );
                
                if ( 1 < $_POST[ 'trans_type' ] ) {
                    add_post_meta( $_POST[ 'trans_id' ], 'comic_transcript_backup', get_post_meta( $_POST[ 'trans_id' ], 'comic_transcript_pending', true ), true );
                    delete_post_meta( $_POST[ 'trans_id' ], 'comic_transcript_pending' );
                }
                
                @wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] New Transcript for "%s"', 'webcomic' ), html_entity_decode( get_option( 'blogname' ) ), html_entity_decode( $title ) ), $message );
                
                $before  = '<span class="success">';
                $message = __( 'Thanks! Your transcript has been submitted for approval.', 'webcomic' );
            }
        } elseif ( $_POST[ 'trans_human' ] && md5( $_POST[ 'trans_captcha' ] ) == $_POST[ 'trans_checksum' ] ) {
            $error = 1;
            $message = ( get_post_meta( $_POST[ 'trans_id' ], 'comic_transcript', true ) ) ? __( 'Error: a transcript has already been approved.', 'webcomic' ) : __( 'Error: a transcript is already awaiting approval.', 'webcomic' );
        } else {
            $error = 1;
            $message = __( 'Error: human check failed, please try again.', 'webcomic' );
        }
                
        if ( 1 < $_POST[ 'comic_transcript_submit' ] ) 
            die( $before . $message . $after );
        elseif ( $error )
            wp_die( $before . $message . $after );
        else
            $transcript_response = $before . $message . $after;
    }   
} add_action( 'template_redirect', 'webcomic_template_redirect' );

/**
 * Add webcomic-specific CSS classes to body element.
 * 
 * @package Webcomic
 * @since 2.1.0
 * 
 * @param arr $classes Body class array.
 * @return arr The updated $classes array.
 */
function webcomic_body_class( $classes ) {
    global $webcomic_series;
    
    //Must be a Webcomic powered site
    $classes[] = 'webcomic';
    
    //Add the webcomic series class
    if ( $webcomic_series ) $classes[] = 'webcomic-' . $webcomic_series->slug;
    
    return $classes;
} add_filter( 'body_class', 'webcomic_body_class' );

/**
 * Add webcomic-specific CSS classes to post elements.
 * 
 * @package Webcomic
 * @since 2.1.0
 * 
 * @param arr $classes Post class array.
 * @return arr The updated $classes array.
 */
function webcomic_post_class( $classes ) {
    global $post;
    
    //Change the post type for comics
    if ( in_comic_category() ) {
        $classes[ 0 ] = 'comic-' . $post->ID;
        $classes[ 1 ] = 'comic';
    }
    
    //Add chapter calsses for comic posts assigned to a chapter
    if ( $chapters = get_post_comic_chapters() ) {
        $classes[] = 'series-' . $chapters->series->slug;
        $classes[] = 'volume-' . $chapters->volume->slug;
        $classes[] = 'chapter-' . $chapters->chapter->slug;
    }
    
    return $classes;
} add_filter( 'post_class', 'webcomic_post_class' );
    

/**
 * Enqueue's necessary javascript for administrative pages.
 */
function webcomic_admin_print_scripts() {
    wp_enqueue_script( 'webcomic-admin-scripts', webcomic_include_url( 'admin-scripts.js' ), array( 'jquery' ) );
} add_action( 'admin_print_scripts', 'webcomic_admin_print_scripts' );

/**
 * wc-core.php           contains all of the new template tags.
 * wc-widgets.php        contains all of the new widgets.
 * wc-admin.php          initializes core administrative functions.
 * wc-admin-settings.php contains all Settings page functionality.
 * wc-admin-library.php  contains all Library page functionality.
 * wc-admin-chapters.php contains all Chapters page functionality.
 * wc-admin-metabox.php  contains all Metabox functionality.
 */
require_once( 'includes/wc-core.php' );
require_once( 'includes/wc-widgets.php' );
require_once( 'includes/wc-permalink-rewrite.php' );
if ( is_admin() ) {
    require_once( 'includes/wc-admin.php' );
    require_once( 'includes/wc-admin-library.php' );
    require_once( 'includes/wc-admin-chapters.php' );
    require_once( 'includes/wc-admin-settings.php' );
    require_once( 'includes/wc-admin-metabox.php' );
}
?>
