<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use DateTimeImmutable;
use DateTimeInterface;

final class RelativeDateFormatter
{
    public static function format(DateTimeInterface $target, ?DateTimeInterface $reference = null): string
    {
        $ref = $reference instanceof DateTimeInterface
            ? self::toImmutable($reference)
            : DateTimeFactory::now();

        $currentTz = $ref->getTimezone();
        $date = self::toImmutable($target)->setTimezone($currentTz);

        $delta = $date->getTimestamp() - $ref->getTimestamp();
        $abs = abs($delta);

        if ($abs < 5) {
            return $delta >= 0 ? 'za okamžik' : 'právě teď';
        }

        $isFuture = $delta > 0;

        $units = [
            ['threshold' => 60,        'unit' => 'seconds', 'divisor' => 1],
            ['threshold' => 3600,      'unit' => 'minutes', 'divisor' => 60],
            ['threshold' => 86400,     'unit' => 'hours',   'divisor' => 3600],
            ['threshold' => 604800,    'unit' => 'days',    'divisor' => 86400],
            ['threshold' => 2592000,   'unit' => 'weeks',   'divisor' => 604800],
            ['threshold' => 31536000,  'unit' => 'months',  'divisor' => 2592000],
        ];

        $unitKey = 'years';
        $count = max(1, (int)round($abs / 31536000));

        foreach ($units as $info) {
            if ($abs < $info['threshold']) {
                $unitKey = $info['unit'];
                $count = max(1, (int)round($abs / $info['divisor']));
                break;
            }
        }

        $forms = $isFuture ? self::futureForms($unitKey) : self::pastForms($unitKey);
        $phrase = self::formatCount($count, $forms);

        return $isFuture
            ? 'za ' . $phrase
            : 'před ' . $phrase;
    }

    private static function toImmutable(DateTimeInterface $dt): DateTimeImmutable
    {
        return $dt instanceof DateTimeImmutable
            ? $dt
            : DateTimeImmutable::createFromInterface($dt);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function pastForms(string $unit): array
    {
        return match ($unit) {
            'seconds' => ['1 sekundou', '%d sekundami', '%d sekundami'],
            'minutes' => ['1 minutou', '%d minutami', '%d minutami'],
            'hours'   => ['1 hodinou', '%d hodinami', '%d hodinami'],
            'days'    => ['1 dnem', '%d dny', '%d dny'],
            'weeks'   => ['1 týdnem', '%d týdny', '%d týdny'],
            'months'  => ['1 měsícem', '%d měsíci', '%d měsíci'],
            default   => ['1 rokem', '%d roky', '%d lety'],
        };
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function futureForms(string $unit): array
    {
        return match ($unit) {
            'seconds' => ['1 sekundu', '%d sekundy', '%d sekund'],
            'minutes' => ['1 minutu', '%d minuty', '%d minut'],
            'hours'   => ['1 hodinu', '%d hodiny', '%d hodin'],
            'days'    => ['1 den', '%d dny', '%d dnů'],
            'weeks'   => ['1 týden', '%d týdny', '%d týdnů'],
            'months'  => ['1 měsíc', '%d měsíce', '%d měsíců'],
            default   => ['1 rok', '%d roky', '%d let'],
        };
    }

    /**
     * @param array{0:string,1:string,2:string} $forms
     */
    private static function formatCount(int $count, array $forms): string
    {
        $form = self::selectForm($count, $forms);

        if (str_contains($form, '%d')) {
            return sprintf($form, $count);
        }

        return $form;
    }

    /**
     * @param array{0:string,1:string,2:string} $forms
     */
    private static function selectForm(int $count, array $forms): string
    {
        $n = abs($count);
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $forms[2];
        }

        $mod10 = $n % 10;
        return match ($mod10) {
            1 => $forms[0],
            2, 3, 4 => $forms[1],
            default => $forms[2],
        };
    }
}
