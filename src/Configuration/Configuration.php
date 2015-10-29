<?php

/**
 * This file is part of cyberspectrum/pharpiler.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    cyberspectrum/pharpiler
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/cyberspectrum/pharpiler/blob/master/LICENSE MIT
 * @link       https://github.com/cyberspectrum/pharpiler
 * @filesource
 */

namespace CyberSpectrum\PharPiler\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * This class defines the base configuration of imports.
 */
class Configuration implements ConfigurationInterface
{
    public static function flatten($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $new = [];
        foreach ($value as $element) {
            if (is_array($element)) {
                $new += self::flatten($element);
                continue;
            }

            $new[] = $element;
        }

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('pharpiler');



        // FIXME: we need to add the base configurations here.
        $rootNode
            ->children()
                ->scalarNode('phar')
                    ->info('The phar file to compile')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end();
        $tasks = $rootNode
            ->children()
                ->arrayNode('tasks')
                    ->prototype('array')
                    ->info('The compile tasks.')
                    ->validate()
                        ->always(function ($value) {
                            // FIXME: mark here which values are mandatory for each task type.
                            return $value;
                        })
                    ->end()
                    ->children();

        /**
         * @var NodeBuilder $tasks
         */
        $tasks
            ->scalarNode('type')
                ->info('Type of the compiler.')
                ->isRequired()
                ->cannotBeEmpty()
                ->validate()
                ->ifNotInArray(['run-process', 'add-package', 'set-stub', 'composer-autoload'])
                ->thenInvalid('Invalid task type %s');

        // run-process task
        $tasks
            ->scalarNode('command')
                ->info('The command to run.')
                ->cannotBeEmpty()
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid command string %s', json_encode($value)));
                    }

                    return $value;
                })
            ->end()
            ->end()
            ->scalarNode('working_dir')
                ->info('The working dir to use.')
                ->cannotBeEmpty()
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid working dir %s', json_encode($value)));
                    }

                    return $value;
                })
            ->end()
            ->end()
            ->arrayNode('env')
                ->prototype('scalar')
                ->info('The environment variables')
                ->cannotBeEmpty()
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid environment variable %s', json_encode($value)));
                    }

                    return $value;
                })
            ->end()
            ->end();

        // add-package task
        $tasks
            ->scalarNode('name')
                ->info('The name of the package to include.')
                ->cannotBeEmpty()
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid package name %s', json_encode($value)));
                    }

                    return $value;
                });
        $tasks
            ->scalarNode('include_require')
                ->info('The dependencies to include or true to include all.')
                ->cannotBeEmpty()
                ->defaultTrue()
                ->validate()
                ->always(function ($value) {
                    if (!is_array($value) && !is_bool($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for include_require: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->scalarNode('include_requiredev')
                ->info('The dev dependencies to include or true to include all.')
                ->cannotBeEmpty()
                ->defaultFalse()
                ->validate()
                ->always(function ($value) {
                    if (!is_array($value) && !is_bool($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for include_requiredev: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->arrayNode('exclude_dependencies')
                ->prototype('scalar')
                ->info('The dependencies to exclude.')
                ->cannotBeEmpty()
                ->defaultValue([])
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for exclude_dependencies: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->arrayNode('include_files')
                ->prototype('scalar')
                ->info('The files to include.')
                ->cannotBeEmpty()
                ->defaultValue([])
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for include_files: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->arrayNode('exclude_files')
                ->prototype('scalar')
                ->info('The files to exclude.')
                ->cannotBeEmpty()
                ->defaultValue([])
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for exclude_files: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->arrayNode('rewrite_paths')
                ->prototype('scalar')
                ->info('The paths to rewrite.')
                ->cannotBeEmpty()
                ->defaultValue([])
                ->validate()
                ->always(function ($value) {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid value for rewrite_paths: %s', json_encode($value)));
                    }

                    return $value;
                });

        $tasks
            ->variableNode('package_override')
                ->info('The values to override for certain packages.')
                ->cannotBeEmpty()
                ->defaultValue([])
                ->validate()
                ->always(function ($value) {
                    if (!is_array($value)) {
                        // FIXME: check that we only have package-name: {include_files: [], exclude_files: [], rewrite_paths: []}
                        throw new \InvalidArgumentException(sprintf('Invalid value for package_override: %s', json_encode($value)));
                    }

                    foreach (array_keys($value) as $packageName) {
                        foreach (['include_files', 'exclude_files'/*, 'rewrite_paths'*/] as $key) {
                            if (isset($value[$packageName][$key])) {
                                $value[$packageName][$key] = self::flatten($value[$packageName][$key]);
                            }
                        }
                    }

                    return $value;
                });

        // task set-stub
        $tasks
            ->scalarNode('stub_file')
            ->info('The file to use as stub.')
            ->cannotBeEmpty()
            ->validate()
            ->always(function ($value) {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException(sprintf('Invalid stub file %s', json_encode($value)));
                }

                return $value;
            });

        // task composer-autoload
        $tasks
            ->scalarNode('optimize')
            ->info('Optimize the autoloader.')
            ->defaultValue(true)
            ->validate()
            ->always(function ($value) {
                if (!is_bool($value)) {
                    throw new \InvalidArgumentException(sprintf('Invalid value %s', json_encode($value)));
                }

                return $value;
            });


        // Rewrite rules.
        $rewrites = $rootNode
            ->children()
            ->arrayNode('rewrites')
                ->prototype('array')
                ->info('The rewrites rules.')
                ->beforeNormalization()
                    ->always(function($value) {
                        if (is_array($value['files'])) {
                            return $value;
                        }
                        if (is_string($value['files'])) {
                            return array_merge($value, ['files' => [$value['files']]]);
                        }

                        return $value;
                    })
                ->end()
                ->validate()
                    ->always(function ($value) {
                        return $value;
                    })
                ->end()
            ->children()
            ->arrayNode('files')
                ->cannotBeEmpty()
                ->prototype('scalar')
                ->info('The file(s) to mangle.')
            ->end()
            ->cannotBeEmpty()
            ->end()
                ->arrayNode('filter')
                ->info('The rewrite filters to use.')
                ->cannotBeEmpty()
                ->prototype('array')
                    ->beforeNormalization()
                        ->always(function($value) {
                            // Allow "short notation" for replace filters.
                            if ('replace' === $value['type'] && (!isset($value['search']) || !isset($value['search']))) {
                                $keys = array_diff(array_keys($value), ['type', 'search', 'replace', '']);
                                if (count($keys) !== 1) {
                                    return $value;
                                }
                                $search           = array_pop($keys);
                                $value['search']  = $search;
                                $value['replace'] = $value[$search];
                                unset($value[$search]);
                            }

                            return $value;
                        })
                    ->end()
                ->validate()
                    ->always(function ($value) {
                        if ('replace' === $value['type']) {
                            if (is_string($value['search']) && is_string($value['replace'])) {
                                return $value;
                            }

                            throw new \InvalidArgumentException('Rewrite type "replace" needs "search" and "replace".');
                        }

                        return $value;
                    })
                ->end()
                ->children()
                    ->scalarNode('type')
                    ->end()
                    ->scalarNode('search')
                    ->end()
                    ->scalarNode('replace')
                    ->end()
                    ->scalarNode('format')
                    ->end()
                    ->scalarNode('ahead')
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
