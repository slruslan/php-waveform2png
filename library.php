<?php
/**
 * Class Waveform2Png
 * @link https://github.com/slruslan/php-waveform2png/
 * @author Ruslan Slinkov (slruslan) <slinkov@podari-track.ru>
 * @author Andrew Freiday (afreiday) <andrewfreiday@gmail.com>
 * @copyright 2015 Ruslan Slinkov
 * @copyright 2011 Andrew Freiday
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */
class Waveform2Png {

    /**
     * Width and height of output image
     * @var int
     */
    private $width, $height;

    /**
     * HTML code for foreground and background colors
     * @var string
     */
    private $foreground, $background;

    /**
     * Defines the level of waveform detail.
     * The larger number means less detail.
     * The lower number means longer processing time.
     * @var int
     */
    private $detail;

    /**
     * Defines if stereo waveform mode is enabled
     * If true draws waveforms for each channel
     * If false draws single waveform
     * @var bool
     */
    private $stereo;

    /**
     * Contains loaded file path
     * @var string
     */
    private $file;

    /**
     * Output image resource
     * @var resource
     */
    private $img;

    /**
     * List of colors for pars of graph
     * @var array
     */
    private $colors;

    /**
     * Defines type of graph.
     * @var string
     */
    private $type;

    public function __construct() {
        // Default settings
        $this->width = 500;
        $this->height = 100;
        $this->foreground = "#d1d1d1";
        $this->background = "";
        $this->detail = 100;
        $this->stereo = false;
        $this->file = null;
        $this->img = false;
        $this->colors = [];
        $this->type = 'waveform';

        // Change max script execution time
        ini_set("max_execution_time", "30000");
    }

    /**
     * Sets width param
     * @param int|width $width width of output image
     */
    public function setWidth($width = 500) {
        $this->width = $width;
    }

    /**
     * Sets height param
     * @param height|int $height height of output image
     */
    public function setHeight($height = 100) {
        $this->height = $height;
    }

    /**
     * Sets foreground color
     * @param foreground|string $foreground foreground HEX color code
     */
    public function setForeground($foreground = '#d1d1d1') {
        $this->foreground = $foreground;
    }

    /**
     * Sets background color
     * Leave empty to make it transparent
     * @param background|string $background background HEX color code
     */
    public function setBackground($background = '') {
        $this->background = $background;
    }

    /**
     * Sets details level
     * @param detail|int $detail detail level
     */
    public function setDetail($detail = 100) {
        $this->detail = $detail;
    }

    /**
     * Sets stereo mode
     * @param bool|false $stereo
     */
    public function setStereo($stereo = false) {
        $stereo = (bool)$stereo;

        $this->stereo = $stereo;
    }

    /**
     * Clears colors array
     */
    public function clearColors() {
        $this->colors = [];
    }

    /**
     * Changes graph type
     * @param string $type Type of graph
     * @throws Exception Type not found exception
     */
    public function setType($type = 'waveform') {
        $availableTypes = ['waveform', 'bars'];

        if(!in_array($type, $availableTypes))
            throw new Exception(sprintf("Type %s not found.", $type));

        $this->type = $type;
    }

    /**
     * Adds new color interval
     * @param $timeMin Minimal time
     * @param $timeMax Maximal time
     * @param $color Foreground color
     */
    public function addColor($timeMin, $timeMax, $color) {
        $this->colors[] = [
            'min' => $timeMin,
            'max' => $timeMax,
            'color' => $color
        ];
    }

    /**
     * Loads file
     * @param $file file path
     * @throws Exception File not found exception
     */
    public function loadFile($file) {
        if (!file_exists($file))
            throw new Exception(sprintf('The file "%s" does not exist', $file));

        $availableTypes = ['audio/mpeg', 'application/octet-stream'];

        if(!in_array(mime_content_type($file), $availableTypes))
            throw new Exception(sprintf('Invalid file type %s, expected to be audio/mpeg', mime_content_type($file)));

        $fileWithoutMP3 = str_replace(".mp3", "", $file);

        copy($file, $fileWithoutMP3);

        $this->file = $fileWithoutMP3;
    }

