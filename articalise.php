<?php
/*
Plugin Name: Articalise
Plugin URI: http://plugins.graq.co.uk/articalise
Description: For grouping posts together that are all part of one article. 
Version: 1.3.0
Autho: GRAQ
Autho URI: http://www.graq.co.uk
Min WP Version: 3.0
*/
$plugin_url = WP_CONTENT_URL.'/plugins/' . plugin_basename(dirname(__FILE__)) ;

define( ARTICALISEPLUGINDIR, dirname(__FILE__) );
define( ARTICALISEPLUGINURL, $plugin_url );

add_action('init', 'register_articalise_taxonomy');
function register_articalise_taxonomy(){
  $tax_labels = array(
    'name'                       => _x( 'Articalise', 'taxonomy general name' ),
    'singular_name'              => _x( 'Articalise', 'taxonomy singular name' ),
    'search_items'               => __( 'Search Articalise' ),
    'popular_items'              => __( 'Popular Articalise' ),
    'all_items'                  => __( 'All Articalise' ),
    'parent_item'                => null,
    'parent_item_colon'          => null,
    'edit_item'                  => __( 'Edit Articalise' ),
    'update_item'                => __( 'Update Articalise' ),
    'add_new_item'               => __( 'Add New Articalise' ),
    'new_item_name'              => __( 'New Articalise Name' ),
    'separate_items_with_commas' => __( 'Separate articalise with commas' ),
    'add_or_remove_items'        => __( 'Add or remove articalise' ),
    'choose_from_most_used'      => __( 'Choose from the most used articalise' )
  );

  register_taxonomy( 'articalise', array('post','page'), array(
    'hierarchical' => true,
    'labels'       => $tax_labels, 
    'show_ui'      => true,
    'query_var'    => true,
    'rewrite'      => array( 'slug' => 'articalise' ),
  ));
}

add_filter( 'the_title', 'prefix_articalisation_to_title');
function prefix_articalisation_to_title($title, $id=null){
  global $post;

  $opts = get_option('articalise');

  // Only dissplay term (articalise group name) if we're in the loop.
  // This should avoid menu's e.t.c.
  if( in_the_loop() && $opts['title'] ){
    $post_terms = wp_get_object_terms($post->ID, 'articalise');
    if( is_single() && count($post_terms) > 0 ){
      $term_name = $post_terms[0]->name;
      $title     = $term_name.': '.$title;
    }
  }

  return $title;
}

add_filter( 'the_content', 'add_articalisation_to_posts' );
function add_articalisation_to_posts($content){
  if( is_home() || is_singular() ){
    $menu = get_articalise_menu();
    $opts = get_option('articalise');
    if( $opts['pre'] ) $content = $menu . $content;
    if( $opts['post']) $content = $content . $menu;
  }

  return $content;
}

function get_articalise_menu(){
  global $post;
  
  $wp_cache_key = 'articalise_menu_'.$post->ID;
  $extra        = wp_cache_get($wp_cache_key, 'articalise');
  if( empty($extra) ){
    $opts       = get_option('articalise');
    $post_terms = wp_get_object_terms($post->ID, 'articalise');
    if( count($post_terms) > 0 ){
      $term_id         = $post_terms[0]->term_id; // Assume only ever one of these
      $term_name       = empty($opts['alt']) ? $post_terms[0]->name : $opts['alt'];
      $term_slug       = $post_terms[0]->slug;
      $sibling_objects = get_objects_in_term($term_id, 'articalise');
      $article_pages   = get_posts( array('include'=>$sibling_objects,'order'=>'ASC') );
      if( count($article_pages) > 0 ){
        $extra  = '<ul class="articalise-menu">';
        if( !empty($opts['link']) ) {
          $link      = get_term_link($term_slug,'articalise');
          $term_name = '<a href="'.$link.'">'.$term_name.'</a>';
        }
        $extra .= '<li>'.$term_name.': </li>';
        foreach( $article_pages as $page ){
          if( $page->ID == $post->ID ){
            $extra .= '<li>'.$page->post_title.'</li>';
          }
          else {
            $link   = get_permalink($page->ID);
            $desc   = '<a href="'.$link.'">'.$page->post_title.'</a>';
            $extra .= '<li>'.$desc.'</li>';
          }
        }
        $extra   .= '</ul>';
      }
      $adjust = empty($opts['adjust']) ? '' : 'articalise-'.$opts['adjust'];
      $extra  = '<div class="articliase-wrapper '.$adjust.'">'.$extra.'</div><br clear="both"/>';
    }
    wp_cache_set('articalise_menu', $extra, 'articalise');
  }

  return $extra;
}

