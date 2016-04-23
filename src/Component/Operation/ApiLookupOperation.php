<?php

/*
 * This file is part of the `rmf/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace RMF\Serferals\Component\Operation;

use RMF\Serferals\Component\Console\InputOutputAwareTrait;
use RMF\Serferals\Component\Console\Style\StyleInterface;
use RMF\Serferals\Component\Fixture\FixtureData;
use RMF\Serferals\Component\Fixture\FixtureEpisodeData;
use RMF\Serferals\Component\Fixture\FixtureMovieData;
use RMF\Serferals\Component\Tmdb\EpisodeResolver;
use RMF\Serferals\Component\Tmdb\MovieResolver;
use SR\Reflection\Inspect;
use SR\Utility\StringUtil;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tmdb\Model\AbstractModel;
use Tmdb\Model\Collection\ResultCollection;
use Tmdb\Model\Common\Country;
use Tmdb\Model\Movie;
use Tmdb\Model\Search\SearchQuery;
use Tmdb\Model\Tv;

/**
 * Class LookupResolverOperation
 */
class ApiLookupOperation
{
    use InputOutputAwareTrait;

    /**
     * @var FileResolverOperation
     */
    protected $fileResolver;

    /**
     * @var EpisodeResolver
     */
    protected $episodeResolver;

    /**
     * @var MovieResolver
     */
    protected $movieResolver;

    /**
     * @param FileResolverOperation $fileResolver
     * @param EpisodeResolver       $episodeResolver
     * @param MovieResolver         $movieResolver
     */
    public function __construct(FileResolverOperation $fileResolver, EpisodeResolver $episodeResolver, MovieResolver $movieResolver)
    {
        $this->fileResolver = $fileResolver;
        $this->episodeResolver = $episodeResolver;
        $this->movieResolver = $movieResolver;
    }

    /**
     * @return FileResolverOperation
     */
    public function getFileResolver()
    {
        return $this->fileResolver;
    }

    /**
     * @param FixtureData[] $fixtureSet
     *
     * @return FixtureData[]|FixtureEpisodeData[]|FixtureMovieData[]
     */
    public function resolve(array $fixtureSet)
    {
        $i = 0;
        $c = count($fixtureSet);

        $fixtureSet = array_map(function ($f) use ($c, &$i) {
            return $this->lookup($f, $c, $i);
        }, $fixtureSet);

        return array_filter($fixtureSet, function (FixtureData $fixture) {
            return $fixture->isEnabled();
        });
    }

    /**
     * @param FixtureData $f
     * @param int         $count
     * @param int         $i
     *
     * @return FixtureData|FixtureEpisodeData|FixtureMovieData
     */
    public function lookup(FixtureData $f, $count, &$i)
    {
        ++$i;
        $mode = $f::TYPE;
        $lookupSelection = 1;

        while (true) {
            $this->io()->section(sprintf('%d of %d', $i, $count));

            if ($mode === MovieResolver::TYPE) {
                if ($f instanceof FixtureEpisodeData) {
                    $f = $this->fileResolver->parseFileAsMovie($f->getFile());
                }

                $results = $this->movieResolver->resolve($f)->getResults();
                $resultSelected = $this->getResultSelection($results, $lookupSelection);
                $item = $this->getResultSelection($results, $lookupSelection);
            } else {
                if ($f instanceof FixtureMovieData) {
                    $f = $this->fileResolver->parseFileAsEpisode($f->getFile());
                }

                $results = $this->episodeResolver->resolve($f)->getResults();
                $resultSelected = $this->getResultSelection($results, $lookupSelection);
                $item = $this->episodeResolver->resolveSingle($f, $resultSelected);
            }

            if ($results->count() == 0 || !$item) {
                $this->writeLookupFailure($f);
            } else {
                $this->writeLookupSuccess($f, $item, $resultSelected);
            }

            $this->ioV(function() use ($mode) {
                $this->writeHelp($mode);
            });

            $actionDefault = $results->count() == 0 || !$item ? 's' : 'c';

            $action = $this->io()->ask('Enter action command shortcut name', $actionDefault);

            switch ($action) {
                case 'c':
                    $this->hydrateFixture($f, $item, $this->getResultSelection($results, $lookupSelection));
                    break 2;

                case 'F':
                    $f->setEnabled(true);
                    break 2;

                case 'e':
                    $this->editFixture($f);
                    break;

                case 'l':
                    $lookupSelection = $this->listResults($results);
                    continue;

                case 's':
                    $f->setEnabled(false);
                    $this->io()->comment('Skipping...');
                    break 2;

                case 'R':
                    $f->setEnabled(false);
                    $removeResult = $this->remove($f);
                    $this->io()->newLine();

                    if ($removeResult === 1) {
                        break 1;
                    } else {
                        break 2;
                    }

                case 'm':
                    $mode = ($mode === EpisodeResolver::TYPE ? MovieResolver::TYPE : EpisodeResolver::TYPE);
                    $this->io()->comment(sprintf(
                        'Lookup mode switched to "%s"',
                        $mode
                    ));
                    break;

                case '?':
                    $this->writeHelp($mode, true);
                    sleep(3);
                    break;

                case 'Q':
                    $this->io()->warning('User requested termination');
                    exit;

                default:
                    $this->io()->error(sprintf('Invalid command shortcut "%s"', $action));
                    sleep(3);
            }
        }

        return $f;
    }

