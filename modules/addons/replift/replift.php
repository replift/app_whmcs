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
 * 
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\AddonModule\Admin\AdminDispatcher;
use WHMCS\Module\Addon\AddonModule\Client\ClientDispatcher;
include_once(ROOTDIR.'/modules/addons/replift/globalvars.php');
global $whmcs;

if (!defined("WHMCS")) {
    die ("This file cannot be accessed directly");
}

// Check if registered
function registerCheck()
{
    global $replift_api_marketplace;
    global $replift_marketplace_token;
    $err = "";

    try {
        // get values from replift table
        $replift_uuid_existing = Capsule::table('replift_config')->where('setting', 'uuid')->value('value');
        $replift_uuid_created = Capsule::table('replift_config')->where('setting', 'uuid')->value('updated_at');

        // check for status
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $replift_api_marketplace . $replift_uuid_existing,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$replift_marketplace_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        )
        );
        $responseJson = curl_exec($curl);
        curl_close($curl);

        // Get registered_at reply
        $response = json_decode($responseJson, true);
        $registered_at = $response['data']['registered_at'];

        // Check if registered_at is null or has a value
        if ($registered_at === null) {

            // Convert created time to a DateTime object
            $createdDateTime = new DateTime($replift_uuid_created);

            // Add 4 hours to get the expiration time
            $expirationDateTime = clone $createdDateTime;
            $expirationDateTime->add(new DateInterval('PT4H'));

            // Get time left
            $currentDateTime = new DateTime();
            $timeLeft = $currentDateTime->diff($expirationDateTime);

            //echo $currentDateTime->format('Y-m-d H:i:s');
            //echo "<br>";
            //echo $expirationDateTime->format('Y-m-d H:i:s');

            // Lets check if link is expired
            if ($timeLeft->invert == 1) {
                $status = "expired";
            } else {
                $status = "pending";
                // Convert to total hours and minutes left
                $totalHours = ($timeLeft->days * 24) + $timeLeft->h;
                $minutesLeft = $timeLeft->i;
                $time_left = $totalHours . " hours and " . $minutesLeft . " minutes";
            }

        } else if ($registered_at) {
            // Registered
            $status = "registered";
            $time_left = $registered_at;
        } else {
            $err = "something else happened";
        }

        return array($status, $replift_uuid_existing, $time_left, $err);

    } catch (\Exception $err) {
        return array($status, $replift_uuid_existing, $time_left, $err);
    }
}

// Development
/*
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset ($_POST['replift_addips'])) {
    replift_addips();
}
*/

