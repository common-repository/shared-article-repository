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


// Add custom post type and mechanisms for managing these
class SharedArticle {

 public static $metaboxes_allowed = array('submitdiv','tagsdiv-subscription','tagsdiv-post_tag','categorydiv','postexcerpt');

 public static $caps =  array(
 'publish_posts' => 'publish_shared_article',
 'edit_posts' => 'edit_shared_article',
 'edit_others_posts' => 'edit_others_shared_article',
 'delete_posts' => 'delete_shared_article',
 'delete_others_posts' => 'delete_others_shared_article',
 'read_private_posts' => 'read_private_shared_article',
 'edit_post' => 'edit_shared_article',
 'delete_post' => 'delete_shared_article',
 'read_post' => 'read_shared_article'
);

 

 public static function custom_post_type() {
   register_post_type( 'shared_article',
     array(
       'labels' => array(
         'name' => __( 'Shared Articles','shared-article-plugin' ),
         'singular_name' => __( 'Article','shared-article-plugin' )
       ),
       'public' => true,
       'has_archive' => true,
       'exclude_from_search'=>true,
       'publicly_queryable'=>false,
       'show_in_menu'=>true,
       'menu_position'=>20, // Below pages
       'supports'=>array('title','editor','author','excerpt'),
       'register_meta_box_cb'=>array('SharedArticle','metaboxes'),
       'show_in_rest'=>true, // Probably
       'rest_controller_class'=>'SharedArticleRESTController',
       'capability_type'=>'shared_article',
       'capabilities'=>static::$caps,
       'map_meta_cap'=>null,
       'taxonomies'=>array('post_tag','category')
     )
   );
 }

 function init() {
  register_meta('shared_article','shared_article_original_url',null,null);
  register_meta('shared_article','shared_article_original_author',null,null);
  register_meta('shared_article','shared_article_original_type',null,null);
  register_meta('shared_article','shared_article_original_id',null,null);
  register_meta('shared_article','shared_article_featured_image',null,null);
  register_meta('shared_article','_subscribers',null,null);
  add_action('save_post',array('SharedArticle','save_post'), 10, 3);
 }

 function admin_init() {
   add_action('do_meta_boxes',array('SharedArticle', 'remove_metaboxes'));
   add_action('manage_shared_article_posts_columns',array('SharedArticle', 'manage_shared_article_posts_columns'));
   add_action('manage_shared_article_posts_custom_column',array('SharedArticle', 'manage_shared_article_posts_custom_column'),10,2);
   add_action('manage_edit-shared_article_sortable_columns',array('SharedArticle','sortable_columns'));
 }

 public static function save_post($postid,$post, $update) {
  // Run this for just *new* shared articles.
  if ($update) {
   return;
  }
  if ($post->post_type != 'shared_article') {
   return;
  }

  // Add the tags from the library-user
  $requiredtags = get_user_meta($post->post_author,'libdb_tags',true);
  if ($requiredtags) {
   $ok =  wp_set_post_tags($post->ID,$requiredtags,true);
  }
 }


  public static function metaboxes () {
   // add_meta_box('sa_something','Something',array('SharedArticle','something_metabox'),'shared_article','side','default')
  }
  
  // Remove all nonessential meta boxes from the article repo view
  public static function remove_metaboxes() {
   global $wp_meta_boxes;
   $myboxes = $wp_meta_boxes['shared_article'];
   if (is_array($myboxes)) {
    foreach($myboxes as $context => $boxset) {
     foreach($boxset as $priority=>$boxlist) {
       foreach($boxlist as $id=>$boxdata) {
        if (!in_array($id,static::$metaboxes_allowed)) {
         remove_meta_box($id,'shared_article',$context);
        }
      }
     }
    }
   }
  }
  public static function sortable_columns ($columns) {
    $columns['author'] = 'display_name';
    return $columns;
  }

  public static function manage_shared_article_posts_columns ($columns) {
   $columns['author'] = __('Library','shared-article-plugin');

   $custom = array('type'=>__('Article Type','shared-article-plugin'),'subscribers'=>__('Subscribers','shared-article-plugin'),'shared_article_original_author'=>__('Author','shared-article-plugin'));
   return array_merge(array_slice($columns,0,-1),$custom,array_slice($columns,-1));
  }

  public static function manage_shared_article_posts_custom_column($column,$post_id=null) {
   global $wpdb;
   switch ( $column ) {
    case 'shared_article_original_author':
     echo esc_html(get_post_meta($post_id,'shared_article_original_author',true));
     break;
    case 'subscribers':
     // cache this in postmeta when subscriptions occur
     echo intval(get_post_meta($post_id,'_subscribers',true));
     break;
    case 'type':
     // Get post_meta containing original 
     $posttype = get_post_meta($post_id,'shared_article_original_type',true);
     echo  __($posttype ? $posttype : 'post');
     break;
   }
  }


}




?>
