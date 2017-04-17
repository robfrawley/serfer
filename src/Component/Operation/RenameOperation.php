<?php

/*
 * This file is part of the `src-run/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Serferals\Component\Operation;

use SR\Console\Style\StyleAwareTrait;
use SR\Exception\Logic\InvalidArgumentException;
use SR\Spl\File\SplFileInfo as FileInfo;
use SR\Serferals\Component\Fixture\FixtureData;
use SR\Serferals\Component\Fixture\FixtureEpisodeData;
use SR\Serferals\Component\Fixture\FixtureMovieData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RenameOperation.
 */
class RenameOperation
{
    use StyleAwareTrait;

    /**
     * @var string
     */
    const MODE_CP = 'cp';

    /**
     * @var string
     */
    const MODE_MV = 'mv';

    /**
     * @var string
     */
    protected $outputPath;

    /**
     * @var bool
     */
    protected $blindOverwrite;

    /**
     * @var bool
     */
    protected $smartOverwrite;

    /**
     * @var string
     */
    protected $mode = self::MODE_MV;

    /**
     * @var string
     */
    protected $tplPathEpisode;

    /**
     * @var string
     */
    protected $tplFileEpisode;

    /**
     * @var string
     */
    protected $tplPathMovie;

    /**
     * @var string
     */
    protected $tplFileMovie;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * @param string $tplPath
     * @param string $tplFile
     */
    public function setFileTemplateEpisode($tplPath, $tplFile)
    {
        $this->tplPathEpisode = $tplPath;
        $this->tplFileEpisode = $tplFile;
    }

    /**
     * @param string $tplPath
     * @param string $tplFile
     */
    public function setFileTemplateMovie($tplPath, $tplFile)
    {
        $this->tplPathMovie = $tplPath;
        $this->tplFileMovie = $tplFile;
    }

    /**
     * @param string $outputPath
     *
     * @return $this
     */
    public function setOutputPath(string $outputPath)
    {
        $this->outputPath = $outputPath;

        return $this;
    }

    /**
     * @param bool $blindOverwrite
     *
     * @return $this
     */
    public function setBlindOverwrite(bool $blindOverwrite)
    {
        $this->blindOverwrite = $blindOverwrite;

        return $this;
    }

    /**
     * @param bool $smartOverwrite
     *
     * @return $this
     */
    public function setSmartOverwrite(bool $smartOverwrite)
    {
        $this->smartOverwrite = $smartOverwrite;

        return $this;
    }

    /**
     * @param string $mode
     *
     * @return $this
     */
    public function setMode(string $mode)
    {
        if (!in_array($mode, [self::MODE_CP, self::MODE_MV])) {
            throw new InvalidArgumentException('Invalid option provided for mode: %s', $mode);
        }

        $this->mode = $mode;

        return $this;
    }

    /**
     * @param FixtureMovieData[]|FixtureEpisodeData[] $collection
     */
    public function run(array $collection)
    {
        if (count($collection) === 0) {
            $this->io()->caution('No input files gathered for output during latest run.');
            return;
        }

        $this->io()->subSection('Writing Output Files');

        $itemCount = count($collection);
        $iteration = 1;

        foreach ($collection as $item) {
            $this->ioVerbose(function () use (&$iteration, $itemCount) {
                $this->io()->section(sprintf('%03d of %03d', $iteration++, $itemCount));
            });
            $this->move($item);
        }

        $this->io()->newLine();
    }

    /**
     * @param FixtureData $f
     */
    private function move(FixtureData $f)
    {
        $f->setName(str_replace(DIRECTORY_SEPARATOR, '-', $f->getName()));

        $tplPathName = uniqid('string_template_'.mt_rand(10, 99).'_', true);
        $tplFileName = uniqid('string_template_'.mt_rand(10, 99).'_', true);

        if ($f instanceof FixtureMovieData) {
            list($e, $opts) = $this->moveMovie($f, $this->getTwig(), $tplPathName, $tplFileName);
        } elseif ($f instanceof FixtureEpisodeData) {
            list($e, $opts) = $this->moveEpisode($f, $this->getTwig(), $tplPathName, $tplFileName);
        } else {
            $this->io()->error('Invalid fixture type!');
            return;
        }

        $path = $e->render($tplPathName, $opts);
        $file = $e->render($tplFileName, $opts);

        $outputFilePath = preg_replace('{[/]+}', '/', sprintf('%s/%s/%s', $this->outputPath, $path, $file));
        $outputPath = pathinfo($outputFilePath, PATHINFO_DIRNAME);
        $inputFilePath = $f->getFile()->getRealPath();

        $offset = 2;
        while (true) {
            if (strncmp($outputFilePath, $inputFilePath, $offset++) !== 0) {
                $offset = $offset - 2;
                break;
            }
        }

        $outputFileInfo = new FileInfo($outputFilePath);
        $inputFileInfo = new FileInfo($inputFilePath);

        try {
            $inputFileSize = $inputFileInfo->getSizeReadable();
        } catch (\RuntimeException $e) {
            $inputFileSize = null;
        }

        $tableRows[] = [
            'Input File',
            sprintf('[...]%s', basename($inputFilePath)),
            $inputFileSize,
        ];

        try {
            $outputFileSize = $outputFileInfo->getSizeReadable();
        } catch (\RuntimeException $e) {
            $outputFileSize = null;
        }

        $tableRows[] = [
            'Output File',
            sprintf('[...]%s', preg_replace('{[/]+}', '/', sprintf('%s/%s', $path, $file))),
            $outputFileSize,
        ];

        $this->io()->table($tableRows, [null, 'Path', 'Size']);

        if (file_exists($outputFilePath) &&
            false === $this->blindOverwrite &&
            false === $this->handleExistingFile($outputFileInfo, $inputFileInfo)
        ) {
            return;
        }

        if (!is_dir($outputPath) && false === @mkdir($outputPath, 0777, true)) {
            $this->io()->error(sprintf('Could not create directory "%s"', $outputPath));
            return;
        }

        if (!$this->io()->isVeryVerbose()) {
            $this->io()->comment(sprintf('Writing "%s"', $outputFilePath));
        }

        if (false === @copy($inputFilePath, $outputFilePath)) {
            $this->io()->error(sprintf('Could not write file "%s"', $outputFilePath));
        } elseif ($this->mode === self::MODE_MV) {
            unlink($inputFilePath);
        }
    }

