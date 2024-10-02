<?php
/*
Copyright (c) 2010-2019 Box Hill LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 */


/**
 * Class EasyIndexDiagnostics
 *
 * Gets, displays and sends (to the support site) diagnostics stuff
 *
 */
class EasyIndexDiagnostics {

    private $haveData = false;
    public $dataVersion = 5;

    public $pluginName = 'easyindex';
    public $pluginVersion = '1.1.1704';

    public $pluginURL = '';
    public $pluginDir = '';

    public $phpinfo = '';

    public $timezone = '';
    public $gmtOffset = '';

    public $PLUGINS = array();

    /** @var  EasyIndexSettings */
    public $settings = '';

    public $wpCapabilities = '';
    public $wpVersion = '';
    public $wpSiteURL = '';
    public $wpHomeURL = '';
    public $wpMultiSite = '';
    public $mysqlVersion = '';

    public $wpTheme = '';
    public $wpThemeVersion = '';
    public $wpThemeURL = '';

    private $existingOP;

    /**
     * If $data is present, then we are on an EasySupport site (admin) and creating an object from customer submitted data, but the data pre-dates this class
     * If so, we need to create an object of the latest version from it
     *
     * @param null|stdclass $data
     */
    function __construct($data = null) {
        if ($data == null) {
            return;
        }

        if (empty($data->diagnosticsVersion)) {
            $data->diagnosticsVersion = 2;
        }

        /**
         * diagnosticsVersion == 2 is from EasyRecipe before we implemented the EasyLibrary diagnostics
         *
         */
        if ($data->diagnosticsVersion == 2) {
            $vars = get_object_vars($this);
            foreach ($vars as $field => $null) {
                if (isset($data->$field)) {
                    $this->$field = $data->$field;
                }
            }

            /**
             * It can only be EasyRecipe
             */
            $this->pluginName = 'easyrecipe(plus)';
            $this->phpinfo = $data->phpinfo;

            $this->wpCapabilities = $data->wpcapabilities;
            $this->wpVersion = $data->wpversion;
            $this->wpSiteURL = $data->wpurl;


            $this->wpTheme = $data->wptheme;
            $this->wpThemeVersion = $data->wpthemeversion;
            $this->wpThemeURL = $data->wpthemeurl;

            $this->PLUGINS = array();
            /**
             * Extract the plugin data
             * Need to do this row by row else the regex gets too complex and is likely to cause catastrophic backtracking
             * First get an array of the <tr> rows
             */
            $s = preg_replace('/\s*<tr.*?>\s*/s', '', $data->plugindata);
            $rows = explode('</tr>', $s);

            foreach ($rows as $row) {
                if (preg_match_all('%\s*<td>(.*?)</td>%s', $row, $result, PREG_PATTERN_ORDER)) {
                    $item = new stdClass();
                    $item->name = $result[1][0];
                    $item->active = $result[1][1];
                    $item->version = $result[1][2];
                    $item->url = $result[1][3];
                    $this->PLUGINS[] = $item;
                }
            }

            $this->haveData = true;

        }

    }

    function __wakeup() {
        $this->haveData = true;
    }