    /**
     * @param ResultCollection $resultSet
     *
     * @return int
     */
    private function listResults(ResultCollection $resultSet)
    {
        $tableRows = array_values(array_map(
            function (AbstractModel $m) {
                static $i = 0;

                if ($m instanceof Tv) {
                    $country = '';
                    $countrySet = $m->getOriginCountry();

                    if ($countrySet->count() > 0) {
                        $countryKey = $countrySet->getKeys()[0];
                        $country = $countrySet->get($countryKey)->getIso31661();
                    }

                    return ['['.++$i.'] '.$m->getId(), $m->getName(), $m->getFirstAirDate()->format('Y\-m\-d'), $country];
                }

                if ($m instanceof Movie) {
                    return ['['.++$i.'] '.$m->getId(), $m->getTitle(), $m->getReleaseDate()->format('Y\-m\-d'), ''];
                }

                return null;
            },
            $resultSet->getAll())
        );

        array_filter($tableRows, function ($row) {
            return $row !== null;
        });

        $this->ioV(
            function (StyleInterface $io) {
                $io->comment('Listing Tvdb lookup search results');
            }
        );

        $this->io()->table(['[#] Tvdb Id', 'Title', 'Release Year', 'Extra'], $tableRows);
        $selection = $this->io()->ask('Enter result item number', 1, null, function ($value) {
            return (int) $value;
        });

        return (int) $selection;
    }

    /**
     * @param FixtureData $f
     *
     * @return array
     */
    private function getEditFixtureTable(FixtureData $f)
    {
        $tableRows = [];
        $control = [];
        $i = 0;

        foreach ($f->getFieldsEditable() as $property => $name) {
            $control[] = [$property, $name];
            $tableRows[] = $this->getEditFixtureTableRow($f, $property, $name, $i, true);
        }

        foreach ($f->getFieldsStatic() as $property => $name) {
            $tableRows[] = $this->getEditFixtureTableRow($f, $property, $name, $i, false);
        }

        return [
            ['[#] Field', 'Value'],
            $tableRows,
            $control,
        ];
    }

    /**
     * @param FixtureData $f
     * @param string      $property
     * @param string      $name
     * @param int         $i
     * @param bool        $editable
     *
     * @return array
     */
    private function getEditFixtureTableRow(FixtureData $f, $property, $name, &$i, $editable)
    {
        $index = $editable === true ? sprintf('[%d] %s', ($i++), $name) : sprintf('[-] %s', $name);
        $method = 'get'.ucfirst($property);
        $result = call_user_func([$f, $method]);

        if (is_bool($result)) {
            $value = ($result === true ? 'yes' : 'no');
        } else {
            $value = $result;
        }

        return [
            $index,
            $value,
        ];
    }

