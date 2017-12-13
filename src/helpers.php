<?php
/**
 *  Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
 *  Everyone is permitted to copy and distribute verbatim copies
 *  of this license document, but changing it is not allowed.
 */

function array_map_assoc(callable $f, array $a) {
    return array_reduce(array_map($f, array_keys($a), $a), function (array $acc, array $a) {
        return $acc + $a;
    }, []);
}