    /**
     * Gets details about the site and installed plugins etc
     *
     * @return stdClass Object containing diagnostics data
     */
    function get() {
        global $wp_version;

        /** @var wpdb $wpdb */
        global $wpdb;


        /**
         * Get the php info.  Save anything already in the output buffer, just in case it's relevant for the site's output in show()
         */
        $this->existingOP = ob_get_clean();
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();
        preg_match('%<body>(.*)</body>%si', $phpinfo, $regs);
        $this->phpinfo = $regs[1];


        /** @noinspection PhpUndefinedClassInspection */
        $this->pluginURL = EasyIndex::$EasyIndexUrl;

        /** @noinspection PhpUndefinedClassInspection */
        $this->pluginDir = EasyIndex::$EasyIndexDir;


        /**
         * Get our own settings. This is the same for all Easy Plugins and individualised by the build processs
         */
        /** @noinspection PhpUndefinedClassInspection */
        $settings = EasyIndexSettings::getInstance();

        /**
         * Don't send any settings (passwords etc) that we really have no business knowing
         */
        if (isset($settings->privateSettings)) {
            foreach ($settings->privateSettings as $privateSetting) {
                if (isset($settings->$privateSetting)) {
                    unset($settings->$privateSetting);
                }
            }
            unset($settings->privateSettings);
        }

        $this->settings = $settings;

        $capabilities = "";
        get_currentuserinfo();

        $user = $GLOBALS['current_user'];

        if (isset($user->caps)) {
            foreach ($user->caps as $cap => $allowed) {
                if ($allowed) {
                    $capabilities .= "$cap,";
                }
            }
        }
        $this->wpCapabilities = rtrim($capabilities, ",");
        $this->wpVersion = $wp_version;
        $this->wpSiteURL = site_url();
        $this->wpHomeURL = home_url();
        $this->wpMultiSite = is_multisite() ? 'Yes' : 'No';
        $this->mysqlVersion = $wpdb->db_version();

        $this->gmtOffset = get_option('gmt_offset');
        $this->timezone = get_option('timezone_string');

        if ($wp_version < '3.4') {
            /** @noinspection PhpDeprecationInspection */
            $themeData = get_theme_data(get_stylesheet_directory() . "/style.css");
            $this->wpTheme = $themeData["Name"];
            $this->wpThemeVersion = $themeData["Version"];
            $this->wpThemeURL = $themeData["URI"];
        } else {
            $themeData = wp_get_theme();
            $this->wpTheme = $themeData->get("Name");
            $this->wpThemeVersion = $themeData->get("Version");
            $this->wpThemeURL = $themeData->get("ThemeURI");
        }

        if (!function_exists('get_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugins = get_plugins();
        foreach ($plugins as $pluginFile => $null) {
            $plugins[$pluginFile]["active"] = is_plugin_active($pluginFile) ? "Active" : "Inactive";
        }
        usort($plugins, array($this, "sortPlugins"));

        $this->PLUGINS = array();
        foreach ($plugins as $plugin) {
            $item = new stdClass();
            $item->name = $plugin["Title"];
            $item->active = $plugin["active"];
            $item->version = $plugin["Version"];
            $item->url = $plugin["PluginURI"];
            $this->PLUGINS[] = $item;
        }

    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function sortPlugins($a, $b) {
        if ($a["active"] != $b["active"]) {
            return strcmp($a["active"], $b["active"]);
        }
        return strcmp($a["Title"], $b["Title"]);
    }

    /**
     * Display the plugin diagnostics page
     */
    function show() {
        /**
         * Just ignore if we're not logged in as admin
         */
        if (!current_user_can('administrator')) {
            return;
        }

        if (!$this->haveData) {
            $this->get();
        }
        /**
         * Get only public settings properties
         */
        $settings = new stdClass();
        foreach ($this->settings as $property => $value) {
            $settings->{$property} = $value;
        }

        $this->settings = print_r($settings, true);
        $template = new EasyIndexTemplate($this->getTemplate(), EasyIndexTemplate::TEXT);
        $html = $template->replace($this, EasyIndexTemplate::PRESERVEWHITESPACE);

        /**
         * Switch off any output buffereing
         */
        $level = ob_get_level();
        while ($level > 0) {
            ob_end_clean();
            $level = ob_get_level();
        }
        $html = $this->existingOP . $html;
        header("HTTP/1.1 200 OK");
        header("Content-Length: " . strlen($html));
        echo $html;

        exit();
    }

    /**
     * Send a support question (and possibly diagnostics) to the plugin support site
     * @param $diagnosticsURL
     * @param array $post
     */
    function send($diagnosticsURL, $post = array()) {
        /**
         * Just ignore if we're not logged in as admin
         */
        if (!current_user_can('administrator')) {
            return;
        }

        $postData = array_merge($_POST, $post);
        $postData['website'] = site_url();

        foreach ($postData as $key => $value) {
            $post[$key] = urldecode(stripslashes($value));
        }
        /**
         * If we are sending diagnostics along with the problem - then get them else just send blank
         * Use serialize instead of JSON because we can't guarantee that the data will not contain invalid UTF-8 sequences and PHP json croaks on bad UTF-8
         */
        if (!empty($_POST['sendDiagnostics'])) {
            $this->get();
            $post['diagnostics'] = @serialize((object)$this);
        } else {
            $post['diagnostics'] = '';
        }

        $response = wp_remote_post($diagnosticsURL, array('body' => $post));

        $result = new stdClass();
        $result->status = 'FAIL';

        
        if (is_a($response, 'WP_Error')) {
            $result->errors = $response->get_error_messages();
        } else if (is_array($response)) {
            if (isset($response['response']) && $response['response']['code'] == 200) {
                /**
                 * Freshdesk support endpoints return json with ticket details, earlier support endpoints return a status as a string
                 * Earlier versions returned HTML to be displayed - the latest version returns the ticket URL and the user's email
                 */
                $decoded = json_decode($response['body']);
                if (!$decoded) {
                    $result->status = $response['body'];
                } else {
                    $result->status = $decoded->status;
                    if (!empty($decoded->html)) {
                        $result->html = stripslashes($decoded->html);
                    }
                    if (!empty($decoded->tkUrl)) {
                        $result->tkUrl = stripslashes($decoded->tkUrl);
                    }
                    if (!empty($decoded->email)) {
                        $result->email = stripslashes($decoded->email);
                    }
                    if (!empty($decoded->result)) {
                        $result->result = stripslashes($decoded->result);
                    }
                    if (!empty($decoded->error)) {
                        $result->result = stripslashes($decoded->error);
                    }
                }

            } else {
                $result->errors = array("Support server returned: " . $response['response']['code']);
            }
        } else {
            $result->errors = array("Unknown error");
        }
        wp_send_json($result);
    }

    /**
     * Gets the template for the diagnostics page.
     * This could be done inline, without using Template()... but that is SO messy and error prone!
     *
     * @return string The template text
     */
    private function getTemplate() {
        $template = <<<EOD
<!-- START PAGE -->
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta name="robots" content="noindex, noarchive">
<title>#pluginName# Diagnostics</title>
<style type="text/css">
#divDDContainer {
  position: relative;
  width: 950px;
  margin-right: auto;
  margin-left: auto;
  border: 1px solid #666;
  text-align: left;
}
#divDDHdr {
  position: relative;
  margin-bottom: 20px;
}
#divDData {
  position: relative;
}
#divDiagData tr.Inactive {
  color: #888;
}

#divDiagData .WPinfo pre {
  white-space: pre-wrap;
}

