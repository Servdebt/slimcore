<?php

namespace Servdebt\SlimCore\Utils;
use Symfony\Component\Process\Process;

class Ffmpeg
{

    private string $ffmpegPath;
    private string $ffprobePath;

    public string $error = "";


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


    public function convertLiveStreamChunk($path, $filename, $params) : bool
    {
        $this->error = "";
        $cmd = $this->ffmpegPath.' -y -i "'.$path.DS.$filename.'" '.$params;
        shell_exec($cmd);

        return true;
    }


    public function getErrors() : string
    {
        return $this->error;
    }

}
