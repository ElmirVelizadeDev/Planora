<?php

namespace Tests\Unit;

use App\Rules\PersonName;
use App\Rules\SupportedEmailDomain;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Tests\TestCase;

class RegistrationInputValidationTest extends TestCase
{
    public function test_person_name_rule_accepts_unicode_names_and_rejects_unsafe_or_invalid_names(): void
    {
        foreach (["Elmir Vəlizadə", 'John Smith', "O'Neill", 'Jean-Luc'] as $name) {
            $this->assertTrue($this->validator(['name' => $name], ['name' => ['required', new PersonName()]])->passes(), $name);
        }

        foreach (['A', str_repeat('A', 51), 'Elmir123', '   ', 'A😀', '<script>'] as $name) {
            $this->assertFalse($this->validator(['name' => $name], ['name' => ['required', new PersonName()]])->passes(), $name);
        }
    }

    public function test_supported_provider_rule_accepts_only_exact_allowlisted_domains(): void
    {
        foreach (['elmir@gmail.com', 'user@outlook.com', 'user@yahoo.com', 'user@icloud.com', 'x@googlemail.com'] as $email) {
            $this->assertTrue($this->validator(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255', new SupportedEmailDomain()]])->passes(), $email);
        }

        foreach (['elmir@elmir.com', 'elmir@gmail', 'elmir@@gmail.com', '@gmail.com', 'user@gmail.com.fake.com', ' user@gmail.com '] as $email) {
            $this->assertFalse($this->validator(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255', new SupportedEmailDomain()]])->passes(), $email);
        }
    }

    private function validator(array $data, array $rules): ValidatorContract
    {
        return $this->app['validator']->make($data, $rules);
    }
}