    /**
     * Processes generated wavs and draws graph
     * @throws Exception File not loaded exception
     */
    public function process() {
        if($this->file == null)
            throw new Exception("File not loaded");

        $wavList = $this->generateWavFiles();

        $this->img = false;
        list($r, $g, $b) = $this->htmlToRGB($this->foreground);

        for($wav = 1; $wav <= sizeof($wavList); $wav++) {
            $filename = $wavList[$wav - 1];

            $handle = fopen($filename, "r");

            /**
             * Read WAV header byte by byte
             * @link http://www.topherlee.com/software/pcm-tut-wavformat.html
             */
            fseek($handle, 20); // Seek first 20 bytes as they're unneeded
            // Read next 16 bytes and parse them
            $rawHead = fread($handle, 16);
            $header = unpack('vtype/vchannels/Vsamplerate/Vbytespersec/valignment/vbits', $rawHead);
            // Seek next 8 bytes as they're unneeded
            fseek($handle, 8);

            // Calculate WAV bitrate
            $peek = $header['bits'];
            $byte = $peek / 8;

            // Check whether a mono or stereo wav
            $channel = hexdec(substr($header['channels'], 0, 2));
            $ratio = ($channel == 2 ? 40 : 80);

            // start putting together the initial canvas
            // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
            $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
            $data_point = 0;

            // now that we have the data_size for a single channel (they both will be the same)
            // we can initialize our image canvas
            if (!$this->img) {
                // create original image width based on amount of detail
                // each waveform to be processed with be $height high, but will be condensed
                // and resized later (if specified)
                $this->img = imagecreatetruecolor($data_size / $this->detail, $this->height * sizeof($wavList));

                // fill background of image
                if ($this->background == "") {
                    // transparent background specified
                    imagesavealpha($this->img, true);
                    $transparentColor = imagecolorallocatealpha($this->img, 0, 0, 0, 127);
                    imagefill($this->img, 0, 0, $transparentColor);
                } else {
                    list($br, $bg, $bb) = $this->htmlToRGB($this->background);
                    imagefilledrectangle($this->img, 0, 0, (int)($data_size / $this->detail), $this->height * sizeof($wavList), imagecolorallocate($this->img, $br, $bg, $bb));
                }
            }

            $drawn = 0;
            $pointstotal = 0;
            $bytestotal = 0;

            while (!feof($handle) && $data_point < $data_size) {
                $pointstotal++;

                // Do we have enough details?
                if($data_point++ % $this->detail != 0) {
                    fseek($handle, $ratio + $byte, SEEK_CUR);
                }
                else {
                    $bytes = [];

                    // Get number of bytes depending on bitrate
                    for ($i = 0; $i < $byte; $i++) {
                        $bytes[$i] = fgetc($handle);
                    }

                    switch ($byte) {
                        // Get value for 8-bit wav
                        case 1:
                            $data = $this->findValues($bytes[0], $bytes[1]);
                            break;
                        // Get value for 16-bit wav
                        case 2:
                            if (ord($bytes[1]) & 128)
                                $temp = 0;
                            else
                                $temp = 128;
                            $temp = chr((ord($bytes[1]) & 127) + $temp);
                            $data = floor($this->findValues($bytes[0], $temp) / 256);
                            break;
                    }

                    $drawn++;

                    // Skip bytes for memory optimization
                    fseek($handle, $ratio, SEEK_CUR);

                    // Draw this data point
                    // Relative value based on height of image being generated
                    // Data values can range between 0 and 255
                    $v = (int)($data / 255 * $this->height);

                    $time = $this->getCurrentTime(  ftell($handle),
                                                    $header['bits'],
                                                    $header['channels'],
                                                    $header['samplerate']);

                    list($r, $g, $b) = $this->htmlToRGB($this->foreground);

                    if(!empty($this->colors)) {
                        foreach($this->colors as $color) {
                            if($time >= $color['min'] && $time <= $color['max'])
                                list($r, $g, $b) = $this->htmlToRGB($color['color']);
                        }
                    }

                    switch($this->type) {
                        case 'bars':
                            // draw the line on the image using the $v value and centering it vertically on the canvas
                            imageline(
                                $this->img,
                                // x1
                                (int)($data_point / $this->detail) + $drawn,
                                // y1: height of the image minus $v as a percentage of the height for the wave amplitude
                                $this->height,
                                // x2
                                (int)($data_point / $this->detail) + $drawn,
                                // y2: same as y1, but from the bottom of the image
                                (($this->height * $wav - ($this->height - $v)) > 5) ? $this->height * $wav - ($this->height - $v) : 5,
                                imagecolorallocate($this->img, $r, $g, $b)
                            );
                        break;

                        case 'waveform':
                            // draw the line on the image using the $v value and centering it vertically on the canvas
                            imageline(
                                $this->img,
                                // x1
                                (int)($data_point / $this->detail),
                                // y1: height of the image minus $v as a percentage of the height for the wave amplitude
                                $this->height * $wav - $v,
                                // x2
                                (int)($data_point / $this->detail),
                                // y2: same as y1, but from the bottom of the image
                                $this->height * $wav - ($this->height - $v),
                                imagecolorallocate($this->img, $r, $g, $b)
                            );
                        break;
                    }
                }
            }

            fclose($handle);
        }

        $this->resampleImage();
        $this->unlinkFiles();
    }

