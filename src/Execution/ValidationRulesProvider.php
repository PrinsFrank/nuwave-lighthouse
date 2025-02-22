<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;

class ValidationRulesProvider implements ProvidesValidationRules
{
    public function __construct(
        protected ConfigRepository $configRepository
    ) {}

    public function validationRules(): ?array
    {
        return [
            QueryComplexity::class => new QueryComplexity($this->configRepository->get('lighthouse.security.max_query_complexity', 0)),
            QueryDepth::class => new QueryDepth($this->configRepository->get('lighthouse.security.max_query_depth', 0)),
            DisableIntrospection::class => new DisableIntrospection($this->configRepository->get('lighthouse.security.disable_introspection', 0)),
        ] + DocumentValidator::allRules();
    }
}
