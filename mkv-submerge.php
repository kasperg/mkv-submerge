<?php

require 'vendor/autoload.php';

use \Symfony\Component\Finder\Finder;
use \Symfony\Component\Process\Process;
use \Commando\Command;

$merge = new Command();
$merge->option('dir')
  ->describedAs('Base directory when searching for mkv files. Default: Current directory.');
$merge->option('mkvmerge')
  ->describedAs('Path to mkvmerge executable. Default: mkvmerge');
$merge->option('periscope')
  ->describedAs('Path to periscope executable. Default: periscope');
$merge->option('lang')
  ->describedAs('Language to retrieve subtitles in. Default: en');
$merge->option('keep')
  ->describedAs('Keep original files. Default: true')
  ->boolean();

// Extract values or set default.
$dir = (!empty($merge['dir'])) ? realpath($merge['dir']) : getcwd();
$periscope = (!empty($merge['periscope'])) ? $merge['periscope'] : 'periscope';
$mkvMerge = (!empty($merge['mkvmerge'])) ? $merge['mkvmerge'] : 'mkvmerge';
$lang = (!empty($merge['lang'])) ? $merge['lang'] : 'en';
$keep = (!isset($merge['keep'])) ? $merge['keep'] : true;

echo 'OUT > Searching for mkv files in ' . $dir . PHP_EOL;
$finder = new Finder();
$files = $finder->in($dir)->name('*.mkv')->notName('*.subs.mkv')->files();
foreach ($files as $file) {
    echo 'OUT > Processing ' . $file->getRealPath() . PHP_EOL;

    $periscopeCommand = $periscope . ' ' . escapeshellarg($file->getRealPath()) . ' -l ' . $lang . ' --force';
    $periscopeProces = new \Symfony\Component\Process\Process($periscopeCommand);
    $periscopeProces->run();
    preg_match('/Downloaded (\d+) subtitles/i', $periscopeProces->getErrorOutput(), $matches);

    echo 'OUT > ' . $matches[1] . ' subtitle file(s) found' . PHP_EOL;
    echo PHP_EOL;

    $mkvMergeCommand = $mkvMerge . ' -o ' . escapeshellarg($file->getPath() . '/' . $file->getBasename('.mkv') . '.subs.mkv') . ' --default-track 0 --language 0:' . $lang . ' ' . escapeshellarg($file->getRealPath());
    $mkvProcess = new Process($mkvMergeCommand);
    $mkvProcess->setTimeout(600);
    $mkvProcess->run(
        function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo 'OUT > ' . $buffer;
            }
        }
    );
    break;
}
