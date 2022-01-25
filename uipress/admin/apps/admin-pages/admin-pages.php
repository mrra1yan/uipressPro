<?php
if (!defined("ABSPATH")) {
  exit();
}

class uipress_admin_pages
{
  public function __construct($version, $pluginName, $pluginPath, $textDomain, $pluginURL)
  {
    $this->version = $version;
    $this->pluginName = $pluginName;
    $this->textDomain = $textDomain;
    $this->path = $pluginPath;
    $this->pathURL = $pluginURL;
    $this->utils = new uipress_util();
  }

  /**
   * Loads menu editor actions
   * @since 1.0
   */

  public function run()
  {
    ///REGISTER THIS COMPONENT
    add_filter("uipress_register_settings", [$this, "admin_pages_settings_options"], 1, 2);

    $utils = new uipress_util();
    $creatorDisabled = $utils->get_option("admin-pages", "status");

    if ($creatorDisabled == "true") {
      return;
    }

    if (function_exists("is_network_admin")) {
      if (is_network_admin()) {
        return;
      }
    }

    if (isset($_GET["page"])) {
      if ($_GET["page"] == "uip-admin-pages") {
        add_action("admin_enqueue_scripts", [$this, "add_scripts"]);
        add_action("wp_print_scripts", [$this, "uip_dequeue_script"], 100);
        add_action("admin_enqueue_scripts", [$this, "uip_dequeue_forms"], 100);
        add_action("parent_file", [$this, "capture_wp_menu"], 999);
      }
    }

    add_action("admin_menu", [$this, "add_menu_item"]);
    add_action("init", [$this, "uipress_create_admin_page_cpt"], 0);

    //AJAX
    add_action("wp_ajax_uipress_get_users_and_roles", [$this, "uipress_get_users_and_roles"]);
    add_action("wp_ajax_uipress_save_admin_page", [$this, "uipress_save_admin_page"]);
    add_action("wp_ajax_uipress_get_admin_pages", [$this, "uipress_get_admin_pages"]);

    add_action("wp_ajax_uipress_delete_menu", [$this, "uipress_delete_menu"]);
    add_action("wp_ajax_uipress_switch_menu_status", [$this, "uipress_switch_menu_status"]);
    add_action("wp_ajax_uipress_duplicate_menu", [$this, "uipress_duplicate_menu"]);
    add_action("wp_ajax_uipress_get_menu_items", [$this, "uipress_get_menu_items"]);
  }

  /**
   * Blocks default wp menu output
   * @since 2.2
   */
  public function capture_wp_menu($parent_file)
  {
    ///CHECK FOR CUSTOM MENU FIRST
    $userid = get_current_user_id();

    ///NO CUSTOM MENU SO PREPARE DEFAULT MENU
    global $menu, $submenu, $self, $parent_file, $submenu_file, $plugin_page, $typenow;
    $this->menu = $menu;
    //CREATE MENU CONSTRUCTOR OBJECT
    $mastermenu["self"] = $self;
    $mastermenu["parent_file"] = $parent_file;
    $mastermenu["submenu_file"] = $submenu_file;
    $mastermenu["plugin_page"] = $plugin_page;
    $mastermenu["typenow"] = $typenow;
    $mastermenu["menu"] = $menu;
    $mastermenu["submenu"] = $submenu;
    ///FORMAT DEFAULT MENU
    $formattedMenu = $mastermenu;
    $mastermenu["menu"] = $formattedMenu;

    set_transient("uip-admin-menu-" . $userid, $mastermenu, 0.5 * HOUR_IN_SECONDS);

    return $parent_file;
  }

  /**
   * Dequeue scripts that cause compatibility issues
   * @since 1.4
   */
  public function uip_dequeue_script()
  {
    wp_dequeue_script("wp-ultimo");
    wp_dequeue_script("wu-admin");
    wp_dequeue_script("wu-vue");
    wp_deregister_script("wu-vue");
    wp_deregister_style("common");
    wp_dequeue_style("common");
  }

