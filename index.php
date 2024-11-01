<?php 
/*
Plugin Name: Shared Article Repository
Version: 0.10
Description: A database of shared articles for participating websites
Author: Iver Odin Kvello

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


Text Domain: shared-article-plugin
Domain Path: /languages/
*/

require_once('shared-article.php');
if (class_exists('WP_REST_Posts_Controller')) {
 require_once('shared-article-rest-controller.php');
}
add_action('init',array('SharedArticle','custom_post_type'));


class SharedArticleRepository {
 
  // Caps for the library role 
  public static $capabilities = array('publish_shared_article'=>true,'read_shared_article'=>true,'edit_shared_article'=>true,'delete_shared_article'=>true,'edit_others_shared_article'=>false);

  function __construct() {
  }

 public function plugins_loaded() {
   load_plugin_textdomain('shared-article-plugin',false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
 }

 // Maintain the subscription-taxonomy for users and articles
 public function subscribe($user,$post) {
  if (!user_can($user,'read_shared_article')) return false;
  if (get_post_type($post) != 'shared_article') return false;

  $login = $user->user_login;
  $postid = $post->ID; 

  if (empty($login) || empty($post->ID)) return; // Can't happen
  if (has_term($login,'subscription',$postid)) {
   return true;
  }

  $ids = wp_set_object_terms($postid,$login,'subscription','append');
  if (empty($ids) || is_wp_error($ids) || is_string($ids)) {
   return false;
  }
  $term = get_term_by('slug',$login,'subscription');
  $termid = $term->term_id;
  if ($termid) {
   $upd = wp_update_term($termid,'subscription',array('name'=>$user->display_name));
  }
  $this->update_subscription_count($postid); 
 
  return true;
 }


 public function unsubscribe($user,$post) {
  if (!user_can($user,'read_shared_article')) return false;
  if (get_post_type($post) != 'shared_article') return false;

  $login = $user->user_login;
  $postid = $post->ID;
  if (empty($login) || empty($post->ID)) return; // Can't happen
  if (!has_term($login,'subscription',$postid)) {
    return true;
  }
  $ok = wp_remove_object_terms($postid,$login,'subscription');
  if (!$ok || is_wp_error($ok)) {
   return false;
  }
  $this->update_subscription_count($postid); 

  return true;
 }

 private function update_subscription_count($post_id) {
  global $wpdb;
  $query = sprintf("SELECT COUNT(t.term_id) as ssum FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id where tt.taxonomy='subscription' && tr.object_id = %d", $post_id);
  $res = $wpdb->get_row($query);
  $count = $res ? $res->ssum : 0;
  update_post_meta($post_id,'_subscribers',$count);
 }

  // HOOKS
  public function admin_init () {
   register_setting('shared_article_repository_options','shared_article_repository_options', array($this,'validate'));

   // We need this, and it is still not part of current WP so notice the user that there are required plugins IOK 2016-04-12
   if (!class_exists('WP_REST_Posts_Controller')) {
    $myself = plugin_basename(__FILE__);
    add_action('admin_notices', function () {
     ?><div class='error notice'><p>
     <?php  _e("The Shared Article Repository plugin requires that the WP_REST_Posts_Controller class exists. Either use a version of WordPress that includes this class, or use the <a href='https://wordpress.org/plugins/json-rest-api/'>WP Rest API plugin</a>. The plugin has been deactivated.",'shared-article-plugin');?>
     </p></div>
     <?php
    });
    deactivate_plugins($myself);
    return;
   }

   if (!get_option('permalink_structure')) {
    add_action('admin_notices', function () {
     ?><div class='error notice'><p>
     <?php _e("For this plugin to work properly with the REST api, you *must* activate pretty permalinks at",'shared-article-plugin'); ?>
     <?php  echo "<a href='" . admin_url('options-permalink.php') . "'>"; ?>
     <?php _e("the permalink settings page",'shared-article-plugin'); ?>
     <?php echo "</a>"; ?>
     </p></div>
     <?php
    });

   }


   SharedArticle::admin_init();
  }

  public function admin_menu () {
//    add_options_page('Shared Article Repository', 'Shared Article Repository', 'manage_options', 'shared_article_repository_options',array($this,'toolpage'));

    if (in_array('library',(array) wp_get_current_user()->roles) && current_user_can('edit_user',get_current_user_id())) {
     add_users_page('Library Data', __('Library Data','shared-article-plugin'), 'read_shared_article', 'library-data',array($this,'librarypage'));
    }
    if (current_user_can('manage_options') && current_user_can('edit_user',$_REQUEST['user_id'])) {
      add_action( 'edit_user_profile_update', array($this,'save_extra_profile_fields'));
      add_action( 'edit_user_profile', array($this,'show_extra_profile_fields'));
    }

  }
  public function init () {
   $this->taxonomy_init();
  }

  public function activate () {
	  $default = array();
	  add_option('shared_article_repository_options',$default,false);
          load_plugin_textdomain('shared-article-plugin',false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
          $caps = static::$capabilities; 
          $caps['read'] = true;
          add_role( 'library', __('Library','shared-article-plugin'), $caps);
       
          $admins= get_role('administrator');
          $admins->add_cap( 'edit_shared_article' ); 
          $admins->add_cap( 'edit_others_shared_article' ); 
          $admins->add_cap( 'publish_shared_article' ); 
          $admins->add_cap( 'read_shared_article' ); 
          $admins->add_cap( 'read_private_shared_article' ); 
          $admins->add_cap( 'delete_shared_article' ); 
          $admins->add_cap( 'edit_published_shared_article' );
          $admins->add_cap( 'delete_published_shared_article' );
       
       // We need to check 'last modified' quickly.
       if (!get_option('shared_article_indexed')) {
         global $wpdb;
         $prefix = $wpdb->prefix;
         $iname = $prefix."modified_index";
         $createquery =  "CREATE INDEX $iname on {$wpdb->posts}(ID,post_status,post_type,post_modified)";
         @$wpdb->query($createquery);
         add_option('shared_article_indexed',true,false);
       }
  }
  public function deactivate () {
    load_plugin_textdomain('shared-article-plugin',false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    $roles = get_editable_roles();
    foreach ($GLOBALS['wp_roles']->role_objects as $key => $role) {
     if (isset($roles[$key])) {
      $caps = $role->capabilities;
      foreach($caps as $cap=>$value) {
         if (preg_match("!shared_articles?$!",$cap)) {
          if ($role->has_cap($cap)) $role->remove_cap($cap);
         }
      }
     }
    }
    remove_role('library');
  }
  public function uninstall() {
   load_plugin_textdomain('shared-article-plugin',false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
   delete_option('shared_article_repository_options');
   global $wpdb;
   $prefix = $wpdb->prefix;
   $iname = $prefix."modified_index";
   $deletequery =  "DROP INDEX $iname on {$wpdb->posts}";
   @$wpdb->query($deletequery);
   delete_option('shared_article_indexed');

   $shared = $wpdb->get_results("select ID from {$wpdb->posts} where 'post_type'='shared_article'",ARRAY_N);
   foreach ($shared as $entry) {
    wp_delete_post($entry[0], true);
   }
  
   // Delete database of shared article 
   $shared = $wpdb->get_results("select ID from {$wpdb->posts} where post_type='shared_article'",ARRAY_N);
   if (!is_wp_error($shared)) {
    foreach ($shared as $entry) {
     wp_delete_post($entry[0], true);
    }
   }
 

  }
  public function validate ($input) {
   $current =  get_option('shared_article_repository_options'); 

   $valid = array();
   foreach($input as $k=>$v) {
     $valid[$k] = $v;
   }
   return $valid;
  }

  public function toolpage () {
    if (!is_admin() || !current_user_can('manage_options')) {
      die("Insufficient privileges");
  }
  $options = get_option('shared_article_repository_options'); 
?>
<div class='wrap'>
 <h2><?php _e('Shared Article Repository','shared-article-plugin'); ?></h2>
<form action='options.php' method='post'>
<?php settings_fields('shared_article_repository_options'); ?>
 <table class="form-table" style="width:100%">
   <tr>
    <td>Example</td><td><input id='example' name='shared_article_repository_options[example]' pattern="[A-Za-z0-9-_]+" type="text" value="<?php echo esc_attr($options['example']); ?>" /></td><td>
<?php _e('Example option','shared-article-plugin'); ?>
</td>
   </tr>
 </table>
 <p class="submit">
  <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
 </p>
</form>
</div>
<?php
  }

  public function librarypage () {
    $userid = get_current_user_id();
    if ($_REQUEST['user_id']) {
     $userid = sprintf("%d",$_REQUEST['user_id']);
     if (!current_user_can('edit_user',$userid)) {
      die("Cannot edit this user");
     }
    }

    if (!is_admin() || !current_user_can('read_shared_article') || !current_user_can('edit_user',$userid)) {
      die("Insufficient privileges");
    }

    // If request contains post data; store as usermeta.
    if (isset($_POST['librarydata'])) {
     check_admin_referer('libnonce');
     $libdata = $_POST['librarydata'];
     $pkey = trim($libdata['libdb_pubkey']);
     $this->update_pubkey($userid,$pkey);
    }

    $userdata = get_userdata($userid);
    $pkey = get_usermeta($userid,'libdb_pubkey',true);




?>
<div class='wrap'>
 <h2><?php echo sprintf(__('Library Data for %s','shared-article-plugin'), $userdata->display_name); ?></h2>
<?php do_action('admin_notices'); ?>

<form  method='post'>
 <input type="hidden" name="user_id" id="user_id" value="<?php echo $userid ?>">
 <?php wp_nonce_field( 'libnonce'); ?>
 <table class="form-table" style="width:100%">
   <tr>
    <td><?php _e('Connection Key','shared-article-plugin'); ?></td><td><textarea cols=75 rows=13 id='pkey' name='librarydata[libdb_pubkey]'><?php echo htmlspecialchars($pkey); ?></textarea></td><td>
<?php _e('Post your connection key from your Shared Articles plugin here to connect','shared-article-plugin'); ?>
</td>
   </tr>
 </table>
 <p class="submit">
  <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
 </p>
</form>
</div>
<?php
  }

  // Used on both profile page and the library data page
  private function update_pubkey($userid,$pkey='') {
   if (empty($pkey)) {
     delete_user_meta($userid,'libdb_pubkey','');
     add_action('admin_notices', function () { echo"<div class='updated'><p>"; _e('Connection key removed','shared-article-plugin'); echo "</p></div>"; });
   } else {
     $ok = 0;
     if ($pkey) {
      $key = openssl_pkey_get_public($pkey);
      if ($key) {
       $ok=1;
       openssl_free_key($key);
      }
     }
     if (!$ok) {
      add_action('admin_notices', function () { echo"<div class='error'><p>"; _e('Not a valid connection key','shared-article-plugin'); echo "</p></div>"; });
     } else {
      update_usermeta($userid,'libdb_pubkey',$pkey);
      add_action('admin_notices', function () { echo"<div class='updated'><p>"; _e('Connection key updated','shared-article-plugin'); echo "</p></div>"; });
     }
   }
  }


  // For the admins
  function save_extra_profile_fields( $userid ) {
    if (!current_user_can('edit_user',$userid)) return false;
    $pkey = trim($_POST['libdb_pubkey']);
    $this->update_pubkey($userid,$pkey);
    if (array_key_exists('libdb_tags',$_POST)) {
     $tags = explode(",",$_POST['libdb_tags']);
     $newtags = array(); 
     foreach ($tags as $tag) {
      if ($tag) $newtags[] = trim(sanitize_text_field($tag));
     }
     $old = get_user_meta($userid,'libdb_tags',true);
     update_usermeta($userid,'libdb_tags',$newtags);

     // This should be triggered when actual new tags are present. IOK 2017-04-24
     if (!$old || $somethingnew = array_diff($newtags,$old)) {
       $posts = get_posts(array('post_type'=>'shared_article','author'=>$userid,'posts_per_page'=>-1));
       foreach ($posts as $post) {
        wp_set_post_tags($post->ID,$newtags,true);
       }
     }
     // Remove tags when removed from the list
     if ($old && $somethingold = array_diff($old,$newtags)) {
       $posts = get_posts(array('post_type'=>'shared_article','author'=>$userid,'posts_per_page'=>-1));
       foreach ($posts as $post) {
        foreach($somethingold as $oldtag) {
          wp_remove_object_terms($post->ID,$oldtag,'post_tag');
        }
       }
     }
    }

  }

  function show_extra_profile_fields( $user ) { ?>
   <h3><?php _e('Library Data','shared-article-plugin'); ?></h3>

    <table class="form-table">
     <tr>
      <th><label for="libtag"><?php _e('Tags','shared-article-plugin'); ?></label></th>
      <td>
      <span class="description"><?php _e('Tags that will be added to articles shared from this library; comma-separated','shared-article-plugin'); ?></span><br>
      <textarea type=textarea columns=75 name='libdb_tags' id=libdb_tags'><?php echo htmlspecialchars(join(", ",get_the_author_meta('libdb_tags',$user->ID))); ?></textarea>
      </td>
     </tr>
     <tr>
      <th><label for="connectionkey"><?php _e('Connection Key','shared-article-plugin'); ?></label></th>
       <td>
        <span class="description"><?php _e('The registered connection key. Delete this to disconnect the user.','shared-article-plugin'); ?></span><br>
        <textarea columns=75 type="text" name="libdb_pubkey" id="connectionkey" style="font-size:10px"><?php echo htmlspecialchars(get_the_author_meta('libdb_pubkey',$user->ID)); ?></textarea>
       </td>
     </tr>
    </table>
<?php }

 // To be used by the clients when determining-current-user. We sign all requests by a private key, the public one is stored here.
 function verify_api_call($userid=null) {
   if ($userid) return $userid; // We already know who we are in this case


   // Apache will normally strip the Authorization header, so we send this as well. The
   // Authorization header must also be sent, to ensure caches don't do their thing.
   $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : $_SERVER['HTTP_X_AUTHORIZATION'];
   $login=null;
   $ts = null;
   $rand= null;
   $sigb64=null;
   if ($auth) {
    list($authmethod,$hash) = @explode(" ",$auth);
    list($login,$ts,$rand,$sigb64) = @explode(":",@base64_decode(trim($hash)));
   } else return null;

   if (empty($sigb64)) return null;
   if (empty($login)) return null;

   $user = get_user_by('login',$login);
   if (empty($user)) return null;
   $userid = $user->ID;
   if (empty($userid)) return null;
   if (!in_array('library', (array) $user->roles)) {
     return null; // Only libraries
   }
   if (user_can($user,'manage_options')) {
     return null;  // No admins
   }

   $pubkey = get_user_meta($userid, 'libdb_pubkey', true);
   if (empty($pubkey)) return null;

   $sig = base64_decode($sigb64);
   if (empty($sig)) return null;
   $method = $_SERVER['REQUEST_METHOD'];
 
   switch($method) {
    case 'POST':
    case 'PUT':
     $data = file_get_contents('php://input');
     break;
    default:
     $data = $_SERVER['QUERY_STRING'];
     break;
   }
   if (empty($data)) return null;


   $key = openssl_pkey_get_public($pubkey);
   $ok = openssl_verify($data,$sig,$key,OPENSSL_ALGO_SHA512);
   openssl_free_key($key);

   if ($ok) return $userid;
   return null;
 }

 // Should be a separate WP_REST_Controller subclass, for the 'subscription' object.
 public function rest_subscribe ($request) {
  $id = isset($request['id']) ? $request['id'] : null;
  $method = $request->get_method();
  if (!$id) {
     return new WP_Error('rest_post_invalid_id', __( 'No such article','shared-article-plugin' ), array( 'status' => 404 ));
  }
  $user = wp_get_current_user();
  if (!user_can($user,'read_shared_article')) {
     return new WP_Error('rest_forbidden', __( 'Not Authorized','shared-article-plugin' ), array( 'status' => 401 ));
  }
  $post = get_post($id);
  if (empty($post) || get_post_type($post) != 'shared_article') {
     return new WP_Error('rest_post_invalid_id', __( 'Not a shared article','shared-article-plugin' ), array( 'status' => 404 ));
  }


  switch($method) {
    case 'POST':
    case 'PUT':
     $this->subscribe($user,$post);
     break;
    case 'DELETE':
     $this->unsubscribe($user,$post);
    break;
  }

  $data = array();
  $resp = rest_ensure_response(true);
  $resp->set_status('201');

  return $resp;
 }
 
 // We will need a 'since' timestamp, which should be seconds after 1970, ie a unix timestamp
 public function rest_subscription_status($request) {
   $since = isset($request['since']) ? $request['since'] : null;
   if ($since===null) return new WP_Error('rest_invalid_call',__('A "since"-timestamp is necessary; use seconds since the Unix epoch','shared-article-plugin'),array('status'=>400));
   $date = gmdate("Y-m-d H:i:s",$since);
   if (!$date)  return new WP_Error('rest_invalid_call',__('A "since"-timestamp is necessary; use seconds since the Unix epoch','shared-article-plugin'),array('status'=>400));

   // We return the 401 to signal to the end user that they are no longer connected to the library.
   $user = wp_get_current_user();
   if (!user_can($user,'read_shared_article')) {
     return new WP_Error('rest_forbidden', __( 'Not Authorized','shared-article-plugin' ), array( 'status' => 401 ));
   }
   $login = $user->user_login;

   global $wpdb;
   $query = $wpdb->prepare("SELECT ID AS id, post_modified_gmt AS modified_gmt
FROM  `{$wpdb->posts}` p 
JOIN  `{$wpdb->term_relationships}` tr ON ( tr.object_id = p.ID ) 
JOIN  `{$wpdb->term_taxonomy}` tx ON ( tr.term_taxonomy_id = tx.term_taxonomy_id)
JOIN  `{$wpdb->terms}` tt ON ( tt.term_id = tx.term_id)
WHERE post_type =  'shared_article' and tt.slug='%s' and tx.taxonomy='subscription'
AND post_modified_gmt >=  '%s'",$login,$date);

   $updated = $wpdb->get_results($query);

   $response = new WP_REST_Response($updated);
   $response->set_status(200);
   $response->header('X-WP-Total',count($updated));
   return $response; 
 }

 public function rest_post_subscriptions ($request) {
  $id = intval(isset($request['id']) ? $request['id'] : null);
  if (!$id) {
     return new WP_Error('rest_post_invalid_id', __( 'No such article','shared-article-plugin' ), array( 'status' => 404 ));
  }
  $user = wp_get_current_user();
  if (!user_can($user,'read_shared_article')) {
     return new WP_Error('rest_forbidden', __( 'Not Authorized','shared-article-plugin' ), array( 'status' => 401 ));
  }
  $post = get_post($id);
  if (empty($post) || get_post_type($post) != 'shared_article') {
     return new WP_Error('rest_post_invalid_id', __( 'Not a shared article','shared-article-plugin' ), array( 'status' => 404 ));
  }

  $count = intval(get_post_meta($id,'_subscribers',true));
  $resp = new WP_REST_Response($count);
  $resp->set_status('200');
  return $resp;
 }
 

 public function rest_api_init () {
   // Subscribe to an article
   register_rest_route( 'shared-article-repository/v2', '/subscription/(?P<id>\d+)', array(
        'methods' => array('POST','PUT','DELETE'),
        'callback' => array($this,'rest_subscribe')
        ) 
   );
   // Get number of subscriptions for a given article
   register_rest_route( 'shared-article-repository/v2', '/subscriptions/(?P<id>\d+)', array(
        'methods' => array('GET'),
        'callback' => array($this,'rest_post_subscriptions')
        ) 
   );
   // Get subscription status
   register_rest_route( 'shared-article-repository/v2', '/subscription/', array(
        'methods' => array('GET'),
        'callback' => array($this,'rest_subscription_status'),
        'args' => array('since' => array('validate_callback'=>function ($param,$request,$key) { return is_numeric($param); }))
        ) 
   );
   SharedArticleRestController::rest_api_init();

 }
 // End of REST api

 /* Taxonomies  */

 private function taxonomy_init() {
  SharedArticle::init();
  $this->subscription_taxonomy();
 }

 private function subscription_taxonomy() {
	$labels = array(
		'name'                       => __('Subscriptions','shared-article-plugin'),
		'singular_name'              => __('Subscription','shared-article-plugin'),
		'menu_name'                  => __('Subscriptions','shared-article-plugin')
	);
	$capabilities = array(
		'manage_terms'               => 'manage_categories',
		'edit_terms'                 => 'manage_categories',
		'delete_terms'               => 'manage_categories',
                // Don't allow editing of these terms. We would have to add
                // an user interface to ensure the slugs refer to the wp_users table. 
                // IOK 2016-02-18
		'assign_terms'               => 'nobody_can_do_this'
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => false,
		'show_ui'                    => true,
		'show_admin_column'          => true,
                'show_in_menu'               => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => false,
		'capabilities'               => $capabilities
	);
	register_taxonomy( 'subscription', array( 'shared_article' ), $args );

 }

 /* End taxonomies */
}

global $SharedArticleRepository;
$SharedArticleRepository = new SharedArticleRepository();
register_activation_hook(__FILE__,array($SharedArticleRepository,'activate'));
register_deactivation_hook(__FILE__,array($SharedArticleRepository,'deactivate'));
register_uninstall_hook(__FILE__,array($SharedArticleRepository,'uninstall'));

add_action('init',array($SharedArticleRepository,'init'));
add_action('rest_api_init',array($SharedArticleRepository,'rest_api_init'));
add_filter( 'determine_current_user',array($SharedArticleRepository,'verify_api_call'), 999 );
add_filter('plugins_loaded',array($SharedArticleRepository,'plugins_loaded'));

if (is_admin()) {
 add_action('admin_init',array($SharedArticleRepository,'admin_init'));
 add_action('admin_menu',array($SharedArticleRepository,'admin_menu'));
} else {
}


?>
