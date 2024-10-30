<?php
/*
Plugin Name: HookNews API
Plugin URI: http://hooknews.com/api/index
Description: HookNews API
Version: 1.0.8
Author: Noppadol Sukprapa
Author URI: http://hooknews.com
*/

class HookAPI{	

	var $hook_domain = 'http://www.hooknews.com/';
	
	function init() {
		global $wpdb;
		
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('hooknews', 'wp-content/plugins/hooknews');
		}
		
		$checkTable= 'DESC '.$wpdb->prefix.'hooknews;';
        @mysql_query($checkTable);
        
		if (mysql_errno()==1146){  //table_name doesn't exist

			 mysql_query("CREATE TABLE `".$wpdb->prefix."hooknews` (
							`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
							`content_id` INT NOT NULL ,
							`hooknews_content_id` INT NOT NULL ,
							`status` TINYINT NOT NULL DEFAULT '0',
							`group_id` INT NOT NULL ,
							`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
							);"
						 );	 
        }
		
		$this->api_key = get_option('hooknews_api_key');
		$this->password = get_option('hooknews_password');
		$this->auth_getSession = $this->auth_connect();

	}
	
	function get_base() {
   		 return '/'.end(explode('/', str_replace(array('\\','/hooknews.php'),array('/',''),__FILE__)));
	}
	
	function do_curl($url,$param){
		$request_url = $url;
    	$curl_handle = curl_init();
		curl_setopt($curl_handle,CURLOPT_URL,$request_url );
		curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,30);
		curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl_handle,CURLOPT_POST,1);
		curl_setopt($curl_handle,CURLOPT_POSTFIELDS,$param);
		$return_value = @curl_exec($curl_handle);
		$header = @curl_getinfo($curl_handle);
		@curl_close($curl_handle);
		
		return $return_value;
	}
	
	function auth_connect($reconnect=false){	
	
		if($_COOKIE['api_key'] && $reconnect===false){
			return $_COOKIE['api_key'];
		}else{
			
			if($this->api_key == ''){
				function hooknews_warning() {				
					echo "
						<div class='updated fade below-h2' style='background-color: rgb(255, 251, 204);'><p>HookNews is almost ready. You must <a href='".get_option('siteurl')."/wp-admin/options-general.php?page=hooknews/hooknews.php'>enter your HookNews API key</a> for it to work.</p></div>
					";
				}
		
				add_action('admin_notices', 'hooknews_warning');
				return;
			}
			
			$request_url = $this->hook_domain."api/authen.json";
			$param       = "api_key=".$this->api_key."&password=".$this->password;
			$curl        = $this->do_curl($request_url,$param);
			$obj         = json_decode($curl);
			
			if($_COOKIE['api_key']){  // Clear Cookie
				setcookie ("api_key", "", time()-1500);
			}
			
			setcookie("api_key", $obj->{'key'}, time()+1500); // Expire in 25 minute 
			
			return $obj->{'key'};
		}
		
		
	}
	
	function insert_content($val=array()){	
		$request_url = $this->hook_domain."api/insert.json";
		$param       = "authenKey=".$this->auth_getSession."&title=".$val['title']."&content=".$val['content']."&contentGroup=".$val['cat']."&sourceurl=".$val['link'];
		$curl        = $this->do_curl($request_url,$param);
	    $obj         = json_decode($curl); 
		
		if($_COOKIE['api_key'] && !$obj->{'id'} ){
			$this->auth_connect(true);
		}else{
			return $obj->{'id'}; //return content id of hooknews
		}
		
		
	}
	
	function update_content($val=array()){	
		$request_url = $this->hook_domain."api/update.json";
		$param       = "authenKey=".$this->auth_getSession."&id=".$val['hooknews_id']."&title=".$val['title']."&content=".$val['content']."&contentGroup=".$val['cat']."&sourceurl=".$val['link'];
		$curl        = $this->do_curl($request_url,$param);
	    $obj         = json_decode($curl);
		
		if($_COOKIE['api_key'] && !$obj->{'id'} ){
			$this->auth_connect(true);
		}else{
			return $obj->{'id'}; //return content id of hooknews
		}
	}
	
	function remove_content($id){	
		$request_url = $this->hook_domain."api/remove.json";
		$param       = "authenKey=".$this->auth_getSession."&id=".$id;
		$curl        = $this->do_curl($request_url,$param);
	    $obj         = json_decode($curl);
		
		if($_COOKIE['api_key'] && !$obj->{'id'} ){
			$this->auth_connect(true);
		}else{
			return $obj->{'id'}; //return content id of hooknews
		}
	}
	
	function add_admin_menu() {
    	// Add a new top-level menu:
   		// The first parameter is the Page name(Site Help), second is the Menu name(Help)
    	// and the number(5) is the user level that gets access
    	//add_menu_page('Hook News', 'Hook News', 5, __FILE__, 'admin_hooknews');
		add_options_page(__('Hook News', 'Hook News'), __('Hook News', 'Hook News'), "manage_options", __FILE__, array(&$this, 'admin_hooknews'));
	}
	
	function admin_hooknews(){
		
		if($_POST['submit']){
			
			/**
			 * add/edit configuration
			 */
			
			if(get_option('hooknews_api_key') != $_POST['hooknews_api_key']) 
				update_option('hooknews_api_key', $_POST['hooknews_api_key']);
			else
				add_option("hooknews_api_key", $_POST['hooknews_api_key'], '', 'yes');
				
			/*	
			if(get_option('hooknews_password') != $_POST['hooknews_password']) 
				update_option('hooknews_password', $_POST['hooknews_password']);
			else
				add_option("hooknews_password", $_POST['hooknews_password'], '', 'yes');	
			*/
			
			if(get_option('hooknews_default_category') != $_POST['hooknews_cat_val']) 
				update_option('hooknews_default_category', $_POST['hooknews_cat_val']);
			else
				add_option("hooknews_default_category", $_POST['hooknews_cat_val'], '', 'yes');
				
		
			echo '<script type="text/javascript"> ';
			echo ' window.location = "'.get_option('siteurl').'/wp-admin/options-general.php?page=hooknews/hooknews.php&e=1"';
			echo '</script>';
			exit;	
			
			
		}
		
		$getCatVal           = get_option('hooknews_default_category');
		$hooknews_api_key    = get_option('hooknews_api_key');
		$hooknews_password   = get_option('hooknews_password');
		$flag                = ( $_GET['e'] != 1 ) ? 0 : 1;
		
		
		if(!$getCatVal) $getCatVal = 9; /// Default is general
		
		/*
		 * Render config page
		 */
		
		echo "<div class=\"wrap\">";
		echo "<h2>" . __('HookNews Settings', 'hooknews') . "</h2>\r\n";
		
		if($flag==1){
		
	   /*
	    * Render Message
		*/
			 
	    	echo '<div class="updated fade below-h2" id="message" style="background-color: rgb(255, 251, 204);"><p>Configuration saved.</p></div>';
		}
		echo "<h3>" . __('HookNews API', 'hooknews') . "</h3>\r\n";
		echo "<form action='' method='post' >";
		echo '<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
						<tbody>

						<tr valign="top">
							<th scope="row" style="width: 70px;"><label for="hooknews_api_key">API Key:</label></th>
							<td>
							<input type="text" value="'.$hooknews_api_key.'" size="50" id="hooknews_api_key" name="hooknews_api_key">
							</td>
						</tr>						
     				</tbody></table>';
		
		echo '<h3 clear:both">Default Category:</h3>
          					<ul style="list-style:none; margin-left:5px;">
                            	<li><input type="radio" name="hooknews_cat_val" value="1" ';
								if($getCatVal==1) echo 'checked="checked"';
								echo '/> การเมือง</li>
                                <li><input type="radio" name="hooknews_cat_val" value="5" ';
								if($getCatVal==5) echo 'checked="checked"';
								echo '/> บันเเทิง</li>
                                <li><input type="radio" name="hooknews_cat_val" value="4" ';
								if($getCatVal==4) echo 'checked="checked"';
								echo '/> กีฬา</li>
                                <li><input type="radio" name="hooknews_cat_val" value="6" ';
								if($getCatVal==6) echo 'checked="checked"';
								echo '/> ไอที</li>
                                <li><input type="radio" name="hooknews_cat_val" value="3" ';
								if($getCatVal==3) echo 'checked="checked"';
								echo '/> เศรษฐกิจ</li>
                                <li><input type="radio" name="hooknews_cat_val" value="7" ';
								if($getCatVal==7) echo 'checked="checked"';
								echo '/> ต่างประเทศ</li>
                                <li><input type="radio" name="hooknews_cat_val" value="8" ';
								if($getCatVal==8) echo 'checked="checked"';
								echo '/> ไลฟ์สไตล์</li>
                                <li><input type="radio" name="hooknews_cat_val" value="9" ';
								if($getCatVal==9) echo 'checked="checked"';
								echo '/> ทั่วไป</li>
                            </ul>';
		echo '<p align="center" class="submit"><input type="submit" value="Submit" name="submit" id="save"></p>';					
		echo "</form>";					
		echo "</div>";		
	}
	
	function hooknews_form(){
		global $wp_version;
		global $post;
		global $wpdb;
		
		$getCatVal = get_option('hooknews_default_category');
		$allow     = ( $_GET['action'] != 'edit' ) ? 1 : 0;
		
		if($_GET['post']){	
			$sql  = "select * from ".$wpdb->prefix."hooknews where content_id=".$_GET['post'];
			$qr   = mysql_query($sql);
			$num  = mysql_num_rows($qr);
		
			if($num>0){
			
				$rs = mysql_fetch_assoc($qr);
				
				$getCatVal = $rs['group_id'];
				$allow     = $rs['status'];
			
			}
		
		}else{
				
			if(!$getCatVal) $getCatVal = 9; /// Default is general
				
		}
		

	?>
		 <?php if (substr($wp_version, 0, 3) >= '2.5') { ?>
                <div class="postbox">
                <h3><?php _e('Hook News', 'hooknews') ?></h3>
                <div class="inside">
                <div id="hooknews_form">
                <?php } else { ?>
                <div class="dbx-b-ox-wrapper">
                <div class="dbx-h-andle-wrapper">
                <h3 class="dbx-handle"><?php _e('Hook News', 'hooknews') ?></h3>
                </div>
                <div class="dbx-c-ontent-wrapper">
                <div class="dbx-content">
                <?php } ?>	
                	<?php if(!isset($this->auth_getSession)){ ?>
                    	<p>Please config API Key in <a href="<?php echo get_option('siteurl')."/wp-admin/options-general.php?page=hooknews/hooknews.php" ?>"><?php echo get_option('siteurl')."/wp-admin/options-general.php?page=hooknews/hooknews.php" ?></p>
					<?php }else{ ?>
                    	
                        <label class="selectit" for="allow_hooknews"> <input type="checkbox" checked="checked" value="1" id="allow_hooknews" name="allow_hooknews"> Allow content to HookNews.</label>
                        <div class="catlist" style="padding:0 20px 10px">
                        	<h4 style="margin:8px 0">Category:</h4>
          					<ul style="list-style:none; margin-left:5px;">
                            	<li><input type="radio" name="hooknews_cat_val" value="1" <?php if($getCatVal==1) echo 'checked="checked"' ?> /> การเมือง</li>
                                <li><input type="radio" name="hooknews_cat_val" value="5" <?php if($getCatVal==5) echo 'checked="checked"' ?> /> บันเเทิง</li>
                                <li><input type="radio" name="hooknews_cat_val" value="4" <?php if($getCatVal==4) echo 'checked="checked"' ?> /> กีฬา</li>
                                <li><input type="radio" name="hooknews_cat_val" value="6" <?php if($getCatVal==6) echo 'checked="checked"' ?> /> ไอที</li>
                                <li><input type="radio" name="hooknews_cat_val" value="3" <?php if($getCatVal==3) echo 'checked="checked"' ?> /> เศรษฐกิจ</li>
                                <li><input type="radio" name="hooknews_cat_val" value="7" <?php if($getCatVal==7) echo 'checked="checked"' ?> /> ต่างประเทศ</li>
                                <li><input type="radio" name="hooknews_cat_val" value="8" <?php if($getCatVal==8) echo 'checked="checked"' ?> /> ไลฟ์สไตล์</li>
                                <li><input type="radio" name="hooknews_cat_val" value="9" <?php if($getCatVal==9) echo 'checked="checked"' ?> /> ทั่วไป</li>
                            </ul>                    	
                        </div>
                        <!--
                    	<input type="button" name="export_content" value="Export content to Hook News" />
                        -->
                    <?php } ?>
				<?php if (substr($wp_version, 0, 3) >= '2.5') { ?>
                </div></div></div>
                <?php } else { ?>
                </div>
                </div>
                <?php } ?>
    <?php            
	}
	
	
	function post_value($id) {
		global $wpdb;
		
		$table     = $wpdb->prefix.'hooknews';
		
		if($_POST['post_status'] == 'publish' && $this->auth_getSession && $_POST['allow_hooknews']==1){

			$sql = "select * from ".$wpdb->prefix."hooknews where content_id=".$_POST['post_ID'];
			$qr  = mysql_query($sql);
			$num = mysql_num_rows($qr);
			
			if($num==0){ /// insert content to HookNews
				
				$valArr['title']   =  $_POST['post_title'];
				$valArr['content'] =  urlencode(trim(strip_tags($_POST['post_content'],'<p><a><img><strong><b><br>')));
				$valArr['cat']     =  $_POST['hooknews_cat_val'];
				$valArr['link']    =  get_permalink($_POST['post_ID']);
				//$valArr['date']  =  $_POST['post_title'];
				
				$insert_id = $this->insert_content($valArr);
				
				if($insert_id){
					
					mysql_query("INSERT INTO `".$wpdb->prefix."hooknews` (
									`id` ,
									`content_id` ,
									`hooknews_content_id` ,
									`group_id` ,
									`status` ,
									`created`
									)
									VALUES (
									NULL , '".$_POST['post_ID']."', '".$insert_id."', '".$_POST['hooknews_cat_val']."', '1',
									CURRENT_TIMESTAMP
									);"
								);
				}
				
			}else{ // Update content to hooknews
			
				$rs = mysql_fetch_assoc($qr);
				$hooknews_content_id = $rs['hooknews_content_id'];

				
				$valArr['hooknews_id'] =  $hooknews_content_id;
				$valArr['title']       =  $_POST['post_title'];
				$valArr['content']     =  urlencode(trim(strip_tags($_POST['post_content'],'<p><a><img><strong><b><br>')));
				$valArr['cat']         =  $_POST['hooknews_cat_val'];
				$valArr['link']        =  get_permalink($_POST['post_ID']);
				
				mysql_query("update `".$wpdb->prefix."hooknews` set `group_id`='".$_POST['hooknews_cat_val']."'  where `content_id`='".$_POST['post_ID']."'");
				
				$insert_id = $this->update_content($valArr);
				
			}	
		}elseif($_POST['allow_hooknews']==0){
			
			$this->delete_hooknews_content($_POST['post_ID']);
			
		}
	}
	
	function delete_hooknews_content($content_id=0){
		global $wpdb;
		
		$content_id = ( isset($_GET['post']) ) ? $_GET['post'] : $content_id; 
		
		$sql = "select * from ".$wpdb->prefix."hooknews where content_id=".$content_id;
		$qr  = mysql_query($sql);
		$num = mysql_num_rows($qr);
		
		if($num>0){
			$rs = mysql_fetch_assoc($qr);
			
			mysql_query("delete from ".$wpdb->prefix."hooknews where content_id=".$content_id);
			$this->remove_content($rs['hooknews_content_id']);
		}
	}
	
}//end class

$hooknews = new HookAPI();
add_action('init', array($hooknews, 'init'));

if (substr($wp_version, 0, 3) >= '2.5') {
	add_action('edit_form_advanced', array($hooknews, 'hooknews_form'));
	add_action('edit_page_form', array($hooknews, 'hooknews_form'));
} else {
	add_action('dbx_post_advanced', array($hooknews, 'hooknews_form'));
	add_action('dbx_page_advanced', array($hooknews, 'hooknews_form'));
}

//add_action('edit_post', array($hooknews, 'post_value'));
add_action('publish_post', array($hooknews, 'post_value'));
add_action('publish_page', array($hooknews, 'post_value'));
add_action('deleted_post', array($hooknews, 'delete_hooknews_content'));
add_action('admin_menu', array($hooknews, 'add_admin_menu'));

?>