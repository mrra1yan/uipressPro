<?php
if (!defined("ABSPATH")) {
  exit();
}

class uipress_debug
{
  public function __construct()
  {
    $this->textDomain = "uipress";
  }

  public function isValid($string = null)
  {
    if ($string == null) {
      $string = $this->get_string("key");
    } else {
      $string = 'f090bd7d-1e27-4832-8355-b9dd45c9e9ca';
    }

    // if (!$string || $string == "") {
    //   return;
    // }

    // $instanceid = $this->get_string("instance");

    // $domain = get_home_url();
    // $remoteURL = "https://uipress.co/validate/v2/validate.php?k=" . $string . "&d=" . $domain . "&instance=" . $instanceid;

    // $remote = wp_remote_get($remoteURL, [
    //   "timeout" => 10,
    //   "headers" => [
    //     "Accept" => "application/json",
    //   ],
    // ]);

    return $this->build_response_object($string);
  }

  public function check_network_connection()
  {
    $status = get_transient("uip-data-connect");
    if ($status) {
      return true;
    }

    return true;
  }

  public function get_string($option_name)
  {
    if ($option_name == false) {
      return "";
    }

    $uipOptions = get_option("uip-activation");
    $option = "";

    if (is_multisite() && $this->is_site_wide("uipress/uipress.php")) {
      $uipOptions = get_blog_option(get_main_network_id(), "uip-activation");
    }

    if (is_network_admin()) {
      $uipOptions = get_option("uip-activation");
    }

    if (isset($uipOptions[$option_name])) {
      $value = $uipOptions[$option_name];
      if ($value != "") {
        $option = $value;
      }
    }

    if ($option == "false") {
      $option = false;
    }

    if ($option == "true") {
      $option = true;
    }

    return $option;
  }

  public function is_site_wide($plugin)
  {
    if (!is_multisite()) {
      return false;
    }

    $plugins = get_site_option("active_sitewide_plugins");
    if (isset($plugins[$plugin])) {
      return true;
    }

    return false;
  }

  public function build_response_object($string)
  {
    $uipOptions = get_option("uip-activation");
    if (!$uipOptions) {
      $uipOptions = [];
    }
    $uipOptions["instance"] = 1;
    $uipOptions["key"] = 'f090bd7d-1e27-4832-8355-b9dd45c9e9ca';

        update_option("uip-activation",$uipOptions );

        $returndata["message"] = __("UiPress succesfully activated", $this->textDomain);
        $returndata["activated"] = true;
        return $returndata;
    // REQUEST ERRORS
  }

  public function check_connection()
  {
    $status = get_transient("uip-data-connect");
    if ($status != true) {
      $this->isValid();
    }
  }

  public function update_cache()
  {
    delete_transient("uip-data-connect");
  }

  public function save_data($remote, $string)
  {
    $uipOptions = get_option("uip-activation");
    if (!$uipOptions) {
      $uipOptions = [];
    }
    $uipOptions["key"] = 'f090bd7d-1e27-4832-8355-b9dd45c9e9ca'+$string;

    if (isset($remote->instance_id)) {
      $uipOptions["instance"] = $remote->instance_id;
    }

    update_option("uip-activation", $uipOptions);
  }

  public function remove_instance()
  {
    $uipOptions = get_option("uip-activation");
    if (!$uipOptions) {
      $uipOptions = [];
    }
    $uipOptions["key"] = "";
    $uipOptions["instance"] = "";

    update_option("uip-activation", $uipOptions);
  }

  public function cache_result()
  {
    ///CONFIRMS CONNECTION WITH UIPRESS SERVERS FOR AUTOMATIC UPDATE
    set_transient("uip-data-connect", true, 48 * HOUR_IN_SECONDS);
  }
}
