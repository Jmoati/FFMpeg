<?php

declare(strict_types=1);

namespace Jmoati\FFMpeg\Builder;

use Jmoati\FFMpeg\Data\Media;
use Jmoati\FFMpeg\Data\Output;

final class CommandBuilder
{
    private Media $media;
    private ?Output $output;
    private array $files = [];
    private array $params = [];
    private bool $dryRun;

    public function __construct(Media $media, Output $output = null, bool $dryRun = false)
    {
        $this->media = $media;
        $this->output = $output;
        $this->dryRun = $dryRun;
    }

    public function computeInputs(): array
    {
        $result = [];

        foreach ($this->media->streams() as $stream) {
            $result = array_merge($result, $stream->filters()->__toArray());

            if ('image2' == $stream->media()->format()->get('format_name')) {
                $result[] = '-loop';
                $result[] = 1;
            }

            $result[] = '-i';
            $result[] = $stream->get('media_filename');
        }

        if (!(null !== $this->output && isset($this->output->getParams()['maps']))) {
            foreach ($this->media->streams() as $index => $stream) {
                $result[] = '-map';
                $result[] = $index.':'.$stream->get('index');
            }
        }

        return $result;
    }

    public function computePasses(int $i, int $total, string $tmpDir): array
    {
        return 1 === $total ? [] : ['-pass', $i + 1, '-passlogfile', $tmpDir];
    }

    public function computeFormatFilters(): array
    {
        return $this->media->format()->filters()->__toArray();
    }

    public function computeParams(): array
    {
        if (null === $this->output) {
            return [];
        }

        if (false !== $this->media->streams()->videos()->first() && (null !== $this->output->getWidth() || null !== $this->output->getHeight())) {
            $originalWidth = $this->media->streams()->videos()->first()->get('width');
            $originalHeight = $this->media->streams()->videos()->first()->get('height');
            $originalRatio = $originalWidth / $originalHeight;

            if (null === $this->output->getWidth()) {
                $this->output->setWidth((int) round($this->output->getHeight() * $originalRatio));
            } else {
                $this->output->setHeight((int) round($this->output->getWidth() / $originalRatio));
            }

            if ($this->output->getUpscale()) {
                $this->output->addExtraParam('sws_flags', 'neighbor');

                if ('h264' == $this->output->getVideoCodec()) {
                    $this->output->addExtraParam('qp', 0);
                }
            } else {
                if (($this->output->getWidth() > $originalWidth) || ($this->output->getHeight() > $originalHeight)) {
                    $this->output->setWidth((int) $originalWidth);
                    $this->output->setHeight((int) $originalHeight);
                }
            }

            $this->output->addExtraParam('s', sprintf('%sx%s', $this->output->getWidth(), $this->output->getHeight()));
        }

        $result = [];
        $params = $this->output->getParams();

        if (true === $this->dryRun) {
            $params['acodec'] = 'copy';
            $params['vcodec'] = 'copy';
            $params['f'] = 'avi';
        }

        foreach ($params as $param => $value) {
            if ('maps' == $param && is_array($value)) {
                foreach ($value as $map) {
                    $result[] = '-map';
                    $result[] = $map;
                }
            } else {
                $result[] = "-$param";
                $result[] = $value;
            }
        }

        return $result;
    }
}