  /**
   * Dequeue default forms style that alters the page editor
   * @since 1.4
   */
  public function uip_dequeue_forms()
  {
    wp_dequeue_style("forms");
    wp_deregister_style("forms");
    wp_register_style("forms", $this->pathURL . "assets/css/uip-blank.css", [], $this->version);
    wp_enqueue_style("forms");
  }

  /**
   * Returns settings options for settings page
   * @since 2.2
   */
  public function admin_pages_settings_options($settings, $network)
  {
    $utils = new uipress_util();

    ///////FOLDER OPTIONS
    $moduleName = "admin-pages";
    $category = [];
    $options = [];
    //
    $category["module_name"] = $moduleName;
    $category["label"] = __("Menu Creator", $this->textDomain);
    $category["description"] = __("Creates custom admin menus.", $this->textDomain);
    $category["icon"] = "segment";

    $temp = [];
    $temp["name"] = __("Disable Menu Creator?", $this->textDomain);
    $temp["description"] = __("If disabled, the menu creator will not be available to any users.", $this->textDomain);
    $temp["type"] = "switch";
    $temp["optionName"] = "status";
    $temp["value"] = $utils->get_option($moduleName, $temp["optionName"]);
    $options[$temp["optionName"]] = $temp;

    $category["options"] = $options;
    $settings[$moduleName] = $category;

    return $settings;
  }

  /**
   * Creates custom folder post type
   * @since 1.4
   */
  public function uipress_create_admin_page_cpt()
  {
    $labels = [
      "name" => _x("Admin Page", "post type general name", $this->textDomain),
      "singular_name" => _x("Admin Page", "post type singular name", $this->textDomain),
      "menu_name" => _x("Admin Pages", "admin menu", $this->textDomain),
      "name_admin_bar" => _x("Admin Page", "add new on admin bar", $this->textDomain),
      "add_new" => _x("Add New", "folder", $this->textDomain),
      "add_new_item" => __("Add New Admin Page", $this->textDomain),
      "new_item" => __("New Admin Page", $this->textDomain),
      "edit_item" => __("Edit Admin Page", $this->textDomain),
      "view_item" => __("View Admin Page", $this->textDomain),
      "all_items" => __("All Admin Pages", $this->textDomain),
      "search_items" => __("Search Admin Pages", $this->textDomain),
      "not_found" => __("No Admin Pages found.", $this->textDomain),
      "not_found_in_trash" => __("No Admin Pages found in Trash.", $this->textDomain),
    ];
    $args = [
      "labels" => $labels,
      "description" => __("Description.", "Add New Admin Page"),
      "public" => false,
      "publicly_queryable" => false,
      "show_ui" => false,
      "show_in_menu" => false,
      "query_var" => false,
      "has_archive" => false,
      "hierarchical" => false,
    ];
    register_post_type("uip-admin-page", $args);
  }
  /**
   * Fetches users and roles
   * @since 2.0.8
   */