/*
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset ($_POST['replift_generate_api_credentials'])) {

    replift_generate_api_credentials();
}
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset ($_POST['replift_test'])) {

    $replift_admin_id = Capsule::table('tbladmins')->where('username', 'replift')->value('id');
    $replift_api_role_id = Capsule::table('tblapi_roles')->where('role', 'Replift')->value('id');
    $replift_api_device_id = Capsule::table('tbldeviceauth')->where('user_id', $replift_admin_id)->whereNull('deleted_at')->value('id');

    // Retrieve all department IDs as a collection
    $departmentIds = Capsule::table('tblticketdepartments')->pluck('id');
    // Convert to array
    $departmentIdsArray = $departmentIds->toArray();
    // Join the array into a string with commas and add commas at both ends
    $departmentIdsString = ',' . implode(',', $departmentIdsArray) . ',';
    print_r("<BR>Replift Departments ids: ".$departmentIdsString);

    //$replift_api_role_id = \WHMCS\Api\Authorization\ApiRole::where("role", 'Replift')->getId();
    print_r("<BR>Replift API role id: ".$replift_api_role_id);

    //$replift_admin->getInfobyUsername('replift', NULL, false);
    //print_r("<BR>Replift Admin id: ".$replift_admin->getId());
    print_r("<BR>Replift Admin id: ".$replift_admin_id);
    
    //$repfilt_api_device = \WHMCS\Authentication\Device::newAdminDevice(\WHMCS\User\Admin::find($replift_admin->getId()), 'Replift API');
    //print_r("<BR>Replift API device id: ".$repfilt_api_device->getId());
    print_r("<BR>Replift API device id: ".$replift_api_device_id);
}

// OLD function that adds any new IPs that are found. This is called by hooks file and runs with cron now.
function replift_addips()
{

    global $whmcs;
    global $replift_api_whitelist;

    // Get IPs that should be whitelisted from replift
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $replift_api_whitelist,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    )
    );

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // HTTP status code
    curl_close($curl);

    // check response is ok
    if ($response !== false && $httpcode == 200) {
        $responseArray = json_decode($response, true); // Decode JSON & make it associative array

        // json_decode ok? 'ips' key exists ?
        if ($responseArray !== null && isset ($responseArray['ips']) && is_array($responseArray['ips'])) {

            // Are we sure these are IP addresses? let's check
            $validIps = array_filter($responseArray['ips'], function ($ip) {
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

    // get ips already here
    $whitelistedips = $whmcs->get_config("APIAllowedIPs");
    $whitelistedips = unserialize($whitelistedips);


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
            $whitelistedips[] = array("ip" => $newIp, "note" => "Replift");
            echo $newIp . " has been added.<br>";
        }
        if ($atLeast1New) {
            $whmcs->set_config("APIAllowedIPs", serialize($whitelistedips));
        }
    }
    echo "<BR>Checking for new Replift IPs Complete<BR><BR>";
}


function replift_generate_api_credentials()
{
    global $whmcs;
    global $replift_uuid_existing;
    global $replift_api_marketplace;
    global $replift_marketplace_token;
 
    $replift_admin = new WHMCS\Auth();
    $replift_admin->getInfobyUsername('replift', NULL, false);
    $pass = phpseclib\Crypt\Random::string(21);
    $replift_user_role = Capsule::table('tbladminroles')->where('name', 'Replift')->value('id');

    // get departments as a collection
    $departmentIds = Capsule::table('tblticketdepartments')->pluck('id');
    // Convert to array
    $departmentIdsArray = $departmentIds->toArray();
    // Join the array into a string with commas and add commas at both ends
    $departmentIdsString = ',' . implode(',', $departmentIdsArray) . ',';

    // create replift user role if it doesnt exist
    if (!$replift_user_role) {
        $roleDetails = array();
        $roleDetails["name"] = 'Replift';
        $roleDetails["widgets"] = '';
        $roleDetails["reports"] = '';
        $roleDetails["systememails"] = 0;
        $roleDetails["accountemails"] = 0;
        $roleDetails["supportemails"] = 0;
        insert_query("tbladminroles", $roleDetails);
    }
    logActivity('Replift: Replift user role created.', 0);
    // Get id of new role
    $replift_user_role = Capsule::table('tbladminroles')->where('name', 'Replift')->value('id');
    
    // add role perms for API Only
    Capsule::table('tbladminperms')->insert(
        array(
            'roleid' => $replift_user_role,
            'permid' => 81
        )
    );
    logActivity('Replift: Replift role set to API perms only.', 0);

    // create replift user if it doesnt exist
    if (!$replift_admin->getAdminID()) {
        $adminDetails = array();
        $adminDetails["roleid"] = $replift_user_role;
        $adminDetails["username"] = 'replift';
        $adminDetails["firstname"] = 'Replift';
        $adminDetails["lastname"] = 'API';
        $adminDetails["email"] = 'noreply@replift.com';
        $adminDetails["signature"] = '';
        $adminDetails["disabled"] = 0;
        $adminDetails["notes"] = '';
        $adminDetails["template"] = 'blend';
        $adminDetails["language"] = 'english';
        $adminDetails["supportdepts"] = $departmentIdsString;
        $adminDetails["ticketnotifications"] = '';
        $adminDetails["password"] = $pass;
        $adminDetails["password_reset_data"] = "";
        $adminDetails["password_reset_key"] = $adminDetails["password_reset_data"];
        $adminDetails["password_reset_expiry"] = "0000-00-00 00:00:00";
        $adminDetails["updated_at"] = WHMCS\Carbon::now()->toDateTimeString();
        $adminDetails["created_at"] = $adminDetails["updated_at"];
        insert_query("tbladmins", $adminDetails);

    }
    $replift_admin->getInfobyUsername('replift', NULL, false);
    //$replift_admin->getInfobyUsername($adminDetails["username"], NULL, false);
    $replift_admin->generateNewPasswordHashAndStoreForApi(md5($pass));

    $replift_api_role = \WHMCS\Api\Authorization\ApiRole::where("role", 'Replift')->get();
    #print_r($replift_api_role[0]->getId());
    // create replift role if it does not exist
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
    $replift_api_role = \WHMCS\Api\Authorization\ApiRole::where("role", 'Replift')->get();

    $repfilt_api_device = \WHMCS\Authentication\Device::where('user_id', $replift_admin->getId())->get();
    if (count($repfilt_api_device) > 0) {
        $repfilt_api_device[0]->delete();
    }

    $repfilt_api_device = \WHMCS\Authentication\Device::newAdminDevice(\WHMCS\User\Admin::find($replift_admin->getId()), 'Replift API');
    $repfilt_api_device->addRole($replift_api_role[0]);
    $secret = $repfilt_api_device->secret;
    $repfilt_api_device->save();

    // make Replift the user for this device
    try {
    $replift_admin_id = Capsule::table('tbladmins')->where('username', 'replift')->value('id');
    $affected = Capsule::table('tbldeviceauth')
              ->where('id', $repfilt_api_device->id)
              ->update(['user_id' => $replift_admin_id]);
              logActivity('Replift: Replift user now successfully owns API.', 0);
    } catch (\Exception $e) {
        logActivity('Replift: failed to make Replift user owner of API:' . $e, 0);
   
    }

    $data = array();
    $data["identifier"] = $repfilt_api_device->identifier;
    $data["secret"] = $secret;

    // get url for api
    $data["api_url"] = $whmcs->get_config("SystemURL") . '/includes/api.php';

    //unique uuid
    function generateUUIDv4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100 for UUID version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10 for UUID variant 1
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    $data["uuid"] = generateUUIDv4();
    $uuid = $data["uuid"];

    // Get company name, agent name & email.
    $company = $whmcs->get_config("CompanyName");
    $admin_fname = Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('firstname');
    $admin_lname = Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('lastname');
    $email = Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('email');
    $name = $admin_fname . " " . $admin_lname;

    $curl = curl_init();

    $postFieldsArray = [
        "uuid" => $uuid,
        "service" => "whmcs",
        "desk_url" => $data["api_url"],
        "desk_identifier" => $data["identifier"],
        "desk_key" => $data["secret"],
        "company" => $company,
        "name" => $name,
        "email" => $email
    ];
    $postFields = json_encode($postFieldsArray, JSON_UNESCAPED_SLASHES);
    //logActivity('Replift: post to marketplace: '.$postFields , 0);

    // Save uuid & postfields to replift_config table
    try {

        $replift_uuid_existing = Capsule::table('replift_config')->where('setting', 'uuid')->value('value');

        Capsule::connection()->transaction(
            function ($connectionManager) use ($uuid, $postFields, $replift_uuid_existing) {
                /** @var \Illuminate\Database\Connection $connectionManager */
                if (!empty ($replift_uuid_existing)) {
                    // if uuid already exists, just update it
                    $updateUUID = Capsule::table('replift_config')
                        ->where('setting', 'uuid')
                        ->update([
                            'value' => $uuid,
                            'updated_at' => WHMCS\Carbon::now()->toDateTimeString()
                        ]);
                } else {
                    // if uuid does not exist, insert it
                    $connectionManager->table('replift_config')->insert(
                        [
                            'setting' => 'uuid',
                            'value' => $uuid,
                        ]
                    );
                    global $uuid;
                }
                $connectionManager->table('replift_config')->insert(
                    [
                        'setting' => 'preflight_attempt',
                        'value' => $postFields,
                    ]
                );
                logActivity('Replift: uuid & preflight successfully saved: ' . $uuid, 0);
            }
        );
    } catch (\Exception $e) {
        echo $e;
        logActivity('Replift: uuid & preflight failed save:' . $e, 0);
    }

    curl_setopt_array($curl, array(
        CURLOPT_URL => $replift_api_marketplace,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$replift_marketplace_token,
            'Content-Type: application/json'
        ),
    )
    );

    $response = curl_exec($curl);
    logActivity('Replift: marketplace response: ' . $response, 0);

    curl_close($curl);
    //echo $response;
    logActivity('Replift: ' . $data["identifier"] . ' created for Replift', 0);

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
        'description' => 'Replift is an AI tool that brings together all your company'
            . '\'s information in one place, making it faster and easier for your sales and support teams to work.',
        // Module author name
        'author' => 'Replift',
        // Default language
        'language' => 'english',
        // Version number
        'version' => '1.0.4',
        //'fields' => [
        // a text field type allows for single line text input
        //'WHMCS replift user' => [
        //'FriendlyName' => 'WHMCS Replift user',
        //'Type' => 'text',
        //'Size' => '25',
        //'Default' => 'Replift',
        //'Description' => 'WHMCS replift user',
        //],
        // a password field type allows for masked text input
        //'Replift API Key' => [
        //'FriendlyName' => 'API Key',
        //'Type' => 'password',
        //'Size' => '25',
        //'Default' => '',
        //'Description' => 'Replift API Key',
        //],
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
        //]
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
    global $replift_dashboard_link;

    // Rollback function in case things go wrong
    function rollback($e)
    {
        replift_deactivate();
        logActivity('Replift: Install rollback. ' . $e->getMessage(), 0);
        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to create replift_config: ' . $e->getMessage()
                . 'Install rollback, replift_config table removed',
        ];
    }

    // Create custom replift table
    try {
        global $replift_dashboard_link;
        Capsule::schema()
            ->create(
                'replift_config',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->increments('id');
                    $table->string('setting');
                    $table->text('value');
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->useCurrent();
                }
            );
        logActivity('Replift: replift_config table has been created', 0);


        // Backup current API Whitelist config on install
        try {
            Capsule::connection()->transaction(
                function ($connectionManager) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    global $whmcs;
                    global $uuid;
                    $whitelistedips = $whmcs->get_config("APIAllowedIPs");
                    $connectionManager->table('replift_config')->insert(
                        [
                            'setting' => 'api_whitelist_before_activate',
                            'value' => $whitelistedips,
                        ]
                    );
                    logActivity('Replift: APIAllowedIPs setting successfully backed up to replift_config table: ' . $whitelistedips, 0);
                }
            );

            try {
                // since backup was a success, let's add the new IPs
                replift_addips();
                // now lets get ready for registration!
                $uuid = replift_generate_api_credentials();

            } catch (\Exception $e) {
                //Add IPs failed so lets rollback
                rollback($e);
            }

        } catch (\Exception $e) {
            //Backup IPs failed so lets rollback
            rollback($e);
        }

        // Give Admin rights to Replift
        try {
            Capsule::table('tbladdonmodules')->insert([
                'module' => 'replift',
                'setting' => 'access',
                'value' => 1
            ]);
        } catch (\Exception $e) {
            // failed update admin. No reason to rollback for this. Will just log it.
            logActivity('Replift was unable to give Admin rights to the add-on. This will need to be done manually,', 0);
        }

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'The Replift module has been activated. '
                . 'Please continue registration here: ' . $replift_dashboard_link . '/register/' . $uuid . ' This link will expire in 4 hours.',

        ];
    } catch (\Exception $e) {
        //install failed so lets rollback
        rollback($e);
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
    global $whmcs;
    try {
        // Undo any database and schema modifications
        Capsule::schema()
            ->dropIfExists('replift_config');

        // Get the currently whitelisted IPs and unserialize them
        $whitelistedips = $whmcs->get_config("APIAllowedIPs");
        logActivity('Replift: APIAllowedIPs setting before Replift IP removals: ' . $whitelistedips, 0);
        $whitelistedips = unserialize($whitelistedips);

        // Remove Replift IP addresses
        $updatedIPs = [];
        foreach ($whitelistedips as $item) {
            if ($item['note'] !== "Replift") {
                $updatedIPs[] = $item;
            } else {
                logActivity($item['ip'] . " has been purged during Replift Deactivation", 0);
            }
        }
        $whmcs->set_config("APIAllowedIPs", serialize($updatedIPs));
        logActivity('Replift: APIAllowedIPs setting after Replift IP removals: ' . serialize($updatedIPs), 0);


        // Remove Replift User
        $replift_admin_id = Capsule::table('tbladmins')->where('username', 'replift')->value('id');
        if (!$replift_admin_id) {
            logActivity('Replift: Replift API User does not exist. Skipping', 0);
        } else {
            try {
                $deleted = Capsule::table('tbladmins')->where('username', 'replift')->delete();
                logActivity('Replift: Replift API User removed successfully during deactivation', 0);
            } catch (\Exception $e) {
                logActivity('Replift: Replift API User failed to remove: '.$e->getMessage(), 0);
            }
        }

        // Remove Replift User Role
        $replift_user_role_id = Capsule::table('tbladminroles')->where('name', 'Replift')->value('id');
        if (!$replift_user_role_id) {
            logActivity('Replift: Replift User Role does not exist. Skipping', 0);
        } else {
            try {
                $deleted = Capsule::table('tbladminroles')->where('name', 'Replift')->delete();
                logActivity('Replift: Replift User Role removed successfully during deactivation', 0);
            } catch (\Exception $e) {
                logActivity('Replift: Replift User Role failed to remove: '.$e->getMessage(), 0);
            }
        }

        $replift_api_role_id = Capsule::table('tblapi_roles')->where('role', 'Replift')->value('id');
        if (!$replift_api_role_id) {
            logActivity('Replift: Replift API Role does not exist. Skipping', 0);
        } else {
            try {
                $deleted = Capsule::table('tblapi_roles')->where('role', 'Replift')->delete();
                logActivity('Replift: Replift API Role removed successfully during deactivation', 0);
            } catch (\Exception $e) {
                logActivity('Replift: Replift API Role failed to remove: '.$e->getMessage(), 0);
            }
        }

        $replift_api_device_id = Capsule::table('tbldeviceauth')->where('user_id', $replift_admin_id)->whereNull('deleted_at')->value('id');
        if (!$replift_api_device_id) {
            logActivity('Replift: Replift API Device does not exist. Skipping', 0);
        } else {
            try {
                $deleted = Capsule::table('tbldeviceauth')->where('user_id', $replift_admin_id)->whereNull('deleted_at')->delete();
                logActivity('Replift: Replift API Device removed successfully during deactivation', 0);
            } catch (\Exception $e) {
                logActivity('Replift: Replift API Device failed to remove: '.$e->getMessage(), 0);
            }
        }

        
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
    global $replift_dashboard_link;

    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
    $version = $vars['version']; // eg. 1.0
    $_lang = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    //$configTextField = $vars['Text Field Name'];
    //$configPasswordField = $vars['Password Field Name'];
    //$configCheckboxField = $vars['Checkbox Field Name'];
    //$configDropdownField = $vars['Dropdown Field Name'];
    //$configRadioField = $vars['Radio Field Name'];
    //$configTextareaField = $vars['Textarea Field Name'];

    list($status, $replift_uuid_existing, $time_left, $err) = registerCheck();

    $register_link = $replift_dashboard_link . "/register/" . $replift_uuid_existing;

    echo '<div id="r-white-window">';
    switch ($status) {
        // REGISTERED
        case "registered":
            //echo "Replift Install UUID: " . $replift_uuid_existing;
            //echo "<br><br>";
            echo "<b>Your Replift registration is complete!</b> Please visit the Replift dashboard to continue at <a href='" . $replift_dashboard_link . "'>" . $replift_dashboard_link . "</a>";
            //echo "<br><br>";
            //echo "Registration Date: " . $time_left;
            break;
        // EXPIRED UUID
        case "expired":
            //echo "Replift Install UUID: " . $replift_uuid_existing;
            //echo "<br>";
            echo "The window between activation and registration has expired. Please regenerate a new link.";
            echo "<br><br>";
            $button_api = '<form action="" method="post">
            <input type="submit" name="replift_generate_api_credentials" value="Regenerate Link" />
            </form><br>';
            echo $button_api;
            break;
        // PENDING REGISTRATION
        case "pending":
            //echo "Replift Install UUID: " . $replift_uuid_existing;
            //echo "<br>";
            echo "<b>Please complete your registration at:</b> <a href='" . $register_link . "'>" . $register_link . "</a>";
            echo "<br><br>";
            echo "This link will expire in " . $time_left;
            break;
        // ELSE
        default;
            echo 'Replift status unknown. Please try again later. ' . $err;
            break;
    }
    echo '</div>';

    /*
    echo "<br>";
    $button_api = '<form action="" method="post">
    <input type="submit" name="replift_test" value="Replift testing" />
    </form><br>';
    echo $button_api;
    */
    
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
    global $whmcs;
    // Get common module parameters
    $modulename = $vars['module'];
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $_lang = $vars['_lang'];

    // Get the currently whitelisted IPs and unserialize them
    $whitelistedips = $whmcs->get_config("APIAllowedIPs");
    $whitelistedips = unserialize($whitelistedips);

    // Display Replift IP addresses
    $repliftIPs = [];
    foreach ($whitelistedips as $item) {
        if ($item['note'] == "Replift") {
            $repliftIPs[] = $item;
        }
    }

    $replift_logo = '<img src="../modules/addons/replift/repliftlogo_mini3.svg" class="replift_logo">';
    $sidebar = '<div class="r-box-shaded">
                    <div class="r-box-shaded-header"> 
                        '.$replift_logo.'
                    </div><br>';

                    /*
                    $sidebar = $sidebar . '
                    <form action="" method="post">
                        <input type="submit" name="replift_addips" value="Check for new Replift IPs" class="rbuttonA"/>
                    </form><br>';
                    */

                    $sidebar =  $sidebar . '
                    <span class="r-header-text">Replift IPs are automatically updated during the WHMCS cron.<br/><br/>Current Whitelist: '. count($repliftIPs).'</span>
                    <div class="r-center">';

                    foreach ($repliftIPs as $ip) {
                        $sidebar = $sidebar.$ip['ip'] . "<br>";
                    }
                $sidebar = $sidebar. '</div></div>';
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
    //$configTextField = $vars['Text Field Name'];
    //$configPasswordField = $vars['Password Field Name'];
    //$configCheckboxField = $vars['Checkbox Field Name'];
    //$configDropdownField = $vars['Dropdown Field Name'];
    //$configRadioField = $vars['Radio Field Name'];
    //$configTextareaField = $vars['Textarea Field Name'];

    /**
     * Dispatch and handle request here. What follows is a demonstration of one
     * possible way of handling this using a very basic dispatcher implementation.
     */

    //$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    //$dispatcher = new ClientDispatcher();
    //return $dispatcher->dispatch($action, $vars);
}