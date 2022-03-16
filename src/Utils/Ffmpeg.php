<?php

namespace Servdebt\SlimCore\Utils;
use Symfony\Component\Process\Process;

class Ffmpeg
{

    private $ffmpegPath;
    private $ffprobePath;

    public $error = "";


    public function setFfmpegPath(string $path) : self
    {
        $this->ffmpegPath = $path;

        return $this;
    }


    public function setFfprobePath(string $path) : self
    {
        $this->ffprobePath = $path;

        return $this;
    }


    public function getVideoFrame(string $videoPath, string $frameTime = "00:00:01.00") : ?string
    {
        $this->error = "";

        $process = new Process([
            $this->ffmpegPath,
            "-y",
            "-ss",
            $frameTime,
            "-i",
            $videoPath,
            "-vframes",
            "1",
            "-f",
            "image2pipe",
            "pipe:1"
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->error = $process->getErrorOutput();
            return null;
        }

        return $process->getOutput();
    }


    public function convertVideo(string $videoInPath, string $videoOutPath) : bool
    {
        $this->error = "";

        $process = new Process([
            $this->ffmpegPath,
            "-i",
            $videoInPath,
            "-c:v",
            "mpeg4",
            $videoOutPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->error = $process->getErrorOutput();
        }

        return $process->isSuccessful();
    }


    public function convertLiveStreamChunk($path, $filename, $outPath) : bool
    {
        $this->error = "";
        $cmd = $this->ffmpegPath.' -y -i "'.$path.DS.$filename.'" -c:v libx264 -preset veryfast -tune animation -crf 25 -c:a aac -bf 1 -keyint_min 25 -g 250 -sc_threshold 40 -hls_list_size 0 -hls_time 20 -hls_allow_cache 0 -hls_segment_type mpegts -hls_segment_filename "'.$outPath.DS.'stream'.DS.'hls_480p_'.$filename.'_"%"04d.ts" -s:v 854x480 -b:v 1200k -hls_base_url "stream/" -hls_flags delete_segments+split_by_time+omit_endlist+discont_start+independent_segments -strict -2 "'.$outPath.DS.'hls_480p.m3u8" -c:v libx264 -preset veryfast -tune animation -crf 25 -c:a aac -bf 1 -keyint_min 25 -g 250 -sc_threshold 40 -hls_list_size 0 -hls_time 20 -hls_allow_cache 0 -hls_segment_type mpegts -hls_segment_filename "'.$outPath.'stream'.DS.'hls_720p_'.$filename.'_"%"04d.ts" -s:v 1280x720 -b:v 2048k -hls_base_url "stream/" -hls_flags delete_segments+split_by_time+omit_endlist+discont_start+independent_segments -strict -2 "'.$outPath.DS.'hls_720p.m3u8"';

        shell_exec($cmd);
        return true;

//        $process = new Process(explode(" ", $cmd));
//        $process->run();
//
//        if (!$process->isSuccessful()) {
//            $this->error = $process->getErrorOutput();
//        }
//
//        return $process->isSuccessful();
    }


    public function getErrors() : string
    {
        return $this->error;
    }

}
