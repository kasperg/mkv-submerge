<?php

namespace MkvSubmerge;

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Finder\Finder;
use \Symfony\Component\Process\Process;

class MergeCommand extends Command
{

    protected function configure()
    {
        $this
          ->setName('mkvsubmerge:merge')
          ->setAliases(array('merge'))
          ->setDescription('Find and merge subtitles into MKV video files')
          ->addOption(
              'dir',
              null,
              InputOption::VALUE_OPTIONAL,
              'Base directory when searching for mkv files.',
              getcwd()
          )
          ->addOption(
              'mkvmerge',
              null,
              InputOption::VALUE_OPTIONAL,
              'Path to mkvmerge executable.',
              'mkvmerge'
          )
          ->addOption(
              'periscope',
              null,
              InputOption::VALUE_OPTIONAL,
              'Path to periscope executable.',
              'periscope'
          )
          ->addOption(
              'lang',
              null,
              InputOption::VALUE_OPTIONAL,
              'Language to retrieve subtitles in.',
              'en'
          )
          ->addOption(
              'cleanup',
              null,
              InputOption::VALUE_OPTIONAL,
              'Strategy for deleting original files. Supported options:' . PHP_EOL .
              '- none: Never not delete original files' . PHP_EOL .
              '- merge-complete: Delete if the merge process leaves an MKV file' . PHP_EOL .
              '- merge-success: Delete if the merge process completed without errors or warnings',
              'none'
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = $input->getOption('dir');
        $periscope = $input->getOption('periscope');
        $mkvMerge = $input->getOption('mkvmerge');
        $lang = $input->getOption('lang');
        $cleanup = $input->getOption('cleanup');

        $this->write($output, '<comment>Searching for video files in ' . $dir . '</comment>', true);
        $files = Finder::create()->in($dir)->name('/\.(avi|mkv)$/')->notName('*.subs.mkv')->sortByName()->files();
        foreach ($files as $file) {
            $this->write($output, '<comment>Processing ' . $file->getRealPath() . '</comment>', true);

            // Having the base name available is useful when determining other
            // file names as they should be derivatives of this.
            $baseName = substr($file->getFilename(), 0, strrpos($file->getFilename(), '.'));

            // Build the command which will try to download subtitles for the
            // file. Use --force so that we always retrieve a fresh version.
            $periscopeCommand = array(
                $periscope,
                escapeshellarg($file->getRealPath()),
                '-l ' . $lang,
                '--force'
            );
            $periscopeCommand = implode(' ', $periscopeCommand);
            $this->write($output, $periscopeCommand, true, OutputInterface::VERBOSITY_VERBOSE);
            $periscopeProces = new \Symfony\Component\Process\Process($periscopeCommand);
            $periscopeProces->run();

            // Periscope always seems to return output with an error return type
            // so grab the output from there.
            $this->write($output, $periscopeProces->getErrorOutput(), true, OutputInterface::VERBOSITY_VERBOSE);
            // To determine whether any subtitles were downloaded we search
            // through the output.
            preg_match(
                '/Downloaded (\d+) subtitles/i',
                $periscopeProces->getErrorOutput(),
                $matches
            );
            $this->write($output, $matches[1] . ' subtitle file(s) found', true);

            // If we found subtitles then merge them in.
            if ($matches[1] > 0) {
                $this->write($output, 'Merging subtitles into MKV file', true);
                // Build the command which will merge the movie and subtitle
                // files into one MKV file.
                $mkvMergeCommand = array(
                    $mkvMerge,
                    '-o ' . escapeshellarg($file->getPath() . '/' . $baseName . '.subs.mkv'),
                    '--default-track 0',
                    '--language 0:' .$lang,
                    escapeshellarg($file->getPath() . '/' . $baseName . '.srt'),
                    escapeshellarg($file->getRealPath()),
                );
                $mkvMergeCommand = implode(' ', $mkvMergeCommand);
                $this->write($output, $mkvMergeCommand, true, OutputInterface::VERBOSITY_VERBOSE);
                $mkvProcess = new Process($mkvMergeCommand);
                // Set timeout for the merge process to 10 minutes. It should
                // not take so long but we want to be safe.
                $mkvProcess->setTimeout(600);
                // In PHP 5.3 we cannot rename closure variables so we reference
                // $this as $command to allow the closure to generate some
                // output.
                $command = $this;
                $mkvProcess->run(
                    function ($type, $buffer) use($command, $output) {
                      // If the output starts with Progress it contains a
                      // progress indicator which we want to show. Otherwise
                      // the output is primarily for debugging.
                      $verbose = (stripos($buffer, 'Progress') === 0) ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;
                      $command->write($output, $buffer, false, $verbose);
                    }
                );
                // We did not include a linebreak above to do it here such that
                // we have proper separation of lines.
                $this->write($output, '', true);

                // Determine whether the merge went well or not.
                $mergeSuccess = $mkvProcess->getExitCodeText() == 'OK';
                if ($mergeSuccess) {
                    $this->write($output, '<info>Merge complete for ' . $file->getRealPath() . '</info>', true);
                } else {
                    $this->write($output, $mkvProcess->getOutput(), true);
                    $this->write($output, '<error>Merge error for ' . $file->getRealPath() . ': ' . $mkvProcess->getExitCodeText() . '</error>', true);
                }

                if (($cleanup == 'merge-success' && $mergeSuccess) ||
                    ($cleanup == 'merge-complete' && is_file($file->getPath() . '/' . $baseName . '.subs.mkv'))) {
                    // If cleanup criteria are met then delete subtitle file
                    // downloaded by periscope and the original movie file.
                    $this->write($output, '<comment>Cleaning up original files</comment>', true);
                    unlink($file->getPath() . '/' . $baseName . '.srt');
                    unlink($file->getRealPath());
                }
            }
        }
    }

    /**
     * Helper method which supports outputting messages with different verbosity
     * levels.
     *
     * The method is public because we want to call it from closures.
     *
     * @param OutputInterface $output   The interface to write the messages to.
     * @param string|array $messages    The message as an array of lines or a single string
     * @param bool $newline             Whether to add a newline at the end of the message(s)
     * @param int $verbosity            The verbosity level for the messages.
     * @param int $type                 The message type.
     */
    public function write(OutputInterface $output, $messages, $newline = false, $verbosity = OutputInterface::VERBOSITY_NORMAL, $type = OutputInterface::OUTPUT_NORMAL) {
        // Only output the messages if their verbosity is equal to or higher
        // than what has been requested.
        if ($output->getVerbosity() >= $verbosity) {
            $output->write($messages, $newline, $type);
        }
    }
}
