<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
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
              'keep',
              null,
              InputOption::VALUE_OPTIONAL,
              'Keep original files.',
              true
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = $input->getOption('dir');
        $periscope = $input->getOption('periscope');
        $mkvMerge = $input->getOption('mkvmerge');
        $lang = $input->getOption('lang');
        $keep = $input->getOption('keep');

        $output->writeln('Searching for mkv files in ' . $dir);
        $finder = new Finder();
        $files = $finder->in($dir)->name('*.mkv')->notName('*.subs.mkv')->files();
        foreach ($files as $file) {
            $output->writeln('Processing ' . $file->getRealPath());

            $periscopeCommand = $periscope . ' ' . escapeshellarg($file->getRealPath()) . ' -l ' . $lang . ' --force';
            $periscopeProces = new \Symfony\Component\Process\Process($periscopeCommand);
            $periscopeProces->run();
            preg_match(
                '/Downloaded (\d+) subtitles/i',
                $periscopeProces->getErrorOutput(),
                $matches
            );

            $output->writeln($matches[1] . ' subtitle file(s) found');

            $mkvMergeCommand = $mkvMerge . ' -o ' . escapeshellarg(
                  $file->getPath() . '/' . $file->getBasename(
                      '.mkv'
                  ) . '.subs.mkv'
              ) . ' --default-track 0 --language 0:' . $lang . ' ' . escapeshellarg(
                  $file->getRealPath()
              );
            $mkvProcess = new Process($mkvMergeCommand);
            $mkvProcess->setTimeout(600);
            $mkvProcess->run(
                function ($type, $buffer) use($output) {
                    $output->write($buffer);
                }
            );
        }
    }
}

$application = new Application();
$application->add(new MergeCommand());
$application->run();