    /**
     * Outputs image
     * @return bool
     */
    public function outputImage() {
        header("Content-Type: image/png");

        return imagepng($this->img);
    }

    /**
     * Saves image on disk
     * @param File|string $filename File path
     * @return string File path
     */
    public function saveImage($filename = '') {
        if($filename == '')
            $filename = sprintf("%s.png", uniqid(rand(), true));

        imagepng($this->img, $filename);

        return $filename;
    }

    /**
     * Resamples image usings previously set width and height
     * @return bool
     */
    private function resampleImage() {
        // Resample the image to the proportions defined in the form
        $resampledImage = imagecreatetruecolor($this->width, $this->height);

        // Save alpha from original image
        imagesavealpha($resampledImage, true);
        imagealphablending($resampledImage, false);

        // Copy to resized
        imagecopyresampled( $resampledImage,
            $this->img,
            0, 0, 0, 0,
            $this->width,
            $this->height,
            imagesx($this->img),
            imagesy($this->img));

        // Change original image
        $this->img = $resampledImage;

        return true;
    }

    /**
     * Generates WAV files from source file with LAME encoder.
     * @return array List of WAV files to process
     */
    private function generateWavFiles() {
        $filename = $this->file;
        $result = [];

        copy($filename, sprintf("%s_o.mp3", $filename));

        if ($this->stereo) {
            // scale right channel down (a scale of 0 does not work)
            exec("lame {$filename}_o.mp3 --scale-r 0.1 -m m -S -f -b 16 --resample 8 {$filename}_2.mp3 && lame -S --decode {$filename}_2.mp3 {$filename}_l.wav");
            // same as above, left channel
            exec("lame {$filename}_o.mp3 --scale-l 0.1 -m m -S -f -b 16 --resample 8 {$filename}_2.mp3 && lame -S --decode {$filename}_2.mp3 {$filename}_r.wav");

            $result[] = "{$filename}_l.wav";
            $result[] = "{$filename}_r.wav";
        } else {
            exec("lame {$filename}_o.mp3 -m m -S -f -b 16 --resample 8 {$filename}_2.mp3 && lame -S --decode {$filename}_2.mp3 {$filename}.wav");

            $result[] = "{$filename}.wav";
        }

        return $result;
    }

    /**
     * Removes all temporary files
     */
    private function unlinkFiles() {
        unlink(sprintf("%s_o.mp3", $this->file));
        unlink(sprintf("%s_2.mp3", $this->file));
        unlink($this->file);

        if(!$this->stereo) {
            unlink(sprintf("%s.wav", $this->file));
        }
        else {
            unlink(spritf("%s_l.wav", $this->file));
            unlink(spritf("%s_r.wav", $this->file));
        }
    }

    /**
     * Returns current audio time
     * @param $bytesRead Count of read bytes
     * @param $bits Bits per sample
     * @param $channels Number of channels
     * @param $sampleRate Sample rate
     * @return float|int
     */
    private function getCurrentTime($bytesRead, $bits, $channels, $sampleRate) {
        if($bits == 0 || $channels == 0 || $sampleRate == 0)
            return 0;

        $bytesRead = floor($bytesRead - 44);

        return ($bytesRead/($bits/8)/$channels/$sampleRate);
    }
  /**
   * Converts HTML HEX color to RGB
   * @param $input HEX color code
   * @return array array with R, G, B values
   */
    private function htmlToRGB($input) {
        $input = str_replace("#", "", $input);

        return [
            hexdec(substr($input, 0, 2)),
            hexdec(substr($input, 2, 2)),
            hexdec(substr($input, 4, 2))
        ];
    }

    /**
     * Finds values of 2 bytes
     * ! Original purpose of this method is unknown for me
     * @param $byte1 First byte
     * @param $byte2 Second byte
     * @return number Final value
     */
    private function findValues($byte1, $byte2) {
        $byte1 = hexdec(bin2hex($byte1));
        $byte2 = hexdec(bin2hex($byte2));

        return ($byte1 + ($byte2 * 256));
    }

}
  
