<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Exporter\Plugin;

/**
 * Class PluginPool
 */
class PluginPool implements PluginPoolInterface
{
    /**
     * @var PluginInterface[]
     */
    private $plugins;

    /**
     * @var array
     */
    private $exportKeys;

    /**
     * @var array
     */
    private $exportKeysNotFound;

    /**
     * @var array
     */
    private $exportKeysAvailable = [];

    /**
     * @param array $plugins
     * @param array $exportKeys
     */
    public function __construct(array $plugins, array $exportKeys)
    {
        $this->plugins = $plugins;
        $this->exportKeys = $exportKeys;

        $this->exportKeysNotFound = $exportKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * {@inheritdoc}
     */
    public function initPlugins(array $ids): void
    {
        foreach ($this->plugins as $plugin) {
            $plugin->init($ids);

            $this->exportKeysAvailable = array_merge($this->exportKeysAvailable, $plugin->getFieldNames());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDataForId(string $id): array
    {
        $result = [];

        foreach ($this->plugins as $index => $plugin) {
            $result = $this->getDataForIdFromPlugin($id, $plugin, $result);
        }

        if (!empty($this->exportKeysNotFound)) {
            throw new \InvalidArgumentException(sprintf(
                'Not all defined export keys have been found: "%s". Choose from: "%s"',
                implode(', ', $this->exportKeysNotFound),
                implode(', ', $this->exportKeysAvailable)
            ));
        }

        return $result;
    }

    /**
     * @param string $id
     * @param PluginInterface $plugin
     * @param array $result
     *
     * @return array
     */
    private function getDataForIdFromPlugin(string $id, PluginInterface $plugin, array $result): array
    {
        foreach ($plugin->getData($id, $this->exportKeys) as $exportKey => $exportValue) {
            if (false === empty($result[$exportKey])) {
                continue;
            }

            // no other plugin has delivered a value till now
            $result[$exportKey] = $exportValue;

            $foundKey = array_search($exportKey, $this->exportKeysNotFound);
            unset($this->exportKeysNotFound[$foundKey]);
        }

        return $result;
    }
}
