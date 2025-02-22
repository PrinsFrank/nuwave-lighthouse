<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use GraphQL\Language\DirectiveLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeResolver;
use Symfony\Component\Console\Input\InputOption;

class DirectiveCommand extends LighthouseGeneratorCommand
{
    /** @var array<int, class-string> */
    public const ARGUMENT_INTERFACES = [
        ArgTransformerDirective::class,
        ArgBuilderDirective::class,
        ArgResolver::class,
        ArgManipulator::class,
    ];

    /** @var array<int, class-string> */
    public const FIELD_INTERFACES = [
        FieldResolver::class,
        FieldMiddleware::class,
        FieldManipulator::class,
    ];

    /** @var array<int, class-string> */
    public const TYPE_INTERFACES = [
        TypeManipulator::class,
        TypeMiddleware::class,
        TypeResolver::class,
        TypeExtensionManipulator::class,
    ];

    protected $name = 'lighthouse:directive';

    protected $description = 'Create a class for a custom schema directive.';

    protected $type = 'Directive';

    /**
     * @var \Illuminate\Support\Collection<int, string>
     */
    protected Collection $requiredImports;

    /**
     * @var \Illuminate\Support\Collection<int, string>
     */
    protected Collection $implementedInterfaces;

    /**
     * @var \Illuminate\Support\Collection<int, string>
     */
    protected Collection $possibleLocations;

    /**
     * @var \Illuminate\Support\Collection<int, string>
     */
    protected Collection $methodStubs;

    protected function getNameInput(): string
    {
        return parent::getNameInput() . 'Directive';
    }

    protected function namespaceConfigKey(): string
    {
        return 'directives';
    }

    protected function buildClass($name): string
    {
        $this->requiredImports = new Collection();
        $this->implementedInterfaces = new Collection();
        $this->possibleLocations = new Collection();
        $this->methodStubs = new Collection();

        $stub = parent::buildClass($name);

        $forType = $this->option('type');
        $forField = $this->option('field');
        $forArgument = $this->option('argument');

        if (! $forType && ! $forField && ! $forArgument) {
            throw new \Exception('Must specify at least one of: --type, --field, --argument');
        }

        if ($forType) {
            $this->askForInterfaces(self::TYPE_INTERFACES);
            $this->askForLocations([
                DirectiveLocation::OBJECT,
                DirectiveLocation::IFACE,
                DirectiveLocation::ENUM,
                DirectiveLocation::INPUT_OBJECT,
                DirectiveLocation::SCALAR,
                DirectiveLocation::UNION,
            ]);
        }

        if ($forField) {
            $this->askForInterfaces(self::FIELD_INTERFACES);
            $this->addLocation(DirectiveLocation::FIELD_DEFINITION);
        }

        if ($forArgument) {
            // Arg directives always either implement ArgDirective or ArgDirectiveForArray.
            if ($this->confirm('Will your argument directive apply to a list of items?')) {
                $this->implementInterface(ArgDirectiveForArray::class);
            } else {
                $this->implementInterface(ArgDirective::class);
            }

            $this->askForInterfaces(self::ARGUMENT_INTERFACES);
            $this->askForLocations([
                DirectiveLocation::ARGUMENT_DEFINITION,
                DirectiveLocation::INPUT_FIELD_DEFINITION,
            ]);
        }

        $stub = str_replace(
            '{{ imports }}',
            $this->requiredImports
                ->filter()
                ->unique()
                ->implode("\n"),
            $stub
        );

        $directiveName = parent::getNameInput();
        $stub = str_replace(
            '{{ name }}',
            lcfirst($directiveName),
            $stub
        );

        $stub = str_replace(
            '{{ locations }}',
            $this->possibleLocations->implode(' | '),
            $stub
        );

        $stub = str_replace(
            '{{ methods }}',
            $this->methodStubs->implode("\n"),
            $stub
        );

        return str_replace(
            '{{ implements }}',
            $this->implementedInterfaces->implode(', '),
            $stub
        );
    }

    /**
     * Ask the user if the directive should implement any of the given interfaces.
     *
     * @param  array<class-string>  $availableInterfaces
     */
    protected function askForInterfaces(array $availableInterfaces): void
    {
        $implementedInterfaces = $this->choice(
            'Which interfaces should the directive implement?',
            $availableInterfaces,
            null,
            null,
            true
        );
        assert(is_array($implementedInterfaces), 'Because we set $multiple = true');

        foreach ($implementedInterfaces as $interface) {
            $this->implementInterface($interface);
        }
    }

    /**
     * @param  array<int, string>  $availableLocations
     */
    public function askForLocations(array $availableLocations): void
    {
        $usedLocations = $this->choice(
            'In which schema locations can the directive be used?',
            $availableLocations,
            null,
            null,
            true
        );
        assert(is_array($usedLocations), 'Because we set $multiple = true');

        foreach ($usedLocations as $location) {
            $this->addLocation($location);
        }
    }

    /**
     * @param  class-string  $interface
     */
    protected function shortName(string $interface): string
    {
        return Str::afterLast($interface, '\\');
    }

    /**
     * @param  class-string  $interface
     */
    protected function implementInterface(string $interface): void
    {
        $shortName = $this->shortName($interface);
        $this->implementedInterfaces->push($shortName);

        $this->requiredImports->push("use {$interface};");
        if ($imports = $this->interfaceImports($shortName)) {
            $imports = explode("\n", $imports);
            $this->requiredImports->push(...$imports);
        }

        if ($methods = $this->interfaceMethods($shortName)) {
            $this->methodStubs->push($methods);
        }
    }

    private function addLocation(string $location): void
    {
        $this->possibleLocations->push($location);
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/directive.stub';
    }

    protected function interfaceMethods(string $interface): ?string
    {
        return $this->getFileIfExists(
            __DIR__ . '/stubs/directives/' . Str::snake($interface) . '_methods.stub'
        );
    }

    protected function interfaceImports(string $interface): ?string
    {
        return $this->getFileIfExists(
            __DIR__ . '/stubs/directives/' . Str::snake($interface) . '_imports.stub'
        );
    }

    protected function getFileIfExists(string $path): ?string
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        return $this->files->get($path);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['type', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to types.'],
            ['field', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to fields.'],
            ['argument', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to arguments.'],
        ];
    }
}
