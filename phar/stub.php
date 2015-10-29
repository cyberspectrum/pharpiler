#!/usr/bin/env php
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

if (version_compare(phpversion(), '@@MIN_PHP_VERSION@@', '<=')) {
    fwrite(
        STDERR,
        'Error: Pharpiler needs at least PHP @@MIN_PHP_VERSION@@ while you have ' . phpversion() . PHP_EOL
    );
    exit;
}

if (!extension_loaded('Phar')) {
    fwrite(STDERR, 'Error: Phar extension is needed.' . PHP_EOL);
    exit;
}

Phar::mapPhar('pharpiler.phar');
// @codingStandardsIgnoreStart

// Compiler generated warning time:
// @@DEV_WARNING_TIME@@

require 'phar://pharpiler.phar/bin/pharpiler';

__HALT_COMPILER();
