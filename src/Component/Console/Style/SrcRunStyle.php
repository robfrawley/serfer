<?php

/*
 * This file is part of the `rmf/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace RMF\Serferals\Component\Console\Style;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\OutputStyle;

/**
 * Output decorator helpers for the Symfony Style Guide.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class SrcRunStyle extends OutputStyle implements StyleInterface
{
    /**
     * @var int
     */
    private $maxLineLength = 160;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var SymfonyQuestionHelper
     */
    private $questionHelper;

    /**
     * @var ProgressBar
     */
    private $progressBar;

    /**
     * @var int
     */
    private $lineLength;

    /**
     * @var BufferedOutput
     */
    private $outputBuffered;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->outputBuffered = new BufferedOutput($output->getVerbosity(), false, clone $output->getFormatter());
        $this->lineLength = min($this->getTerminalWidth() - (int) (DIRECTORY_SEPARATOR === '\\'), $this->maxLineLength);

        parent::__construct($output);
        
        $this->getFormatter()->setStyle('highlight', new OutputFormatterStyle('magenta'));
        $this->getFormatter()->setStyle('em', new OutputFormatterStyle(null, null, ['bold']));
        $this->getFormatter()->setStyle('success', new OutputFormatterStyle('black', 'green'));
    }

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages The message to write in the block
     * @param string|null  $type     The block type (added in [] on first line)
     * @param string|null  $style    The style to apply to the whole block
     * @param string       $prefix   The prefix for the block
     * @param bool         $padding  Whether to add vertical padding
     */
    public function block($messages, $type = null, $style = null, $prefix = ' ', $padding = false)
    {
        $this->autoPrependBlock();
        $messages = is_array($messages) ? array_values($messages) : array($messages);
        $lines = array();

        // add type
        if (null !== $type) {
            $messages[0] = sprintf('[%s] %s', $type, $messages[0]);
        }

        // wrap and add newlines for each element
        foreach ($messages as $key => $message) {
            $message = OutputFormatter::escape($message);
            $lines = array_merge($lines, explode(PHP_EOL, wordwrap($message, $this->lineLength - Helper::strlen($prefix), PHP_EOL, true)));

            if (count($messages) > 1 && $key < count($messages) - 1) {
                $lines[] = '';
            }
        }

        if ($padding && $this->isDecorated()) {
            array_unshift($lines, '');
            $lines[] = '';
        }

        foreach ($lines as &$line) {
            $line = sprintf('%s%s', $prefix, $line);
            $line .= str_repeat(' ', $this->lineLength - Helper::strlenWithoutDecoration($this->getFormatter(), $line));

            if ($style) {
                $line = sprintf('<%s>%s</>', $style, $line);
            }
        }

        $this->writeln($lines);
        $this->newLine();
    }

    public function getSeparatorFullWidth()
    {
        return sprintf('<fg=white>%s</>', str_repeat('▬', $this->lineLength));
    }

    public function applicationTitle($name, $version = null, ...$additionals)
    {
        $lines = [null, sprintf(' <em>%s (version %s)</em> ', $name, $version ?: 'master')];

        foreach ($additionals as $additionalParts) {
            $lines[] = sprintf(' %s %s ', ...$additionalParts);
        }

        $lines[] = null;
        //$lines[] = $this->getSeparatorFullWidth();

        $this->autoPrependBlock();
        $this->writeln($lines);
        $this->newLine();
    }
    
    /**
     * {@inheritdoc}
     */
    public function title($message)
    {
        $this->autoPrependBlock();
        $this->writeln(array(
            sprintf('<comment>%s</>', $message),
            sprintf('<comment>%s</>', str_repeat('=', Helper::strlenWithoutDecoration($this->getFormatter(), $message))),
        ));
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function section($message)
    {
        $messageLen = Helper::strlenWithoutDecoration($this->getFormatter(), $message);

        $this->autoPrependBlock();
        $this->writeln([
            sprintf('<fg=magenta>%s</>', '-[ '.strtoupper($message).' ]'.str_repeat('-', $this->lineLength-$messageLen-5)),
        ]);
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function listing(array $elements)
    {
        $this->autoPrependText();
        $elements = array_map(function ($element) {
            return sprintf(' * %s', $element);
        }, $elements);

        $this->writeln($elements);
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function text($message)
    {
        $this->autoPrependText();

        $messages = is_array($message) ? array_values($message) : array($message);
        foreach ($messages as $message) {
            $this->writeln(sprintf(' %s', $message));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function comment($message)
    {
        $this->autoPrependText();

        $messages = is_array($message) ? array_values($message) : array($message);
        foreach ($messages as $message) {
            $this->writeln(sprintf(' // %s', $message));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function success($message)
    {
        $this->block($message, 'OK', 'fg=black;bg=green', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message)
    {
        $this->block($message, 'ERROR', 'fg=white;bg=red', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message)
    {
        $this->block($message, 'WARNING', 'fg=white;bg=red', ' ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function note($message)
    {
        $this->block($message, 'NOTE', 'fg=yellow', ' ! ');
    }

    /**
     * {@inheritdoc}
     */
    public function caution($message)
    {
        $this->block($message, 'CAUTION', 'fg=white;bg=red', ' ! ', true);
    }

    /**
     * {@inheritdoc}
     */
    public function table(array $headers, array $rows)
    {
        $rows = array_map(function ($value) {
            if (!is_array($value)) {
                return $value;
            }
            $header = array_shift($value);
            array_unshift($value, sprintf('<fg=blue>%s</>', $header));
            return $value;
        }, $rows);

        $table = new Table($this);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $style = new TableStyle();
        $style->setVerticalBorderChar('<fg=blue>|</>');
        $style->setHorizontalBorderChar('<fg=blue>-</>');
        $style->setCrossingChar('<fg=blue>+</>');
        $style->setCellHeaderFormat('%s');
        $table->setStyle($style);

        $table->render();
        $this->newLine();
    }

    /**
     * {@inheritdoc}
     */
    public function ask($question, $default = null, $validator = null)
    {
        $question = new Question($question, $default);
        $question->setValidator($validator);

        return $this->askQuestion($question);
    }

    /**
     * {@inheritdoc}
     */
    public function askHidden($question, $validator = null)
    {
        $question = new Question($question);

        $question->setHidden(true);
        $question->setValidator($validator);

        return $this->askQuestion($question);
    }

    /**
     * {@inheritdoc}
     */
    public function confirm($question, $default = true)
    {
        return $this->askQuestion(new ConfirmationQuestion($question, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function choice($question, array $choices, $default = null)
    {
        if (null !== $default) {
            $values = array_flip($choices);
            $default = $values[$default];
        }

        return $this->askQuestion(new ChoiceQuestion($question, $choices, $default));
    }

    /**
     * {@inheritdoc}
     */
    public function progressStart($max = 0)
    {
        $this->progressBar = $this->createProgressBar($max);
        $this->progressBar->start();
    }

    /**
     * {@inheritdoc}
     */
    public function progressAdvance($step = 1)
    {
        $this->getProgressBar()->advance($step);
    }

    /**
     * {@inheritdoc}
     */
    public function progressFinish()
    {
        $this->getProgressBar()->finish();
        $this->newLine(2);
        $this->progressBar = null;
    }

    /**
     * {@inheritdoc}
     */
    public function createProgressBar($max = 0)
    {
        $progressBar = parent::createProgressBar($max);

        if ('\\' !== DIRECTORY_SEPARATOR) {
            $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        }

        return $progressBar;
    }

    /**
     * @param Question $question
     *
     * @return string
     */
    public function askQuestion(Question $question)
    {
        if ($this->input->isInteractive()) {
            $this->autoPrependBlock();
        }

        if (!$this->questionHelper) {
            $this->questionHelper = new SymfonyQuestionHelper();
        }

        $answer = $this->questionHelper->ask($this->input, $this, $question);

        if ($this->input->isInteractive()) {
            $this->newLine();
            $this->outputBuffered->write("\n");
        }

        return $answer;
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        parent::writeln($messages, $type);
        $this->outputBuffered->writeln($this->reduceBuffer($messages), $type);
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        parent::write($messages, $newline, $type);
        $this->outputBuffered->write($this->reduceBuffer($messages), $newline, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function newLine($count = 1)
    {
        parent::newLine($count);
        $this->outputBuffered->write(str_repeat("\n", $count));
    }

    /**
     * @return ProgressBar
     */
    private function getProgressBar()
    {
        if (!$this->progressBar) {
            throw new RuntimeException('The ProgressBar is not started.');
        }

        return $this->progressBar;
    }

    private function getTerminalWidth()
    {
        $application = new Application();
        $dimensions = $application->getTerminalDimensions();

        return $dimensions[0] ?: $this->maxLineLength;
    }

    private function autoPrependBlock()
    {
        $chars = substr(str_replace(PHP_EOL, "\n", $this->outputBuffered->fetch()), -2);

        if (!isset($chars[0])) {
            return $this->newLine(); //empty history, so we should start with a new line.
        }
        //Prepend new line for each non LF chars (This means no blank line was output before)
        $this->newLine(2 - substr_count($chars, "\n"));
    }

    private function autoPrependText()
    {
        $fetched = $this->outputBuffered->fetch();
        //Prepend new line if last char isn't EOL:
        if ("\n" !== substr($fetched, -1)) {
            $this->newLine();
        }
    }

    private function reduceBuffer($messages)
    {
        // We need to know if the two last chars are PHP_EOL
        // Preserve the last 4 chars inserted (PHP_EOL on windows is two chars) in the history buffer
        return array_map(function ($value) {
            return substr($value, -4);
        }, array_merge(array($this->outputBuffered->fetch()), (array) $messages));
    }
}
