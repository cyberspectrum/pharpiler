#!/bin/bash

(php ./bin/pharpiler compile -vvv || exit 1) && \
(mv pharpiler.phar pharpiler.run1.phar || exit 1) && \
(./pharpiler.run1.phar compile -vv || exit 1) && \
(mv pharpiler.phar pharpiler.run2.phar || exit 1) && \
(./pharpiler.run2.phar compile -vv || exit 1) && \
./pharpiler.phar --version
