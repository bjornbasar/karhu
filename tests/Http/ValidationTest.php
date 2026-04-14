<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Attributes\Email;
use Karhu\Attributes\In;
use Karhu\Attributes\NumericRange;
use Karhu\Attributes\Regex;
use Karhu\Attributes\Required;
use Karhu\Attributes\StringLength;
use Karhu\Http\Validation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* --- Stub DTOs --- */

final class StubCreateUser
{
    #[Required]
    #[StringLength(min: 3, max: 20)]
    public string $name = '';

    #[Required]
    #[Email]
    public string $email = '';

    #[NumericRange(min: 1, max: 150)]
    public int $age = 0;

    #[In(values: ['admin', 'editor', 'viewer'])]
    public string $role = '';

    #[Regex(pattern: '/^[A-Z]{2,3}$/')]
    public string $countryCode = '';
}

final class StubCustomMessage
{
    #[Required(message: 'Please provide a title')]
    public string $title = '';
}

/* --- Tests --- */

final class ValidationTest extends TestCase
{
    #[Test]
    public function valid_data_returns_no_errors(): void
    {
        $errors = Validation::validate([
            'name' => 'Bjorn',
            'email' => 'bjorn@example.com',
            'age' => 30,
            'role' => 'admin',
            'countryCode' => 'NZ',
        ], StubCreateUser::class);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function required_catches_missing(): void
    {
        $errors = Validation::validate([], StubCreateUser::class);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function required_catches_empty_string(): void
    {
        $errors = Validation::validate(['name' => '', 'email' => ''], StubCreateUser::class);
        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function string_length_min(): void
    {
        $errors = Validation::validate([
            'name' => 'AB',
            'email' => 'a@b.com',
        ], StubCreateUser::class);
        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('at least 3', $errors['name']);
    }

    #[Test]
    public function string_length_max(): void
    {
        $errors = Validation::validate([
            'name' => str_repeat('A', 21),
            'email' => 'a@b.com',
        ], StubCreateUser::class);
        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('at most 20', $errors['name']);
    }

    #[Test]
    public function email_validation(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'not-an-email',
        ], StubCreateUser::class);
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('valid email', $errors['email']);
    }

    #[Test]
    public function numeric_range_min(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'a@b.com',
            'age' => 0,
        ], StubCreateUser::class);
        $this->assertArrayHasKey('age', $errors);
    }

    #[Test]
    public function numeric_range_max(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'a@b.com',
            'age' => 200,
        ], StubCreateUser::class);
        $this->assertArrayHasKey('age', $errors);
    }

    #[Test]
    public function in_validation(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'a@b.com',
            'role' => 'superuser',
        ], StubCreateUser::class);
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('one of', $errors['role']);
    }

    #[Test]
    public function regex_validation(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'a@b.com',
            'countryCode' => 'new zealand',
        ], StubCreateUser::class);
        $this->assertArrayHasKey('countryCode', $errors);
    }

    #[Test]
    public function custom_error_message(): void
    {
        $errors = Validation::validate([], StubCustomMessage::class);
        $this->assertSame('Please provide a title', $errors['title']);
    }

    #[Test]
    public function optional_fields_skip_when_empty(): void
    {
        $errors = Validation::validate([
            'name' => 'Test',
            'email' => 'a@b.com',
            // age, role, countryCode all absent → no errors (not required)
        ], StubCreateUser::class);
        $this->assertArrayNotHasKey('age', $errors);
        $this->assertArrayNotHasKey('role', $errors);
        $this->assertArrayNotHasKey('countryCode', $errors);
    }
}