  public function uipress_get_menu_items()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $userid = get_current_user_id();
      $menu = get_transient("uip-admin-menu-" . $userid);
      $returndata["menu"] = $menu;
      echo json_encode($returndata);
    }
    die();
  }

  /**
   * Fetches users and roles
   * @since 2.0.8
   */

  public function uipress_get_admin_pages()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $returndata = [];

      $args = [
        "post_type" => "uip-admin-page",
        "post_status" => "publish",
        "numberposts" => -1,
      ];

      $pages = get_posts($args);
      $formattedmenus = [];

      foreach ($pages as $page) {
        $temp = [];
        $temp["id"] = $page->ID;
        $temp["name"] = esc_html(get_the_title($page->ID));
        $temp["content"] = wpautop($page->post_content);
        $temp["status"] = get_post_meta($page->ID, "status", true);
        $temp["subsites"] = get_post_meta($page->ID, "subsites", true);
        $temp["roleMode"] = get_post_meta($page->ID, "role_mode", true);
        $temp["appliedTo"] = get_post_meta($page->ID, "applied_to", true);

        if (!is_array($temp["appliedTo"])) {
          $temp["appliedTo"] = [];
        }

        $temp["date"] = get_the_date(get_option("date_format"), $page->ID);

        $formattedmenus[] = $temp;
      }

      $returndata["menus"] = $formattedmenus;

      echo json_encode($returndata);
    }
    die();
  }

  public function uipress_switch_menu_status()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $menuid = $this->utils->clean_ajax_input($_POST["menuid"]);
      $status = $this->utils->clean_ajax_input($_POST["status"]);

      $returndata = [];

      if (!$menuid || $menuid == "" || $status == "") {
        $returndata["error"] = _e("Something went wrong", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      update_post_meta($menuid, "status", $status);

      $returndata["message"] = __("Status Updated", $this->textDomain);

      $userid = get_current_user_id();
      delete_transient("uip-custom-admin-menu-" . $userid);

      echo json_encode($returndata);
    }
    die();
  }

  public function uipress_delete_menu()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $menuid = $this->utils->clean_ajax_input($_POST["menuid"]);

      $returndata = [];

      if (!$menuid || $menuid == "") {
        $returndata["error"] = _e("Something went wrong", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      if (!current_user_can("delete_post", $menuid)) {
        $returndata["error"] = _e('You don\'t have permission to delete this', $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      $status = wp_delete_post($menuid);

      if (!$status) {
        $returndata["error"] = _e("Unable to delete menu", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      $returndata["message"] = __("Menu deleted", $this->textDomain);

      $userid = get_current_user_id();
      delete_transient("uip-custom-admin-menu-" . $userid);

      echo json_encode($returndata);
    }
    die();
  }

  public function uipress_duplicate_menu()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $menu = $this->utils->clean_ajax_input_html($_POST["menu"]);

      $returndata = [];

      if (!$menu || $menu == "") {
        $returndata["error"] = _e("Something went wrong", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      if (!isset($menu["items"]) || !is_array($menu["items"])) {
        $returndata["error"] = _e("Unable to duplicate menu, menu is corrupted", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      $my_post = [
        "post_title" => $menu["name"] . " " . __("(copy)", $this->textDomain),
        "post_status" => "publish",
        "post_type" => "uipress_admin_menu",
      ];

      $themenuID = wp_insert_post($my_post);

      if (!$themenuID) {
        $returndata["error"] = __("Unable to duplicate menu", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      update_post_meta($themenuID, "items", $menu["items"]);
      update_post_meta($themenuID, "status", "false");
      update_post_meta($themenuID, "role_mode", $menu["roleMode"]);
      update_post_meta($themenuID, "applied_to", $menu["appliedTo"]);

      $returndata["message"] = __("Menu duplicated", $this->textDomain);
      $returndata["original"] = $menu["items"];

      echo json_encode($returndata);
    }
    die();
  }

  /**
   * Fetches users and roles
   * @since 2.0.8
   */

  public function uipress_save_admin_page()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $page = json_decode(stripslashes($_POST["menu"]));

      $sanitized = $this->utils->clean_ajax_input_admin_pages_editor($page);

      $returndata = [];

      if (!$page || $page == "") {
        $returndata["error"] = _e("Something went wrong", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      if (!isset($page->content)) {
        $page->content = "";
      }

      $my_post = [
        "post_title" => wp_strip_all_tags($page->name),
        "post_status" => "publish",
        "post_type" => "uip-admin-page",
        "post_content" => $page->content,
      ];

      // Insert the post into the database.
      // UPDATE OR CREATE NEW
      if (isset($page->id) && $page->id > 0) {
        $my_post["ID"] = $page->id;
        $themenuID = wp_update_post($my_post);
      } else {
        $themenuID = wp_insert_post($my_post);
      }

      if (!$themenuID) {
        $returndata["error"] = __("Unable to save menu", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      if ($page->status == true) {
        $stat = "true";
      } else {
        $stat = "false";
      }

      if (isset($page->subsites) && $page->subsites == true) {
        $subs = "true";
      } else {
        $subs = "false";
      }

      update_post_meta($themenuID, "status", $stat);
      update_post_meta($themenuID, "subsites", $subs);
      update_post_meta($themenuID, "role_mode", $page->roleMode);
      update_post_meta($themenuID, "applied_to", $page->appliedTo);

      $returndata["message"] = __("Menu Saved", $this->textDomain);
      $returndata["menuID"] = $themenuID;

      echo json_encode($returndata);
    }
    die();
  }

  public function uipress_get_users_and_roles()
  {
    if (defined("DOING_AJAX") && DOING_AJAX && check_ajax_referer("uip-security-nonce", "security") > 0) {
      $term = $this->utils->clean_ajax_input($_POST["searchString"]);

      $returndata = [];

      if (!$term || $term == "") {
        $returndata["error"] = _e("Something went wrong", $this->textDomain);
        echo json_encode($returndata);
        die();
      }

      $term = strtolower($term);

      $users = new WP_User_Query([
        "search" => "*" . esc_attr($term) . "*",
        "fields" => ["display_name"],
        "search_columns" => ["user_login", "user_nicename", "user_email", "user_url"],
      ]);

      $users_found = $users->get_results();
      $empty_array = [];

      foreach ($users_found as $user) {
        $temp = [];
        $temp["name"] = $user->display_name;
        $temp["label"] = $user->display_name;

        array_push($empty_array, $temp);
      }

      global $wp_roles;

      foreach ($wp_roles->roles as $role) {
        $rolename = $role["name"];

        if (strpos(strtolower($rolename), $term) !== false) {
          $temp = [];
          $temp["label"] = $rolename;
          $temp["name"] = $rolename;

          array_push($empty_array, $temp);
        }
      }

      if (strpos(strtolower("Super Admin"), $term) !== false) {
        $temp = [];
        $temp["name"] = "Super Admin";
        $temp["label"] = "Super Admin";

        array_push($empty_array, $temp);
      }

      $returndata["roles"] = $empty_array;

      echo json_encode($returndata);
    }
    die();
  }

  /**
   * Grabs unmodified menu
   * @since 1.4
   */

  public function set_menu($parent_file)
  {
    global $menu, $submenu;
    $this->menu = $this->sort_menu_settings($menu);
    $this->submenu = $this->sort_sub_menu_settings($this->menu, $submenu);

    return $parent_file;
  }

  /**
   * Enqueue menu editor scripts
   * @since 1.4
   */

  public function add_scripts()
  {
    ///ADMIN PAGES EDITOR
    wp_enqueue_script("uip-grapejs", $this->pathURL . "admin/apps/admin-pages/js/grapejs.js", ["uip-app"], $this->version, true);
    wp_enqueue_script("uip-grapejs-webpage", $this->pathURL . "admin/apps/admin-pages/js/grapejs-webpage.js", ["uip-app"], $this->version, true);
    wp_enqueue_script("admin-pages-creator-js", $this->pathURL . "admin/apps/admin-pages/js/admin-pages-creator-app.min.js", ["uip-app"], $this->version, true);
    //ADMIN PAGES STYLES
    wp_register_style("uip-grapejs-style", $this->pathURL . "admin/apps/admin-pages/css/grapejs.css", [], $this->version);
    wp_enqueue_style("uip-grapejs-style");

    //wp_dequeue_style("forms");
    //wp_deregister_style("forms");
    //wp_dequeue_style("forms");
  }

  /**
   * Adds menu editor page to settings
   * @since 1.4
   */

  public function add_menu_item()
  {
    add_options_page("UiPress Admin Pages Creator", __("Admin Pages Creator", $this->textDomain), "manage_options", "uip-admin-pages", [$this, "uip_admin_pages_app"]);
  }

  public function add_menu_item_network()
  {
    add_submenu_page(
      "settings.php", // Parent element
      "Admin Pages Creator", // Text in browser title bar
      __("Admin Pages Creator", $this->textDomain), // Text to be displayed in the menu.
      "manage_options", // Capability
      "uip-admin-pages", // Page slug, will be displayed in URL
      [$this, "uip_admin_pages_app"] // Callback function which displays the page
    );
  }

  /**
   * Creates menu editor page
   * @since 1.4
   */

  public function uip_admin_pages_app()
  {
    $previewImage = $this->pathURL . "assets/img/menu-creator-preview.png"; ?>
		<style>
			  #wpcontent{
				  padding-left: 0;
			  }
			  #wpfooter{
					display: none;
				}
				#wpbody-content{
					padding:0;
				}
		</style>
		
		<div id="admin-pages-creator-app" class="uip-text-normal uip-background-default">
			
			<div class="uip-fade-in uip-hidden" :class="{'uip-nothidden' : !loading}">
			
				<div  v-if="!loading && !dataConnect" class="uip-width-100p uip-position-relative">
					<img class="uip-w-100p " src="<?php echo $previewImage; ?>">
					
					
					<div class="uip-position-absolute uip-top-0 uip-bottom-0 uip-left-0 uip-right-0" 
					style="background: linear-gradient(0deg, rgba(255,255,255,1) 0%, rgba(255,255,255,0) 100%);"></div>
					
					<div class="uip-position-absolute uip-top-0 uip-bottom-0 uip-left-0 uip-right-0 uip-flex uip-flex-center uip-flex-middle">
					  
					  
					  <div class="uip-background-default uip-border-round uip-padding-m uip-shadow uip-flex uip-flex-center uip-flex-column">
						<div class="uip-flex uip-text-l uip-text-bold uip-margin-bottom-s">
						  <span class="material-icons-outlined uip-margin-right-xs">redeem</span>
						  <span><?php _e("Pro Feature", $this->textDomain); ?></span>
						</div> 
						
						<p class="uip-text-normal uip-margin-bottom-m"><?php _e("Upgrade to UiPress Pro to unlock the admin page creator", $this->textDomain); ?></p>
						
						<a href="https://uipress.co/pricing/" target="_BLANK" class="uip-button-primary uip-no-underline"><?php _e("See UiPress Pro Plans", $this->textDomain); ?></a>
					  </div>
					  
					</div>
				</div>
				
				<template v-if="!loading && dataConnect">
				
					
					
					<?php $this->build_menu_list(); ?>
					<?php $this->build_editor(); ?>
				
				</template>
			
			</div>
			
		</div>
		
		<?php
  }

  public function build_menu_list()
  {
    ?>
		<div class="uip-padding-m uip-max-w-900 uip-margin-auto" v-if="!ui.editingMode">
			
			<div class="uip-flex uip-margin-bottom-l">
				<div class="uip-flex-grow">
					<div class="uip-text-emphasis uip-text-xxl uip-text-bold">
						<?php _e("Admin Page Creator", $this->textDomain); ?>
					</div>
				</div>
				
				<div class="">
					<button @click="createNewMenu()" class="uip-button-primary" type="button"><?php _e("New", $this->textDomain); ?></button>
				</div>
			
			</div>
			
			<div v-if="user.allMenus.length < 1" class="uip-padding-m uip-text-center ">
				<p class="uip-text-xl uip-text-muted"><?php _e('Looks like you haven\'t created any admin menus yet', $this->textDomain); ?></p>
				<button class="uip-button-primary " type="button" @click="createNewMenu()"><?php _e("Create your first admin menu", $this->textDomain, $this->textDomain); ?></button>
			</div>
			
			<div v-if="user.allMenus.length > 0" class="uip-background-muted uip-border-round uip-padding-s uip-margin-bottom-s" >
				<div class="uip-flex">
					
					
					<div class="uip-text-bold uip-flex-grow">
						<?php _e("Name", $this->textDomain); ?>
					</div>
					
					<div class="uip-text-bold uip-w-200">
						<?php _e("Status", $this->textDomain); ?>
					</div>
										
					<div class=" uip-text-bold uip-w-200">
						<?php _e("Date", $this->textDomain); ?>
					</div>
					
					<div style="width:40px;">
					</div>
					
					
				</div>
				
			</div>
			
			<template v-for="menu in user.allMenus">
			
				<div class="uip-padding-s">
					
					<div class="uip-flex uip-flex-between">
						
						
						<div class="uip-flex-grow">
							<a href="#" class="uip-text-bold uip-link-muted uip-no-underline uip-text-emphasis" @click="openMenu(menu)">{{menu.name}}</a>
						</div>
						
						<div class="uip-w-200">
							<label class="uip-switch">
							  <input type="checkbox" v-model="menu.status" @change="switchStatus(menu.id, menu.status)">
							  <span class="uip-slider"></span>
							</label>
						</div>
						
						<div class="uip-w-200">
							{{menu.date}}
						</div>
						
						<div style="width:40px;">
							
								
								<uip-dropdown type="icon" icon="more_horiz" pos="botton-left" size="small">
							
									
										
										<ul class="uip-flex uip-flex-column uip-margin-remove">
											<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
												<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" @click="openMenu(menu)" >
													<span class="material-icons-outlined uip-margin-right-xs">edit</span>
													<?php _e("Edit", $this->textDomain); ?>
												</a>
											</li>
											
											<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
												<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex"@click="duplicateMenu(menu)" >
													<span class="material-icons-outlined uip-margin-right-xs">copy</span>
													<?php _e("Duplicate", $this->textDomain); ?>
												</a>
											</li>
											
											<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
												<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" @click="exportMenu(menu)" >
													<span class="material-icons-outlined uip-margin-right-xs">file_download</span>
													<?php _e("Export", $this->textDomain); ?>
												</a>
												<a href="#" id="uipress-export-menus" class="uip-hidden"></a>
											</li>
											
											<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
												<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" @click="confirmDelete(menu)" >
													<span class="material-icons-outlined uip-margin-right-xs">delete</span>
													<?php _e("Delete", $this->textDomain); ?>
												</a>
											</li>
										</ul>
										
								</uip-dropdown>
							
							<!-- END OF DROPDOWN -->
							
							
						</div>
						
						
					</div>
					
				</div>
				
			</template>
			
		</div>
		
		<?php
  }

  public function build_editor()
  {
    $logo = esc_url($this->pathURL . "/assets/img/default_logo.png");
    $dark_logo = "";
    ?>
		
		<div class="uip-padding-s uip-border-box uip-border-bottom uip-border-top uip-background-default" v-if="ui.editingMode">
			<?php $this->build_header(); ?>
		</div>
		
		<div  v-if="ui.editingMode && isSmallScreen()">
			<div class="uip-padding-m">
				<div class="notice">
					<p class="uip-text-bold"><?php _e('Menu creator isn\'t optimised for mobile devices. For best results switch to a larger screen', $this->textDomain); ?></p>
				</div>
			</div>
		</div>
		
		<div  class="uip-flex" v-if="ui.editingMode" style="height:calc(100vh - 73px - var(--uip-toolbar-height)); max-height:calc(100vh - 73px - var(--uip-toolbar-height))">
			
			<div v-if="!isSmallScreen()"
			class="uip-w-300 uip-background-default uip-h-100p uip-border-right uip-overflow-auto uip-padding-s uip-flex uip-flex-column uip-border-box"  >
				
				
				<div class="" >
					
					<div class="">
					
						<div class="uip-margin-bottom-m">
							<div class="uip-text-bold uip-margin-bottom-xs"><?php _e("Status", $this->textDomain); ?></div>
							<label class="uip-switch">
							  <input type="checkbox" v-model="user.currentMenu.status">
							  <span class="uip-slider"></span>
							</label>
						</div>
						
						<?php if (is_main_site() && is_multisite()) { ?>
						
						<div class="uip-margin-bottom-m">
							<div class="uip-text-bold uip-margin-bottom-xs"><?php _e("Apply to subsites", $this->textDomain); ?></div>
							<label class="uip-switch">
							  <input type="checkbox" v-model="user.currentMenu.subsites">
							  <span class="uip-slider"></span>
							</label>
						</div>
						
						<?php } ?>
						
						<div class="uip-margin-bottom-m">
							<div class="uip-text-bold uip-margin-bottom-xs"><?php _e("Page Name", $this->textDomain); ?></div>
							<input class="uip-padding-xs uip-border-round" v-model="user.currentMenu.name" type="text" placeholder="<?php _e("Menu Name", $this->textDomain); ?>">
						</div>
            
            <div class="uip-margin-bottom-m">
              <div class="uip-text-bold uip-margin-bottom-xs"><?php _e("Page Slug", $this->textDomain); ?></div>
              <input class="uip-padding-xs uip-border-round" v-model="user.currentMenu.slug" type="text" placeholder="<?php _e("Menu Slug", $this->textDomain); ?>">
            </div>
						
						<div class="uip-margin-bottom-s">
							<div class="uip-text-bold uip-margin-bottom-xs"><?php _e("Page Applies to", $this->textDomain); ?></div>
							<div class=" uip-background-muted uip-border-round uip-padding-xxs uip-margin-bottom-xs">
								<button type="button" class="uip-button-default uip-w-50p" :class="{ 'uip-background-default' : user.currentMenu.roleMode == 'inclusive'}" 
								  @click="user.currentMenu.roleMode = 'inclusive'"> 
									<?php _e("Inclusive", $this->textDomain); ?>
								</button>
								<button type="button" class="uip-button-default uip-w-50p" :class="{ 'uip-background-default' : user.currentMenu.roleMode == 'exclusive'}" 
								  @click="user.currentMenu.roleMode = 'exclusive'">
									<?php _e("Exclusive", $this->textDomain); ?>
								</button>
							</div>
							<p class="uip-text-muted" v-if="user.currentMenu.roleMode == 'inclusive'">
								<?php _e("In Inclusive mode, this page will show for all Usernames and roles selected below.", $this->textDomain); ?>
							</p>
							<p class="uip-text-muted" v-if="user.currentMenu.roleMode == 'exclusive'">
								<?php _e("In Exclusive mode, this page will load for every user except those Usernames and roles selected below.", $this->textDomain); ?>
							</p>
						</div>
						
						<div class="uip-margin-bottom-m">
							<multi-select :selected="user.currentMenu.appliedTo"
							:name="'<?php _e("Choose users or roles...", $this->textDomain); ?>'"
							:single='false'
							:placeholder="'<?php _e("Search roles and users...", $this->textDomain); ?>'"></multi-select>
						</div>
            
            <div>
              <button @click="editCurrentPage()" class="uip-button-secondary uip-flex uip-margin-bottom-m">
                <span><?php _e("Edit Page", $this->textDomain); ?></span>
                <span class="material-icons-outlined uip-margin-left-s">chevron_right</span>
              </button>
            </div>
            
            
            <textarea v-model="user.currentMenu.content"></textarea>
					
					</div>
					
				</div>
			</div>
			
			<div class="uip-flex-grow uip-page-editor" style="height:calc(100vh - 73px - var(--uip-toolbar-height)); max-height:calc(100vh - 73px - var(--uip-toolbar-height))">
				
				<div class="uip-position-relative" v-html="user.currentMenu.content" id="uip-admin-page-preview">
					
				
				</div>
				
			</div>
			
		</div>
		
		<?php
  }

  public function build_header()
  {
    $logo = esc_url($this->pathURL . "/assets/img/default_logo.png"); ?>
	
	<div class="uip-flex" >
		<div class="uip-flex-grow">
			<div class="uip-text-bold uip-text-emphasis uip-text-l uip-margin-bottom-xxs"><?php _e("Admin Page Creator", $this->textDomain); ?></div>
			<a v-if="ui.editingMode" @click="ui.editingMode = false" href="#" class="uip-link-muted uip-no-outline uip-no-underline uip-text-muted uip-flex">
				<span class="material-icons-outlined " >chevron_left</span>
				<?php _e("Back to all admin pages", $this->textDomain); ?>
			</a>
		</div>
		<div class="">
			
			<div class="uip-flex uip-flex-middle">
				
				<button class="uip-button-primary uip-margin-right-xs" @click="saveSettings()"><?php _e("Save", $this->textDomain); ?></button>
				
				<uip-dropdown type="icon" icon="tune" pos="botton-left">
					
						
						<ul class="uip-flex uip-flex-column uip-margin-remove">
							<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
								<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" @click="exportMenu(user.currentMenu)" >
									<span class="material-icons-outlined uip-margin-right-xxs"  >file_download</span>
									<?php _e("Export", $this->textDomain); ?>
									<a href="#" id="uipress-export-menus" class="uip-hidden"></a>
								</a>
							</li>
							
							<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
								<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" >
									<label class="uip-flex">
										<span class="material-icons-outlined uip-margin-right-xxs">file_upload</span>
										<?php _e("Import Menu", $this->textDomain); ?>
										<input hidden accept=".json" type="file" single="" id="uipress_import_menu" @change="import_menu()">
									</label>
								</a>
							</li>
							
							<li class="uip-padding-xxs hover:uip-background-grey uip-border-round">
								<a href="#" class="uip-link-default uip-no-underline uip-no-outline uip-flex" @click="reset_settings()">
									<span class="material-icons-outlined uip-margin-right-xxs" >restart_alt</span>
									<?php _e("Reset Settings", $this->textDomain); ?></a>
								</a>
							</li>
							
						</ul>	
						
				</uip-dropdown>
			</div>
		</div>
	</div>
	<?php
  }

  public function add_loader_placeholder()
  {
    ?>
		
		<div class="uip-max-w-100p uip-overflow-hidden">
      <div  class="uip-padding-left-s uip-padding-top-xs">
        <div class="uip-flex uip-flex-row uip-margin-bottom-s">
          <div>
            <svg height="28" width="28">
              <circle cx="14" cy="14" r="14" stroke-width="0" fill="#bbbbbb2e"></circle>
            </svg>
          </div>
        </div>
        <div class="uip-flex uip-flex-row uip-padding-xxs">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="80">
              <rect width="80" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-margin-m"></div>
        <div class="uip-flex uip-flex-row uip-padding-xxs" style="padding-top: 0;">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="140">
              <rect width="140" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-flex uip-flex-row uip-padding-xxs">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="50">
              <rect width="50" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-flex uip-flex-row uip-padding-xxs">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="77">
              <rect width="77" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-flex uip-flex-row uip-padding-xxs">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="107">
              <rect width="107" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-margin-m"></div>
        <div class="uip-flex uip-flex-row uip-padding-xxs" style="padding-top: 0;">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="87">
              <rect width="87" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
        <div class="uip-flex uip-flex-row uip-padding-xxs" style="padding-top: 0;">
          <div>
            <svg class="uip-margin-right-xs" height="20" width="20">
              <rect width="20" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
          <div>
            <svg height="20" width="47">
              <rect width="47" height="20" rx="4" fill="#bbbbbb2e"></rect>
            </svg>
          </div>
        </div>
      </div>
    </div>
		
		<?php
  }
}
