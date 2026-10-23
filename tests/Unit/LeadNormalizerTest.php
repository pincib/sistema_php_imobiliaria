<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Modules\Leads\Support\LeadNormalizer;
use PHPUnit\Framework\TestCase;

final class LeadNormalizerTest extends TestCase
{
    public function testPhoneNormalizationStripsNonDigits(): void
    {
        $this->assertSame('21999998888', LeadNormalizer::normalizePhone('(21) 99999-8888'));
        $this->assertSame('2133334444', LeadNormalizer::normalizePhone('21 3333-4444'));
        $this->assertSame('21999998888', LeadNormalizer::normalizePhone('+55 (21) 99999-8888'));
        $this->assertSame('2133334444', LeadNormalizer::normalizePhone('+552133334444'));
        $this->assertNull(LeadNormalizer::normalizePhone(''));
        $this->assertNull(LeadNormalizer::normalizePhone(null));
    }

    public function testPhoneFormattingForDisplay(): void
    {
        $this->assertSame('(21) 99999-8888', LeadNormalizer::formatPhone('21999998888'));
        $this->assertSame('(21) 3333-4444', LeadNormalizer::formatPhone('2133334444'));
        $this->assertSame('—', LeadNormalizer::formatPhone(''));
        $this->assertSame('—', LeadNormalizer::formatPhone(null));
    }

    public function testEmailNormalization(): void
    {
        $this->assertSame('cliente@pinciara.com', LeadNormalizer::normalizeEmail('  CLIENTE@pinciara.COM  '));
        $this->assertNull(LeadNormalizer::normalizeEmail(''));
        $this->assertNull(LeadNormalizer::normalizeEmail(null));
    }

    public function testNameSanitization(): void
    {
        $this->assertSame('Carlos Eduardo', LeadNormalizer::sanitizeName('  <b>Carlos</b>   Eduardo  '));
    }

    public function testValidationRequiresName(): void
    {
        $errors = LeadNormalizer::validate([
            'name' => '',
            'phone' => '21999998888',
        ]);
        $this->assertArrayHasKey('name', $errors);

        $errorsShort = LeadNormalizer::validate([
            'name' => 'A',
            'phone' => '21999998888',
        ]);
        $this->assertArrayHasKey('name', $errorsShort);
    }

    public function testValidationAllowsEmptyOptionalContactMethods(): void
    {
        $errors = LeadNormalizer::validate([
            'name' => 'Mariana Silva',
            'phone' => '',
            'email' => '',
        ]);
        $this->assertArrayNotHasKey('contact', $errors);
        $this->assertSame([], $errors);
    }

    public function testValidationRejectsInvalidEmail(): void
    {
        $errors = LeadNormalizer::validate([
            'name' => 'Mariana Silva',
            'email' => 'email-invalido-sem-arroba',
        ]);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidationRejectsInvalidPhoneLength(): void
    {
        $errorsShort = LeadNormalizer::validate([
            'name' => 'Mariana Silva',
            'phone' => '12345',
        ]);
        $this->assertArrayHasKey('phone', $errorsShort);

        $errorsLong = LeadNormalizer::validate([
            'name' => 'Mariana Silva',
            'phone' => '219999988889999',
        ]);
        $this->assertArrayHasKey('phone', $errorsLong);
    }

    public function testValidationPassesWithValidData(): void
    {
        $errors = LeadNormalizer::validate([
            'name' => 'Roberto Carlos',
            'phone' => '(21) 98888-7777',
            'email' => 'roberto@exemplo.com',
            'source' => 'whatsapp',
        ]);
        $this->assertEmpty($errors);
    }
}
