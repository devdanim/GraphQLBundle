<?php

declare(strict_types=1);

namespace Escqrs\Bundle\EventStore\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectionNamesCommand extends Command
{
    use FormatsOutput;

    protected const ARGUMENT_FILTER = 'filter';
    protected const OPTION_REGEX = 'regex';
    protected const OPTION_LIMIT = 'limit';
    protected const OPTION_OFFSET = 'offset';
    protected const OPTION_MANAGER = 'manager';

    /**
     * @param  Container $projectionManagersLocator
     * @param array $projectionManagerNames
     */
    public function __construct(
        private readonly ContainerInterface $projectionManagersLocator,
        private readonly array $projectionManagerNames
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('event-store:projection:names')
            ->setDescription('Shows a list of all projection names. Can be filtered.')
            ->addArgument(self::ARGUMENT_FILTER, InputArgument::OPTIONAL, 'Filter by this string')
            ->addOption(self::OPTION_REGEX, 'r', InputOption::VALUE_NONE, 'Enable regex syntax for filter')
            ->addOption(self::OPTION_LIMIT, 'l', InputOption::VALUE_REQUIRED, 'Limit the result set', 20)
            ->addOption(self::OPTION_OFFSET, 'o', InputOption::VALUE_REQUIRED, 'Offset for result set', 0)
            ->addOption(self::OPTION_MANAGER, 'm', InputOption::VALUE_REQUIRED, 'Manager for result set', null);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isComposerCall = $input->getOption('composer');

        $container  = $this->container;
        $rootDir    = $container->getParameter('kernel.root_dir');
        $configFile = $rootDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config/packages/graphql.yml';

        $className       = 'Schema';
        $schemaNamespace = self::PROJECT_NAMESPACE . '\\GraphQL';
        $graphqlPath     = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'GraphQL';
        $classPath       = $graphqlPath . DIRECTORY_SEPARATOR . $className . '.php';

        $inputHelper = $this->getHelper('question');
        if (file_exists($classPath)) {
            if (!$isComposerCall) {
                $output->writeln(sprintf('Schema class %s was found.', $schemaNamespace . '\\' . $className));
            }
        } else {
            $question = new ConfirmationQuestion(sprintf('Confirm creating class at %s ? [Y/n]', $schemaNamespace . '\\' . $className), true);
            if (!$inputHelper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            if (!is_dir($graphqlPath)) {
                mkdir($graphqlPath, 0777, true);
            }
            file_put_contents($classPath, $this->getSchemaClassTemplate($schemaNamespace, $className));

            $output->writeln('Schema file has been created at');
            $output->writeln($classPath . "\n");

            if (!file_exists($configFile)) {
                $question = new ConfirmationQuestion(sprintf('Config file not found (look at %s). Create it? [Y/n]', $configFile), true);
                if (!$inputHelper->ask($input, $output, $question)) {
                    return Command::SUCCESS;
                }

                touch($configFile);
            }

            $originalConfigData = file_get_contents($configFile);
            if (strpos($originalConfigData, 'graphql') === false) {
                $projectNameSpace = self::PROJECT_NAMESPACE;
                $configData       = <<<CONFIG
graphql:
    schema_class: "{$projectNameSpace}\\\\GraphQL\\\\{$className}"

CONFIG;
                file_put_contents($configFile, $configData . $originalConfigData);
            }
        }
        if (!$this->graphQLRouteExists()) {
            $question = new ConfirmationQuestion('Confirm adding GraphQL route? [Y/n]', true);
            $resource = $this->getMainRouteConfig();
            if ($resource && $inputHelper->ask($input, $output, $question)) {
                $routeConfigData = <<<CONFIG

graphql:
    resource: "@GraphQLBundle/Controller/"
CONFIG;
                file_put_contents($resource, $routeConfigData, FILE_APPEND);
                $output->writeln('Config was added to ' . $resource);
            }
        } else {
            if (!$isComposerCall) {
                $output->writeln('GraphQL default route was found.');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return null|string
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int Command exit status
     */
    protected function getMainRouteConfig(): ?string
    {
        $this->formatOutput($output);

        $managerNames = array_keys($this->projectionManagerNames);

        if ($requestedManager = $input->getOption(self::OPTION_MANAGER)) {
            $managerNames = array_filter(
                $managerNames,
                fn(string $managerName) => $managerName === $requestedManager
            );
        }

        /** @param string|null $filter */
        $filter = $input->getArgument(self::ARGUMENT_FILTER);
        $regex = $input->getOption(self::OPTION_REGEX);

        $output->write(sprintf('<action>Projection names'));
        if ($filter) {
            $output->write(sprintf(' filter <highlight>%s</highlight>', $filter));
        }
        if ($regex) {
            $output->write(' <comment>regex enabled</comment>');
            $method = 'fetchProjectionNamesRegex';
        } else {
            $method = 'fetchProjectionNames';
        }
        $output->writeln('</action>');

        $names = [];
        /** @param int $offset */
        $offset = (int)$input->getOption(self::OPTION_OFFSET);
        /** @param int $limit */
        $limit = (int)$input->getOption(self::OPTION_LIMIT);
        $maxNeeded = $offset + $limit;

        foreach ($managerNames as $managerName) {
            $projectionManager = $this->projectionManagersLocator->get($managerName);

            $projectionNames = count($names) > $offset
                ? $projectionManager->$method($filter, $limit - (count($names) - $offset))
                : $projectionManager->$method($filter, $limit);

            foreach ($projectionNames as $projectionName) {
                $names[] = [$managerName, $projectionName];
            }

            if (count($names) >= $maxNeeded) {
                break;
            }
        }

        $names = array_slice($names, $offset, $limit);

    /**
     * @return bool
     * @throws \Exception
     */
    protected function graphQLRouteExists(): bool
    {
        $routerResources = $this->container->get('router')->getRouteCollection()->getResources();
        foreach ($routerResources as $resource) {
            /** @var FileResource|DirectoryResource $resource */
            if (method_exists($resource, 'getResource') && strpos($resource->getResource(), 'GraphQLController.php') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function generateRoutes(): void
    {
    }

    protected function getSchemaClassTemplate($nameSpace, $className = 'Schema'): string
    {
        $tpl = <<<TEXT
<?php
/**
 * This class was automatically generated by GraphQL Schema generator
 */

namespace $nameSpace;

use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Type\Scalar\StringType;

class $className extends AbstractSchema
{
    public function build(SchemaConfig \$config)
    {
        \$config->getQuery()->addFields([
            'hello' => [
                'type'    => new StringType(),
                'args'    => [
                    'name' => [
                        'type' => new StringType(),
                        'defaultValue' => 'Stranger'
                    ]
                ],
                'resolve' => function (\$context, \$args) {
                    return 'Hello ' . \$args['name'];
                }
            ]
        ]);
    }

}


TEXT;

        return $tpl;
    }
}
