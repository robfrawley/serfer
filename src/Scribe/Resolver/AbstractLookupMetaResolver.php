#!/usr/bin/env php
<?php

/**
 * Serferals
 * 
 * Episode/Series filename re-namer and information lookup script
 *
 * @author    Rob M Frawley 2nd
 * @copyright 2009-2014 Inserrat Technologies, LLC
 * @license   MIT License
 */

/**
 * Queue class
 */
class Queue
{
    /**
     * @var array
     */
    static public $queue = [];

    /**
     * @param QueueItem $item
     */
    static public function add(QueueItem $item)
    {
        self::$queue[] = $item;
    }

    /**
     * @return null|QueueItem
     */
    static public function shift()
    {
        if (count(self::$queue) === 0) {
            return null;
        }

        return array_shift(self::$queue);
    }

    /**
     * @return array
     */
    static public function getAll()
    {
        return self::$queue;
    }
}

/**
 * QueueItem class
 */
abstract class QueueItem
{
    /**
     * @var string
     */
    private $filepath;

    /**
     * @param  string $filepath
     * @return QueueItem
     */
    public function setFilepath($filepath)
    {
        $this->filepath = $filepath;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilepath()
    {
        return $this->filepath;
    }
}

/**
 * QueueItemTV class
 */
class QueueItemTV extends QueueItem
{
    /**
     * @var string
     */
    private $show;

    /**
     * @var string
     */
    private $season;

    /**
     * @var string
     */
    private $episode;

    /**
     * @var string
     */
    private $title;

    /**
     * @param  string $show
     * @return QueueItem
     */
    public function setShow($show)
    {
        $this->show = $show;

        return $this;
    }

    /**
     * @return string
     */
    public function getShow()
    {
        return $this->show;
    }

    /**
     * @param  string $season
     * @return QueueItem
     */
    public function setSeason($season)
    {
        $this->season = $season;

        return $this;
    }

    /**
     * @return string
     */
    public function getSeason()
    {
        return $this->season;
    }

    /**
     * @param  string $episode
     * @return QueueItem
     */
    public function setEpisode($episode)
    {
        $this->episode = $episode;

        return $this;
    }

    /**
     * @return string
     */
    public function getEpisode()
    {
        return $this->episode;
    }

    /**
     * @param  string $title
     * @return QueueItem
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}

/**
 * QueueItemMovie class
 */
class QueueItemMovie extends QueueItem
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $year;

    /**
     * @var string
     */
    private $imdb;

    /**
     * @param  string $name
     * @return QueueItem
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  string $year
     * @return QueueItem
     */
    public function setYear($year)
    {
        $this->year = $year;

        return $this;
    }

    /**
     * @return string
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * @param  string $imdb
     * @return QueueItem
     */
    public function setImdb($imdb)
    {
        $this->imdb = $imdb;

        return $this;
    }

    /**
     * @return string
     */
    public function getImdb()
    {
        return $this->imdb;
    }
}

/**
 * Serferals class
 */
abstract class Serferals
{
    /**
     * @param string $item
     */
    abstract protected function handleItem($item);

    /**
     * handle the go operation
     */
    public function go()
    {
        if (!is_dir(Config::get('dirpath.output')) || !is_writable(Config::get('dirpath.output'))) {
            Console::error('Output directory does not exist or is not writable', 1);
        }

        if (is_dir(Config::get('dirpath.input')) && is_writable(Config::get('dirpath.input'))) {
            $items = $this->scanDirectory(Config::get('dirpath.input'));
        } else {
            $items = [];
        }

        $count = count($items);

        if ($count === 0) {
            Console::error('No input files found.', 0);
        }

        $skipRest = false;

        for ($i = 0; $i < $count; $i++) {

            $item = $items[$i];
            $this->showItemHeader($i, $count);

            $skipRest = $this->handleItem($item);

            if ($skipRest === true) {
                break;
            }
        }

        $this->handleQueue();

        Console::outl('%P[ CLEANUP %p'.Config::get('dirpath.input').' %P]');
        $this->removeEmptyDirectories(Config::get('dirpath.input'), true);

        Console::outl('%P[ CLEANUP %p'.Config::get('dirpath.output').' %P]');
        $this->removeEmptyDirectories(Config::get('dirpath.output'), true);

        Console::outl();
    }

