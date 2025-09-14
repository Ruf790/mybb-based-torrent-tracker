<?php

class Timer
{
    public ?string $name = null;
    public ?float $start = null;
    public ?float $end = null;
    public ?float $totaltime = null;
    public ?string $formatted = null;

    public function __construct()
    {
        $this->add();
    }

    public function add(): void
    {
        if (!$this->start) {
            $this->start = microtime(true);
        }
    }

    public function getTime(): ?string
    {
        if ($this->end !== null) {
            return $this->formatted ?? null;
        }

        if ($this->start !== null) {
            $totaltime = microtime(true) - $this->start;
            return $this->format($totaltime);
        }

        return null;
    }

    public function stop(): string
    {
        if ($this->start !== null) {
            $this->end = microtime(true);
            $this->totaltime = $this->end - $this->start;
            $this->formatted = $this->format($this->totaltime);
            return $this->formatted;
        }

        return '';
    }

    public function remove(): void
    {
        $this->name = null;
        $this->start = null;
        $this->end = null;
        $this->totaltime = null;
        $this->formatted = null;
    }

    public function format(float $time): string
    {
        return number_format($time, 7);
    }
}
