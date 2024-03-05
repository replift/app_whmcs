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

// For adding to system cron
add_hook('AfterCronJob', 1, function($vars) {
    // this has to be called inside the hook or we lose access to whmcs calls
    global $whmcs;

    // We added this one so we could update Replift IPs in WHMCS
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
            logActivity('Replift: IP check for API whitelist complete.' , 0);
        } else {
            // "Error: Invalid format or 'ips' key not found in response.";
            logActivity('Replift: Failure to retrieve IPlist. Format or Decode error. Contact Replift Support.', 0);
        }
    } else {
        // Handle error (either cURL error or non-200 response)
        logActivity('Replift: Failure to retrieve IPlist. cURL or HTTP error. Contact Replift Support. '. $httpcode, 0);
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
            <link rel="stylesheet" href="../modules/addons/replift/replift_custom2.css">
        HTML;
    }
});