    /**
     * @param  string $dir
     * @param  string $subDir
     * @return string
     */
    protected function scanDirectory($dir, $subDir = '.') 
    {
        $items = [];
        $acceptedExts = explode(',', Config::get('files.extensions'));

        foreach (scandir($dir) as $item) {

            if ($item == '.' || $item == '..' || substr($item, 0, 1) == '.') {
                continue;
            }

            if (is_dir($dir.$item)) {
                $items = array_merge($items, (array)$this->scanDirectory($dir.$item.DIRECTORY_SEPARATOR, $subDir.DIRECTORY_SEPARATOR.$item));
            } else {
                if (!in_array(pathinfo($item, PATHINFO_EXTENSION), $acceptedExts)) {
                    continue;
                }

                if ($subDir === null) {
                    $items[] = $item;
                } else {
                    $items[] = $subDir.DIRECTORY_SEPARATOR.$item;
                }
            }
        }

        return $items;
    }

    protected function removeEmptyDirectories($root, $top = false)
    {
        $empty = true;

        foreach (scandir($root.DIRECTORY_SEPARATOR) as $path) {

            if ($path == '.' || $path == '..') {
                continue;
            }

            if (is_dir($root.DIRECTORY_SEPARATOR.$path)) {
                if ($this->removeEmptyDirectories($root.DIRECTORY_SEPARATOR.$path) === false) {
                    $empty = false;
                }
            } elseif ($path != '.DS_Store') {
                $empty = false;
            } elseif ($path == '.DS_Store') {
                unlink($root.DIRECTORY_SEPARATOR.$path);
            }
        }

        if ($top === false && $empty === true && $root != '.' && $root != '..') {
            @rmdir($root);
        }

        return $empty;
    }

    /**
     * @param  integer $i
     * @param  integer $count
     */
    protected function showItemHeader($i, $count)
    {
        Console::outl('%P[ ITEM %p'.str_pad($i+1, 3, '0', STR_PAD_LEFT).' %Pof '.str_pad($count, 3, '0', STR_PAD_LEFT).' ]');
    }

    /**
     * @param  integer $i
     * @param  integer $count
     */
    protected function showQueueHeader($i, $count)
    {
        Console::outl('%P[ QUEUE %p'.str_pad($i+1, 3, '0', STR_PAD_LEFT).' %Pof '.str_pad($count, 3, '0', STR_PAD_LEFT).' ]');
    }
}

/**
 * SerferalsTV
 */
class SerferalsTV extends Serferals
{
    protected function handleQueue()
    {
        $i     = 0;
        $count = count(Queue::getAll());

        while (null !== ($queueItem = Queue::shift())) {
            
            $this->showQueueHeader($i, $count);

            $newFilepath = $this->generateNewFilepath($queueItem);

            Console::buffer();
            Console::buffer('Show%k[  :]%W'.            $queueItem->getShow());
            Console::buffer('Season%k[  :]%W'.          $queueItem->getSeason());
            Console::buffer('Episode%k[  :]%W'.         $queueItem->getEpisode());
            Console::buffer('Title%k[  :]%W'.           $queueItem->getTitle());
            Console::buffer('Original Path%k[  :]%W'.   $queueItem->getFilepath());
            Console::buffer('New Path%k[  :]%W'.        $newFilepath);
            Console::buffer();
            Console::flush();

            $newPath = pathinfo($newFilepath, PATHINFO_DIRNAME);
            if (!is_dir($newPath)) {
                mkdir($newPath, 0775, true);
            }

            Console::out('%cAction: %CMoving file...');
            rename($queueItem->getFilepath(), $newFilepath);
            Console::outl('%Cdone.');
            Console::outl();

            $i++;

        }
    }