    /**
     * @param FixtureData $f
     */
    private function editFixture(FixtureData $f)
    {
        $this->ioV(
            function (StyleInterface $io) use ($f) {
                $io->comment('Listing fixture property values');
            }
        );

        while(true) {
            list($tableHeads, $tableRows, $control) = $this->getEditFixtureTable($f);
            $this->io()->table($tableHeads, $tableRows);
            $action = strtolower($this->io()->ask('Enter value number or no value to exit editor', 'done'));

            switch ($action) {
                case 'done':
                    break 2;

                default:
                    $this->editFixtureProperty($f, $action, $control);
            }
        }
    }

    /**
     * @param FixtureData $f
     * @param string      $act
     * @param array[]     $ctl
     */
    private function editFixtureProperty(FixtureData $f, $act, $ctl)
    {
        if (!array_key_exists($act, $ctl)) {
            $this->io()->error('Invalid selection of '.$act);

            return;
        }

        $property = $ctl[$act][0];
        $getMethod = 'get'.ucfirst($property);
        $setMethod = 'set'.ucfirst($property);

        $name = $ctl[$act][1];
        $oldValue = call_user_func([$f, $getMethod]);

        if (is_bool($oldValue)) {
            $oldValue = $oldValue === true ? 'true' : 'false';
        }

        $value = $this->io()->ask(sprintf('EDITOR: Enter new value for "%s"', $name), $oldValue);

        if ($property === 'enabled' && strtolower($value) === 'false') {
            $value = false;
        }

        call_user_func([$f, $setMethod], $value);
    }

    /**
     * @param FixtureData $f
     *
     * @return int
     */
    private function remove(FixtureData $f)
    {
        $relativePathName = $f->getFile()->getRelativePathname();
        $relativePath = pathinfo($relativePathName, PATHINFO_DIRNAME);
        $absolutePathName = $f->getFile()->getRealPath();
        $absolutePath = pathinfo($absolutePathName, PATHINFO_DIRNAME);

        $removeDirectory = $this->io()->confirm('Remove directory path and all its contents?', false);
        $removeItem = $removeDirectory === true ? $absolutePath : $absolutePathName;

        $this->io()->caution(
            sprintf(
                'Remove %s %s',
                $removeDirectory === true ? 'directory' : 'file',
                $removeDirectory === true ? $relativePath : $relativePathName
            )
        );

        if ($this->io()->confirm('Continue with deletion', true) === false) {
            return 1;
        }

        if (!is_writable($removeItem)) {
            $this->io()->error(sprintf('Could not delete "%s"', $relativePathName));
            return 1;
        }

        if ($removeDirectory === true) {
            return $this->removeFilePath($f, $removeItem);
        }

        return $this->removeFileItem($f, $removeItem);
    }

    /**
     * @param FixtureData $f
     * @param string      $path
     * @param bool        $newLine
     *
     * @return int
     */
    private function removeFileItem(FixtureData $f, $path, $newLine = false)
    {
        $this->ioV(function(StyleInterface $io) use ($path, $newLine) {
            $io->comment(sprintf('Removing "%s"', $path), $newLine);
        });

        if (false === @unlink($path)) {
            $this->io()->error(sprintf('Could not remove "%s"', $path));

            return 1;
        }

        $f->setEnabled(false);

        return 2;
    }

    /**
     * @param FixtureData $f
     * @param string      $path
     *
     * @return int
     */
    private function removeFilePath(FixtureData $f, $path)
    {
        $resultSet = [];

        foreach (array_diff(scandir($path), array('..', '.')) as $file) {
            if (is_dir($path.DIRECTORY_SEPARATOR.$file)) {
                $resultSet[] = $this->removeFilePath($f, $path.DIRECTORY_SEPARATOR.$file);
            } else {
                $resultSet[] = $this->removeFileItem($f, $path.DIRECTORY_SEPARATOR.$file, false);
            }
        }

        $this->ioV(function(StyleInterface $io) use ($path) {
            $io->comment(sprintf('Removing "%s"', $path), false);
        });

        $resultsSet[] = @rmdir($path) === false ? 1 : 2;

        $resultSet = array_filter($resultSet, function ($result) {
            return $result !== 2;
        });

        if (count($resultSet) !== 0) {
            $this->io()->error(sprintf('Could not remove "%s"', $path));
            return 1;
        }

        $f->setEnabled(false);

        return 2;
    }



