<?php

declare(strict_types=1);

namespace Jmoati\FFMpeg\Test;

use Jmoati\FFMpeg\Builder\CommandBuilder;
use Jmoati\FFMpeg\Data\Dimension;
use Jmoati\FFMpeg\Data\Format;
use Jmoati\FFMpeg\Data\Media;
use Jmoati\FFMpeg\Data\Output;
use Jmoati\FFMpeg\Data\StreamCollection;
use Jmoati\FFMpeg\Data\Timecode;
use Jmoati\FFMpeg\FFMpeg;
use Jmoati\FFMpeg\Filter\ClipFilter;
use Jmoati\FFMpeg\Filter\ResizeFilter;
use Jmoati\FFMpeg\Filter\RotationFilter;
use Jmoati\FFMpeg\Test\Progress\Progress;
use Symfony\Component\Filesystem\Filesystem;

class FFMpegTest extends FFAbstract
{
    public function testOpenFile()
    {
        $media = FFMpeg::openFile($this->filenameVideo);

        $this->assertTrue($media instanceof Media);
        $this->assertTrue($media->streams() instanceof StreamCollection);
        $this->assertTrue($media->format() instanceof Format);

        $streams = $media->streams();

        $this->assertTrue($media instanceof Media);
        $this->assertTrue($media->streams() instanceof StreamCollection);
        $this->assertTrue($media->format() instanceof Format);

        $this->assertEquals(2, $streams->count());
        $this->assertTrue($streams->audios()->first()->isAudio());
        $this->assertTrue($streams->videos()->first()->isVideo());

        $this->assertFalse($streams->audios()->first()->isData());
        $this->assertFalse($streams->audios()->first()->isImage());
    }

    public function testCreateFile()
    {
        $media = FFMpeg::createFile();
        $streams = $media->streams();

        $this->assertTrue($media instanceof Media);
        $this->assertTrue($media->streams() instanceof StreamCollection);
        $this->assertTrue($media->format() instanceof Format);

        $this->assertEquals(0, $streams->count());

        $this->assertFalse($streams->audios()->first());
        $this->assertFalse($streams->videos()->first());
    }

    public function testEncodage()
    {
        $video = FFMpeg::openFile($this->filenameVideo);
        $audio = FFMpeg::openFile($this->filenameAudio);

        $this->assertTrue($video instanceof Media);
        $this->assertTrue($audio instanceof Media);

        $new = FFMpeg::createFile();

        $this->assertTrue($new instanceof Media);
        $this->assertEquals(0, $new->streams()->count());

        $new->streams()
                ->add($video->streams()->videos()->first())
                ->add($audio->streams()->audios()->first());

        $this->assertEquals(2, $new->streams()->count());

        $new->format()->filters()->add(new ClipFilter(Timecode::createFromSeconds(16)));

        $this->assertEquals(1, $new->format()->filters()->count());

        $output = new Output();
        $commandBuilder = new CommandBuilder($new, $output);

        $inputs = $commandBuilder->computeInputs();
        $this->assertTrue(is_numeric(strpos($inputs, sprintf('-i "%s"', $this->filenameAudio))));
        $this->assertTrue(is_numeric(strpos($inputs, sprintf('-i "%s"', $this->filenameVideo))));

        $filters = $commandBuilder->computeFormatFilters();
        $this->assertTrue(is_numeric(strpos($filters, sprintf('-t %s', Timecode::createFromSeconds(16)))));

        $result = $new->save($this->filenameDestination, $output);
        $this->assertTrue(file_exists($this->filenameDestination));
        $this->assertTrue($result);

        $check = FFMpeg::openFile($this->filenameDestination);
        $videoStream = $check->streams()->videos()->first();

        $this->assertEquals(16, floor($check->format()->get('duration')));
        $this->assertGreaterThan($videoStream->get('height'), $videoStream->get('width'));
    }

    public function testFilter()
    {
        $video = FFMpeg::openFile($this->filenameVideo);

        $dimension = new Dimension(320, 240);

        $rotationFilter = new RotationFilter(RotationFilter::ROTATION_90);
        $resizeFilter = new ResizeFilter($dimension);

        $video->format()->filters()->add(new ClipFilter(Timecode::createFromSeconds(10), Timecode::createFromSeconds(5)));
        $video->format()->filters()->add($rotationFilter);
        $video->format()->filters()->add($resizeFilter);

        $output = Output::create()
            ->setVideoKiloBitrate(1200)
            ->setHeight((int)($video->streams()->videos()->first()->get('height') * 2))
            ->setUpscale(true)
            ->setPasses(2);

        $commandBuilder = new CommandBuilder($video, $output);

        $inputs = $commandBuilder->computeInputs();
        $this->assertTrue(is_numeric(strpos($inputs, sprintf('-i "%s"', $this->filenameVideo))));

        $filters = $commandBuilder->computeFormatFilters();

        $this->assertTrue(is_numeric(strpos($filters, sprintf('-ss %s', Timecode::createFromSeconds(5)))));
        $this->assertTrue(is_numeric(strpos($filters, sprintf('-t %s', Timecode::createFromSeconds(10)))));
        $this->assertTrue(is_numeric(strpos($filters, 'transpose=1')));

        $result = $video->save($this->filenameDestination, $output);
        $this->assertTrue(file_exists($this->filenameDestination));
        $this->assertTrue($result);

        $check = FFMpeg::openFile($this->filenameDestination);

        $this->assertEquals(10, floor($check->format()->get('duration')));
        $this->assertEquals(3, $video->format()->filters()->count());

        $video->format()->filters()->clear();

        $this->assertEquals(0, $video->format()->filters()->count());
    }

    public function testFrame()
    {
        $timecode = Timecode::createFromFrame(2, 24);

        $video = FFMpeg::openFile($this->filenameVideo);
        $frame = $video->frame($timecode);
        $result = $frame->save($this->filenameFrameDestination);
        $this->assertTrue($result);

        $this->assertTrue(file_exists($this->filenameFrameDestination));

        (new Filesystem())->remove($this->filenameFrameDestination);

        $result = $frame->save($this->filenameFrameDestination, true);
        $this->assertTrue($result);

        $this->assertTrue(file_exists($this->filenameFrameDestination));
    }

    public function testProgress()
    {
        $progress = new Progress();
        $video = FFMpeg::openFile($this->filenameVideo);
        $video->format()->filters()->add(new ClipFilter(Timecode::createFromSeconds(1)));

        $result = $video->save($this->filenameDestination, Output::create(), $progress);
        $this->assertTrue(file_exists($this->filenameDestination));
        $this->assertTrue($result);
    }

    public function setUp()
    {
        $this->tearDown();
    }

    public function tearDown()
    {
        (new Filesystem())->remove($this->filenameDestination);
        (new Filesystem())->remove($this->filenameFrameDestination);
    }
}