    protected function generateNewFilepath(QueueItem $queueItem)
    {
        $format = Config::get('tpl.filepath.tv');
        $outdir = Config::get('dirpath.output');

        $title = $queueItem->getTitle();
        if ($title) {
            $title = ' - '.$title;
        } else {
            '';
        }

        $filename = str_replace('${seriesname}',    $queueItem->getShow(),                                   $format);
        $filename = str_replace('${episodeseason}', str_pad($queueItem->getSeason(), 2, '0', STR_PAD_LEFT),  $filename);
        $filename = str_replace('${episodenumber}', str_pad($queueItem->getEpisode(), 2, '0', STR_PAD_LEFT), $filename);
        $filename = str_replace('${episodename}',   $title,                                                  $filename);
        $filename = str_replace('${ext}',           pathinfo($queueItem->getFilepath(), PATHINFO_EXTENSION), $filename);

        return $outdir.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * @inheritdoc
     */
    protected function handleItem($item)
    {
        list($show, $season, $episode, $date) = $this->parseItem($item);

        $loop     = true;
        $skip     = false;
        $skipRest = false;
        $del      = false;
        $title    = null;

        $show = preg_replace('#\b\.\b#', ' ', $show);

        $tvdb = LookupApiTheTvdb::create()->lookup($show, $season, $episode, $title, $date);

        if ($tvdb->found()) {
            $lookupSuccess = true;
            list($show, $season, $episode, $title, $date) = $tvdb->getMetadata();
        } else {
            list($show, $season, $episode, $title, $date, $lookupSuccess) = 
                $this->doLookupTVEpisode($show, $season, $episode, $title, $date);
        }

        $show  = $this->handleTitleCaseAndStringCleanup($show);
        $title = $this->handleTitleCaseAndStringCleanup($title);

        while($loop === true) {
            
            Console::buffer();
            Console::buffer('Show%k[  :]%w'.$show);

            if ($lookupSuccess === true) {
                Console::buffer('Lookup%k[  :]%gSuccess');
            } else {
                Console::buffer('Lookup%k[  :]%rFailure');
            }

            if ($date !== null) {
                Console::buffer('Year%k[  :]%w'.$date);
            }

            if ($season === null) {
                Console::buffer('Season%k[  :]%w[null]');
            } else {
                Console::buffer('Season%k[  :]%w'.$season);
            }

            if ($episode === null) {
                Console::buffer('Episode%k[  :]%w[null]');
            } else {
                Console::buffer('Episode%k[  :]%w'.$episode);
            }

            if ($title === null) {
                Console::buffer('Title%k[  :]%w[null]');
            } else {
                Console::buffer('Title%k[  :]%w'.$title);
            }

            Console::buffer('Filepath%k[  :]%w'.$item);
            Console::buffer();
            Console::flush();

            Console::buffer('Metadata:');
            Console::buffer("\t%k[%wh%k]%%  %WInput%k.......%pSeries%P:%pName");
            Console::buffer("\t%k[%ws%k]%%  %WInput%k.......%pSeason%P:%pNumber");
            Console::buffer("\t%k[%we%k]%%  %WInput%k......%pEpisode%P:%pNumber");
            Console::buffer("\t%k[%wt%k]%%  %WInput%k......%pEpisode%P:%pTitle");
            Console::buffer("\t%k[%wl%k]%%  %WQuery%k......%pService%P:%pAPI        %R*Destructive");
            Console::buffer("\t%k[%wr%k]%%  %WParse%k.....%pFilename%P:%pString     %R*Destructive");
            Console::buffer('Actions:');
            Console::buffer("\t%k[%wk%k]%%  %WIgnore%k....%PCurrent Episode");
            Console::buffer("\t%k[%wK%k]%%  %WIgnore%k....%PRemaining Episodes");
            Console::buffer("\t%k[%wD%k]%%  %WDelete%k....%PEntry and Files");
            Console::buffer("\t%k[%wq%k]%%  %WForce%k.....%PImmediate Exit");
            Console::buffer("\t%k[%wc%k]%%  %WAccept%k....%PCurrent Metadata    %g*Default");

            Console::buffer();
            Console::flush();

            $response = Console::prompt('What would you like to do?', 'c');

            switch ($response) {
                case 's':
                    $response = Console::prompt('Enter the season number');
                    $season = $response;
                    break;
                case 'e':
                    $response = Console::prompt('Enter the episode number');
                    $episode = $response;
                    break;
                case 'h':
                    $response = Console::prompt('Enter the show name');
                    $show = $response;
                    break;
                case 't':
                    $response = Console::prompt('Enter the show title');
                    $title = $response;
                    break;
                case 'l':
                    Console::buffer();
                    Console::buffer('Matches:');
                    $matches = $this->doLookupTVShow($show, true);
                    foreach ($matches as $mi => $mv) {
                        Console::buffer("\t".($mi+1).'. '.$mv->title.' ('.$mv->year.')');
                    }
                    Console::buffer();
                    Console::flush();
                    $response = (int)Console::prompt('Enter the match number');
                    $mimdb = $matches[($response - 1)]->imdb_id;
                    list($show, $season, $episode, $title, $date, $lookupSuccess) = 
                        $this->doLookupTVEpisode($show, $season, $episode, $title, $date, $mimdb)
                    ;
                    Console::outl();
                    Console::outl('%cSelection: %C#'.$response.' '.$mv->title.' ('.$mv->year.')');
                    break;
                case 'r':
                    list($show, $season, $episode, $date) = 
                        $this->parseItem($item)
                    ;
                    break;
                case 'D':
                    $skip = true;
                    $del  = true;
                    @unlink(Config::get('dirpath.input').$item);
                    break;
                case 'k':
                    $skip = true;
                    break;
                case 'K':
                    $skipRest = true;
                    break;
                case 'c':
                    $loop = false;
                    break;
                case 'q':
                    exit(0);
                    break;
            }

            if ($skip === true || $skipRest === true) {
                break;
            }

        }

        if ($skip === true) {
            Console::outl();
            Console::outl('%cAction: %CSkipping this item...');
            if ($del === true) {
                Console::outl('%cAction: %CDeleting this item...');
            }
            Console::outl();

            return $skipRest;
        }

        $queueItem = new QueueItemTV();
        $queueItem
            ->setFilepath(Config::get('dirpath.input').DIRECTORY_SEPARATOR.$item)
            ->setShow($show)
            ->setSeason($season)
            ->setEpisode($episode)
            ->setTitle($title)
        ;

        Queue::add($queueItem);

        if ($skipRest !== true) {
            Console::outl();
            Console::outl('%cAction: %CAdded item #'.count(Queue::getAll()).' to queue...');
            Console::outl();
        } else {
            Console::outl();
            Console::outl('%cAction: %CAdded item #'.count(Queue::getAll()).' to queue...');
            Console::outl('%cAction: %CSkipping the remaining items...');
            Console::outl();

            return $skipRest;
        }

        return false;
    }

    protected function handleTitleCaseAndStringCleanup($string)
    {
        if ($string === null) { return null; }

        $string = mb_strtoupper($string);
        $string = preg_replace('#[^a-z0-9\s\'\._)(-]#i',  '',  $string);
        $string = preg_replace('#(\b)\.(\b)#',            ' ', $string);
        $string = trim(preg_replace('#[\s]{2,}#',         ' ', $string));

        return (!$string ? null : mb_convert_case(strtolower($string), MB_CASE_TITLE));
    }

    /**
     * @inheritdoc
     */
    protected function parseItem($filepath)
    {
        $pattern  = '#\.?/?(.*?)/?season ?([0-9]{1,2})[ /]?episode ?([0-9]{1,2})#i';
        $matches  = [];

        $return = preg_match($pattern, $filepath, $matches, PREG_OFFSET_CAPTURE);

        if ($return === 1) {
            return [
                isset($matches[1][0]) ? trim($matches[1][0]) : $filepath,
                isset($matches[2][0]) ? trim($matches[2][0]) : null,
                isset($matches[3][0]) ? trim($matches[3][0]) : null,
                null
            ];
        }

        $pattern  = '#\.?/?(.*?)([0-9]{4}\.[0-9]{2}\.[0-9]{2})#i';
        $matches  = [];

        $return = preg_match($pattern, $filepath, $matches, PREG_OFFSET_CAPTURE);

        if ($return === 1) {
            return [
                isset($matches[1][0]) ? trim($matches[1][0]) : $filepath,
                null,
                null,
                isset($matches[2][0]) ? trim($matches[2][0]) : null
            ];
        }

        $pattern  = '#(.*?) ?s?([0-9]{1,2}) ?[xe\.]([0-9]{1,2})#i';
        $matches  = [];

        $return = preg_match($pattern, basename($filepath), $matches, PREG_OFFSET_CAPTURE);

        if ($return === 1) {
            return [
                isset($matches[1][0]) ? basename(trim($matches[1][0])) : basename($filepath),
                isset($matches[2][0]) ? trim($matches[2][0]) : null,
                isset($matches[3][0]) ? trim($matches[3][0]) : null,
                null
            ];
        }

        $pattern  = '#(.*?)\b([0-9]{1,2})([0-9]{2})\b#i';
        $matches  = [];

        $return = preg_match($pattern, $filepath, $matches, PREG_OFFSET_CAPTURE);

        if ($return === 1) {
            return [
                isset($matches[1][0]) ? basename($matches[1][0]) : basename($filepath),
                isset($matches[2][0]) ? $matches[2][0] : null,
                isset($matches[3][0]) ? $matches[3][0] : null,
                null
            ];
        }

        return [
            $filepath,
            null,
            null,
            null
        ];
    }

    /**
     * @param  string $show
     * @param  string $season
     * @param  string $episode
     * @param  string $title
     * @return array
     */
    protected function doLookupTVEpisode($show, $season, $episode, $title, $date, $imdbID = false)
    {
        $traktApiKey = Config::getRuntime('lookup.api.trakt');
        $show = trim(str_replace('.', ' ', $show));

        if ($imdbID === false) {
            $imdbID = $this->doLookupTVShow($show);
        }

        if ($show === null) {
            return [null, null, null];
        }

        $api = "http://api.trakt.tv/show/episode/summary.json/$traktApiKey/$imdbID/$season/$episode";
        $json = @file_get_contents($api);
        $info = @json_decode($json);

        return [
            @$info->show->title     ? $info->show->title     : $show,
            @$info->episode->season ? $info->episode->season : $season,
            @$info->episode->number ? $info->episode->number : $episode,
            @$info->episode->title  ? $info->episode->title  : $title,
            @$info->show->year      ? $info->show->year      : null,
            @$info->episode->title  ? true                   : false,
        ];
    }

    /**
     * @param  string $show
     * @return StdObject
     */
    protected function doLookupTVShow($show, $returnJson = false)
    {
        $traktApiKey = Config::getRuntime('lookup.api.trakt');

        $api = "http://api.trakt.tv/search/shows.json/$traktApiKey?query=".urlencode($show);

        $json = @file_get_contents($api);

        $info = @json_decode($json);

        if ($returnJson === true) {
            return $info;
        }

        if (!count($info) > 0) {
            return null;
        }

        return @$info[0]->imdb_id;
    }
}

/**
 * SerferalsMovie class
 */
class SerferalsMovie extends Serferals
{
    protected function handleQueue()
    {
        $i     = 0;
        $count = count(Queue::getAll());

        while (null !== ($queueItem = Queue::shift())) {
            
            $this->showQueueHeader($i, $count);

            $newFilepath = $this->generateNewFilepath($queueItem);

            Console::buffer();
            Console::buffer('Movie%k[  :]%W'.           $queueItem->getName());
            Console::buffer('Year%k[  :]%W'.            $queueItem->getYear());
            Console::buffer('Imdb ID%k[  :]%W'.         $queueItem->getImdb());
            Console::buffer('Original Path%k[  :]%W'.   $queueItem->getFilepath());
            Console::buffer('New Path%k[  :]%W'.        $newFilepath);
            Console::buffer();
            Console::flush();

            $newPath = pathinfo($newFilepath, PATHINFO_DIRNAME);
            if (!is_dir($newPath)) {
                mkdir($newPath, 0775, true);
            }

            Console::out('%cAction: %CMoving file...');
            rename($queueItem->getFilepath(), $newFilepath);
            Console::outl('%Cdone.');
            Console::outl();

            $i++;

        }
    }