add_action('wp_print_styles', 'add_articalise_style');
function add_articalise_style(){
  global $post;
  $post_terms = wp_get_object_terms($post->ID, 'articalise');
  if( count($post_terms) > 0 ){
    wp_register_style('articalise', ARTICALISEPLUGINURL.'/articalise.css');
    wp_enqueue_style('articalise');
  }
}

add_action('admin_menu', 'add_articalise_options_page');

function add_articalise_options_page(){
  add_options_page( 'Articalise','Articalise', 'edit_published_posts', 'articalise-options', 'articalise_options_menu' ); 
}

function articalise_options_menu(){
  if( !current_user_can('edit_published_posts') )
    wp_die( __('You do not have sufficient permissions to access this page.') );

  $saved = '';
  $opts  = get_option('articalise');
  if( 'articalise_save' == $_POST['action'] ){
    $opts['pre']    = isset($_POST['articalise_pre'])    ? $_POST['articalise_pre']    : '';
    $opts['post']   = isset($_POST['articalise_post'])   ? $_POST['articalise_post']   : '';
    $opts['adjust'] = isset($_POST['articalise_adjust']) ? $_POST['articalise_adjust'] : '';
    $opts['title']  = isset($_POST['articalise_title'])  ? $_POST['articalise_title']  : '';
    $opts['alt']    = isset($_POST['articalise_alt'])    ? $_POST['articalise_alt']    : '';
    $opts['link']   = isset($_POST['articalise_link'])   ? $_POST['articalise_link']   : '';
    update_option('articalise', $opts);
    $saved = '<div class="updated">Articalise settings updated.</div>';
  }
  $pre    = $opts['pre'];
  $post   = $opts['post'];
  $adjust = $opts['adjust'];
  $title  = $opts['title'];
  $alt    = $opts['alt'];
  $link    = $opts['link'];
  echo '<div class="wrap">';
	echo '<div id="icon-options-general" class="icon32"><br /></div>'; 
  echo '<h2>Articalise Settings</h2>'; 
  echo $saved;
  ?>
    <form name="articalise_options_form" method="post">
     <h3>Post Options</h3>
     <p><label>Display articalise name in post title: <input type="checkbox" name="articalise_title" value="1"<?php if(!empty($title)) echo ' checked="checked"'; ?>></label></p>
     <p><label>Display articalise menu before post content: <input type="checkbox" name="articalise_pre" value="1"<?php if(!empty($pre)) echo ' checked="checked"'; ?>></label></p>
     <p><label>Display articalise menu after post content: <input type="checkbox" name="articalise_post" value="1"<?php if(!empty($post)) echo ' checked="checked"'; ?>></label></p>
     <h3>Menu options</h3>
     <p><label title="Display this instead of the term name. E.g. 'Pages'">Alternate text: <input type="text" name="articalise_alt" value="<?php echo $alt?>"></label></p>
     <p><label>Link term (like category link): <input type="checkbox" name="articalise_link" value="1"<?php if(!empty($link)) echo ' checked="checked"'; ?>></label></p>
     <p>Adjust text: 
       <label><input name="articalise_adjust" type="radio" value="" <?php if(empty($adjust)) echo ' checked="checked"'; ?>>No</label>
       <label><input name="articalise_adjust" type="radio" value="left" <?php if('left' == $adjust) echo ' checked="checked"'; ?>>Left</label>
       <label><input name="articalise_adjust" type="radio" value="right" <?php if('right' == $adjust) echo ' checked="checked"'; ?>>Right</label>
     </p>

     <input type="hidden" name="action" value="articalise_save"/>
     <p class="submit">
      <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
     </p>
    </form>
  <?php
  echo '</div>';
}