    /**
     * @param FixtureData     $f
     * @param object|object[] $parameters
     */
    private function hydrateFixture(FixtureData $f, ...$parameters)
    {
        if ($f instanceof FixtureEpisodeData) {
            $this->hydrateFixtureEpisode($f, ...$parameters);
        } elseif ($f instanceof FixtureMovieData) {
            $this->hydrateFixtureMovie($f, ...$parameters);
        }
    }

    /**
     * @param FixtureEpisodeData $f
     * @param Tv\Episode|null    $e
     * @param Tv|null            $s
     */
    private function hydrateFixtureEpisode(FixtureEpisodeData $f, Tv\Episode $e = null, Tv $s = null)
    {
        if ($s === null || $e === null) {
            return;
        }

        $f->setName($s->getName());
        $f->setTitle($e->getName());
        $f->setEpisodeNumberStart($e->getEpisodeNumber());
        $f->setSeasonNumber($e->getSeasonNumber());
        $f->setId($e->getId());
        $f->setYear($s->getFirstAirDate()->format('Y'));
        $f->setEnabled(true);
    }

    /**
     * @param FixtureMovieData $f
     * @param Movie|null       $m
     */
    private function hydrateFixtureMovie(FixtureMovieData $f, Movie $m = null)
    {
        if ($m === null) {
            return;
        }

        $f->setName($m->getTitle());
        $f->setId($m->getId());
        $f->setYear($m->getReleaseDate()->format('Y'));
        $f->setEnabled(true);
    }

    /**
     * @param ResultCollection $resultSet
     * @param int              $selection
     *
     * @return Tv|Movie
     */
    private function getResultSelection(ResultCollection $resultSet, $selection = 1)
    {
        $keys = $resultSet->getKeys();

        if (--$selection > count($keys)) {
            $selection = 0;
        }

        if (!array_key_exists($selection, $keys)) {
            return null;
        }

        return $resultSet->get($keys[$selection]);
    }

    /**
     * @param FixtureData $f
     */
    private function writeLookupFailure(FixtureData $f)
    {
        if ($f instanceof FixtureEpisodeData) {
            $this->writeLookupFailureEpisode($f);
        } elseif ($f instanceof FixtureMovieData) {
            $this->writeLookupFailureMovie($f);
        }
    }

    /**
     * @param FixtureData|FixtureEpisodeData|FixtureMovieData $f
     * @param Movie[]|Tv[]|Tv\Episode[]                       ...$parameters
     */
    private function writeLookupSuccess(FixtureData $f, ...$parameters)
    {
        if (!$this->io()->isVerbose()) {
            $this->io()->comment($f->getFile()->getRelativePathname());
        }

        if (count($parameters) > 1 && $f instanceof FixtureEpisodeData) {
            $this->writeLookupSuccessEpisode($f, ...$parameters);
        } elseif (count($parameters) > 1 && $f instanceof FixtureMovieData) {
            $this->writeLookupSuccessMovie($f, ...$parameters);
        }
    }

    /**
     * @param FixtureMovieData $f
     * @param Movie            $m
     */
    private function writeLookupSuccessMovie(FixtureMovieData $f, Movie $m)
    {
        $this->io()->success(
            sprintf(
                'Match Found: %s (%d) [%d%s]',
                $m->getTitle(),
                $m->getReleaseDate()->format('Y'),
                $m->getId(),
                empty($m->getImdbId()) ? '' : '/'.$m->getImdbId()
            )
        );

        $rows = [
            ['Tvdb Id', $m->getId().($m->getImdbId() === null ? '' : '/'.$m->getImdbId())],
            ['Path Name', $f->getFile()->getRelativePathname()],
            ['Movie Title', $m->getTitle()],
            ['Release Date', $m->getReleaseDate()->format('Y\-m\-d')],
        ];

        if ($this->io()->isVerbose() && false) {
            $overview = $m->getOverview();
            $i = 0;

            $rows[] = new TableSeparator();

            while ($i < strlen($overview)) {
                $rows[] = [$i === 0 ? 'Overview' : '', substr($overview, $i, 80),];
                $i = $i + 80;
            }
        }

        if ($this->io()->isVerbose()) {
            $this->io()->table([], $rows);
        }
    }

