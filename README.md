# mkv-submerge

mkv-submerge is a small PHP command line tool which automatically finds and merges subtitles into Matroska files in a directory structure.

mkv-submerge was created to add subtitle support for video players which do not support external subtitle files.

## Prerequisites

To use mkv-submerge you need the following available from the command line:

* PHP 5.3+
* [Composer](http://getcomposer.org/)
* [periscope](https://code.google.com/p/periscope/) 
* [MKVToolNix](http://www.bunkus.org/videotools/mkvtoolnix/)

MKVToolNix can be installed by following [the download instructions](http://www.bunkus.org/videotools/mkvtoolnix/downloads.html).

Apparently [periscope](https://code.google.com/p/periscope/) cannot be installed using `pip` at the moment so the best bet is to download a recent version and compile it yourself.

## Usage

1. Download or `git clone https://github.com/kasperg/mkv-submerge.git` to a directory
2. From the directory run `composer install` to install external dependencies.
3. Run `./bin/mkv-submerge help merge` to see the available options


## Credits

* [periscope](https://code.google.com/p/periscope/) - Python module searching subtitles on the web.
* [MKVToolNix](http://www.bunkus.org/videotools/mkvtoolnix/) - Cross-platform tools for Matroska
* [Symfony Components](http://symfony.com/components)

