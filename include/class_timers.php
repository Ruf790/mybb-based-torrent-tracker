<?php
declare(strict_types=1);

class Timer
{
    public ?string $name      = null;
    private ?float $start     = null;
    private ?float $end       = null;
    public  ?float $totaltime = null;  // public — используется в footer.php
    private ?string $formatted = null;

    public function __construct()
    {
        $this->start = microtime(true);
    }

    // Текущее время с момента старта (не останавливает таймер)
    public function getTime(): string
    {
        if ($this->start === null) return '';

        $time = $this->end !== null
            ? $this->totaltime
            : microtime(true) - $this->start;

        return $this->format((float)$time);
    }

    // Остановить и вернуть итоговое время
    public function stop(): string
    {
        if ($this->start === null) return '';
        if ($this->end !== null)   return $this->formatted ?? '';

        $this->end       = microtime(true);
        $this->totaltime = $this->end - $this->start;
        $this->formatted = $this->format($this->totaltime);

        return $this->formatted;
    }

    public function reset(): void
    {
        $this->start     = microtime(true);
        $this->end       = null;
        $this->totaltime = null;
        $this->formatted = null;
    }

    private function format(float $time): string
    {
        return number_format($time, 7);
    }
}