    /**
     * @param FixtureEpisodeData $f
     * @param Tv\Episode         $e
     * @param Tv                 $s
     */
    private function writeLookupSuccessEpisode(FixtureEpisodeData $f, Tv\Episode $e, Tv $s)
    {
        $this->io()->success(
            sprintf(
                'Match Found: %s S%02dE%02d "%s" [%d/%d]',
                $s->getName(),
                $e->getSeasonNumber(),
                $e->getEpisodeNumber(),
                $e->getName(),
                $s->getId(),
                $e->getId()
            )
        );

        $country = '';
        $countrySet = $s->getOriginCountry();

        if ($countrySet->count() > 0) {
            $countryKey = $countrySet->getKeys()[0];
            $country = $countrySet->get($countryKey)->getIso31661();
        }

        $rows = [
            ['Tvdb Id', $s->getId().'/'.$e->getId()],
            ['Path Name', $f->getFile()->getRelativePathname()],
            ['Show Name', $s->getName()],
            ['Season', $e->getSeasonNumber()],
            ['Episode Number', $e->getEpisodeNumber()],
            ['Episode Title', $e->getName()],
            ['Origin Country', $country],
            ['Air Date', $e->getAirDate()->format('Y\-m\-d')],
        ];

        if ($this->io()->isVerbose() && false) {
            $overview = $e->getOverview();
            $i = 0;

            $rows[] = new TableSeparator();

            while ($i < strlen($overview)) {
                $rows[] = [$i === 0 ? 'Overview' : '', substr($overview, $i, 80),];
                $i = $i + 80;
            }
        }

        if ($this->io()->isVerbose()) {
            $this->io()->table([], $rows);
        }
    }

    /**
     * @param FixtureMovieData $f
     */
    private function writeLookupFailureMovie(FixtureMovieData $f)
    {
        $this->io()->error(
            sprintf(
                'Match failure: %s',
                $f->getFile()->getRelativePathname()
            )
        );

        $rows = [
            ['Tvdb Id', ''],
            ['Path Name', $f->getFile()->getRelativePathname()],
            ['Movie Title', $f->getName()],
            ['Release Year', $f->getYear()],
        ];

        if ($this->io()->isVerbose()) {
            $this->io()->table([], $rows);
        }
    }

    /**
     * @param FixtureEpisodeData $f
     */
    private function writeLookupFailureEpisode(FixtureEpisodeData $f)
    {
        $this->io()->error(
            sprintf(
                'Match failure: %s S%02dE%02d',
                $f->getName(),
                $f->getSeasonNumber(),
                $f->getEpisodeNumberStart()
            )
        );

        $rows = [
            ['Tvdb Id', ''],
            ['Path Name', $f->getFile()->getRelativePathname()],
            ['Show Name', $f->getName()],
            ['Season', $f->getSeasonNumber()],
            ['Episode Number', $f->getEpisodeNumberStart()],
            ['Episode Title', $f->getTitle()],
            ['Air Year', $f->getYear()],
        ];

        if ($this->io()->isVerbose()) {
            $this->io()->table([], $rows);
        }
    }

    /**
     * @param string $mode
     */
    private function writeHelp($mode, $v = false)
    {
        if ($v === true) {
            $this->io()->comment('Listing available actions');
        }
        
        $mode = ($mode === EpisodeResolver::TYPE ? MovieResolver::TYPE : EpisodeResolver::TYPE);
        $this->io()->writeln(' [ <em>c</em> ] Continue <info>(default)</info>', false);
        $this->io()->writeln(' [ <em>s</em> ] Skip', false);
        $this->io()->writeln(' [ <em>e</em> ] Edit Fixture', false);
        $this->io()->writeln(' [ <em>l</em> ] Search Results', false);
        $this->io()->writeln(' [ <em>?</em> ] Show Help', false);

        if ($v === true) {
            $this->io()->writeln(sprintf(' [ <em>m</em> ] Mode <info>(switch to %s)</info>', $mode), false);
            $this->io()->writeln(' [ <em>F</em> ] Forced Continue', false);
            $this->io()->writeln(' [ <em>R</em> ] Remove', false);
            $this->io()->writeln(' [ <em>Q</em> ] Quit');
        }
    }
}

/* EOF */
