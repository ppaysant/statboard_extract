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

/************** CONF *************/
// Database server
define('DB_HOST', 'localhost');
// Port to database
define('DB_PORT', 3306);
// Schema to scan
define('DB_BASE', 'owncloud7');
// Connection username
define('DB_USER', 'owncloud7');
// Connection password
define('DB_PASS', 'owncloud7');
/************** END CONF *********/

$stats = new FileStats('/var/www/owncloud/data');
$stats->run();

class FileStats
{
    protected $path;
    protected $stats;
    protected $db;

    public function __construct($path='/data/owncloud')
    {
        $this->setPath($path);
        $this->stats = array();

        $this->db = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_BASE . ';charset=utf8', DB_USER, DB_PASS);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::MYSQL_ATTR_FOUND_ROWS, true);
    }

    // launch the directory reading, stats extracting and display the result
    public function run()
    {
        $time_start = microtime(true);
        $this->stats = $this->getFileStats();
        $time_end = microtime(true);

        $time = $time_end - $time_start;

        $nbUsers = $this->countUsers();

        $this->display("Stats for " . $this->path);
        $this->display("  nbFiles : " . $this->stats['totalFiles'] . " for " . $this->human_filesize($this->stats['totalSize']));
        $this->display("  nbDirs  : " . $this->stats['totalFolders']);
        $this->display("  time needed to go through entire filetree : " . $time);
        $this->display("  nbUsers : ". $nbUsers);
        $this->display("End");
    }

    /**
     * Setter for path
     * @param string $path Initial path for owncloud data directory
     */
    protected function setPath($path)
    {
        /* get the absolute path and ensure it has a trailing slash */
        $this->path = realpath($path);
        if (substr($this->path, -1) !== DIRECTORY_SEPARATOR) {
            $this->path .= DIRECTORY_SEPARATOR;
        }
    }

    /**
     * Go through the $this->path directory
     * @return [type] [description]
     */
    protected function getFileStats()
    {
        $stats = array();
        $stats['totalFiles'] = 0;
        $stats['totalFolders'] = 0;
        $stats['totalShares'] = 0;
        $stats['totalSize'] = 0;

        $user = array();
        $user['nbFiles'] = 0;
        $user['nbFolders'] = 0;
        $user['nbShares'] = 0;
        $user['filesize'] = 0;

        $path = $this->path;

        // Inspire by http://rosettacode.org/wiki/Walk_Directory_Tree#PHP_BFS_.28Breadth_First_Search.29
        $level = 0;
        $queue = array($path => 0);
        $nbFiles = $nbDirs = $totalSize = 0;
        clearstatcache();

// debug($queue, "démarrage");

        while(!empty($queue)) {
            /* get first element from the queue */
            // array_shift do not return the 'key', but only the 'value'...
            foreach($queue as $path => $level) {
                unset($queue[$path]);
                break;
            }
// debug($path . "($level)", "traitement de ");
            if ($level == 1) {
                        $stats['totalFolders'] += $user['nbFolders'];
                        $stats['totalFiles'] += $user['nbFiles'];
                        $stats['totalSize'] += $user['filesize'];

                        $user = array();
                        $user['nbFiles'] = 0;
                        $user['nbFolders'] = 0;
                        $user['nbShares'] = 0;
                        $user['filesize'] = 0;
            }
// debug($stats, 'stats');

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

                if (is_dir($filename)) {
                    // if ($level == 0) {
                    //     echo $filename . PHP_EOL;
                    // }

                    /* ensure the path has a trailing slash */
                    if (substr($filename, -1) !== DIRECTORY_SEPARATOR) {
                        $filename .= DIRECTORY_SEPARATOR;
                    }

                    /* check if we have already queued this path */
                    if (array_key_exists($filename, $queue))
                        continue;

                    if ($level >= 1) {
                        $user['nbFolders']++;
                    }

                    /* queue directories for later search */
                    $queue = array($filename => $level + 1) + $queue;
                }
                else {
                    if ($level >= 1) {
                        $user['nbFiles']++;
                        $user['filesize'] += filesize($filename);
                    }
                }
            }
            closedir($dh);

// debug($queue);
        }

        // last pass
        $stats['totalFolders'] += $user['nbFolders'];
        $stats['totalFiles'] += $user['nbFiles'];
        $stats['totalSize'] += $user['filesize'];

        return $stats;
    }

    /**
     * Display msg on screen
     * @param  string $msg
     */
    protected function display($msg)
    {
        echo $msg . PHP_EOL;
    }

    /**
     * Return readable string of a big number of bits
     * @param  [type]  $bytes    [description]
     * @param  integer $decimals [description]
     * @return [type]            [description]
     */
    protected function human_filesize($bytes, $decimals = 2)
    {
      $sz = 'BKMGTP';
      $factor = floor((strlen($bytes) - 1) / 3);
      return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * Returns nb of users (with or without folder)
     * @return int
     */
    protected function countUsers() {
        $nbUsers = 0;

        $sql = "SELECT COUNT(uid) AS nbUsers FROM oc_users";
        $st = $this->db->prepare($sql);
        $st->execute();

        if ($st->rowCount() > 0) {
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $nbUsers = $row['nbUsers'];
        }
        else {
            $this->display("ERROR : No table `oc_users` in base " . DB_BASE .".");
        }

        return $nbUsers;
    }
}

function debug($var, $msg="", $lvl=0, $border=false) {
    $tabul = str_repeat("    ", $lvl) ; ;

    if ($border) {
        echo '<div style="background-color:#d99;text-align:left;margin:5px;padding:5px;color:black;border:3px solid red;">' ;
    }

    if (is_array($var)) {
        echo $tabul."$msg (array)\n" ;
        foreach($var as $key => $val) {
            debug($val, "[$key]", $lvl+1) ;
        }
    }
    elseif(is_object($var)) {
        $array = array() ;
        $array = (array)$var ;
        echo $tabul ."$msg (object ". get_class($var) .") \n" ;
        debug($array, "", $lvl+1) ;
    }
    elseif(is_bool($var)) {
        $boolean2string = ($var)?"TRUE":"FALSE" ;
        echo $tabul .$msg ." (boolean):". $boolean2string .":\n" ;
    }
    else {
        echo $tabul ."$msg (". gettype($var) ."):$var:\n" ;
    }

    if ($border) {
        echo "</div>" ;
    }
}
