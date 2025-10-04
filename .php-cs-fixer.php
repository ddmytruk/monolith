<?php

$main = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notPath('#^cache/#')
    ->exclude([
        'storage',
        'vendor',
        '.docker',
        'node_modules',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$boot = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/bootstrap'])
    ->name('*.php')
    ->notPath('#^cache/#')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$finder = PhpCsFixer\Finder::create()->append($main)->append($boot);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_trim' => true,
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'single_quote' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => false,
    ]);