    protected function generateNewFilepath(QueueItem $queueItem)
    {
        $format = Config::get('tpl.filepath.movie');
        $outdir = Config::get('dirpath.output');

        $filename = str_replace('${moviename}', $queueItem->getName(), $format);
        $filename = str_replace('${movieyear}', $queueItem->getYear(), $filename);
        $filename = str_replace('${movieimdb}', $queueItem->getImdb(), $filename);
        $filename = str_replace('${ext}',       pathinfo($queueItem->getFilepath(), PATHINFO_EXTENSION), $filename);

        return $outdir.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * @inheritdoc
     */
    protected function handleItem($item)
    {
        list($name, $year) = 
            $this->parseItem($item)
        ;

        $loop     = true;
        $skip     = false;
        $skipRest = false;
        $del      = false;
        $imdb     = null;

        while($loop === true) {

            list($name, $year, $imdb, $lookups)
                = $this->doLookupMovie($name, $year, $imdb)
            ;
            
            Console::buffer();
            Console::buffer('Movie%k[  :]%w'.$name);

            /*
            if (count($lookups) > 0) {
                Console::buffer('Lookup%k[  :]%gSuccess');
            } else {
                Console::buffer('Lookup%k[  :]%rFailure');
            }
            */

            if ($year === null) {
                Console::buffer('Year%k[  :]%w[null]');
            } else {
                Console::buffer('Year%k[  :]%w'.$year);
            }

            if ($imdb === null) {
                Console::buffer('ImdbID%k[  :]%w[null]');
            } else {
                Console::buffer('ImdbID%k[  :]%w'.$imdb);
            }

            Console::buffer('Filepath%k[  :]%w'.$item);
            Console::buffer();
            Console::flush();

            Console::buffer('Actions:');
            Console::buffer("\tn. Edit the Name");
            Console::buffer("\ty. Edit the Year");
            Console::buffer("\th. Edit the ImdbID");
            Console::buffer("\tr. Reset Using Filename");
            Console::buffer("\tk. Skip");
            Console::buffer("\tK. Skip Remaining");
            Console::buffer("\tD. Delete");
            Console::buffer("\tq. Quit");
            Console::buffer("\tc. Continue");
            Console::buffer();
            Console::flush();

            $response = Console::prompt('What would you like to do?', 'c');

            switch ($response) {
                case 'n':
                    $response = Console::prompt('Enter the movie name');
                    $name = $response;
                    break;
                case 'y':
                    $response = Console::prompt('Enter the movie year');
                    $episode = $response;
                    break;
                case 'h':
                    $response = Console::prompt('Enter the movie IMDB ID');
                    $imdb = $response;
                    break;
                case 'r':
                    list($name, $year) = 
                        $this->parseItem($item)
                    ;
                    break;
                case 'D':
                    $skip = true;
                    $del  = true;
                    @unlink(Config::get('dirpath.input').$item);
                    break;
                case 'k':
                    $skip = true;
                    break;
                case 'K':
                    $skipRest = true;
                    break;
                case 'c':
                    $loop = false;
                    break;
                case 'q':
                    exit(0);
                    break;
                case 'l':
                    Console::buffer();
                    Console::buffer('Listings:');
                    foreach ($lookups as $i => $l) {
                        Console::buffer("\t".($i+1).". ".$l->Title.' ('.$l->Year.')');
                    }
                    Console::buffer();
                    Console::flush();
                    $result = Console::prompt('Select the correct movie listing');
                    list($name, $year, $imdb)
                        = $this->doLookupMovieByImdb($lookups[$result-1]->imdbID)
                    ;
                    break;
            }

            if ($skip === true || $skipRest === true) {
                break;
            }

        }

        if ($skip === true) {
            Console::outl();
            Console::outl('%cAction: %CSkipping this item...');  
            if ($del === true) {
                Console::outl('%cAction: %CDeleting this item...');
            }
            Console::outl();

            return $skipRest;
        }

        $queueItem = new QueueItemMovie();
        $queueItem
            ->setFilepath(Config::get('dirpath.input').DIRECTORY_SEPARATOR.$item)
            ->setName($name)
            ->setYear($year)
            ->setImdb($imdb)
        ;

        Queue::add($queueItem);

        if ($skipRest !== true) {
            Console::outl();
            Console::outl('%cAction: %CAdded item #'.count(Queue::getAll()).' to queue...');
            Console::outl();
        } else {
            Console::outl();
            Console::outl('%cAction: %CAdded item #'.count(Queue::getAll()).' to queue...');
            Console::outl('%cAction: %CSkipping the remaining items...');
            Console::outl();

            return $skipRest;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    protected function parseItem($filepath)
    {
        $pattern  = '#(.*?) ?\(?\[?([0-9]{4})\]?\)?#i';
        $matches  = [];

        $return = preg_match($pattern, basename($filepath), $matches, PREG_OFFSET_CAPTURE);

        if ($return === 1) {
            return [
                isset($matches[1][0]) ? trim($matches[1][0]) : basename($filepath),
                isset($matches[2][0]) ? trim($matches[2][0]) : null,
            ];
        }

        return [
            basename($filepath),
            null,
        ];
    }

    function doLookupMovie($title, $year = null)
    {
        $apiUrl = 'http://www.omdbapi.com/?s='.urlencode($title);

        if ($year !== null) {
            $apiUrl .= '&y='.urlencode($year);
        }

        $json = file_get_contents($apiUrl);
        $info = json_decode($json);
        
        $items = @$info->Search;

        if (!count($items) > 0) {
            return [
                $title,
                $year,
                null,
                []
            ];
        }

        $movies = [];
        for ($i = 0; $i < count($items); $i++) {
            if ($items[$i]->Type != 'movie') {
                continue;
            }
            $movies[] = $items[$i];
        }

        if (!count($movies) > 0) {
            return [
                $title,
                $year,
                null,
                []
            ];
        }

        return [
            $movies[0]->Title,
            $movies[0]->Year,
            $movies[0]->imdbID,
            $movies,
        ];
    }

    function doLookupMovieByImdb($imdb = null)
    {
        $apiUrl = 'http://www.omdbapi.com/?i='.urlencode($imdb);

        $json  = file_get_contents($apiUrl);
        $movie = json_decode($json);

        if (!$movie) {
            return [
                null,
                null,
                null
            ];
        }

        return [
            $movie->Title,
            $movie->Year,
            $movie->imdbID,
        ];
    }
}

class LookupApiTheTvdb
{
    protected $show;
    protected $season;
    protected $episode;
    protected $title;
    protected $data;
    protected $imdbId;
    protected $apiKey;
    protected $apiBasePath;
    protected $apiServerTime;

    public function __construct()
    {
        $this->apiBasePath = 'http://thetvdb.com/api/';
        $this->getServerTime();
    }

    static public function create()
    {
        return new self;
    }

    protected function getServerTime()
    {
        $this->apiServerTime = $this->request('Updates.php?type=none', 'Time');
    }

    protected function request($path, $xpath = null)
    {
        $request = $this->apiBasePath.$path;
        $response = @simplexml_load_string(@file_get_contents($request));

        if (!$request) {
            new \Exception('Error requesting '.$request);
        }

        if ($xpath === null) {
            return $response;
        }

        return $response->xpath($xpath)[0];
    }

    protected function getValues()
    {
        return [$this->show, $this->season, $this->episode, $this->title, $this->date];
    }

    public function lookup($show, $season, $episode, $title, $date, $imdbId = false)
    {
        $this->show = $show;
        $this->season = $season;
        $this->episode = $episode;
        $this->title = $title;
        $this->data = $date;
        $this->imdbId = $imdbId;

        if (!($this->apiKey = Config::getRuntime('lookup.api.thetvdb'))) {
            return $this->getValues();
        }

        var_dump($this);

        die();
    }
}

/**
 * main function handles the script from beginning to end
 */
function main()
{
    date_default_timezone_set(Config::getRuntime('php.timezone'));

    try {
        $optConfig = [
            'options' => [
                [
                    'long'  => 'tv',
                    'short' => 't',
                    'type'  => 'noarg',
                    'desc'  => 'Mode: Parse TV shows',
                ],
                [
                    'long'  => 'movie',
                    'short' => 'm',
                    'type'  => 'noarg',
                    'desc'  => 'Mode: parse movies',
                ],
                [
                    'long'  => 'verbosity',
                    'short' => 'v',
                    'type'  => 'mandatory',
                    'desc'  => 'Verbosity 0, 1, or 2',
                ],
                [
                    'long'  => 'in',
                    'short' => 'i',
                    'type'  => 'mandatory',
                    'desc'  => 'Input directory',
                ],
                [
                    'long'  => 'out',
                    'short' => 'o',
                    'type'  => 'mandatory',
                    'desc'  => 'Output directory',
                ],
                [
                    'long'  => 'extensions',
                    'short' => 'e',
                    'type'  => 'mandatory',
                    'desc'  => 'Acceptable extensions',
                ]
            ]
        ];
        $options = Console_Getoptplus::getoptplus($optConfig);
    }
    catch (Console_GetoptPlus_Exception $e) {
        echo $e->getCode() . ': ' . $e->getMessage();
        die();
    }

    Console::buffer();
    Console::buffer('%wSerferals[   ]%WSeries/Episode/movie File Renamer And Lookup Script');
    Console::buffer('%kVersion[  :]0.6.0');
    Console::buffer('%kWritten By[  :]Rob Frawley 2nd');
    Console::buffer('%kCopyright[  :]2009-2014 Inserrat Technologies, LLC');
    Console::buffer('%kLicense[  :]MIT License');
    Console::buffer();
    Console::flush();

    foreach ($options[0] as $o) {
        switch ($o[0]) {
            case 'verbosity':
            case 'v':
                Config::setRuntime('script.verbosity', $o[1]);
                break;
            case 'extensions':
            case 'e':
                Config::set('files.extensions', $o[1]);
                break;
            case 'in':
            case 'i':
                Config::set('dirpath.input', $o[1]);
                break;
            case 'out':
            case 'o':
                Config::set('dirpath.output', $o[1]);
                break;
            case '--tv':
            case 't':
                Config::setRuntime('script.mode', 'tv');
                break;
            case '--movie':
            case 'm':
                Config::setRuntime('script.mode', 'movie');
                break;
        }
    }

    if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'lookup.api.trakt')) {
        Config::setRuntime('lookup.api.trakt', trim(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'lookup.api.trakt')));
    } else {
        Config::setRuntime('lookup.api.trakt', 'Undefined');
    }

