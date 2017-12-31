# ScanRss

Karmorra's ShowRss Simple Torrent Downloader

### Usage
```php
use nmanley\ScanRss\ScanRss;
$scan = new ScanRss();

$scan->readFeed()
$scan->getHistory();
$scan->parseFeed();

$scan->getTorrents();
```