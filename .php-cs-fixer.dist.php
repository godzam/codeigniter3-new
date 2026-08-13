<?php

/**
 * Only lints code this project actually owns and maintains — an explicit
 * whitelist (mirroring phpstan.neon.dist's `paths`), not a blacklist.
 * Vendored framework/library code (system/, application/third_party/),
 * CI3's own test suite (tests/), and CI3's stock skeleton files (default
 * config data tables, default error views) are deliberately left out —
 * reformatting code we didn't author just creates noise and makes it
 * harder to diff against upstream if it's ever re-vendored.
 */

$finder = (new PhpCsFixer\Finder())
	->in([
		__DIR__.'/application/controllers',
		__DIR__.'/application/core',
		__DIR__.'/application/models',
		__DIR__.'/application/helpers',
		__DIR__.'/application/hooks',
		__DIR__.'/application/libraries',
		__DIR__.'/application/modules',
		__DIR__.'/application/src',
	])
	->exclude([
		'views',
	])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true);

return (new PhpCsFixer\Config())
	->setRules([
		'@PSR12' => true,
		'array_syntax' => ['syntax' => 'short'],
		'no_unused_imports' => true,
		'ordered_imports' => ['sort_algorithm' => 'alpha'],
		'trailing_comma_in_multiline' => true,
		'single_quote' => true,
		'no_trailing_whitespace' => true,
		'blank_line_after_namespace' => true,
	])
	->setFinder($finder)
	->setRiskyAllowed(false);
