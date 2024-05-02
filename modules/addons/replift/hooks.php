<?php
/**
 * Register a hook with WHMCS.
 *
 * This sample demonstrates triggering a service call when a change is made to
 * a client profile within WHMCS.
 *
 * For more information, please refer to https://developers.whmcs.com/hooks/
 *
 * add_hook(string $hookPointName, int $priority, string|array|Closure $function)
 */

 use WHMCS\Database\Capsule;

// For adding to system cron
add_hook('AfterCronJob', 1, function($vars) {
    include_once(ROOTDIR.'/modules/addons/replift/globalvars.php');
    // this has to be called inside the hook or we lose access to whmcs calls
    global $whmcs;
    //$replift_api_whitelist = "https://api-stage.replift.com/api/v2/ip_whitelist/";
    // We added this one so we could update Replift IPs in WHMCS
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
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // HTTP status code
    curl_close($curl);

    // Make sure of response
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

            // get ips already here
            $whitelistedips = $whmcs->get_config( "APIAllowedIPs" );
            $whitelistedips = unserialize( $whitelistedips );

            $atLeast1New = false;
            // check and add each IP to array if it doesn't exist
            foreach ($newData as $newIp) {
                $ipExists = false;
                foreach ($whitelistedips as $item) {
                    if ($item['ip'] === $newIp) {
                        $ipExists = true;
                        //logActivity('Replift: '.$newIp.' already exists in the API whitelist. Skipping.' , 0);
                        break;
                    }
                }
                if (!$ipExists) {
                    // store it!
                    $atLeast1New = true;
                    $whitelistedips[] = array( "ip" => $newIp, "note" => "Replift" );
                    logActivity('Replift: '.$newIp.' has been added to the API whitelist.' , 0);
                }
                if ($atLeast1New) {
                    $whmcs->set_config( "APIAllowedIPs", serialize( $whitelistedips ));
                }
            }
            //logActivity('Replift: IP check for API whitelist complete.' , 0);
            //logActivity('ROOTDIR: ' . ROOTDIR, 0);
            //logActivity($replift_api_whitelist, 0);

            // add time of cron run to replift table
            $replift_cron_log_existing = Capsule::table('replift_config')->where('setting', 'cron')->value('value');
            Capsule::connection()->transaction(
                function ($connectionManager) use ($replift_cron_log_existing) {
                    /** @var \Illuminate\Database\Connection $connectionManager */
                    if (!empty ($replift_cron_log_existing)) {
                        // if cronlog already exists, just update it
                        $updateCron = Capsule::table('replift_config')
                            ->where('setting', 'cron')
                            ->update([
                                'value' => "success",
                                'updated_at' => WHMCS\Carbon::now()->toDateTimeString()
                            ]);
                    } else {
                        // if cronlog does not exist, insert it
                        $connectionManager->table('replift_config')->insert(
                            [
                                'setting' => 'cron',
                                'value' => "success",
                                'updated_at' => WHMCS\Carbon::now()->toDateTimeString()
                            ]
                        );
                    }
                }
            );

        } else {
            // "Error: Invalid format or 'ips' key not found in response.";
            logActivity('Replift: Failure to retrieve IP list. Format or Decode error. Contact Replift Support.', 0);
        }
    } else {
        // Handle error (either cURL error or non-200 response)
        logActivity('Replift: Failure to retrieve IP list. cURL or HTTP error. Contact Replift Support. '. $httpcode, 0);
    }

});

// For adding Admin CSS
add_hook('AdminAreaHeadOutput', 1, function($vars) {
    $pagetitle = $vars['pagetitle'];

    //logActivity('Hook variables: '.print_r($vars, 0));
    //logActivity('Page title:'.print_r($pagetitle, 0));
    //jackie updated 2-29-24

    // Only hook in this CSS if we are in the Replift module
    if ($pagetitle == "Replift") {
        return <<<HTML
            <link rel="stylesheet" href="../modules/addons/replift/replift_custom11.css">
        HTML;
    }
    /*
    // Only add this js if we are in Module Addons Config
    if ($pagetitle == "Addons") {
        return <<<HTML
        <script type="text/javascript" src="../modules/addons/replift/replift1.js"></script>
    HTML;
    }
    */
});


// For adding date ticket was originally submitted on ticket page
add_hook('AdminAreaViewTicketPage', 1, function($vars) {

    $ticket_created = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->value('date');
    $ticket_status = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->value('status');
    $ticket_department = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->value('did');

    $return = '<div id="replift_ticket_created" style="display: none">'.$ticket_created.'</div>';
    $return = $return.'<div id="replift_ticket_status" style="display: none">'.$ticket_status.'</div>';
    $return = $return.'<div id="replift_ticket_department" style="display: none">'.$ticket_department.'</div>';
    
    return $return;
});