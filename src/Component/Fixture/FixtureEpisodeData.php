<?php

/*
 * This file is part of the `src-run/serferals` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Serferals\Component\Fixture;

use SR\Serferals\Component\Tmdb\EpisodeResolver;

/**
 * Class FixtureEpisodeData
 */
class FixtureEpisodeData extends FixtureData
{
    /**
     * @var string
     */
    const TYPE = EpisodeResolver::TYPE;

    /**
     * @var null|int
     */
    protected $seasonNumber;

    /**
     * @var null|int
     */
    protected $episodeNumberStart;

    /**
     * @var null|int
     */
    protected $episodeNumberEnd;

    /**
     * @var null|string
     */
    protected $title;

    /**
     * @return string[]
     */
    public function getFieldsStatic()
    {
        return parent::getFieldsStatic();
    }

    /**
     * @return string[]
     */
    public function getFieldsEditable()
    {
        return array_merge(parent::getFieldsEditable(), [
            'seasonNumber' => 'Season',
            'episodeNumberStart' => 'Episode Number',
            'title' => 'Episode Title'
        ]);
    }


    /**
     * @return int|null
     */
    public function getSeasonNumber()
    {
        return $this->seasonNumber;
    }

    /**
     * @param int|null $seasonNumber
     *
     * @return $this
     */
    public function setSeasonNumber($seasonNumber)
    {
        $this->seasonNumber = (int) $seasonNumber;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasSeasonNumber()
    {
        return $this->seasonNumber !== null && !empty($this->seasonNumber);
    }

    /**
     * @return int|null
     */
    public function getEpisodeNumberStart()
    {
        return $this->episodeNumberStart;
    }

    /**
     * @param int|null $episodeNumberStart
     *
     * @return $this
     */
    public function setEpisodeNumberStart($episodeNumberStart)
    {
        $this->episodeNumberStart = (int) $episodeNumberStart;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasEpisodeNumberStart()
    {
        return $this->episodeNumberStart !== null && !empty($this->episodeNumberStart);
    }

    /**
     * @return int|null
     */
    public function getEpisodeNumberEnd()
    {
        return $this->episodeNumberEnd;
    }

    /**
     * @param int|null $episodeNumberEnd
     *
     * @return $this
     */
    public function setEpisodeNumberEnd($episodeNumberEnd)
    {
        $this->episodeNumberEnd = (int) $episodeNumberEnd;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasEpisodeNumberEnd()
    {
        return $this->episodeNumberEnd !== null && !empty($this->episodeNumberEnd);
    }

    /**
     * @return null|string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = (string) $title;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasTitle()
    {
        return $this->title !== null && !empty($this->title);
    }
}

/* EOF */
