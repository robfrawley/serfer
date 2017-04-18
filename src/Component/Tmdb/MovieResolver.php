<?php

/*
 * This file is part of the `src-run/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Serferals\Component\Tmdb;

use SR\Serferals\Component\Model\MediaMetadataModel;
use Tmdb\Model\Movie;
use Tmdb\Model\Search\SearchQuery\MovieSearchQuery;
use Tmdb\Repository\MovieRepository;

/**
 * Class MovieResolver.
 */
class MovieResolver extends AbstractResolver
{
    /**
     * @var string
     */
    const TYPE = 'movie';

    /**
     * @return MovieSearchQuery
     */
    protected function getQuery()
    {
        return new MovieSearchQuery();
    }

    /**
     * @return MovieRepository
     */
    protected function getSingleRepository()
    {
        return new MovieRepository($this->getClient(false));
    }

    /**
     * @param MediaMetadataModel $fixture
     * @param string      $method
     *
     * @return $this
     */
    public function resolve(MediaMetadataModel $fixture, $method = 'searchMovie')
    {
        return parent::resolve($fixture, $method);
    }
}

/* EOF */
