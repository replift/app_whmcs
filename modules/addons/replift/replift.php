<?php
/**
 * All functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. "replift_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/addon-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

/**
 * Require any libraries needed for the module to function.
 * require_once __DIR__ . '/path/to/library/loader.php';
 *
 * Also, perform any initialization required by the service's library.
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\AddonModule\Admin\AdminDispatcher;
use WHMCS\Module\Addon\AddonModule\Client\ClientDispatcher;

global $whmcs;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// button click to add IPs to database?
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['replift_addips'])) {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.replift.com/api/v2/ip_whitelist/',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // HTTP status code
    curl_close($curl);

    // Curl, you ok bruh?
    if ($response !== false && $httpcode == 200) {
        $responseArray = json_decode($response, true); // Decode JSON & make it associative array
        
        // json_decode ok? 'ips' key exists ?
        if ($responseArray !== null && isset($responseArray['ips']) && is_array($responseArray['ips'])) {
            
            // Are we sure these are IP addresses? let's check
            $validIps = array_filter($responseArray['ips'], function($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP) !== false;
            });
            
            // use this array
            $newData = $validIps;

        } else {
            echo "Error: Invalid format or 'ips' key not found in response.";
        }
    } else {
        // Handle error (either cURL error or non-200 response)
        echo "Error: Request failed or returned HTTP status code " . $httpcode;
    }
        

    // old placeholder before ip list existed
    /* $newData = [
        '96.29.116.209',
        '96.29.116.210'
    ]; */

   replift_addips($whmcs,$newData);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['replift_generate_api_credentials'])) {

   replift_generate_api_credentials($whmcs);
}

// function that adds any new IPs that are found
function replift_addips($whmcs, $newData) {

    // get ips already here
    //$command = 'GetConfigurationValue';
    //$postData = array('setting' => 'APIAllowedIPs');
    //$data = localAPI($command, $postData, $adminUsername);
    // Extract serialized string
    //$serializedData = $data['value'];
    // Unserialize it
    //$whitelistedips = unserialize($serializedData) ?: [];

    // Easier way to get current IPs below
    $whitelistedips = $whmcs->get_config( "APIAllowedIPs" );
    $whitelistedips = unserialize( $whitelistedips );

    // test
    //$ipaddress = '96.29.116.209';
    //$whitelistedips[] = array( "ip" => $ipaddress, "note" => "Replift" );
	//$whmcs->set_config( "APIAllowedIPs", serialize( $whitelistedips ) );

    $atLeast1New = false;
    // check and add each IP to array if it doesn't exist
    foreach ($newData as $newIp) {
        $ipExists = false;
        foreach ($whitelistedips as $item) {
            if ($item['ip'] === $newIp) {
                $ipExists = true;
                echo "<br>" . $newIp . " already exists. Skipping.";
                break;
            }
        }
        if (!$ipExists) {
            // store it!
            $atLeast1New = true;
            $whitelistedips[] = array( "ip" => $newIp, "note" => "Replift" );
            echo $newIp . " has been added.<br>";
        }
        if ($atLeast1New) {
            $whmcs->set_config( "APIAllowedIPs", serialize( $whitelistedips ));
        }
    }
    echo "<BR>Checking for new Replift IPs Complete<BR><BR>";
}


