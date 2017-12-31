<?php

namespace nmanley\ScanRss;

/**
 * Class ScanRss
 * @package nmanley\ScanRss
 */
class ScanRss
{
    protected $feeds = [];
    protected $settings = [];
    protected $feedData = [];
    protected $history = [];
    protected $parsed = [];
    protected $torCount = 0;
    protected $endTime = 0;
    protected $status = 0;

    /**
     * ScanRss constructor.
     */
    function __construct()
    {
        $this->startTime = time();

        // Default config setup
        $this->settings['log_file_name'] = "downloaded.log";
        $this->settings['file_indexes'] = "indexes.log";
        $this->settings['history_log'] = "history.log";
        $this->settings['file_location'] = "shares/";

        // Checking if a Few Things
        if (!file_exists($this->settings['file_location'])) {
            mkdir($this->settings['file_location'], 0766, true);
        }

        if (!is_writeable($this->settings['file_location'])) {
            $this->writeHistory(3);
            exit;
        }
    }

    /**
     * @param string $sFeedUrl
     * @return bool
     */
    public function addFeed($sFeedUrl)
    {
        return (bool)($this->feeds[] = $sFeedUrl);
    }

    /**
     * @param string $sKey
     * @param * $sValue
     * @return bool|null
     */
    public function setConfig($sKey, $sValue)
    {
        if (key_exists($sKey, $this->settings)) {
            return (bool)($this->settings[$sKey] = $sValue);
        }

        return null;
    }

    /**
     * @param int $status
     */
    public function writeHistory($status)
    {
        $this->endTime = time() - $this->startTime;

        Switch ($status) {
            case 0:
                $msg = "Failed To Run Script.";
                break;
            case 1:
                $msg = "Script Ran Successfully.[ " . $this->torCount . " ] New. [ Time ] . " . $this->endTime . " seconds.";
                break;
            case 2:
                $msg = "Script Ran Successfully, but no new torrents were downloaded. [ Time ] . " . $this->endTime . " seconds.";
                break;
            case 3:
                $msg = "Unable to write to directory : " . $this->settings['file_location'];
                break;
            default:
                $msg = "Unable to determine the Pass/Fail status of the Script, plese fire you're programmer. Status: " . $status;
                break;
        }

        $date = (string)date('Y-m-d H:i:s', time());
        $form = "[ " . $date . " ] - " . $msg . "\r\n";

        file_put_contents($this->settings['history_log'], $form, FILE_APPEND);
        return;
    }

    /**
     * @param null|string $url
     * @return array|int
     */
    public function readFeed($url = null)
    {
        $ret = [];

        if (!$url) {
            $url = $this->feeds;
        } else {
            if (!is_array($url)) {
                return 0;
            }
        }

        foreach ($url as $f) {

            $ch = curl_init();
            if ($ch) {

                curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
                curl_setopt($ch, CURLOPT_URL, $f);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $ret[] = curl_exec($ch);
                curl_close($ch);
            }
        }

        $this->feedData = $ret;
        return $this->feedData;
    }

    /**
     * @param * $data
     * @return array|int
     */
    public function parseFeed($data = null)
    {
        if (!$data) {
            $data = $this->feedData;

        } else {
            if (!is_array($data)) {
                return 0;
            }
        }

        foreach ($data as $set) {
            if ($rss = simplexml_load_string($set)) {
                foreach ($rss->channel->item as $i) {
                    $id = md5($i->link);

                    if (!in_array($id, $this->history)) {
                        $this->torCount++; // Add 1 To the Download Count for record purposes.

                        // Escaping Illegal Chars
                        $i->title = preg_replace('/[^A-Za-z0-9_\-.\s]/', '_', $i->title);


                        $this->parsed[(string)$i->guid] = array(
                            'link' => $i->link,
                            'file' => $i->title . " - " . $id . ".torrent",
                            'hash' => $id
                        );
                    }

                }
            }
        }

        return $this->parsed;
    }

    /**
     * @return array|bool
     */
    public function getHistory()
    {
        $this->history = [];
        if (is_file($this->settings['file_indexes']) && file_exists($this->settings['file_indexes']) && is_readable($this->settings['file_indexes'])) {
            $this->history = file($this->settings['file_indexes'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }

        return $this->history;
    }

    /**
     * @param * $data
     * @return bool
     */
    public function saveHistory($data)
    {
        if (is_array($data)) {
            $date = (string)date('Y-m-d H:i:s', time());
            $form = "[ " . $date . " ] - " . $data['file'] . "\r\n";

            // Writing To File
            file_put_contents($this->settings['log_file_name'], $form, FILE_APPEND);
            return true;
        } else {
            file_put_contents($this->settings['file_indexes'], $data . "\r\n", FILE_APPEND);
            return true;
        }
    }

    /**
     * @return int
     */
    public function getTorrents()
    {
        if (isset($this->parsed) && is_array($this->parsed) && sizeof($this->parsed) > 0) {
            foreach ($this->parsed as $item) {
                $ch = curl_init($item['link']);
                if ($ch) {
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                    // cURL Getting File
                    $ret = curl_exec($ch);
                    curl_close($ch);

                    if (!$ret) {
                        $this->status = 0;
                    } else {
                        $file = fopen($this->settings['file_location'] . $item['file'], "w");
                        fputs($file, $ret);
                        fclose($file);

                        // Check if the file size is > 0
                        if (@filesize((string)$this->settings['file_location'] . $item['file']) <= 0) {
                            unlink($this->settings['file_location'] . $item['file']);
                        }

                        $this->saveHistory(array('file' => $item['file']));
                        $this->saveHistory((string)$item['hash']);
                    }
                }
            }
            $this->status = 1;
        } elseif ($this->parsed > 0) {
            $this->status = 2;
        } else {
            $this->status = 0;
        }

        return $this->status;
    }
}