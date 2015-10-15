<?php

/**
 * filestats.php
 *
 * This very simple script will run through entire owncloud data directory
 * Non recursive algorithm is used to avoid memory problem with a large number of files (> 1 million)
 *
 * @copyright 2015 CNRS DSI
 * @author Patrick Paysant patrick.paysant@linagora.com
 * @licence This file is licensed under the Affero General Public License version 3 or later
 *
 */

$path = $originalPath = "/data/owncloud";

// Inspire by http://rosettacode.org/wiki/Walk_Directory_Tree#PHP_BFS_.28Breadth_First_Search.29

$time_start = microtime(true);

/* get the absolute path and ensure it has a trailing slash */
$path = realpath($path);
if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
    $path .= DIRECTORY_SEPARATOR;
}

$queue = array($path => 1);
$nbFiles = $nbDirs = $totalSize = 0;
clearstatcache();

while(!empty($queue)) {
    /* get one element from the queue */
    foreach($queue as $path => $unused) {
        unset($queue[$path]);
        break;
    }
    unset($unused);

    $dh = @opendir($path);
    if (!$dh) continue;
    while(($filename = readdir($dh)) !== false) {
        /* dont recurse back up levels */
        if ($filename == '.' || $filename == '..')
            continue;

        /* get the full path */
        $filename = $path . $filename;

        /* Don't follow symlinks */
        if (is_link($filename))
            continue;

        /* queue directories for later search */
        if (is_dir($filename)) {
            /* ensure the path has a trailing slash */
            if (substr($filename, -1) !== DIRECTORY_SEPARATOR) {
                $filename .= DIRECTORY_SEPARATOR;
            }

            /* check if we have already queued this path */
            if (array_key_exists($filename, $queue))
                continue;

            /* queue the file */
            $nbDirs++;
            $queue[$filename] = null;
        }
        else {
            $nbFiles++;
            $totalSize += filesize($filename);
        }
    }
    closedir($dh);
}

$time_end = microtime(true);
$time = $time_end - $time_start;

// Quickly get the list of users dir
$userList = scandir($originalPath);

display("Stats for " . $originalPath);
display("  nbFiles : " . $nbFiles . " for " . human_filesize($totalSize));
display("  nbDirs  : " . $nbDirs);
display("  time needed to go through entire filetree : " . $time);
display("  nbUsers : ". count($userList));
display("End");

function display($msg)
{
    echo $msg . "\n";
}

function human_filesize($bytes, $decimals = 2)
{
  $sz = 'BKMGTP';
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}
