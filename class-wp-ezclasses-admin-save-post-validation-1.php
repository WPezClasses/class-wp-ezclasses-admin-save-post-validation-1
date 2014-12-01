<?php
/** 
 * Standardizes and "automates" WordPress save_post validation for pages, posts and CPTs.
 *
 * If the field is on the page and you know the field name then you can validate it. 
 *
 * PHP version 5.3
 *
 * LICENSE: TODO
 *
 * @package WPezClasses
 * @author Mark Simchock <mark.simchock@alchemyunited.com>
 * @since 0.5.1
 * @license TODO
 */
 
/**
 * == Change Log == 
 *
 * -- 0.5.0 - Tue 25 Nov 2014
 *
 * ---- Pop the champagne!
 */
 
/**
 * == TODO == 
 *
 *
 */

// No WP? Die! Now!!
if ( ! defined('ABSPATH')) {
	header( 'HTTP/1.0 403 Forbidden' );
    die();
}

if ( ! class_exists('Class_WP_ezClasses_Admin_Save_Post_Validation_1') ) {
  class Class_WP_ezClasses_Admin_Save_Post_Validation_1 extends Class_WP_ezClasses_Master_Singleton{
 
	private $_version;
	private $_url;
	private	$_path;
	private $_path_parent;
	private $_basename;
	private $_file;
	
    protected $_arr_init;
	protected $_bool_transition_post_status_new_is_publish;
	protected $_arr_validate_errors;
	
	public function __construct() {
	  parent::__construct();
	}
		
	/**
	 *
	 */
	public function ez__construct($arr_args = ''){
	
	  // TODO - revisit
	  if ( ! is_admin()){
	    return;
	  }
	  
	  $this->setup();
	
	  $arr_init_defaults = $this->init_defaults();
	  
	  // TODO - $this->arr_args_validate($arr_args);
	  
	  $this->_arr_init = WPezHelpers::ez_array_merge(array($arr_init_defaults, $arr_args));
	  
	  add_action( 'save_post', array($this, 'save_post_validation_do'),$this->_arr_init['priority_save_post'] );
	  
	  add_filter( 'post_updated_messages', array($this, 'post_updated_messages_remove_published' ), $this->_arr_init['priority_post_updated_messages'] );
	  
	  add_action('admin_notices', array($this, 'admin_notices_echo_error_messages'), $this->_arr_init['priority_admin_notices']); 
	  	  
	  /**
	   * we're going to presume a post is being published unless 'transition_post_status_do() setsotherwise.
	   *
	   * This is important because we want to enforce a post NOT be published unless it passes the validation. 
	   * And if it's not being published (yet) then we don't necessarily enforce validation
	   *
	   * ref: http://codex.wordpress.org/Post_Status_Transitions
	   */
	   $this->_bool_transition_post_status_new_is_publish = true;
	   add_action('transition_post_status', array( $this, 'transition_post_status_do'), $this->_arr_init['priority_transition_post_status'], 3);			
	}
	
	/**
	 * 
	 */
	protected function setup(){
	
	  $this->_version = '0.5.0';
	  $this->_url = plugin_dir_url( __FILE__ );
	  $this->_path = plugin_dir_path( __FILE__ );
	  $this->_path_parent = dirname($this->_path);
	  $this->_basename = plugin_basename( __FILE__ );
	  $this->_file = __FILE__ ;
	  
	}
	
	/**
	 *
	 */
	protected function init_defaults($str_lang = 'en'){
	
	  $arr_post_types = $this->init_defaults_post_types();
	  $str_post_types = implode('_', $arr_post_types);
	
	  $arr_defaults = array(
	    'active'			 					=> true,	// think of this as a kill switch to turn off this process
	//	'active_true'							=> true,	// currently NA (use the active true "filtering")
	//	'filters'								=> false, 	// currently NA
	//	'arr_arg_validation'					=> false, 	// currently NA
		
		'debug'									=> false,	// if set to true, missing validate methods will be added to errors. 
		'post_status_on_error'					=> 'draft',	// if there's errors fallback to what post status?
		'post_types'							=> $arr_post_types,
		'post_meta_key_for_errors'				=> '_wp_ezc_admin_save_post_1_' . $str_post_types,  // unique post meta key where errors will be stashed
		
		'validate_to_publish_warning_title'		=> 'WARNING',
		'validate_to_publish_warning_intro'		=> 'Once you decide to publish, the following errors will need to be resolved:',
		'validate_to_publish_error_title'		=> 'ERROR',	
		'validate_to_publish_error_intro'		=> 'In order to publish, you must correct the following errors:',
		
		'unset_message_key'						=> 'post',	// allows us to muck with the WP msg queue
		'unset_message_int'						=> 6,		// allows us to muck with the WP msg queue
		
		'priority_transition_post_status'		=> 10,
		'priority_save_post'					=> 5,   // ideally, we want to do this before anything else.
		'priority_admin_notices'				=> 10,
		'priority_post_updated_messages'		=> 10,
	  );
	
	  return $arr_defaults;
	}
	
	
	/**
	 * You can override this method or you can pass in an array with the arr_args['post_types']. If you do not 
	 * pass in an array for arr_args['post_types'] (or override this method) then we'll default to 'post'. This
	 * method just makes it ez to configure in a TODO sorta way
	 */
	protected function init_defaults_post_types(){
	  return array('post');
	}
			
	/**
	 * Simple bool toggle for the form elements that are to be validated. 
	 *
	 * The key (e.g., post_title) is the actual field name as is rendered by WP
	 *
	 * Note: This is simply an example. You will need to override this method.
	 */
	protected function form_elements_active(){
	  
	  $arr_form_elements_active = array(
	    'post_title'		=> true,
		'excerpt'			=> true,
		'post_category'		=> true,
		'featured_image'	=> true
		);							
	  
	  return $arr_form_elements_active;
	}
	
	/**
	 * When we display any validation errors we want to know which label to attribute it to. 
	 *
	 * Note: In the case of non-simple errors (e.g., "Blank and Blank - Must be blank") you may want to establish special labels. 
	 *
	 * The key (e.g., post_title) is the actual field name as is rendered by WP
	 *
	 * Note: This is simply an example. You will need to override this method.
	 */
	protected function form_elements_labels(){
	
	  $arr_form_elements_labels = array(
	    'post_title'		=> 'Post Title',
		'excerpt'			=> 'Excerpt',
		'post_category'		=> 'Category',
		'featured_image'	=> 'Featured Image'
		);
	  return $arr_form_elements_labels;
	}
	
	
	/**
	 * Returns an instance of the classes that we're using for (basic) form / field validation
	 *
	 * Notice how you can use more than one validation class.
	 */
	protected function form_elements_validation_objects() {
	
	  $arr_validation_objects = array(
	    
		'one'	=> Class_WP_ezClasses_Admin_Save_Post_Validation_Helpers_1::ez_new('en'),  
	  );
	  return $arr_validation_objects;
	}
	
	protected function form_elements_validation_types(){
		
	  $arr_types = array(
	  
	    'text'				=> true,
		'category'			=> true,
		'taxonomy'			=> true,
		'featured_image'	=> true,
	  );
	  
	  return $arr_types;
	}

	/**
	 * * * Important * * *
	 * The primary array key is NOT the name. The name has it's own designation. 
	 *
	 * Note: This is simply an example. You will need to override this method.
	 */
	protected function form_elements_validation_methods(){
	  
	  $arr_form_elements_validation_methods = array(
	  
	    'the_post_title' => array(
		  'name'		=> 'post_title',
		  'type'		=> 'text',
		  'validation'	=> array(
		  
		    'method_1'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',		// which key defined in form_elements_validation_objects() will we find this method?
			  'method'		=> 'some_method_name',
			  'arr_args'		=> array('method_args' => 'if_any'),
			  ),
			  
		    'method_2'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'required',
			  'arr_args'		=> array(),		// even if there are no args you must pass an empty array()
			  ),
			),
		  ),
		
		// 
		'the_excerpt'	=> array(
		  'name'		=> 'excerpt',
		  'type'		=> 'text',
		  'validation'	=> array(
		  
		    'method_1'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'required',
			  'arr_args'		=> array(),
			  ),
			  
		    'method_2'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'length_min',
			  'arr_args'		=> array('strlen' => 10),
			  ),
			  
		    'method_3'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'length_max',
			  'arr_args'		=> array('strlen' => 15),
			  ),
		    ),
		  ),
		  
		  // name="post_category[]"
		  
		'the_post_category'	=> array(
		  'name'		=> 'post_category',
		  'type'		=> 'category',
		  'validation'	=> array(
		  
		    'method_1'	=> array(
		      'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'required',
			  'arr_args'		=> array(),
			  ),
			),
		  ),
		  
		'the_featured_image'	=> array(
		  'name'		=> 'featured_image',
		  'type'		=> 'featured_image',
		  'validation'	=> array(
		  
		    'method_1'	=> array(
			  'active'	 	=> true,
			  'object'		=> 'one',
			  'method'		=> 'required',
			  'arr_args'		=> array(),
			  ),
			),
		  ),
	
		);
		
	  return $arr_form_elements_validation_methods;
	}
		
	/**
	 * TODO - not a priority atm
	 *
	 * Validates arg_args values that are passed in (we know our defaults are good to go).
	 */
	protected function arr_args_validate($arr_args = array()){
	
	  if ( ! is_array($arr_args) ){
	    return array();
	  }
	  
	  if ( isset($arr_args['active']) && ! is_bool($arr_args['active']) ){
	    unset($arr_args['active']);
	  }
	  
	  if ( isset($arr_args['debug']) && ! is_bool($arr_args['debug']) ){
	    unset($arr_args['debug']);
	  }
	  
	  // TODO - check for valid post status
	  if ( isset($arr_args['post_status_on_error']) && ! is_string($arr_args['post_status_on_error']) ){
	    unset($arr_args['post_status_on_error']);
	  }
	  
	  // TODO - check to make sure the post_types are valid / legit post_types
	  if ( isset($arr_args['post_types']) && ! is_array($arr_args['post_types']) ){
	    unset($arr_args['post_types']);
	  }
	  
	  if ( isset($arr_args['form_class']) && ! is_object($arr_args['form_class']) ){
	    unset($arr_args['form_class']);
	  }
	  
	  // TODO - do a tighter check
	  if ( isset($arr_args['post_meta_key_for_errors']) && ! is_string($arr_args['post_meta_key_for_errors']) ){
	    unset($arr_args['post_meta_key_for_errors']);
	  }
	  
	  // 
	  if ( isset($arr_args['validate_to_publish_warning_label']) && ! is_string($arr_args['validate_to_publish_warning_label']) ){
	    unset($arr_args['validate_to_publish_warning_label']);
	  }
	  
	  // TODO - do a tighter check
	  if ( isset($arr_args['validate_to_publish_warning_msg']) && ! is_string($arr_args['validate_to_publish_warning_msg']) ){
	    unset($arr_args['validate_to_publish_warning_msg']);
	  }

	  // TODO - do a tighter check
	  if ( isset($arr_args['validate_to_publish_error_label']) && ! is_string($arr_args['validate_to_publish_error_label']) ){
	    unset($arr_args['validate_to_publish_error_label']);
	  }
	  
	  // TODO - do a tighter check
	  if ( isset($arr_args['validate_to_publish_error_msg']) && ! is_string($arr_args['validate_to_publish_error_msg']) ){
	    unset($arr_args['validate_to_publish_error_msg']);
	  }
	  
	  if ( isset($arr_args['unset_message_int']) && ! is_int($arr_args['unset_message_int']) ){
	    unset($arr_args['unset_message_int']);
	  }
	  
	  if ( isset($arr_args['priority_transition_post_status']) && ! is_int($arr_args['priority_transition_post_status']) ){
	    unset($arr_args['priority_transition_post_status']);
	  }
	  
	  if ( isset($arr_args['priority_save_post']) && ! is_int($arr_args['priority_save_post']) ){
	    unset($arr_args['priority_save_post']);
	  }
	  
	  if ( isset($arr_args['priority_admin_notices']) && ! is_int($arr_args['priority_admin_notices']) ){
	    unset($arr_args['priority_admin_notices']);
	  }
	  
	  if ( isset($arr_args['priority_post_updated_messages']) && ! is_int($arr_args['priority_post_updated_messages']) ){
	    unset($arr_args['priority_post_updated_messages']);
	  }			
	  
	  return $arr_args;
	}
	
	/**
	 * When a post changes status on save, let's see if post_status is publish. We need to know this so we 
	 * can change the post_status (if necessary).
	 *
	 * ref: http://codex.wordpress.org/Post_Status_Transitions
	 */
	public function transition_post_status_do($new_status, $old_status, $post){
	
	  if ( $old_status != 'inherit' && $new_status != 'inherit' &&  $new_status != 'publish' ){
	    $this->_bool_transition_post_status_new_is_publish = false;
	  }
	}
	

	/**
	 * Let's make the magic happen
	 */
	public function save_post_validation_do($int_post_id){
	
	  $arr_init = $this->_arr_init;
	
	  // If the main active switch is off then return, else keep going
	  if ( ! isset($arr_init['active']) || $arr_init['active'] !== true ){
	    return;
	  }
	
	  global $post;
	  
	  // is this a post_type we want to do?
	  if ( is_array($arr_init['post_types']) && in_array($post->post_type, $arr_init['post_types']) ) {
	  
		$arr_elements_active = $this->form_elements_active();
		$arr_elements_labels = $this->form_elements_labels();
		
		$arr_validation_objects = $this->form_elements_validation_objects();
		$arr_validation_type = $this->form_elements_validation_types();
		$arr_validation_methods = $this->form_elements_validation_methods();
		
		// start with a clean log
		$arr_errors_log = array();
		// let check'em
		foreach ( $arr_validation_methods as $str_key => $arr_args_methods ){
		
		  // do we have a name && is it active?
		  if ( isset($arr_args_methods['name']) && WPezHelpers::ez_true($arr_elements_active[$arr_args_methods['name']]) ){
		    $str_name = $arr_args_methods['name'];
			
		    // do we have a type? and is it legit and true
		    if ( isset($arr_args_methods['type']) && WPezHelpers::ez_true($arr_validation_type[$arr_args_methods['type']]) ){
			  $str_type = $arr_args_methods['type'];
			  
			  // super high-level screening for issues that should trigger a continue
			  $bool_continue = false;
			  if ( $str_type == 'text' && ! isset($_POST[$str_name]) ){
			  
			    $bool_continue = true;
				
			  } elseif  ( $str_type == 'category' && ! isset($_POST['post_category']) ){
			  
			    $bool_continue = true;
				
			  } elseif ( $str_type == 'featured_image'  && ! current_theme_supports('post-thumbnails') ){
			  
			    $bool_continue = true;
			  
			  } elseif ($str_type == 'taxonomy' ){  // TODO!!!!!!!!!!
			  // TODO			    
			    $bool_continue = true;
			  } else {
			    // 
			  }
			  
			  // foreach: continue?
			  if ( $bool_continue === true ) {
			    // debug'in
			    if ( $arr_init['debug'] === true ){
				  $arr_errors_log[$str_name][$str_type] = 'Debug: ' . $str_key . ' > ' . $str_name . ' > ' . $str_type;
				}
				continue;
			  }
			  
			  // so far so good. now lets get into the validation
			  foreach ( $arr_args_methods['validation'] as $str_key_validation => $arr_args_validation ){
			    // active?
			    if ( WPezHelpers::ez_true($arr_args_validation['active']) ){
				  
				  // do we  the 'object' and 'method'
				  if ( isset($arr_args_validation['object']) && isset($arr_validation_objects[$arr_args_validation['object']]) && isset($arr_args_validation['method'])){
				   
				    $obj_validation = $arr_validation_objects[$arr_args_validation['object']];
				    $str_method = $arr_args_validation['method'];
					 
					// does the 'method' exist?
					if ( method_exists($obj_validation, $str_method) ) {
					  // just do it! (finally)
					  $str_validation_return = $obj_validation->$str_method($str_name, $str_type, $arr_args_validation);
					   
					  // if we get a validation error then "log" it. 
					  if ( ! empty($str_validation_return ) && $str_validation_return !== true ){
					    $arr_errors_log[$str_name][$arr_args_validation['object'] . '_' . $str_method] = $str_validation_return;
					  }	
					} else {
					  // debug'in
  					  if ( $arr_init['debug'] === true ){
					    $arr_errors_log[$str_name][$str_type] = 'Debug: ' . $str_key . ' > ' . $str_name . ' > ' . $str_type . ' > ' . 'method: ' . $str_method;
					  }					
					}  
				  } else {
				    // debug'in
					if ( $arr_init['debug'] === true ){
					  $arr_errors_log[$str_name][$str_type] = 'Debug: ' . $str_key . ' > ' . $str_name . ' > ' . $str_type . ' > ' . 'key: object or key method';
					}	
				  }
				}
			  }
		    }  
		  }
		}
	
		/**
		 * If you have custom / advanced validation or any other fancy pants
		 * kinda stuff, now is your chance.
		 *
		 * Note: TODO - maybe this isn't really necessary? 
		 */
		$arr_errors_log = $this->save_post_validation_do_custom($arr_errors_log);
		
		// if we don't have errors then delete the post_meta_key, if there is one. 
		if ( empty($arr_errors_log) ){
		  delete_post_meta( $int_post_id, $arr_init['post_meta_key_for_errors'] );
		} else {
		
		  // if we were trying to publish AND there are errors we $wpdb->update the post_status back to 'post_status_on_error'
		  if ( $this->_bool_transition_post_status_new_is_publish === true ) {
		    
			global $wpdb;
			$resp_wpdb_update = $wpdb->update( $wpdb->posts, array( 'post_status' => $arr_init['post_status_on_error']), array( 'ID' => $post->ID) );
			
			if ( $resp_wpdb_update == false ) {
			  return false;
			}
			
			$arr_errors_log['error_title'] = $arr_init['validate_to_publish_error_title'];
			$arr_errors_log['error_intro'] = $arr_init['validate_to_publish_error_intro'];
		  
		  } else {
		    // if we're not publishing, we'll issue warnings
		    $arr_errors_log['error_title'] = $arr_init['validate_to_publish_warning_title'];
			$arr_errors_log['error_intro'] = $arr_init['validate_to_publish_warning_intro'];
		  }
		  
		  /**
		   * yup! we're storing the errors as post_meta. the key to this whole process isn't
		   * what may or may not get into the DB. it's the fact that if there's anything 
		   * invalid we prevent the post from being published. it's not til something is
		   * published that its QA / DQ really matters. 
		   *
		   * Okay. Okay. Perhaps not ideal but given "The WordPress way" is gets the job done.
		   */
		  update_post_meta( $int_post_id, $arr_init['post_meta_key_for_errors'], $arr_errors_log );
		}
	  }
	}
	
	/**
	  * The goal of the basic validation is to be just that, basic. This method gives you the 
	  * opportunity to do more sophisticated validation. 
	  *
	  * For example, if one field has a value then a second field becomes required, that custom
	  * validation would be added here. You could also decide to manipulate / change the 
	  * $arr_validate_errors here before they are committed to being post_meta'ed.
	  *
	  * All that said, this pre-dates the ability to define / use multiple validations as
	  * seen in form_elements_validation_objects(). In theory, it now seems that anything
	  * fancy / custom could be done via a class defined there. Maybe? We'll see...
	  */
	protected function save_post_validation_do_custom($arr_validate_errors = array()){
	  return $arr_validate_errors;
	}

	/**
	 * admin_notices_echo_error_messages
	 */
	public function admin_notices_echo_error_messages(){
	
	  global $post;
	  
	  if ( isset($post) ){
	    $arr_init = $this->_arr_init;
		if ( in_array($post->post_type, $arr_init['post_types']) ) {
		  if ( ! empty($this->_arr_validate_errors) ){
		    $str_error_box = $this->display_errors( $this->_arr_validate_errors );
			if ( ! empty($str_error_box) ){
			  echo $str_error_box;
			}
		  }
		}
	  }
	}
	
	/**
	 * If we have any error msgs, this is how we render the markup that will display them. 
	 */
	protected function display_errors( $arr_validate_errors = array() ){
	
	  $arr_init = $this->_arr_init; 
	  $arr_form_elements_labels = $this->form_elements_labels();
	  $arr_markup = $this->display_errors_markup();
	  
	  $str_to_return = '';
	  if ( WPezHelpers::ez_array_pass($arr_validate_errors) ){
	    
		$str_error_title = $arr_validate_errors['error_title'];
		unset($arr_validate_errors['error_title']);
		$str_error_intro = $arr_validate_errors['error_intro'];
		unset($arr_validate_errors['error_intro']);
		
		foreach ($arr_validate_errors as $str_label => $arr_inner){
		  foreach ($arr_inner as $str_method => $str_error_msg){
		    $str_to_return .= $arr_markup['row_outer_open'] .  $arr_markup['row_inner_open'] . $arr_form_elements_labels[$str_label] .  $arr_markup['row_inner_close'] . ' - ' . $str_error_msg . $arr_markup['row_outer_close'];
		  }
		}
		
		// if there were error - and there should have been, we're just being extra careful - then put a wrapper around them and send return the whole string. 
		if ( ! empty($str_to_return) ){
		  $str_to_return = $arr_markup['rows_wrap_open'] . $str_to_return . $arr_markup['rows_wrap_close'];
		  
		  $str_to_return = $arr_markup['wrap_open'] . $arr_markup['title_open'] . $str_error_title . $arr_markup['title_close'] .  $arr_markup['intro_open'] . $str_error_intro  .  $arr_markup['intro_close'] . $str_to_return . $arr_markup['wrap_close'];
		}
	  }
	  return $str_to_return;
	}
	
	protected function display_errors_markup(){
	
	  $arr_markup = array(
	  
	    'wrap_open'			=> '<br><div class="ezc-save-post-errors-wrap postbox error">',
		'wrap_close'		=> '</div>',
		'title_open'		=> '<span class="ezc-save-post-errors-title"><h3>',
		'title_close'		=> '</h3></span>',
		'intro_open'		=> '<div class="ezc-save-post-errors-intro"><p><strong>',
		'intro_close'		=> '</strong></p></div>',
		'rows_wrap_open'	=> '<ul>',
		'rows_wrap_close'	=> '</ul>',
		'row_outer_open'	=> '<li>&bull; ',
		'row_outer_close'	=> '</li>',
		'row_inner_open'	=> '<span class="ezc-save-post-errors-label">',
		'row_inner_close'	=> '</span>',
	    );
	
	  return $arr_markup;
	}
	
	/**
	 * 
	 */
	public function post_updated_messages_remove_published($arr_messages){
	  
	  global $post;
	  
	  $arr_init = $this->_arr_init;
	  
	  if ( ! in_array($post->post_type, $arr_init['post_types']) ){
	    return $arr_messages;
	  }
	  
	  /**
	   * Do we have any errors?
	   */
	  $this->_str_error_box = '';
	  $_arr_validate_errors = get_post_meta($post->ID, $arr_init['post_meta_key_for_errors'], true );
	  if ( is_array($_arr_validate_errors) && ! empty($_arr_validate_errors) ) {
	    
		$this_msg = $arr_init['unset_message_int'];
		unset( $arr_messages[$arr_init['unset_message_key']][$this_msg] );
		$this->_arr_validate_errors = $_arr_validate_errors;
	  }
	  return $arr_messages;
	}	
  
  }
} 