    /**
     * @param FileInfo $output
     * @param FileInfo $input
     *
     * @return bool|null
     */
    private function handleExistingFile(FileInfo $output, FileInfo $input)
    {
        try {
            if ($this->smartOverwrite === true && $input->getSize() > $output->getSize()) {
                $this->io()->info('Automatically overwriting smaller output filepath with larger input.');
                return true;
            }

            if ($this->smartOverwrite === true && $input->getSize() <= $output->getSize()) {
                unlink($input->getPathname());
                $this->io()->info('Automatically removing input file path of less than or equal size to existing output filepath.');
                return false;
            }
        } catch (\RuntimeException $e) {
            $this->io()->error('Could not use smart output mode! An error occurred while processing file sizes.');
        }

        while (true) {
            $this->io()->comment('File already exists in output path');

            $this->io()->writeln(' [ <em>o</em> ] Overwrite <info>(default)</info>', false);
            $this->io()->writeln(' [ <em>s</em> ] Skip', false);
            $this->io()->writeln(' [ <em>R</em> ] Delete Input', false);

            $action = $this->io()->ask('Enter action command shortcut name', 'o');

            switch ($action) {
                case 'o':
                    $this->io()->info('Overwriting smaller output filepath with larger input.');
                    return true;

                case 's':
                    return false;

                case 'R':
                    $this->io()->info(sprintf('Removing input file: %s', $input->getPathname()));
                    unlink($input->getPathname());
                    return false;

                default:
                    $this->io()->error(sprintf('Invalid command shortcut "%s"', $action));
                    sleep(3);
            }
        }

        return null;
    }

    /**
     * @param FixtureEpisodeData $f
     * @param \Twig_Environment  $e
     * @param string             $tplPathName
     * @param string             $tplFileName
     *
     * @return \Twig_Environment[]|mixed[][]
     */
    private function moveEpisode(FixtureEpisodeData $f, \Twig_Environment $e, $tplPathName, $tplFileName)
    {
        $e->setLoader(new \Twig_Loader_Array([$tplPathName => $this->tplPathEpisode, $tplFileName => $this->tplFileEpisode]));

        $opts = [
            'name' => $f->getName(),
            'season' => str_pad($f->getSeasonNumber(), 2, 0, STR_PAD_LEFT),
            'start' => str_pad($f->getEpisodeNumberStart(), 2, 0, STR_PAD_LEFT),
            'ext' => strtolower(pathinfo($f->getFile()->getRealPath(), PATHINFO_EXTENSION)),
        ];

        if ($f->hasTitle()) {
            $opts['title'] = $f->getTitle();
        }

        if ($f->hasYear()) {
            $opts['year'] = $f->getYear();
        }

        return [$e, $opts];
    }

    /**
     * @param FixtureMovieData  $f
     * @param \Twig_Environment $e
     * @param string            $tplPathName
     * @param string            $tplFileName
     *
     * @return \Twig_Environment[]|mixed[][]
     */
    private function moveMovie(FixtureMovieData $f, \Twig_Environment $e, $tplPathName, $tplFileName)
    {
        $e->setLoader(new \Twig_Loader_Array([$tplPathName => $this->tplPathMovie, $tplFileName => $this->tplFileMovie]));

        $opts = [
            'name' => $f->getName(),
            'ext' => strtolower(pathinfo($f->getFile()->getRealPath(), PATHINFO_EXTENSION)),
        ];

        if ($f->hasId()) {
            $opts['id'] = $f->getId();
        }

        if ($f->hasYear()) {
            $opts['year'] = $f->getYear();
        }

        return [$e, $opts];
    }

    /**
     * @return \Twig_Environment
     */
    private function getTwig()
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array([]));
        $twig->setCache(false);

        return $twig;
    }
}
