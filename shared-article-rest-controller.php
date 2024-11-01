<?php
/*
    This file is part of the WordPress plugin Shared Article Repository
    Copyright (C) 2016 Iver Odin Kvello

    Shared Article Repository is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Shared Article Repository is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// This currently REQUIRES the REST API plugin. The plugin will refuse to activate if the class below is missing.
class SharedArticleRESTController extends WP_REST_Posts_Controller {

  public static function rest_api_init () {
   $postmeta_args = array('get_callback' => array('SharedArticleRestController','get_postmeta_field'),
                             'update_callback'=> array('SharedArticleRestController','set_postmeta_field'),
                             'schema'=>null);

   register_rest_field('shared_article','shared_article_original_author', $postmeta_args);
   register_rest_field('shared_article','shared_article_original_type', $postmeta_args);
   register_rest_field('shared_article','shared_article_original_id', $postmeta_args);
   register_rest_field('shared_article','shared_article_original_url', $postmeta_args);
   register_rest_field('shared_article','shared_article_featured_image', $postmeta_args);

   register_rest_field('shared_article','shared_article_library',
                          array('get_callback' => array('SharedArticleRestController','get_library'),
                             'update_callback'=> null,
                             'schema'=>null));;
   register_rest_field('shared_article','shared_article_library_name',
                         array('get_callback' => array('SharedArticleRestController','get_library_name'),
                             'update_callback'=> null,
                             'schema'=>null));;
   register_rest_field('shared_article','shared_article_tags',
                         array('get_callback' => array('SharedArticleRestController','get_tag_names'),
                             'update_callback'=> array('SharedArticleRestController','set_tag_names'),
                             'schema'=>null));;
  }

  public static function get_postmeta_field($object,$field,$request) {
   return get_post_meta($object['id'],$field,true);
  }
  public static function set_postmeta_field($value,$object,$field) {
    // I want to *mostly* avoid deleting values, so I'm going to do this weirdly.
    // The reason is that bugs in the client shouldn't change readonly values in the database.
    if (!$value || !is_string($value)) {
     return;
    }
    if ($value == 'WANG_CHUNG_DELETE_ME') { // yeah. IOK 2016-05-20.
     $value = '';
    }
    return update_post_meta($object->ID,$field,strip_tags($value));
  }

  public function add_allowable_html($allowed_tags) {
    $allowed_tags['iframe'] =  array('src'=> 1,'height'=> 1,'width'=> 1,'frameborder'=>1,'allowfullscreen'=>1,'id'=>1,'class'=>1,'scrolling'=>1,'style'=>1);
    $allowed_tags['embed'] =  array( 'src'=> 1, 'height'=> 1, 'width'=> 1, 'type'=>1);
    return $allowed_tags;
  }

  // We don't want to call the_excerpt here, just push the post_excerpt if present. This is to avoid certain shortcodes etc being expanded or the 
  // wrong 'red more' functions being used. IOK 2016-06-30
  protected function prepare_excerpt_response( $excerpt ) {
                if ( post_password_required() ) {
                        return '';
                }
                return $excerpt;
  }

  // Filtering of content is too strong; we want to keep embeds etc. All these methods must ensure we use the more liberal filter. IOK 2016-07-01
  protected function prepare_item_for_database( $request ) {
    add_filter('wp_kses_allowed_html',array($this,'add_allowable_html'));
    return parent::prepare_item_for_database($request);
  }
  public function create_item($request) {
    add_filter('wp_kses_allowed_html',array($this,'add_allowable_html'));
    return parent::create_item($request);
  } 
  public function update_item($request) {
    add_filter('wp_kses_allowed_html',array($this,'add_allowable_html'));
    return parent::update_item($request);
  } 

  // For these, read data directly from original post data if provided,
  // else use standard wp methods to augment field.
  public static function get_library($object,$field,$request) {
   global $post;
   if (isset($post) && isset($post->shared_article_library)) {
    return $post->shared_article_library;
   } else {
    return get_the_author_meta('user_login',$object['author']); 
   }
  }
  public static function get_library_name($object,$field,$request) {
   global $post;
   if (isset($post) && isset($post->shared_article_library_name)) {
    return $post->shared_article_library_name;
   } else {
   return get_the_author_meta('display_name',$object['author']); 
   }
  }

  public static function get_tag_names($object,$field,$request) {
   $tags = array();
   foreach(wp_get_post_tags($object['id']) as $tag) {
     $tags[] = $tag->name;
   }
   return $tags;
  }
  public static function set_tag_names($value,$object,$field) {
    if (!$value || !is_array($value)) {
      return; 
   }
   wp_set_post_tags($object->ID,$value,true);
  }


  private function read_check($request) {
    $current = wp_get_current_user();  
    if (empty($current) || !$current->ID)  {
     return new WP_Error('rest_forbidden', __( 'Sorry, shared articles are only available for logged-in users.','shared-article-plugin' ), array( 'status' => 401 ));
    }
    if (!current_user_can('read_shared_article')) {
     return new WP_Error('rest_forbidden', __( 'Sorry, this user cannot read shared articles.','shared-article-plugin' ), array( 'status' => 403 ));
    }
  }
  private function edit_check($request) {
    $current = wp_get_current_user();  
    if (empty($current) || !$current->ID)  {
     return new WP_Error('rest_forbidden', __( 'Sorry, shared articles are only available for logged-in users.','shared-article-plugin' ), array( 'status' => 401 ));
    }
    if (!current_user_can('edit_shared_article')) {
     return new WP_Error('rest_forbidden', __( 'Sorry, this user cannot edit or publish shared articles.','shared-article-plugin' ), array( 'status' => 403 ));
    }
  }

  // We need to check that we actually own this post unless we have edit_others_posts -
  // in the admin-area, this is handled by the *save* test, not the edit test. Might be fixed in the future.
  // For now, just add a check to 'edit others posts' IOK 2016-02-17
  protected function check_update_permission( $post ) {
   $ok = parent::check_update_permission($post);
   if (!$ok) return $ok;
   $current = wp_get_current_user();
   if ($post->post_author != $current->ID) {
    return current_user_can( $post_type->cap->edit_others_post, $post->ID );
   }
   return $ok;
  }

  public function get_items_permissions_check($request) {
    $canread = $this->read_check($request);
    if (is_wp_error($canread)) return $canread;
    return parent::get_items_permissions_check($request);
  }
  public function get_item_permissions_check($request) {
    $canread = $this->read_check($request);
    if (is_wp_error($canread)) return $canread;
    return parent::get_item_permissions_check($request);
  }
  public function create_item_permissions_check($request) {
    $canedit = $this->edit_check($request);
    if (is_wp_error($canedit)) return $canedit;
    return parent::create_item_permissions_check($request);
  }
  public function update_item_permissions_check($request) {
    $canedit = $this->edit_check($request);
    if (is_wp_error($canedit)) return $canedit;
    return parent::update_item_permissions_check($request);
  }

  public function get_collection_params() {
   $params = parent::get_collection_params();
   
   $params['orderby']['enum'][] = 'author';
   $params['orderby']['enum'][] = 'display_name';
   return $params;
  }

  public function get_items($request) {
    global $wpdb;
    //// Add filters here for posts_fields (SELECT) and posts_join etc
    //// this is for more efficiently getting author-metadata, taxonomies etc as we cannot modify the 'SELECT' query otherwise
     add_filter( 'posts_fields', array( $this, 'posts_fields' ) ); 
     add_filter( 'posts_join', array( $this, 'posts_join' ) );
    $result = parent::get_items($request); 
    
    //// Make sure filters are removed afterwards
     remove_filter( 'posts_fields', array( $this, 'posts_fields' ) );
     remove_filter( 'posts_join', array( $this, 'posts_join' ) );
    return $result;
  }

  public function get_item($request) {
   $response = parent::get_item($request);
   if ($response->data['status'] == 'trash') {
     return new WP_Error( 'rest_post_deleted', __( 'Post has been deleted.','shared-article-plugin' ), array( 'status' => 404 ) );
   }
   return $response;
  }

  // If fields has been added to the post response, you can 
  // modify this instead of the standard REST filters. Be aware
  // that both the get_items and the get_item method should provide these.
  public function prepare_item_for_response( $post, $request ) {
   $prepared = parent::prepare_item_for_response($post,$request);
   $prepared->data['status'] = $post->post_status;  // Add whatever the view
   return $prepared;
  }

  // Filters for get_items
  public function posts_fields($sql) {
   global $wpdb;
   $sql .= ", aa_u.user_login as shared_article_library,coalesce(aa_u.display_name,aa_u.user_nicename) as shared_article_library_name";
   return $sql;
  }
  public function posts_join($sql) {
   global $wpdb;
   $sql .= " INNER JOIN $wpdb->users aa_u ON ($wpdb->posts.post_author = aa_u.`ID`) ";
   return $sql;
  }

  // Modify query args here to ensure joins etc are ok. 
  // Also, map from tagnames to tag-ids now that the rest-api won't work with 'filter' anymore. IOK 2017-04-11
  protected function prepare_items_query($args = array(),$request=null) {
   $args = parent::prepare_items_query($args,$request);
   $params=array();
   if ($request) {
    $params = $request->get_query_params(); 
   }
   // When developed, we could do 'filter[tag]' and it would have this effect. Now, not so much.
   if (array_key_exists('tagname',$params) && $params['tagname']) {
    $term = get_term_by('name',$params['tagname'],'post_tag');
    if ($term) {
     $args['tag_slug__and'] = array($term->slug);
    }
   }
   // Same thing with categories
   if (array_key_exists('cat',$params) && $params['cat']) {
     $args['category__and'] = array($params['cat']);
   }


   return $args;
  }


}


?>
