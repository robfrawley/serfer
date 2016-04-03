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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class DeleteExtensionsOperation
 */
class DeleteExtensionsOperation
{
    use InputOutputAwareTrait;

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
     * @param string[]    $ins
     * @param string[] ...$extensions
     */
    public function run(array $ins, ...$extensions)
    {
        $finder = Finder::create();

        foreach ($ins as $in) {
            $finder->in($in);
        }

        $finder->files();

        foreach ($extensions as $e) {
            $finder->name('*.'.$e);
        }

        $this->ioV(function (SymfonyStyle $io) use ($finder, $extensions) {
            $io->text(sprintf(
                'Removing <info>%d</info> files with extensions <info>%s</info>.',
                $finder->count(), implode('|', $extensions)));
        });

        foreach ($finder as $file) {
            $this->delete($file);
        }
    }

    /**
     * @param SplFileInfo $file
     */
    private function delete(SplFileInfo $file)
    {
        if (!unlink($file->getPathname())) {
            $this->io()->error('Could not remove '.$file->getPathname());
        }
    }
}