function replift_generate_api_credentials($whmcs) {

    $replift_admin = new WHMCS\Auth();
    $replift_admin->getInfobyUsername('replift', NULL, false);
    if (!$replift_admin->getAdminID()) {
        $adminDetails = array();
        $adminDetails["roleid"] = 1;
        $adminDetails["username"] = 'replift';
        $adminDetails["firstname"] = 'Replift';
        $adminDetails["lastname"] = 'API';
        $adminDetails["email"] = 'support@replift.com';
        $adminDetails["signature"] = '';
        $adminDetails["disabled"] = 0;
        $adminDetails["notes"] = '';
        $adminDetails["template"] = 'blend';
        $adminDetails["language"] = 'english';
        $adminDetails["supportdepts"] = '[1]';
        $adminDetails["ticketnotifications"] = '';
        $adminDetails["password"] = $pass;
        $adminDetails["password_reset_data"] = "";
        $adminDetails["password_reset_key"] = $adminDetails["password_reset_data"];
        $adminDetails["password_reset_expiry"] = "0000-00-00 00:00:00";
        $adminDetails["updated_at"] = WHMCS\Carbon::now()->toDateTimeString();
        $adminDetails["created_at"] = $adminDetails["updated_at"];
        insert_query("tbladmins", $adminDetails);

    }
    $replift_admin->getInfobyUsername($adminDetails["username"], NULL, false);
    $pass = phpseclib\Crypt\Random::string(21);
    $replift_admin->generateNewPasswordHashAndStoreForApi(md5($pass));

	$replift_api_role =  \WHMCS\Api\Authorization\ApiRole::where("role", 'Replift')->get();
	#print_r($replift_api_role[0]->getId());
	if (count($replift_api_role) == 0) {
		$apiRolePermissions = array();
		$apiRolePermissions["getticket"] = 1;
		$apiRolePermissions["gettickets"] = 1;
		$apiRolePermissions["updateticket"] = 1;
		$apiRolePermissions["addticketnote"] = 1;
		$apiRolePermissions["getsupportdepartments"] = 1;
		$apiRolePermissions["getsupportstatuses"] = 1;
        $apiRoleDetails = array();
        $apiRoleDetails["role"] = 'Replift';
        $apiRoleDetails["description"] = 'Replift API Role';
        $apiRoleDetails["permissions"] = json_encode($apiRolePermissions);
        $apiRoleDetails["created_at"] = WHMCS\Carbon::now()->toDateTimeString();
        $apiRoleDetails["updated_at"] = $apiRoleDetails["created_at"];
        insert_query("tblapi_roles", $apiRoleDetails);
    }
    $replift_api_role =  \WHMCS\Api\Authorization\ApiRole::where("role", 'Replift')->get();

    $repfilt_api_device = \WHMCS\Authentication\Device::where('user_id', $replift_admin->getId())->get();
	if (count($repfilt_api_device) > 0) {
		$repfilt_api_device[0]->delete();
	}

        $repfilt_api_device = \WHMCS\Authentication\Device::newAdminDevice(\WHMCS\User\Admin::find($replift_admin->getId()), 'Replift API');
        $repfilt_api_device->addRole($replift_api_role[0]);
        $secret = $repfilt_api_device->secret;
        $repfilt_api_device->save();
        $data = array();
        $data["identifier"] = $repfilt_api_device->identifier;
	    $data["secret"] = $secret;
	    $data["api_url"] = $_SERVER["HTTP_ORIGIN"].'/includes/api.php';
	    $data["uuid"] = uniqid();
        
        echo $data["identifier"];
        echo "<br>";
        echo $data["secret"];
        echo "<br>";
        echo $data["uuid"];
        echo "<br>";
        /* $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://development-app.replift.com/api/whmcs/connect',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "desk_url": "'.$data["api_url"].'",
                "desk_identifier": "'.$data["identifier"].'",
                "desk_key": "'.$data["secret"].'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        echo $response;
        logActivity('Replift: '.$data["identifier"].' created for Replift' , 0); */

        return $data["uuid"];

}


/**
 * Define addon module configuration parameters.
 *
 * Includes a number of required system fields including name, description,
 * author, language and version.
 *
 * Also allows you to define any configuration parameters that should be
 * presented to the user when activating and configuring the module. These
 * values are then made available in all module function calls.
 *
 * Examples of each and their possible configuration parameters are provided in
 * the fields parameter below.
 *
 * @return array
 */
function replift_config()
{
    return [
        // Display name for your module
        'name' => 'Replift',
        // Description displayed within the admin interface
        'description' => 'Replift is an AI solution that consolidates all of your organization'
            .'\'s knowledge resources into a single platform, enhancing the speed and efficiency of your support team.',
        // Module author name
        'author' => 'Replift',
        // Default language
        'language' => 'english',
        // Version number
        'version' => '1.0',
        'fields' => [
            // a text field type allows for single line text input
            'WHMCS replift user' => [
                'FriendlyName' => 'WHMCS replift user',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'Replift',
                'Description' => 'WHMCS replift user',
            ],
            // a password field type allows for masked text input
            'Replift API Key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'password',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Replift API Key',
            ],
            // the yesno field type displays a single checkbox option
            //'Checkbox Field Name' => [
                //'FriendlyName' => 'Checkbox Field Name',
                //'Type' => 'yesno',
                //'Description' => 'Tick to enable',
            //],
            // the dropdown field type renders a select menu of options
            //'Dropdown Field Name' => [
                //'FriendlyName' => 'Dropdown Field Name',
                //'Type' => 'dropdown',
                //'Options' => [
                    //'option1' => 'Display Value 1',
                    //'option2' => 'Second Option',
                    //'option3' => 'Another Option',
                //],
                //'Default' => 'option2',
                //'Description' => 'Choose one',
            //],
            // the radio field type displays a series of radio button options
            //'Radio Field Name' => [
                //'FriendlyName' => 'Radio Field Name',
                //'Type' => 'radio',
                //'Options' => 'First Option,Second Option,Third Option',
                //'Default' => 'Third Option',
                //'Description' => 'Choose your option!',
            //],
            // the textarea field type allows for multi-line text input
            //'Textarea Field Name' => [
                //'FriendlyName' => 'Textarea Field Name',
                //'Type' => 'textarea',
                //'Rows' => '3',
                //'Cols' => '60',
                //'Default' => 'A default value goes here...',
                //'Description' => 'Freeform multi-line text input field',
            //],
        ]
    ];
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function replift_activate()
{
    global $whmcs;
    
    // Create custom replift table. Stores backup configs
    try {
        Capsule::schema()
            ->create(
                'replift_config',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->increments('id');
                    $table->string('setting');
                    $table->text('value');
                    $table->timestamps();
                }
        );
        logActivity('Replift: replift_config table has been created' , 0);

        // Backup current API Whitelist config on install
        Capsule::connection()->transaction(
            function ($connectionManager) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    global $whmcs;
                    $whitelistedips = $whmcs->get_config( "APIAllowedIPs" );
                    $connectionManager->table('replift_config')->insert(
                        [
                            'setting' => 'api_whitelist_before_activate',
                            'value' => $whitelistedips,
                        ]
                    );
            }
        );
        logActivity('Replift: APIAllowedIPs setting successfully backed up to replift_config table' , 0);

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Replift has been activated.'
                . 'Please continue registration here for full activation',

        ];
    } catch (\Exception $e) {
        //install failed so lets remove the table if it made it that far
        replift_deactivate();
        logActivity('Replift: Install rollback, replift_config table removed' , 0);

        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to create replift_config: ' . $e->getMessage()
            . 'Install rollback, replift_config table removed',
        ];
    }
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to undo any database and schema modifications
 * performed by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function replift_deactivate()
{
    // Undo any database and schema modifications
    try {
        Capsule::schema()
            ->dropIfExists('replift_config');

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Replift has been deactivated.',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            "status" => "error",
            "description" => "Unable to drop table replift_config: {$e->getMessage()}",
        ];
    }
}

