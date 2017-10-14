<?php

/*
 * This file is part of the `src-run/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Serferals\Component\Tasks\Filesystem;

interface FileAtomicMoverTaskInterface
{
    /**
     * @var string
     */
    public const MODE_DEFAULT = self::MODE_CP;

    /**
     * @var string
     */
    public const MODE_MV = 'mv';

    /**
     * @var string
     */
    public const MODE_CP = 'cp';
}
