<?php

declare(strict_types=1);

namespace App\Modules\Leads\Support;

final class LeadNormalizer
{
    public const ALLOWED_SOURCES = [
        'manual' => 'Cadastro Manual',
        'whatsapp' => 'WhatsApp',
        'site' => 'Site Institucional',
        'portal' => 'Portais Imobiliários',
        'placa' => 'Placa no Imóvel',
        'indicacao' => 'Indicação',
        'telefone' => 'Ligação Telefônica',
        'presencial' => 'Atendimento Presencial',
        'outro' => 'Outro',
    ];

    /**
     * Normaliza e sanitiza número de telefone (apenas dígitos).
     * Se contiver o código do Brasil (55) com 12 ou 13 dígitos, remove o 55 para padronizar em 10 ou 11 dígitos.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Se começa com DDI 55 e tem mais de 11 dígitos, remove o DDI 55
        if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Formata um telefone para exibição legível (padrão brasileiro).
     * Ex: 21999998888 -> (21) 99999-8888 | 2133334444 -> (21) 3333-4444
     */
    public static function formatPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $phone;
    }

    /**
     * Normaliza e sanitiza e-mail (lowercase e trim).
     */
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $trimmed = trim(strtolower($email));
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Sanitiza nome de lead.
     */
    public static function sanitizeName(string $name): string
    {
        $cleaned = strip_tags(trim($name));
        return preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
    }

    /**
     * Valida os campos do formulário de lead no servidor.
     * Retorna array associativo de erros (vazio se tudo for válido).
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validate(array $input): array
    {
        $errors = [];

        // 1. Validação do nome
        $name = trim((string)($input['name'] ?? ''));
        if (!mb_check_encoding($name, 'UTF-8')) {
            $errors['name'] = 'O nome contém caracteres inválidos.';
        } elseif ($name === '') {
            $errors['name'] = 'O nome do lead é obrigatório.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter pelo menos 2 caracteres.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'O nome não pode exceder 150 caracteres.';
        }

        // 2. Contatos são opcionais; cada campo informado deve ser válido.
        $email = self::normalizeEmail($input['email'] ?? null);
        $phone = self::normalizePhone($input['phone'] ?? null);

        $notes = (string)($input['notes'] ?? '');
        if (!mb_check_encoding($notes, 'UTF-8')) $errors['notes'] = 'As observações contêm texto inválido.';
        elseif (mb_strlen($notes) > 4000) $errors['notes'] = 'As observações devem ter até 4.000 caracteres.';
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $notes)) $errors['notes'] = 'As observações contêm caracteres inválidos.';

        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Informe um endereço de e-mail válido.';
            } elseif (strlen($email) > 150) {
                $errors['email'] = 'O e-mail não pode exceder 150 caracteres.';
            }
        }

        if ($phone !== null) {
            $length = strlen($phone);
            if ($length < 10 || $length > 11) {
                $errors['phone'] = 'O telefone deve conter DDD + número (10 ou 11 dígitos).';
            }
        }

        // 3. Validação de origem
        $source = trim((string)($input['source'] ?? 'manual'));
        if ($source !== '' && !array_key_exists($source, self::ALLOWED_SOURCES)) {
            $errors['source'] = 'Origem do lead inválida.';
        }

        return $errors;
    }
}
