<?php

namespace Dashed\DashedLivechat\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Dashed\DashedLivechat\Models\ChatOpeningHour;

class OpeningHoursService
{
    protected function tz(): string
    {
        return config('dashed-livechat.timezone', config('app.timezone'));
    }

    public function isOpen(string $siteId, ?CarbonInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at->toDateTime())->setTimezone($this->tz()) : now($this->tz());

        $row = $this->ruleFor($siteId, $at);
        if (! $row || $row->is_closed || ! $row->opens_at || ! $row->closes_at) {
            return false;
        }

        $time = $at->format('H:i:s');

        return $time >= $this->time($row->opens_at) && $time < $this->time($row->closes_at);
    }

    public function todaysHours(string $siteId, ?CarbonInterface $at = null): ?array
    {
        $at = $at ? Carbon::instance($at->toDateTime())->setTimezone($this->tz()) : now($this->tz());
        $row = $this->ruleFor($siteId, $at);
        if (! $row || $row->is_closed || ! $row->opens_at || ! $row->closes_at) {
            return null;
        }

        return ['opens_at' => substr($this->time($row->opens_at), 0, 5), 'closes_at' => substr($this->time($row->closes_at), 0, 5)];
    }

    public function nextOpening(string $siteId, ?CarbonInterface $from = null): ?Carbon
    {
        $cursor = ($from ? Carbon::instance($from->toDateTime())->setTimezone($this->tz()) : now($this->tz()))->copy();

        // Scan maximaal 14 dagen vooruit naar het eerstvolgende open moment.
        for ($i = 0; $i < 14 * 24 * 4; $i++) { // kwartier-stappen, 14 dagen
            if ($this->isOpen($siteId, $cursor)) {
                return $cursor->copy();
            }
            $cursor->addMinutes(15);
        }

        return null;
    }

    protected function ruleFor(string $siteId, CarbonInterface $at): ?ChatOpeningHour
    {
        $atInTz = Carbon::instance($at->toDateTime())->setTimezone($this->tz());

        // Uitzondering op datum wint.
        $exception = ChatOpeningHour::where('site_id', $siteId)
            ->whereDate('date', $atInTz->toDateString())
            ->first();
        if ($exception) {
            return $exception;
        }

        return ChatOpeningHour::where('site_id', $siteId)
            ->whereNull('date')
            ->where('day_of_week', $atInTz->dayOfWeek)
            ->first();
    }

    protected function time($value): string
    {
        // opens_at/closes_at kan 'HH:MM' of 'HH:MM:SS' zijn.
        $value = (string) $value;

        return strlen($value) === 5 ? $value . ':00' : $value;
    }
}