    if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'lookup.api.thetvdb')) {
        Config::setRuntime('lookup.api.thetvdb', trim(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'lookup.api.thetvdb')));
    } else {
        Config::setRuntime('lookup.api.thetvdb', 'Undefined');
    }
    
    Console::buffer('%bSET %Bscript.timezone%k[  :]%W'.   Config::getRuntime('php.timezone'),     2);
    Console::buffer('%bSET %Bscript.verbosity%k[  :]%W'.  Config::getRuntime('script.verbosity'), 2);
    Console::buffer('%bSET %Bscript.colors%k[  :]%W'.     Config::getRuntime('script.colors'),    2);
    Console::buffer('%bSET %Bscript.mode%k[  :]%W'.       Config::getRuntime('script.mode'),      1);
    Console::buffer('%bSET %Btpl.filepath.tv%k[  :]%W'.   Config::get('tpl.filepath.tv'),         2);
    Console::buffer('%bSET %Btpl.filepath.movie%k[  :]%W'.Config::get('tpl.filepath.movie'),      2);
    Console::buffer('%bSET %Bdirpath.input%k[  :]%W'.     Config::get('dirpath.input'),           1);
    Console::buffer('%bSET %Bdirpath.output%k[  :]%W'.    Config::get('dirpath.output'),          1);
    Console::buffer('%bSET %Bfiles.extensions%k[  :]%W'.  Config::get('files.extensions'),        2);
    Console::buffer('%bSET %Blookup.api.trakt%k[  :]%W'.  Config::getRuntime('lookup.api.trakt'), 2);
    Console::buffer('%bSET %Blookup.api.thetvdb%k[  :]%W'.  Config::getRuntime('lookup.api.thetvdb'), 2);
    Console::buffer();
    Console::flush();

    if (Config::getRuntime('script.mode') === 'tv') {
        $handler = new SerferalsTV();
    } else {
        $handler = new SerferalsMovie();
    }

    $handler->go();

    Console::outl('Exiting...');

}

// lets do this
main();

/* EOF */
