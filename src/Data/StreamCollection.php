<?php

declare(strict_types=1);

namespace Jmoati\FFMpeg\Data;

use ArrayIterator;

final class StreamCollection implements \Countable, \IteratorAggregate, \ArrayAccess
{
    private array $streams = [];

    public function __construct(array $streams = [])
    {
        foreach ($streams as $stream) {
            if ($stream instanceof Stream) {
                $this->add($stream);
            } else {
                $this->streams[] = new Stream($stream);
            }
        }
    }

    /**
     * @return Stream|false
     */
    public function first()
    {
        return reset($this->streams);
    }

    public function add(Stream $stream): self
    {
        $newStream = clone $stream;
        $this->streams[] = $newStream;

        return $this;
    }

    public function remove(Stream $stream): self
    {
        for ($i = 0, $l = count($this->streams); $i < $l; ++$i) {
            if ($this->streams[$i] === $stream) {
                unset($this->streams[$i]);
                break;
            }
        }

        return $this;
    }

    public function videos(): self
    {
        return new static(array_filter(
            $this->streams,
            function (Stream $stream) {
                return $stream->isVideo();
            }
        ));
    }

    public function audios(): self
    {
        return new static(array_filter(
            $this->streams,
            function (Stream $stream) {
                return $stream->isAudio();
            }
        ));
    }

    public function data(): self
    {
        return new static(array_filter(
            $this->streams,
            function (Stream $stream) {
                return $stream->isData();
            }
        ));
    }

    public function count(): int
    {
        return count($this->streams);
    }

    /**
     * @return Stream[]
     */
    public function all(): array
    {
        return $this->streams;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->streams);
    }

    /**
     * @param string|int $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->streams[$offset]);
    }

    /**
     * @param string|int $offset
     */
    public function offsetGet($offset): Stream
    {
        return $this->streams[$offset];
    }

    /**
     * @param string|int $offset
     * @param Stream     $value
     *
     * @return StreamCollection
     */
    public function offsetSet($offset, $value): self
    {
        $this->streams[$offset] = $value;

        return $this;
    }

    /**
     * @param string|int $offset
     *
     * @return StreamCollection
     */
    public function offsetUnset($offset): self
    {
        unset($this->streams[$offset]);

        return $this;
    }

    public function setMedia(Media $media): self
    {
        foreach ($this->streams as $stream) {
            $stream->setMedia($media);
        }

        return $this;
    }
}
