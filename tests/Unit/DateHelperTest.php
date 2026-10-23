<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\DateHelper;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateHelperTest extends TestCase
{
    protected function setUp(): void
    {
        DateHelper::setTimezone('America/Sao_Paulo');
    }

    protected function tearDown(): void
    {
        DateHelper::resetTimezone();
    }

    public function testTimezoneIsConfiguredCorrectly(): void
    {
        $tz = DateHelper::getTimezone();
        $this->assertSame('America/Sao_Paulo', $tz->getName());
    }

    public function testFormattingBrazilianDateAndLocalTime(): void
    {
        $utcStr = '2026-10-23 15:30:00';
        $formatted = DateHelper::format($utcStr);
        $this->assertSame('23/10/2026 às 15:30', $formatted);

        $dateOnly = DateHelper::formatDate($utcStr);
        $this->assertSame('23/10/2026', $dateOnly);

        $timeOnly = DateHelper::formatTime($utcStr);
        $this->assertSame('15:30', $timeOnly);

        $empty = DateHelper::format(null);
        $this->assertSame('—', $empty);
    }

    public function testHtmlDatetimeLocalConversion(): void
    {
        $dbDate = '2026-10-25 09:45:00';
        $htmlInput = DateHelper::toHtmlDatetimeLocal($dbDate);
        $this->assertSame('2026-10-25T09:45', $htmlInput);

        $convertedBack = DateHelper::fromHtmlDatetimeLocal('2026-10-25T09:45');
        $this->assertSame('2026-10-25 09:45:00', $convertedBack);

        $this->expectException(InvalidArgumentException::class);
        DateHelper::fromHtmlDatetimeLocal('');
    }

    public function testIsOverdueAndIsTodayAndIsFuture(): void
    {
        $now = DateHelper::now();
        $past = $now->modify('-2 hours')->format('Y-m-d H:i:s');
        $future = $now->modify('+2 hours')->format('Y-m-d H:i:s');
        $tomorrow = $now->modify('+1 day')->format('Y-m-d H:i:s');
        $yesterday = $now->modify('-1 day')->format('Y-m-d H:i:s');

        // Past
        $this->assertTrue(DateHelper::isOverdue($past, 'pending'));
        $this->assertFalse(DateHelper::isOverdue($past, 'completed')); // Completed is not overdue
        $this->assertFalse(DateHelper::isFuture($past));

        // Future
        $this->assertFalse(DateHelper::isOverdue($future, 'pending'));
        $this->assertTrue(DateHelper::isFuture($future));

        // Today
        $this->assertTrue(DateHelper::isToday($past));
        $this->assertTrue(DateHelper::isToday($future));
        $this->assertFalse(DateHelper::isToday($tomorrow));
        $this->assertFalse(DateHelper::isToday($yesterday));

        // Semantic status
        $this->assertSame('overdue', DateHelper::getFollowUpStatus($past, 'pending'));
        $this->assertSame('today', DateHelper::getFollowUpStatus($future, 'pending'));
        $this->assertSame('future', DateHelper::getFollowUpStatus($tomorrow, 'pending'));
        $this->assertSame('completed', DateHelper::getFollowUpStatus($past, 'completed'));
    }

    public function testHumanDiffDescriptions(): void
    {
        $now = DateHelper::now();
        $past = $now->modify('-30 minutes')->format('Y-m-d H:i:s');
        $diff = DateHelper::humanDiff($past, 'pending');
        $this->assertStringContainsString('Atrasado', $diff);

        $completed = DateHelper::humanDiff($past, 'completed');
        $this->assertSame('Concluído', $completed);
    }
}
