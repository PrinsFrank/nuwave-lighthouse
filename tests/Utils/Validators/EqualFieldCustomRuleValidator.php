<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;
use Tests\Utils\Rules\EqualFieldRule;

final class EqualFieldCustomRuleValidator extends Validator
{
    /**
     * @return array{bar: array<\Tests\Utils\Rules\EqualFieldRule>}
     */
    public function rules(): array
    {
        return [
            'bar' => [new EqualFieldRule()],
        ];
    }
}
