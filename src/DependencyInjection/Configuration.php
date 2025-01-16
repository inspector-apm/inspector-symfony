<?php


namespace Inspector\Symfony\Bundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('inspector');
        $tree->getRootNode()->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('url')->defaultValue('https://ingest.inspector.dev')->end()
            ->scalarNode('ingestion_key')->defaultNull()->end()
            ->booleanNode('unhandled_exceptions')->defaultTrue()->end()
            ->booleanNode('messenger')->defaultTrue()->end()
            ->booleanNode('query')->defaultTrue()->end()
            ->booleanNode('query_bindings')->defaultTrue()->end()
            ->booleanNode('templates')->defaultTrue()->end()
            ->booleanNode('user')->defaultTrue()->end()
            ->scalarNode('transport')->defaultValue('async')->end()
            ->arrayNode('ignore_routes')->scalarPrototype()->end()->end()
            ->arrayNode('ignore_commands')->scalarPrototype()->end()->end()
            ->arrayNode('ignore_messages')->scalarPrototype()->end()->end()
            ->end();

        return $tree;
    }
}
