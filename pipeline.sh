#!/bin/bash

rtl_fm -f 169650000hz -s22050 | multimon-ng -a FLEX -f auto -t raw - | php -f ./output.php
