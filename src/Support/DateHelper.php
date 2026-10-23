<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DateHelper
{
    private static ?string $customTimezone = null;

    /**
     * Retorna a instância DateTimeZone configurada no ambiente.
     */
    public static function getTimezone(): DateTimeZone
    {
        $tzName = self::$customTimezone ?? ($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
        return new DateTimeZone($tzName);
    }

    /**
     * Define temporariamente o fuso horário (útil para testes).
     */
    public static function setTimezone(string $timezone): void
    {
        self::$customTimezone = $timezone;
        date_default_timezone_set($timezone);
    }

    /**
     * Reseta para o padrão do ambiente.
     */
    public static function resetTimezone(): void
    {
        self::$customTimezone = null;
        $tzName = $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo';
        date_default_timezone_set($tzName);
    }

    /**
     * Retorna DateTimeImmutable atual no fuso configurado.
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::getTimezone());
    }

    /**
     * Retorna a data/hora atual formatada para SQLite (Y-m-d H:i:s).
     */
    public static function nowString(string $format = 'Y-m-d H:i:s'): string
    {
        return self::now()->format($format);
    }

    /**
     * Retorna apenas a data atual no fuso (Y-m-d).
     */
    public static function todayDate(): string
    {
        return self::now()->format('Y-m-d');
    }

    /**
     * Retorna o início do dia de hoje (Y-m-d 00:00:00).
     */
    public static function todayStart(): string
    {
        return self::now()->format('Y-m-d 00:00:00');
    }

    /**
     * Retorna o final do dia de hoje (Y-m-d 23:59:59).
     */
    public static function todayEnd(): string
    {
        return self::now()->format('Y-m-d 23:59:59');
    }

    /**
     * Formata uma string de data/hora para exibição amigável brasileira (ex: 23/10/2026 às 14:30).
     */
    public static function format(?string $dateTimeStr, string $format = 'd/m/Y \à\s H:i'): string
    {
        if (empty($dateTimeStr)) {
            return '—';
        }

        try {
            $dt = new DateTimeImmutable($dateTimeStr, self::getTimezone());
            return $dt->format($format);
        } catch (\Throwable) {
            return $dateTimeStr;
        }
    }

    /**
     * Formata apenas a data (ex: 23/10/2026).
     */
    public static function formatDate(?string $dateTimeStr, string $format = 'd/m/Y'): string
    {
        return self::format($dateTimeStr, $format);
    }

    /**
     * Formata apenas a hora (ex: 14:30).
     */
    public static function formatTime(?string $dateTimeStr, string $format = 'H:i'): string
    {
        return self::format($dateTimeStr, $format);
    }

    /**
     * Converte data/hora para o formato de input HTML <input type="datetime-local"> (Y-m-d\TH:i).
     */
    public static function toHtmlDatetimeLocal(?string $dateTimeStr = null): string
    {
        if (empty($dateTimeStr)) {
            return self::now()->format('Y-m-d\TH:i');
        }

        try {
            $dt = new DateTimeImmutable($dateTimeStr, self::getTimezone());
            return $dt->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return self::now()->format('Y-m-d\TH:i');
        }
    }

    /**
     * Converte valor vindo de input HTML datetime-local para formato padrão SQLite (Y-m-d H:i:s).
     *
     * @throws InvalidArgumentException
     */
    public static function fromHtmlDatetimeLocal(string $input): string
    {
        $clean = trim($input);
        if ($clean === '') {
            throw new InvalidArgumentException('Data e hora não podem ser vazias.');
        }

        $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'];
        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $clean, self::getTimezone());
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        try {
            $dt = new DateTimeImmutable($clean, self::getTimezone());
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new InvalidArgumentException("Formato de data e hora inválido: '{$clean}'.");
        }
    }

    /**
     * Determina se o retorno está atrasado (horário agendado é anterior ao instante atual e pendente).
     */
    public static function isOverdue(string $scheduledAt, string $status = 'pending'): bool
    {
        if ($status !== 'pending') {
            return false;
        }

        try {
            $dt = new DateTimeImmutable($scheduledAt, self::getTimezone());
            return $dt < self::now();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Determina se a data do agendamento coincide com o dia de hoje no fuso configurado.
     */
    public static function isToday(string $scheduledAt): bool
    {
        try {
            $dt = new DateTimeImmutable($scheduledAt, self::getTimezone());
            return $dt->format('Y-m-d') === self::now()->format('Y-m-d');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Determina se o agendamento é estritamente futuro (após o instante atual).
     */
    public static function isFuture(string $scheduledAt): bool
    {
        try {
            $dt = new DateTimeImmutable($scheduledAt, self::getTimezone());
            return $dt > self::now();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retorna o status semântico do follow-up: 'overdue', 'today', 'future', 'completed', 'rescheduled', 'cancelled'.
     */
    public static function getFollowUpStatus(string $scheduledAt, string $status = 'pending'): string
    {
        if ($status === 'completed') {
            return 'completed';
        }
        if ($status === 'rescheduled') {
            return 'rescheduled';
        }
        if ($status === 'cancelled') {
            return 'cancelled';
        }

        if (self::isOverdue($scheduledAt, $status)) {
            return 'overdue';
        }

        if (self::isToday($scheduledAt)) {
            return 'today';
        }

        return 'future';
    }

    /**
     * Rótulo em português para a situação do retorno.
     */
    public static function getStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'overdue' => 'Atrasado',
            'today' => 'Para Hoje',
            'future' => 'Futuro',
            'completed' => 'Concluído',
            'rescheduled' => 'Reagendado',
            'cancelled' => 'Cancelado',
            'pending' => 'Pendente',
            default => ucfirst($statusKey),
        };
    }

    /**
     * Classe CSS de badge semântica para a situação.
     */
    public static function getStatusBadgeClass(string $statusKey): string
    {
        return match ($statusKey) {
            'overdue' => 'badge-overdue',
            'today' => 'badge-today',
            'future' => 'badge-future',
            'completed' => 'badge-completed',
            'rescheduled' => 'badge-neutral',
            'cancelled' => 'badge-inactive',
            default => 'badge-neutral',
        };
    }

    /**
     * Descrição relativa amigável (ex: "Atrasado há 30 min", "Hoje às 15:00", "Amanhã às 10:00").
     */
    public static function humanDiff(string $scheduledAt, string $status = 'pending'): string
    {
        if ($status === 'completed') {
            return 'Concluído';
        }

        try {
            $dt = new DateTimeImmutable($scheduledAt, self::getTimezone());
            $now = self::now();

            $dateScheduled = $dt->format('Y-m-d');
            $dateNow = $now->format('Y-m-d');

            if ($dateScheduled === $dateNow) {
                if ($dt < $now && $status === 'pending') {
                    $diffMinutes = max(1, (int)round(($now->getTimestamp() - $dt->getTimestamp()) / 60));
                    if ($diffMinutes < 60) {
                        return "Atrasado ({$diffMinutes} min atrás)";
                    }
                    $diffHours = (int)round($diffMinutes / 60);
                    return "Atrasado ({$diffHours}h atrás)";
                }
                return 'Hoje às ' . $dt->format('H:i');
            }

            $yesterday = $now->modify('-1 day')->format('Y-m-d');
            if ($dateScheduled === $yesterday) {
                return 'Ontem às ' . $dt->format('H:i') . ' (Atrasado)';
            }

            $tomorrow = $now->modify('+1 day')->format('Y-m-d');
            if ($dateScheduled === $tomorrow) {
                return 'Amanhã às ' . $dt->format('H:i');
            }

            if ($dt < $now && $status === 'pending') {
                $days = max(1, (int)round(($now->getTimestamp() - $dt->getTimestamp()) / 86400));
                return "Atrasado há {$days} dia(s)";
            }

            return $dt->format('d/m/Y \à\s H:i');
        } catch (\Throwable) {
            return $scheduledAt;
        }
    }
}
