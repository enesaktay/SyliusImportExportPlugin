<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Command;

use FriendsOfSylius\SyliusImportExportPlugin\Importer\ImporterRegistry;
use FriendsOfSylius\SyliusImportExportPlugin\Importer\ItemReaderInterface;
use FriendsOfSylius\SyliusImportExportPlugin\Importer\SingleDataArrayImporterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class ImportDataFromMessageQueueCommand extends Command
{
    use ContainerAwareTrait;

    /**
     * @var ImporterRegistry
     */
    private $importerRegistry;

    public function __construct(ImporterRegistry $importerRegistry)
    {
        $this->importerRegistry = $importerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('sylius:import-from-message-queue')
            ->setDescription('Import data from message queue.')
            ->setDefinition([
                new InputArgument('importer', InputArgument::OPTIONAL, 'The importer to use.'),
            ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $importer = $input->getArgument('importer');

        if (empty($importer)) {
            $this->listImporters($input, $output);

            return;
        }

        // only accepts the format of json as messages
        $name = ImporterRegistry::buildServiceName($importer, 'json');

        if (!$this->importerRegistry->has($name)) {
            $message = sprintf(
                "<error>There is no '%s' importer.</error>",
                $name
            );

            $this->listImporters($input, $output, $message);
        }

        /** @var SingleDataArrayImporterInterface $service */
        $service = $this->importerRegistry->get($name);

        /** @var ItemReaderInterface $mqItemReader */
        $mqItemReader = $this->container->get('sylius.message_queue_reader');
        $this->getImporterJsonDataFromMessageQueue($mqItemReader, $importer, $service, $output);
        $this->finishImport($name, $output);
    }

    private function finishImport(string $name, OutputInterface $output): void
    {
        $message = sprintf(
            '<info>Imported from the message queue via the %s importer</info>',
            $name
        );
        $output->writeln($message);
    }

    private function getImporterJsonDataFromMessageQueue(ItemReaderInterface $mqItemReader, $importer, SingleDataArrayImporterInterface $service, OutputInterface $output): void
    {
        $mqItemReader->initQueue('sylius.export.queue.' . $importer);
        $mqItemReader->readAndImport($service);
        $output->writeln('Imported: ' . $mqItemReader->getMessagesImportedCount());
        $output->writeln('Skipped: ' . $mqItemReader->getMessagesSkippedCount());
    }

    private function listImporters(InputInterface $input, OutputInterface $output, ?string $errorMessage = null): void
    {
        $all = array_keys($this->importerRegistry->all());
        $importers = [];
        foreach ($all as $importer) {
            $importer = explode('.', $importer);
            $importers[$importer[0]][] = $importer[1];
        }

        $list = [];
        $output->writeln('<info>Available importers:</info>');
        foreach ($importers as $importer => $formats) {
            $list[] = sprintf(
                '%s',
                $importer
            );
        }

        $io = new SymfonyStyle($input, $output);
        $io->listing($list);

        if ($errorMessage) {
            throw new \RuntimeException($errorMessage);
        }
    }
}
