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

        $this->write($output, '<comment>Searching for video files in ' . $dir . '</comment>', TRUE);
        $files = Finder::create()->in($dir)->name('/\.(avi|mkv)$/')->notName('*.subs.mkv')->sortByName()->files();
        foreach ($files as $file) {
            $this->write($output, '<comment>Processing ' . $file->getRealPath() . '</comment>', TRUE);

            $baseName = substr($file->getFilename(), 0, strrpos($file->getFilename(), '.'));

            $periscopeCommand = array(
              $periscope,
              escapeshellarg($file->getRealPath()),
              '-l ' . $lang,
              '--force');
            $periscopeCommand = implode(' ', $periscopeCommand);
            $this->write($output, $periscopeCommand, TRUE, OutputInterface::VERBOSITY_VERBOSE);
            $periscopeProces = new \Symfony\Component\Process\Process($periscopeCommand);
            $periscopeProces->run();

            $this->write($output, $periscopeProces->getErrorOutput(), TRUE, OutputInterface::VERBOSITY_VERBOSE);
            preg_match(
                '/Downloaded (\d+) subtitles/i',
                $periscopeProces->getErrorOutput(),
                $matches
            );
            $this->write($output, $matches[1] . ' subtitle file(s) found', TRUE);

            if ($matches[1] > 0) {
                $this->write($output, 'Merging subtitles into MKV file', TRUE);
                $mkvMergeCommand = array(
                  $mkvMerge,
                  '-o ' . escapeshellarg($file->getPath() . '/' . $baseName . '.subs.mkv'),
                  '--default-track 0',
                  '--language 0:' .$lang,
                  escapeshellarg($file->getPath() . '/' . $baseName . '.srt'),
                  escapeshellarg($file->getRealPath()),
                );
                $mkvMergeCommand = implode(' ', $mkvMergeCommand);
                $this->write($output, $mkvMergeCommand, TRUE, OutputInterface::VERBOSITY_VERBOSE);
                $mkvProcess = new Process($mkvMergeCommand);
                $mkvProcess->setTimeout(600);
                $command = $this;
                $mkvProcess->run(
                    function ($type, $buffer) use($command, $output) {
                      $verbose = (stripos($buffer, 'Progress') === 0) ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;
                      $command->write($output, $buffer, FALSE, $verbose);
                    }
                );
                $this->write($output, '', TRUE);

                $mergeSuccess = $mkvProcess->getExitCodeText() == 'OK';
                if ($mergeSuccess) {
                    $this->write($output, '<info>Merge complete for ' . $file->getRealPath() . '</info>', TRUE);
                } else {
                    $this->write($output, $mkvProcess->getOutput(), TRUE);
                    $this->write($output, '<error>Merge error for ' . $file->getRealPath() . ': ' . $mkvProcess->getExitCodeText() . '</error>', TRUE);
                }

                if (($cleanup == 'merge-success' && $mergeSuccess) ||
                    ($cleanup == 'merge-complete' && is_file($file->getPath() . '/' . $baseName . '.subs.mkv'))) {
                    $this->write($output, '<comment>Cleaning up original files</comment>', true);
                    unlink($file->getPath() . '/' . $baseName . '.srt');
                    unlink($file->getRealPath());
                }
            }
        }
    }

    public function write(OutputInterface $output, $messages, $newline = FALSE, $verbosity = OutputInterface::VERBOSITY_NORMAL, $type = OutputInterface::OUTPUT_NORMAL) {
        if ($output->getVerbosity() >= $verbosity) {
            $output->write($messages, $newline, $type);
        }
    }
}
