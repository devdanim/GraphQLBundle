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
     * Executes the command.
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int Command exit status
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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

        (new Table($output))
            ->setHeaders(['Projection Manager', 'Name'])
            ->setRows($names)
            ->render();

        return Command::SUCCESS;
    }
}