#divDiagData .PHPinfo table {
  table-layout: fixed;
  white-space: pre-line;
}

#divDiagData .PHPinfo td {
  overflow: hidden;
}
.PHPinfo body, .PHPinfo td, .PHPinfo th, .PHPinfo h1, .PHPinfo h2 {
  font-family: sans-serif;
}
.PHPinfo pre {
  margin: 0px;
  font-family: monospace;
}
.PHPinfo a:link {
  color: #000099;
  text-decoration: none;
  background-color: #ffffff;
}
.PHPinfo a:hover {
  text-decoration: underline;
}
.PHPinfo table {
  border-collapse: collapse;
}
.PHPinfo .center {
  text-align: center;
}
.PHPinfo .center table {
  margin-left: auto;
  margin-right: auto;
  text-align: left;
  width: 900px;
}
.PHPinfo .center th {
  text-align: center !important;
}
.PHPinfo td, .PHPinfo th {
  border: 1px solid #000000;
  vertical-align: baseline;
}
.PHPinfo h1 {
  font-size: 150%;
}
.PHPinfo h2 {
  font-size: 125%;
}
.PHPinfo .p {
  text-align: left;
}
.PHPinfo .e {
  background-color: #ccccff;
  font-weight: bold;
  color: #000000;
}
.PHPinfo .h {
  background-color: #9999cc;
  font-weight: bold;
  color: #000000;
}
.PHPinfo .v {
  background-color: #cccccc;
  color: #000000;
}
.PHPinfo .vr {
  background-color: #cccccc;
  text-align: right;
  color: #000000;
}
.PHPinfo img {
  float: right;
  border: 0px;
}
.PHPinfo hr {
  width: 600px;
  background-color: #cccccc;
  border: 0px;
  height: 1px;
  color: #000000;
}
.WPinfo table tr th {
  text-align: left;
}
.DPlugins table tr th {
  text-align: left;
}
.WPinfo .DTable tr th {
  text-align: left;
}
.PHPinfo {
  font-size: 12px;
}
.WPinfo {
  font-size: 12px;
}
#divDDContainer #divDDHdr table {
  width: 650px;
}
#divDDContainer #divDDHdr th {
  text-align: right;
  width: 110px;
}
body {
  text-align: center;
  font: 12px Arial, Helvetica, sans-serif;
}
</style>
</head>

