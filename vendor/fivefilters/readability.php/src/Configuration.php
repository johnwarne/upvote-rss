<?php

namespace fivefilters\Readability;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Configuration.
 */
class Configuration
{
    use LoggerAwareTrait;

    protected int $maxTopCandidates = 5;
    protected int $charThreshold = 500;
    protected bool $articleByline = false;
    protected bool $stripUnlikelyCandidates = true;
    protected bool $cleanConditionally = true;
    protected bool $weightClasses = true;
    protected bool $fixRelativeURLs = false;
    protected bool $substituteEntities = false;
    protected bool $normalizeEntities = false;
    protected bool $summonCthulhu = false;
    protected string $originalURL = 'http://fakehost';
    protected string $parser = 'html5';
    protected bool $keepClasses = false;
    protected bool $disableJSONLD = false;

    /**
     * Configuration constructor.
     */
    public function __construct(array $params = [])
    {
        foreach ($params as $key => $value) {
            $setter = sprintf('set%s', $key);
            if (method_exists($this, $setter)) {
                call_user_func([$this, $setter], $value);
            }
        }
    }

    /**
     * Returns an array-representation of configuration.
     */
    public function toArray(): array
    {
        $out = [];
        foreach ($this as $key => $value) {
            $getter = sprintf('get%s', $key);
            if (!is_object($value) && method_exists($this, $getter)) {
                $out[$key] = call_user_func([$this, $getter]);
            }
        }

        return $out;
    }

    /**
     * Get logger.
     */
    public function getLogger(): LoggerInterface
    {
        // If no logger has been set, just return a null logger
        if ($this->logger === null) {
            return new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Set logger.
     */
    public function setLogger(LoggerInterface $logger): Configuration
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get max top candidates.
     */
    public function getMaxTopCandidates(): int
    {
        return $this->maxTopCandidates;
    }

    /**
     * Set max top candidates.
     */
    public function setMaxTopCandidates(int $maxTopCandidates): Configuration
    {
        $this->maxTopCandidates = $maxTopCandidates;

        return $this;
    }

    /**
     * Get char threshold.
     */
    public function getCharThreshold(): int
    {
        return $this->charThreshold;
    }

    /**
     * Set char threshold.
     */
    public function setCharThreshold(int $charThreshold): Configuration
    {
        $this->charThreshold = $charThreshold;

        return $this;
    }

    /**
     * Get article by line.
     */
    public function getArticleByline(): bool
    {
        return $this->articleByline;
    }

    /**
     * Set article by line.
     */
    public function setArticleByline(bool $articleByline): Configuration
    {
        $this->articleByline = $articleByline;

        return $this;
    }

    /**
     * Get strip unlikely candidates.
     */
    public function getStripUnlikelyCandidates(): bool
    {
        return $this->stripUnlikelyCandidates;
    }

    /**
     * @param bool $stripUnlikelyCandidates
     */
    public function setStripUnlikelyCandidates(bool $stripUnlikelyCandidates): Configuration
    {
        $this->stripUnlikelyCandidates = $stripUnlikelyCandidates;

        return $this;
    }

    /**
     * Get clean conditionally.
     */
    public function getCleanConditionally(): bool
    {
        return $this->cleanConditionally;
    }

    /**
     * Set clean conditionally.
     */
    public function setCleanConditionally(bool $cleanConditionally): Configuration
    {
        $this->cleanConditionally = $cleanConditionally;

        return $this;
    }

    /**
     * Get weight classes.
     */
    public function getWeightClasses(): bool
    {
        return $this->weightClasses;
    }

    /**
     * Set weight classes.
     */
    public function setWeightClasses(bool $weightClasses): Configuration
    {
        $this->weightClasses = $weightClasses;

        return $this;
    }

    /**
     * Get fix relative URLs.
     */
    public function getFixRelativeURLs(): bool
    {
        return $this->fixRelativeURLs;
    }

    /**
     * Set fix relative URLs.
     */
    public function setFixRelativeURLs(bool $fixRelativeURLs): Configuration
    {
        $this->fixRelativeURLs = $fixRelativeURLs;

        return $this;
    }

    /**
     * Get substitute entities.
     */
    public function getSubstituteEntities(): bool
    {
        return $this->substituteEntities;
    }

    /**
     * Set substitute entities.
     */
    public function setSubstituteEntities(bool $substituteEntities): Configuration
    {
        $this->substituteEntities = $substituteEntities;

        return $this;
    }

    /**
     * Get normalize entities.
     */
    public function getNormalizeEntities(): bool
    {
        return $this->normalizeEntities;
    }

    /**
     * Set normalize entities.
     */
    public function setNormalizeEntities(bool $normalizeEntities): Configuration
    {
        $this->normalizeEntities = $normalizeEntities;

        return $this;
    }

    /**
     * Get original URL.
     */
    public function getOriginalURL(): string
    {
        return $this->originalURL;
    }

    /**
     * Set original URL.
     */
    public function setOriginalURL(string $originalURL): Configuration
    {
        $this->originalURL = $originalURL;

        return $this;
    }

    /**
     * Get parser.
     */
    public function getParser(): string
    {
        return $this->parser;
    }

    /**
     * Set parser.
     */
    public function setParser(string $parser): Configuration
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Get keep classes.
     */
    public function getKeepClasses(): bool
    {
        return $this->keepClasses;
    }

    /**
     * Set keep classes.
     */
    public function setKeepClasses(bool $keepClasses): Configuration
    {
        $this->keepClasses = $keepClasses;

        return $this;
    }

    /**
     * Get disable JSON-LD.
     */
    public function getDisableJSONLD(): bool
    {
        return $this->disableJSONLD;
    }

    /**
     * Set disable JSON-LD.
     */
    public function setDisableJSONLD(bool $disableJSONLD): Configuration
    {
        $this->disableJSONLD = $disableJSONLD;

        return $this;
    }

    /**
     * Get summon Cthulhu.
     */
    public function getSummonCthulhu(): bool
    {
        return $this->summonCthulhu;
    }

    /**
     * Set summon Cthulhu.
     */
    public function setSummonCthulhu(bool $summonCthulhu): Configuration
    {
        $this->summonCthulhu = $summonCthulhu;

        return $this;
    }
}