/**
 * Upgrade.
 *
 * Called the first time the module is accessed following an update.
 * Use this function to perform any required database and schema modifications.
 *
 * This function is optional.
 *
 * @see https://laravel.com/docs/5.2/migrations
 *
 * @return void
 */
function replift_upgrade($vars)
{
    $currentlyInstalledVersion = $vars['version'];

    /*
    // Perform SQL schema changes required by the upgrade to version 1.1 of your module
    if ($currentlyInstalledVersion < 1.1) {
        $schema = Capsule::schema();
        // Alter the table and add a new text column called "demo2"
        $schema->table('mod_addonexample', function($table) {
            $table->text('demo2');
        });
    }

    /// Perform SQL schema changes required by the upgrade to version 1.2 of your module
    if ($currentlyInstalledVersion < 1.2) {
        $schema = Capsule::schema();
        // Alter the table and add a new text column called "demo3"
        $schema->table('mod_addonexample', function($table) {
            $table->text('demo3');
        });
    }
    */
}

/**
 * Admin Area Output.
 *
 * Called when the addon module is accessed via the admin area.
 * Should return HTML output for display to the admin user.
 *
 * This function is optional.
 *
 * @see AddonModule\Admin\Controller::index()
 *
 * @return string
 */

function replift_output($vars)
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
    $version = $vars['version']; // eg. 1.0
    $_lang = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    //$configCheckboxField = $vars['Checkbox Field Name'];
    //$configDropdownField = $vars['Dropdown Field Name'];
    //$configRadioField = $vars['Radio Field Name'];
    //$configTextareaField = $vars['Textarea Field Name'];

    // Dispatch and handle request here. What follows is a demonstration of one
    // possible way of handling this using a very basic dispatcher implementation.

    //$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    //$dispatcher = new AdminDispatcher();
    //$response = $dispatcher->dispatch($action, $vars);
    //echo $response;


    $button_api = '<form action="" method="post">
    <input type="submit" name="replift_generate_api_credentials" value="Generate API Creds" />
    </form><br>';
    echo $button_api;


  
}

/**
 * Admin Area Sidebar Output.
 *
 * Used to render output in the admin area sidebar.
 * This function is optional.
 *
 * @param array $vars
 *
 * @return string
 */
function replift_sidebar($vars)
{
    // Get common module parameters
    $modulename = $vars['module'];
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $_lang = $vars['_lang'];

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    $replift_logo = '<img src="../modules/addons/replift/repliftlogo_mini.png" class="replift_logo">';
    $sidebar = $replift_logo;
    
    $sidebar = $sidebar.'<br><form action="" method="post">
    <input type="submit" name="replift_addips" value="Check for new Replift IPs" class="rbuttonA"/>
    </form><br>';

    return $sidebar;
}

/**
 * Client Area Output.
 *
 * Called when the addon module is accessed via the client area.
 * Should return an array of output parameters.
 *
 * This function is optional.
 *
 * @see AddonModule\Client\Controller::index()
 *
 * @return array
 */
function replift_clientarea($vars)
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. index.php?m=replift
    $version = $vars['version']; // eg. 1.0
    $_lang = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    /**
     * Dispatch and handle request here. What follows is a demonstration of one
     * possible way of handling this using a very basic dispatcher implementation.
     */

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    $dispatcher = new ClientDispatcher();
    return $dispatcher->dispatch($action, $vars);
}