<body>
<div id="divDDContainer">
  <div id="divDData">
    <div id="divDiagData">
      <div class="WPinfo">
        <h3>Wordpress </h3>
        <table border="0" cellspacing="5">
          <tr>
            <th scope="row">Wordpress version</th>
            <td>#wpVersion#</td>
          </tr>
          <tr>
            <th scope="row">Current user capabilities</th>
            <td>#wpCapabilities#</td>
          </tr>
          <tr>
            <th scope="row">Home URL</th>
            <td>#wpHomeURL#</td>
          </tr>
          <tr>
            <th scope="row">Site URL</th>
            <td>#wpSiteURL#</td>
          </tr>
          <tr>
            <th scope="row">MultiSite</th>
            <td>#wpMultiSite#</td>
          </tr>
          <tr>
            <th scope="row">Current theme</th>
            <td>#wpTheme#</td>
          </tr>
          <tr>
            <th scope="row">Theme version</th>
            <td>#wpThemeVersion#</td>
          </tr>
          <tr>
            <th scope="row">Theme URL</th>
            <td>#wpThemeURL#</td>
          </tr>
          <tr>
            <th scope="row">Timezone</th>
            <td>#timezone#</td>
          <tr>
            <th scope="row">GMT Offset</th>
            <td>#gmtOffset#</td>
          </tr>
          <tr>
            <th scope="row">MySQL Version</th>
            <td>#mysqlVersion#</td>
          </tr>
        </table>
        <h3>Plugins</h3>
        <table class="Table" border="0">
          <tr>
            <th>Plugin</th>
            <th>Activated</th>
            <th>Version</th>
            <th>URL</th>
          </tr>
          <!-- START REPEAT PLUGINS -->
          <tr class="#active#">
            <td>#name#</td>
            <td>#active#</td>
            <td>#version#</td>
            <td>#url#</td>
          </tr>
          <!-- END REPEAT PLUGINS -->
        </table>
        <h3>#pluginName#</h3>
        <table border="0" cellspacing="5">
          <tr>
            <th scope="row">Version</th>
            <td>#pluginVersion#</td>
          </tr>
          <tr>
            <th scope="row">URL</th>
            <td>#pluginURL#</td>
          </tr>
          <tr>
            <th scope="row">Directory</th>
            <td>#pluginDir#</td>
          </tr>
        </table>
        <pre>
#settings#
</pre>
      </div>
      <h3>PHP</h3>
      <div class="PHPinfo">#phpinfo#</div>
    </div>
  </div>
</div>
</body>
</html>
<!-- END PAGE -->
EOD;

        return $template;
    }

}


