<?php

namespace fivefilters\Readability;

use fivefilters\Readability\Nodes\DOM\DOMDocument;
use fivefilters\Readability\Nodes\DOM\DOMElement;
use fivefilters\Readability\Nodes\DOM\DOMNode;
use fivefilters\Readability\Nodes\DOM\DOMProcessingInstruction;
use fivefilters\Readability\Nodes\DOM\DOMText;
use fivefilters\Readability\Nodes\DOM\DOMComment;
use fivefilters\Readability\Nodes\DOM\DOMCdataSection;
use fivefilters\Readability\Nodes\NodeUtility;
use Psr\Log\LoggerInterface;
use Masterminds\HTML5;
use League\Uri\BaseUri;

/**
 * Class Readability.
 */
class Readability
{
    /**
     * Main DOMDocument where all the magic happens.
     */
    protected DOMDocument $dom;

    /**
     * Title of the article.
     */
    protected ?string $title = null;

    /**
     * Final DOMDocument with the fully parsed HTML.
     */
    protected ?DOMDocument $content = null;

    /**
     * Excerpt of the article.
     */
    protected ?string $excerpt = null;

    /**
     * Main image of the article.
     */
    protected ?string $image = null;

    /**
     * Author of the article. Extracted from the byline tags and other social media properties.
     */
    protected ?string $author = null;

    /**
     * Website name.
     */
    protected ?string $siteName = null;

    /**
     * Direction of the text.
     */
    protected ?string $direction = null;

    /**
     * Base URI
     * HTML5PHP doesn't appear to store it in the baseURI property like PHP's DOMDocument does when parsing with libxml
     */
    protected ?string $baseURI = null;

    /**
     * Configuration object.
     */
    private Configuration $configuration;

    /**
     * Logger object.
     */
    private LoggerInterface $logger;

    /**
     * JSON-LD
     */
    private array $jsonld = [];

    /**
     * Collection of attempted text extractions.
     */
    private array $attempts = [];

    /**
     * Default tags to score.
     */
    private array $defaultTagsToScore = [
        'section',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'p',
        'td',
        'pre',
    ];

    /**
     * Unlikely roles.
     */
    private array $unlikelyRoles = ['menu', 'menubar', 'complementary', 'navigation', 'alert', 'alertdialog', 'dialog'];

    /**
     * Alter to DIV exceptions.
     */
    private array $alterToDIVExceptions = [
        'div',
        'article',
        'section',
        'p',
    ];

    /**
     * HTML escape map.
     */
    private array $htmlEscapeMap = [
        'lt' => '<',
        'gt' => '>',
        'amp' => '&',
        'quot' => '"',
        'apos' => '\'',
    ];

    /**
     * Readability constructor.
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->logger = $this->configuration->getLogger();
    }

    /**
     * Main parse function.
     *
     * @throws ParseException
     */
    public function parse(?string $html = null): bool
    {
        $this->logger->info('*** Starting parse process...');

        if (isset($html)) {
            $this->loadHTML($html);
        }

        // Unwrap image from noscript
        $this->unwrapNoscriptImages($this->dom);

        // Extract JSON-LD metadata before removing scripts
        $this->jsonld = $this->configuration->getDisableJSONLD() ? [] : $this->getJSONLD($this->dom);

        $this->removeScripts($this->dom);

        $this->prepDocument($this->dom);

        // Checking for minimum HTML to work with.
        if (!($root = $this->dom->getElementsByTagName('body')->item(0)) || !$root->firstChild) {
            $this->logger->emergency('No body tag present or body tag empty');

            throw new ParseException('Invalid or incomplete HTML.');
        }

        $bodyCache = $root->cloneNode(true);

        $this->getMetadata();

        $this->getMainImage();

        while (true) {

            $this->logger->debug('Starting parse loop');
            //$root = $root->firstChild;

            $elementsToScore = $this->getNodes($root->firstChild);
            $this->logger->debug(sprintf('Elements to score: \'%s\'', count($elementsToScore)));

            $result = $this->rateNodes($elementsToScore);

            /*
             * Now that we've gone through the full algorithm, check to see if
             * we got any meaningful content. If we didn't, we may need to re-run
             * grabArticle with different flags set. This gives us a higher likelihood of
             * finding the content, and the sieve approach gives us a higher likelihood of
             * finding the -right- content.
             */

            $length = !$result ? 0 : mb_strlen(preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $result->textContent));

            $this->logger->info(sprintf('[Parsing] Article parsed. Amount of words: %s. Current threshold is: %s', $length, $this->configuration->getCharThreshold()));

            if ($result && $length < $this->configuration->getCharThreshold()) {
                $root->parentNode->replaceChild($bodyCache, $root);
                $root = $bodyCache;

                if ($this->configuration->getStripUnlikelyCandidates()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting StripUnlikelyCandidates as false');
                    $this->configuration->setStripUnlikelyCandidates(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } elseif ($this->configuration->getWeightClasses()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting WeightClasses as false');
                    $this->configuration->setWeightClasses(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } elseif ($this->configuration->getCleanConditionally()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting CleanConditionally as false');
                    $this->configuration->setCleanConditionally(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } else {
                    $this->logger->debug('[Parsing] Threshold not met, searching across attempts for some content.');
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];

                    // No luck after removing flags, just return the longest text we found during the different loops
                    usort($this->attempts, function ($a, $b) {
                        return $b['textLength'] - $a['textLength'];
                    });

                    // But first check if we actually have something
                    if (!$this->attempts[0]['textLength']) {
                        $this->logger->emergency('[Parsing] Could not parse text, giving up :(');

                        throw new ParseException('Could not parse text.');
                    }

                    $this->logger->debug('[Parsing] Threshold not met, but found some content in previous attempts.');

                    $result = $this->attempts[0]['articleContent'];
                    break;
                }
            } else {
                break;
            }
        }

        if (!$result) {
            $this->logger->info('*** Parse failed :(');
            return false;
        }

        $result = $this->postProcessContent($result);

        // If we haven't found an excerpt in the article's metadata, use the article's
        // first paragraph as the excerpt. This can be used for displaying a preview of
        // the article's content.
        if (!$this->getExcerpt()) {
            $this->logger->debug('[Parsing] No excerpt text found on metadata, extracting first p node and using it as excerpt.');
            $paragraphs = $result->getElementsByTagName('p');
            if ($paragraphs->length > 0) {
                $this->setExcerpt(trim($paragraphs->item(0)->textContent));
            }
        }

        $this->setContent($result);

        $this->logger->info('*** Parse successful :)');

        return true;
    }

    /**
     * Creates a DOM Document object and loads the provided HTML on it.
     *
     * Used for the first load of Readability and subsequent reloads (when disabling flags and rescanning the text)
     * Previous versions of Readability used this method one time and cloned the DOM to keep a backup. This caused bugs
     * because cloning the DOM object keeps a relation between the clone and the original one, doing changes in both
     * objects and ruining the backup.
     */
    public function loadHTML(string $html): void
    {
        $this->logger->debug('[Loading] Loading HTML...');

        // To avoid throwing a gazillion of errors on malformed HTMLs
        libxml_use_internal_errors(true);

        //$html = preg_replace('/(<br[^>]*>[ \n\r\t]*){2,}/i', '</p><p>', $html);

        if ($this->configuration->getParser() === 'html5') {
            $this->logger->debug('[Loading] Using HTML5 parser...');
            $html5 = new HTML5(['disable_html_ns' => true, 'target_document' => new DOMDocument('1.0', 'utf-8')]);
            $dom = $html5->loadHTML($html);
            //TODO: Improve this so it looks inside <html><head><base>, not just any <base>
            $base = $dom->getElementsByTagName('base');
            if ($base->length > 0) {
                $base = $base->item(0);
                $base = $base->getAttribute('href');
                if ($base != '') {
                    $this->baseURI = $base;
                }
            }
        } else {
            $this->logger->debug('[Loading] Using libxml parser...');
            $dom = new DOMDocument('1.0', 'utf-8');
            if ($this->configuration->getNormalizeEntities()) {
                $this->logger->debug('[Loading] Normalized entities via mb_convert_encoding.');
                // Replace UTF-8 characters with the HTML Entity equivalent. Useful to fix html with mixed content
                $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            }
        }

        if (!$this->configuration->getSubstituteEntities()) {
            // Keep the original HTML entities
            $dom->substituteEntities = false;
        }

        if ($this->configuration->getSummonCthulhu()) {
            $this->logger->debug('[Loading] Removed script tags via regex H̶͈̩̟̬̱͠E̡̨̬͔̳̜͢͠ ̡̧̯͉̩͙̩̹̞̠͎͈̹̥̠͞ͅͅC̶͉̞̘̖̝̗͓̬̯͍͉̤̬͢͢͞Ò̟̘͉͖͎͉̱̭̣̕M̴̯͈̻̱̱̣̗͈̠̙̲̥͘͞E̷̛͙̼̲͍͕̹͍͇̗̻̬̮̭̱̥͢Ş̛̟͔̙̜̤͇̮͍̙̝̀͘');
            $html = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/', '', $html);
        }

        // Prepend the XML tag to avoid having issues with special characters. Should be harmless.
        if ($this->configuration->getParser() !== 'html5') {
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $this->baseURI = $dom->baseURI;
        }
        $dom->encoding = 'UTF-8';

        $this->logger->debug('[Loading] Loaded HTML successfully.');

        $this->dom = $dom;
    }

    /**
     * Try to extract metadata from JSON-LD object.
     * For now, only Schema.org objects of type Article or its subtypes are supported.
     *
     * @return array with any metadata that could be extracted (possibly none)
     */
    private function getJSONLD(DOMDocument $dom): array
    {
        $scripts = $this->_getAllNodesWithTag($dom, ['script']);

        $jsonLdElement = $this->findNode($scripts, function ($el) {
            return $el->getAttribute('type') === 'application/ld+json';
        });

        if ($jsonLdElement) {
            try {
                // Strip CDATA markers if present
                $content = preg_replace('/^\s*<!\[CDATA\[|\]\]>\s*$/', '', $jsonLdElement->textContent);
                $parsed = json_decode($content, true);
                $metadata = [];
                if (
                    !isset($parsed['@context']) ||
                    !is_string($parsed['@context']) ||
                    !preg_match('/^https?:\/\/schema\.org$/', $parsed['@context'])
                ) {
                    return $metadata;
                }

                if (!isset($parsed['@type']) && isset($parsed['@graph']) && is_array($parsed['@graph'])) {
                    $_found = null;
                    foreach ($parsed['@graph'] as $it) {
                        if (isset($it['@type']) && is_string($it['@type']) && preg_match(NodeUtility::$regexps['jsonLdArticleTypes'], $it['@type'])) {
                            $_found = $it;
                        }
                    }
                    $parsed = $_found;
                }

                if (
                    !$parsed ||
                    !isset($parsed['@type']) ||
                    !is_string($parsed['@type']) ||
                    !preg_match(NodeUtility::$regexps['jsonLdArticleTypes'], $parsed['@type'])
                ) {
                    return $metadata;
                }
                if (isset($parsed['name']) && is_string($parsed['name'])) {
                    $metadata['title'] = trim($parsed['name']);
                } elseif (isset($parsed['headline']) && is_string($parsed['headline'])) {
                    $metadata['title'] = trim($parsed['headline']);
                }
                if (isset($parsed['author'])) {
                    if (isset($parsed['author']['name']) && is_string($parsed['author']['name'])) {
                        $metadata['byline'] = trim($parsed['author']['name']);
                    } elseif (is_array($parsed['author']) && is_string($parsed['author'][0]['name'] ?? null)) {
                        $metadata['byline'] = array_filter($parsed['author'], function ($author) {
                            return is_array($author) && is_string($author['name'] ?? null);
                        });
                        $metadata['byline'] = array_map(fn($author) => trim($author['name']), $metadata['byline']);
                        $metadata['byline'] = implode(', ', $metadata['byline']);
                    }
                }
                if (isset($parsed['description']) && is_string($parsed['description'])) {
                    $metadata['excerpt'] = trim($parsed['description']);
                }
                if (is_array($parsed['publisher'] ?? null) && is_string($parsed['publisher']['name'] ?? null)) {
                    $metadata['siteName'] = trim($parsed['publisher']['name']);
                }
                return $metadata;
            } catch (\Exception $err) {
                // The try-catch blocks are from the JS version. Not sure if there's anything
                // here in the PHP version that would trigger an error or exception, so perhaps we can
                // remove the try-catch blocks here (or at least translate errors to exceptions for this bit)
                $this->logger->debug('[JSON-LD] Error parsing: ' . $err->getMessage());
            }
        }
        return [];
    }

    /**
     * Tries to guess relevant info from metadata of the html. Sets the results in the Readability properties.
     */
    private function getMetadata(): void
    {
        $this->logger->debug('[Metadata] Retrieving metadata...');

        $values = [];
        // property is a space-separated list of values
        $propertyPattern = '/\s*(dc|dcterm|og|twitter)\s*:\s*(author|creator|description|title|image|site_name)(?!:)\s*/i';

        // name is a single value
        $namePattern = '/^\s*(?:(dc|dcterm|og|twitter|weibo:(article|webpage))\s*[.:]\s*)?(author|creator|description|title|image|site_name)(?!:)\s*$/i';

        // Find description tags.
        foreach ($this->dom->getElementsByTagName('meta') as $meta) {
            /* @var DOMNode $meta */
            $elementName = $meta->getAttribute('name');
            $elementProperty = $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            $matches = null;
            $name = null;

            if ($elementProperty) {
                if (preg_match($propertyPattern, $elementProperty, $matches)) {
                    $name = preg_replace('/\s/', '', mb_strtolower($matches[0]));
                    // multiple authors
                    $values[$name] = trim($content);
                }
            }

            if (!$matches && $elementName && preg_match($namePattern, $elementName)) {
                $name = $elementName;
                if ($content) {
                    // Convert to lowercase, remove any whitespace, and convert dots
                    // to colons so we can match below.
                    $name = preg_replace(['/\s/', '/\./'], ['', ':'], mb_strtolower($name));
                    $values[$name] = trim($content);
                }
            }
        }

        // get title
        /*
         * This is a very convoluted way of extracting the first matching key of the $values array
         * against a set of options.
         *
         * This could be easily replaced with an ugly set of isset($values['key']) or a bunch of ??s.
         * Will probably replace it with ??s after dropping support of PHP5.6
         */
        $key = current(array_intersect([
            'dc:title',
            'dcterm:title',
            'og:title',
            'weibo:article:title',
            'weibo:webpage:title',
            'title',
            'twitter:title'
        ], array_keys($values)));

        if (isset($this->jsonld['title'])) {
            $this->setTitle($this->jsonld['title']);
        } else {
            $this->setTitle(isset($values[$key]) ? trim($values[$key]) : null);
        }

        if (!$this->getTitle()) {
            $this->setTitle($this->getArticleTitle());
        }

        // get author
        $key = current(array_intersect([
            'dc:creator',
            'dcterm:creator',
            'author'
        ], array_keys($values)));

        if (isset($this->jsonld['byline'])) {
            $this->setAuthor($this->jsonld['byline']);
        } else {
            $this->setAuthor($values[$key] ?? null);
        }

        // get description
        $key = current(array_intersect([
            'dc:description',
            'dcterm:description',
            'og:description',
            'weibo:article:description',
            'weibo:webpage:description',
            'description',
            'twitter:description'
        ], array_keys($values)));

        if (isset($this->jsonld['excerpt'])) {
            $this->setExcerpt($this->jsonld['excerpt']);
        } else {
            $this->setExcerpt($values[$key] ?? null);
        }

        // get main image
        $key = current(array_intersect([
            'image',
            'og:image',
            'twitter:image'
        ], array_keys($values)));

        $this->setImage($values[$key] ?? null);

        $key = current(array_intersect([
            'og:site_name'
        ], array_keys($values)));

        if (isset($this->jsonld['siteName'])) {
            $this->setSiteName($this->jsonld['siteName']);
        } else {
            $this->setSiteName($values[$key] ?? null);
        }

        // in many sites the meta value is escaped with HTML entities,
        // so here we need to unescape it
        $this->setTitle($this->unescapeHtmlEntities($this->getTitle()));
        $this->setAuthor($this->unescapeHtmlEntities($this->getAuthor()));
        $this->setExcerpt($this->unescapeHtmlEntities($this->getExcerpt()));
        $this->setSiteName($this->unescapeHtmlEntities($this->getSiteName()));
    }

    /**
     * Returns all the images of the parsed article.
     */
    public function getImages(): array
    {
        $result = [];
        if ($this->getImage()) {
            $result[] = $this->getImage();
        }

        if (null == $this->getDOMDocument()) {
            return $result;
        }

        foreach ($this->getDOMDocument()->getElementsByTagName('img') as $img) {
            if ($src = $img->getAttribute('src')) {
                $result[] = $src;
            }
        }

        if ($this->configuration->getFixRelativeURLs()) {
            foreach ($result as &$imgSrc) {
                try {
                    $imgSrc = $this->toAbsoluteURI($imgSrc);
                } catch (\Exception $err) {
                    $imgSrc = '';
                }
            }
        }

        return array_unique(array_filter($result));
    }

    /**
     * Tries to get the main article image. Will only update the metadata if the getMetadata function couldn't
     * find a correct image.
     */
    public function getMainImage(): void
    {
        $imgUrl = false;

        if ($this->getImage() !== null) {
            $imgUrl = $this->getImage();
        }

        if (!$imgUrl) {
            foreach ($this->dom->getElementsByTagName('link') as $link) {
                /** @var \DOMElement $link */
                /*
                 * Check for the rel attribute, then check if the rel attribute is either img_src or image_src, and
                 * finally check for the existence of the href attribute, which should hold the image url.
                 */
                if ($link->hasAttribute('rel') && ($link->getAttribute('rel') === 'img_src' || $link->getAttribute('rel') === 'image_src') && $link->hasAttribute('href')) {
                    $imgUrl = $link->getAttribute('href');
                    break;
                }
            }
        }

        if (!empty($imgUrl) && $this->configuration->getFixRelativeURLs()) {
            try {
                $this->setImage($this->toAbsoluteURI($imgUrl));
            } catch (\Exception $err) {
                $this->setImage(null);
            }
        }
    }

    /**
     * Remove unnecessary nested elements
     */
    private function simplifyNestedElements(DOMDocument $article): void
    {
        $node = $article;

        while ($node) {
            if ($node->parentNode && in_array($node->nodeName, ['div', 'section']) && !($node->hasAttribute('id') && strpos($node->getAttribute('id'), 'readability') === 0)) {
                /** @var DOMElement $node */
                if ($node->isElementWithoutContent()) {
                    $node = NodeUtility::removeAndGetNext($node);
                    continue;
                } elseif ($node->hasSingleTagInsideElement('div') || $node->hasSingleTagInsideElement('section')) {
                    $child = $node->children()->item(0);
                    for ($i = 0; $i < $node->attributes->length; $i++) {
                        $child->setAttribute($node->attributes->item($i)->name, $node->attributes->item($i)->value);
                    }
                    $node->parentNode->replaceChild($child, $node);
                    $node = $child;
                    continue;
                }
            }

            $node = NodeUtility::getNextNode($node);
        }
    }

    /**
     * Returns the title of the html. Prioritizes the title from the metadata against the title tag.
     */
    private function getArticleTitle(): ?string
    {
        $originalTitle = null;

        if ($this->getTitle()) {
            $originalTitle = $this->getTitle();
        } else {
            $this->logger->debug('[Metadata] Could not find title in metadata, searching for the title tag...');
            $titleTag = $this->dom->getElementsByTagName('title');
            if ($titleTag->length > 0) {
                $this->logger->info(sprintf('[Metadata] Using title tag as article title: \'%s\'', $titleTag->item(0)->nodeValue));
                $originalTitle = $titleTag->item(0)->nodeValue;
            }
        }

        if ($originalTitle === null) {
            return null;
        }

        $curTitle = $originalTitle = trim($originalTitle);
        $titleHadHierarchicalSeparators = false;

        /*
         * If there's a separator in the title, first remove the final part
         *
         * Sanity warning: if you eval this match in PHPStorm's "Evaluate expression" box, it will return false
         * I can assure you it works properly if you let the code run.
         */
        if (preg_match('/ [|\-\\\\\/>»] /i', $curTitle)) {
            $titleHadHierarchicalSeparators = (bool) preg_match('/ [\\\\\/>»] /', $curTitle);
            $curTitle = preg_replace('/(.*)[|\-\\\\\/>»] .*/i', '$1', $originalTitle);

            $this->logger->info(sprintf('[Metadata] Found hierarchical separators in title, new title is: \'%s\'', $curTitle));

            // If the resulting title is too short (3 words or fewer), remove
            // the first part instead:
            if (count(preg_split('/\s+/', $curTitle)) < 3) {
                $curTitle = preg_replace('/[^\|\-\\\\\/>»]*[\|\-\\\\\/>»](.*)/i', '$1', $originalTitle);
                $this->logger->info(sprintf('[Metadata] Title too short, using the first part of the title instead: \'%s\'', $curTitle));
            }
        } elseif (str_contains($curTitle, ': ')) {
            // Check if we have an heading containing this exact string, so we
            // could assume it's the full title.
            $match = false;
            for ($i = 1; $i <= 2; $i++) {
                foreach ($this->dom->getElementsByTagName('h' . $i) as $hTag) {
                    // Trim texts to avoid having false negatives when the title is surrounded by spaces or tabs
                    if (trim($hTag->nodeValue) === trim($curTitle)) {
                        $match = true;
                    }
                }
            }

            // If we don't, let's extract the title out of the original title string.
            if (!$match) {
                $curTitle = substr($originalTitle, strrpos($originalTitle, ':') + 1);

                $this->logger->info(sprintf('[Metadata] Title has a colon in the middle, new title is: \'%s\'', $curTitle));

                // If the title is now too short, try the first colon instead:
                if (count(preg_split('/\s+/', $curTitle)) < 3) {
                    $curTitle = substr($originalTitle, strpos($originalTitle, ':') + 1);
                    $this->logger->info(sprintf('[Metadata] Title too short, using the first part of the title instead: \'%s\'', $curTitle));
                } elseif (count(preg_split('/\s+/', substr($curTitle, 0, strpos($curTitle, ':')))) > 5) {
                    // But if we have too many words before the colon there's something weird
                    // with the titles and the H tags so let's just use the original title instead
                    $curTitle = $originalTitle;
                }
            }
        } elseif (mb_strlen($curTitle) > 150 || mb_strlen($curTitle) < 15) {
            $hOnes = $this->dom->getElementsByTagName('h1');

            if ($hOnes->length === 1) {
                $curTitle = $hOnes->item(0)->nodeValue;
                $this->logger->info(sprintf('[Metadata] Using title from an H1 node: \'%s\'', $curTitle));
            }
        }

        $curTitle = preg_replace(NodeUtility::$regexps['normalize'], ' ', trim($curTitle));

        /*
         * If we now have 4 words or fewer as our title, and either no
         * 'hierarchical' separators (\, /, > or ») were found in the original
         * title or we decreased the number of words by more than 1 word, use
         * the original title.
         */
        $curTitleWordCount = count(preg_split('/\s+/', $curTitle));
        $originalTitleWordCount = count(preg_split('/\s+/', preg_replace('/[\|\-\\\\\/>»]+/', '', $originalTitle))) - 1;

        if ($curTitleWordCount <= 4 &&
            (!$titleHadHierarchicalSeparators || $curTitleWordCount !== $originalTitleWordCount)) {
            $curTitle = $originalTitle;

            $this->logger->info(sprintf('Using title from an H1 node: \'%s\'', $curTitle));
        }

        return $curTitle;
    }

    /**
     * Convert URI to an absolute URI.
     */
    private function toAbsoluteURI(string $uri): string
    {
        list($pathBase, $scheme, $prePath) = $this->getPathInfo($this->configuration->getOriginalURL());

        $uri = trim($uri);

        // If this is already an absolute URI, return it.
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $uri)) {
            return $uri;
        }

        // Scheme-rooted relative URI.
        if (substr($uri, 0, 2) === '//') {
            return $scheme . '://' . substr($uri, 2);
        }

        // Prepath-rooted relative URI.
        if (substr($uri, 0, 1) === '/') {
            return $prePath . $uri;
        }

        // Ignore hash URIs:
        if (substr($uri, 0, 1) === '#') {
            return $uri;
        }

        // Dotslash relative URI.
        //if (strpos($uri, './') === 0) {
        //    return $pathBase . substr($uri, 2);
        //}

        $baseUri = BaseUri::from($pathBase);
        return (string) $baseUri->resolve($uri);

        // Standard relative URI; add entire path. pathBase already includes a
        // trailing "/".
        //return $pathBase . $uri;
    }

    /**
     * Returns full path info of an URL.
     *
     * @return array [$pathBase, $scheme, $prePath]
     */
    public function getPathInfo(string $url): array
    {
        // Check for base URLs
        if ($this->baseURI !== null) {
            if (substr($this->baseURI, 0, 1) === '/') {
                // URLs starting with '/' override completely the URL defined in the link
                $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . $this->baseURI;
            } else {
                // Otherwise just prepend the base to the actual path
                $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . dirname(parse_url($url, PHP_URL_PATH) ?? '') . '/' . rtrim($this->baseURI, '/') . '/';
            }
        } else {
            $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . dirname(parse_url($url, PHP_URL_PATH) ?? '') . '/';
        }

        $scheme = parse_url($pathBase, PHP_URL_SCHEME);
        $prePath = $scheme . '://' . parse_url($pathBase, PHP_URL_HOST);

        return [$pathBase, $scheme, $prePath];
    }

    /**
     * Gets nodes from the root element.
     */
    private function getNodes(DOMNode|DOMComment|DOMText|DOMElement|null $node): array
    {
        $this->logger->info('[Get Nodes] Retrieving nodes...');
        if ($node === null) {
            return [];
        }

        $stripUnlikelyCandidates = $this->configuration->getStripUnlikelyCandidates();

        $elementsToScore = [];

        $shouldRemoveTitleHeader = true;

        /*
         * First, node prepping. Trash nodes that look cruddy (like ones with the
         * class name "comment", etc), and turn divs into P tags where they have been
         * used inappropriately (as in, where they contain no other block level elements.)
         */

        while ($node) {
            // Remove DOMComments nodes as we don't need them and mess up children counting
            if ($node->nodeType === XML_COMMENT_NODE) {
                $this->logger->debug(sprintf('[Get Nodes] Found comment node, removing... Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');

            if (!$node->isProbablyVisible()) {
                $this->logger->debug(sprintf('[Get Nodes] Removing hidden node... Match string was: \'%s\'', $matchString));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            // Check to see if this node is a byline, and remove it if it is.
            if ($this->checkByline($node, $matchString)) {
                $this->logger->debug(sprintf('[Get Nodes] Found byline, removing... Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            if ($shouldRemoveTitleHeader && $this->headerDuplicatesTitle($node)) {
                $this->logger->debug(sprintf('Removing header: %s', $node->getTextContent()));
                $shouldRemoveTitleHeader = false;
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            // Remove unlikely candidates
            if ($stripUnlikelyCandidates) {
                if (
                    preg_match(NodeUtility::$regexps['unlikelyCandidates'], $matchString) &&
                    !preg_match(NodeUtility::$regexps['okMaybeItsACandidate'], $matchString) &&
                    !$node->hasAncestorTag( 'table') &&
                    !$node->hasAncestorTag( 'code') &&
                    $node->nodeName !== 'body' &&
                    $node->nodeName !== 'a'
                ) {
                    $this->logger->debug(sprintf('[Get Nodes] Removing unlikely candidate. Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $node = NodeUtility::removeAndGetNext($node);
                    continue;
                }
            }

            if (in_array($node->getAttribute('role'), $this->unlikelyRoles)) {
                $this->logger->debug(sprintf('Removing content with role %s - %s', $node->getAttribute('role'), $matchString));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            // Remove DIV, SECTION, and HEADER nodes without any content(e.g. text, image, video, or iframe).
            if (($node->nodeName === 'div' || $node->nodeName === 'section' || $node->nodeName === 'header' ||
                    $node->nodeName === 'h1' || $node->nodeName === 'h2' || $node->nodeName === 'h3' ||
                    $node->nodeName === 'h4' || $node->nodeName === 'h5' || $node->nodeName === 'h6' ||
                    $node->nodeName === 'p') &&
                $node->isElementWithoutContent()) {
                $this->logger->debug(sprintf('[Get Nodes] Removing empty \'%s\' node.', $node->nodeName));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            if (in_array(strtolower($node->nodeName), $this->defaultTagsToScore)) {
                $this->logger->debug(sprintf('[Get Nodes] Adding node to score list, node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                $elementsToScore[] = $node;
            }

            // Turn all divs that don't have children block level elements into p's
            if ($node->nodeName === 'div') {
                // Put phrasing content into paragraphs.
                $p = null;
                $childNode = $node->firstChild;
                while ($childNode) {
                    $nextSibling = $childNode->nextSibling;
                    if ($childNode->isPhrasingContent()) {
                        if ($p !== null) {
                            $p->appendChild($childNode);
                        } elseif (!$childNode->isWhitespace()) {
                            $p = $this->dom->createElement('p');
                            $node->replaceChild($p, $childNode);
                            $p->appendChild($childNode);
                        }
                    } elseif ($p !== null) {
                        /** @var DOMNode $p */
                        while ($p->lastChild && $p->lastChild->isWhitespace()) {
                            $p->removeChild($p->lastChild);
                        }
                        $p = null;
                    }
                    $childNode = $nextSibling;
                }

                /*
                 * Sites like http://mobile.slate.com encloses each paragraph with a DIV
                 * element. DIVs with only a P element inside and no text content can be
                 * safely converted into plain P elements to avoid confusing the scoring
                 * algorithm with DIVs with are, in practice, paragraphs.
                 */
                if ($node->hasSingleTagInsideElement('p') && $node->getLinkDensity() < 0.25) {
                    $this->logger->debug(sprintf('[Get Nodes] Found DIV with a single P node, removing DIV. Node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $pNode = NodeUtility::filterTextNodes($node->childNodes)->item(0);
                    $node->parentNode->replaceChild($pNode, $node);
                    $node = $pNode;
                    $elementsToScore[] = $node;
                } elseif (!$node->hasSingleChildBlockElement()) {
                    $this->logger->debug(sprintf('[Get Nodes] Found DIV with a single child block element, converting to a P node. Node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $node = NodeUtility::setNodeTag($node, 'p');
                    $elementsToScore[] = $node;
                }
            }

            $node = NodeUtility::getNextNode($node);
        }

        return $elementsToScore;
    }

    /**
     * compares second text to first one
     * 1 = same text, 0 = completely different text
     * works the way that it splits both texts into words and then finds words that are unique in second text
     * the result is given by the lower length of unique parts
     *
     * @param string $textA
     * @param string $textB
     *
     * @return int 1 = same text, 0 = completely different text
     */
    private function textSimilarity(string $textA, string $textB): float
    {
        $tokensA = array_filter(preg_split(NodeUtility::$regexps['tokenize'], mb_strtolower($textA)));
        $tokensB = array_filter(preg_split(NodeUtility::$regexps['tokenize'], mb_strtolower($textB)));
        if (!count($tokensA) || !count($tokensB)) {
            return 0;
        }
        $uniqTokensB = array_filter($tokensB, function ($token) use (&$tokensA) {
            return !in_array($token, $tokensA);
        });
        $distanceB = mb_strlen(implode(' ', $uniqTokensB)) / mb_strlen(implode(' ', $tokensB));
        return 1 - $distanceB;
    }

    /**
     * Checks if the node is a byline.
     */
    private function checkByline(DOMNode|DOMText|DOMElement|DOMProcessingInstruction|DOMCdataSection $node, string $matchString): bool
    {
        if (!$this->configuration->getArticleByline()) {
            return false;
        }

        /*
         * Check if the byline is already set
         */
        if ($this->getAuthor()) {
            return false;
        }

        $rel = $node->getAttribute('rel');
        $itemprop = $node->getAttribute("itemprop");

        if ($rel === 'author' || ($itemprop && str_contains($itemprop, 'author')) || preg_match(NodeUtility::$regexps['byline'], $matchString) && $this->isValidByline($node->getTextContent(false))) {
            $this->logger->info(sprintf('[Metadata] Found article author: \'%s\'', $node->getTextContent(false)));
            $this->setAuthor(trim($node->getTextContent(false)));

            return true;
        }

        return false;
    }

    /**
     * Checks the validity of a byline. Based on string length.
     */
    private function isValidByline(string $text): bool
    {
        if (gettype($text) == 'string') {
            $byline = trim($text);

            return (mb_strlen($byline) > 0) && (mb_strlen($byline) < 100);
        }

        return false;
    }

    /**
     * Converts some of the common HTML entities in string to their corresponding characters.
     */
    private function unescapeHtmlEntities(?string $str): ?string
    {
        if (!$str) {
            return $str;
        }

        $htmlEscapeMap = $this->htmlEscapeMap;
        $str = preg_replace_callback('/&(quot|amp|apos|lt|gt);/', function ($tag) use ($htmlEscapeMap) {
            return $htmlEscapeMap[$tag[1]];
        }, $str);
        $str = preg_replace_callback('/&#(?:x([0-9a-z]{1,4})|([0-9]{1,4}));/i', function ($matches) {
            $hex = $matches[1];
            $numStr = $matches[2] ?? '0';
            if ($hex !== '') {
                $num = intval($hex, 16);
            } else {
                $num = intval($numStr, 10);
            }
            return mb_chr($num);
        }, $str);
        return $str;
    }

    /**
     * Check if node is image, or if node contains exactly only one image
     * whether as a direct child or as its descendants.
     */
    private function isSingleImage(DOMElement|DOMNode|DOMText $node): bool
    {
        if ($node->tagName === 'img') {
            return true;
        }

        if ($node->children()->length !== 1 || trim($node->textContent) !== '') {
            return false;
        }

        return $this->isSingleImage($node->children()->item(0));
    }

    /**
     * Find all <noscript> that are located after <img> nodes, and which contain only one
     * <img> element. Replace the first image with the image from inside the <noscript> tag,
     * and remove the <noscript> tag. This improves the quality of the images we use on
     * some sites (e.g. Medium).
     */
    private function unwrapNoscriptImages(DOMDocument $dom): void
    {
        // Find img without source or attributes that might contains image, and remove it.
        // This is done to prevent a placeholder img is replaced by img from noscript in next step.
        $imgs = iterator_to_array($dom->getElementsByTagName('img'));
        array_walk($imgs, function ($img) {
            for ($i = 0; $i < $img->attributes->length; $i++) {
                $attr = $img->attributes->item($i);
                switch ($attr->name) {
                    case 'src':
                    case 'srcset':
                    case 'data-src':
                    case 'data-srcset':
                        return;
                }

                if (preg_match('/\.(jpg|jpeg|png|webp)/i', $attr->value)) {
                    return;
                }
            }

            $img->parentNode->removeChild($img);
        });

        // Next find noscript and try to extract its image
        $noscripts = iterator_to_array($dom->getElementsByTagName('noscript'));
        array_walk($noscripts, function ($noscript) use($dom) {
            // Parse content of noscript and make sure it only contains image
            // [PHP port] Could copy innerHTML support over for the commented lines below, but is it needed?
            // var tmp = doc.createElement("div");
            // tmp.innerHTML = noscript.innerHTML;
            $tmp = $noscript->cloneNode(true);
            //$dom->importNode($tmp); // Isn't this node already from the $dom?
            if (!$this->isSingleImage($tmp)) {
                return;
            }

            // If noscript has previous sibling and it only contains image,
            // replace it with noscript content. However we also keep old
            // attributes that might contains image.
            $prevElement = $noscript->previousElementSibling;
            if ($prevElement && $this->isSingleImage($prevElement)) {
                $prevImg = $prevElement;
                if ($prevImg->tagName !== 'img') {
                    $prevImg = $prevElement->getElementsByTagName('img')->item(0);
                }

                $newImg = $tmp->getElementsByTagName('img')->item(0);
                for ($i = 0; $i < $prevImg->attributes->length; $i++) {
                    $attr = $prevImg->attributes->item($i);
                    if ($attr->value === '') {
                        continue;
                    }

                    if ($attr->name === 'src' || $attr->name === 'srcset' || preg_match('/\.(jpg|jpeg|png|webp)/i', $attr->value)) {
                        if ($newImg->getAttribute($attr->name) === $attr->value) {
                            continue;
                        }

                        $attrName = $attr->name;
                        if ($newImg->hasAttribute($attrName)) {
                            $attrName = 'data-old-' . $attrName;
                        }

                        $newImg->setAttribute($attrName, $attr->value);
                    }
                }

                $noscript->parentNode->replaceChild($tmp->firstElementChild, $prevElement);
            }
        });
    }

    /**
     * Removes all the scripts of the html.
     */
    private function removeScripts(DOMDocument $dom): void
    {
        foreach (['script', 'noscript'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            foreach (iterator_to_array($nodes) as $node) {
                NodeUtility::removeNode($node);
            }
        }
    }

    /**
     * Prepares the document for parsing.
     */
    private function prepDocument(DOMDocument $dom): void
    {
        $this->logger->info('[PrepDocument] Preparing document for parsing...');

        foreach ($dom->shiftingAwareGetElementsByTagName('br') as $br) {
            $next = $br->nextSibling;

            /*
             * Whether 2 or more <br> elements have been found and replaced with a
             * <p> block.
             */
            $replaced = false;

            /*
             * If we find a <br> chain, remove the <br>s until we hit another element
             * or non-whitespace. This leaves behind the first <br> in the chain
             * (which will be replaced with a <p> later).
             */
            while (($next = NodeUtility::nextNode($next)) && ($next->nodeName === 'br')) {
                $this->logger->debug('[PrepDocument] Removing chain of BR nodes...');

                $replaced = true;
                $brSibling = $next->nextSibling;
                $next->parentNode->removeChild($next);
                $next = $brSibling;
            }

            /*
             * If we removed a <br> chain, replace the remaining <br> with a <p>. Add
             * all sibling nodes as children of the <p> until we hit another <br>
             * chain.
             */

            if ($replaced) {
                /** @var DOMElement $p */
                $p = $dom->createElement('p');
                $br->parentNode->replaceChild($p, $br);

                $next = $p->nextSibling;
                while ($next) {
                    // If we've hit another <br><br>, we're done adding children to this <p>.
                    if ($next->nodeName === 'br') {
                        $nextElem = NodeUtility::nextNode($next->nextSibling);
                        if ($nextElem && $nextElem->nodeName === 'br') {
                            break;
                        }
                    }

                    if (!$next->isPhrasingContent()) {
                        break;
                    }

                    $this->logger->debug('[PrepDocument] Replacing BR with a P node...');

                    // Otherwise, make this node a child of the new <p>.
                    $sibling = $next->nextSibling;
                    $p->appendChild($next);
                    $next = $sibling;
                }

                while ($p && $p->lastChild && $p->lastChild->isWhitespace()) {
                    $p->removeChild($p->lastChild);
                }

                while ($p && $p->firstChild && $p->firstChild->isWhitespace()) {
                    $p->removeChild($p->firstChild);
                }

                if ($p->parentNode->tagName === 'p') {
                    NodeUtility::setNodeTag($p->parentNode, 'div');
                }
            }
        }

        // Replace font tags with span
        $fonts = $this->_getAllNodesWithTag($dom, ['font']);
        $length = count($fonts);
        for ($i = 0; $i < $length; $i++) {
            $this->logger->debug('[PrepDocument] Converting font tag into a span tag.');
            $font = $fonts[$length - 1 - $i];
            NodeUtility::setNodeTag($font, 'span');
        }
    }

    /**
     * Assign scores to each node. Returns full article parsed or false on error.
     */
    private function rateNodes(array $nodes): DOMDocument|bool
    {
        $this->logger->info('[Rating] Rating nodes...');

        $candidates = [];

        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            if (is_null($node->parentNode)) {
                continue;
            }

            // Discard nodes with less than 25 characters, without blank space
            if (mb_strlen($node->getTextContent(true)) < 25) {
                continue;
            }

            $ancestors = $node->getNodeAncestors(5);

            // Exclude nodes with no ancestor
            if (count($ancestors) === 0) {
                continue;
            }

            // Start with a point for the paragraph itself as a base.
            $contentScore = 1;

            // Add points for any commas within this paragraph.
            $contentScore += count(explode(',', $node->getTextContent(true)));

            // For every 100 characters in this paragraph, add another point. Up to 3 points.
            $contentScore += min(floor(mb_strlen($node->getTextContent(true)) / 100), 3);

            $this->logger->debug(sprintf('[Rating] Node score %s, content: \'%s\'', $contentScore, substr($node->nodeValue, 0, 128)));

            /** @var $ancestor DOMElement */
            foreach ($ancestors as $level => $ancestor) {
                $this->logger->debug('[Rating] Found ancestor, initializing and adding it as a candidate...');
                if (!$ancestor->isInitialized()) {
                    $ancestor->initializeNode($this->configuration->getWeightClasses());
                    $candidates[] = $ancestor;
                }

                /*
                 * Node score divider:
                 *  - parent:             1 (no division)
                 *  - grandparent:        2
                 *  - great grandparent+: ancestor level * 3
                 */

                if ($level === 0) {
                    $scoreDivider = 1;
                } elseif ($level === 1) {
                    $scoreDivider = 2;
                } else {
                    $scoreDivider = $level * 3;
                }

                $currentScore = $ancestor->contentScore;
                $ancestor->contentScore = $currentScore + ($contentScore / $scoreDivider);

                $this->logger->debug(sprintf('[Rating] Ancestor score %s, value: \'%s\'', $ancestor->contentScore, substr($ancestor->nodeValue, 0, 128)));
            }
        }

        /*
         * After we've calculated scores, loop through all of the possible
         * candidate nodes we found and find the one with the highest score.
         */

        $topCandidates = [];
        foreach ($candidates as $candidate) {

            /*
             * Scale the final candidates score based on link density. Good content
             * should have a relatively small link density (5% or less) and be mostly
             * unaffected by this operation.
             */

            $candidate->contentScore = $candidate->contentScore * (1 - $candidate->getLinkDensity());

            for ($i = 0; $i < $this->configuration->getMaxTopCandidates(); $i++) {
                $aTopCandidate = isset($topCandidates[$i]) ? $topCandidates[$i] : null;

                if (!$aTopCandidate || $candidate->contentScore > $aTopCandidate->contentScore) {
                    array_splice($topCandidates, $i, 0, [$candidate]);
                    if (count($topCandidates) > $this->configuration->getMaxTopCandidates()) {
                        array_pop($topCandidates);
                    }
                    break;
                }
            }
        }

        $topCandidate = isset($topCandidates[0]) ? $topCandidates[0] : null;
        $parentOfTopCandidate = null;

        /*
         * If we still have no top candidate, just use the body as a last resort.
         * We also have to copy the body node so it is something we can modify.
         */

        if ($topCandidate === null || $topCandidate->nodeName === 'body') {
            $this->logger->info('[Rating] No top candidate found or top candidate is the body tag. Moving all child nodes to a new DIV node.');

            // Move all of the page's children into topCandidate
            $topCandidate = new DOMDocument('1.0', 'utf-8');
            $topCandidate->encoding = 'UTF-8';
            $topCandidate->appendChild($topCandidate->createElement('div', ''));
            $kids = $this->dom->getElementsByTagName('body')->item(0)->childNodes;

            // Cannot be foreached, don't ask me why.
            for ($i = 0; $i < $kids->length; $i++) {
                $import = $topCandidate->importNode($kids->item($i), true);
                $topCandidate->firstChild->appendChild($import);
            }

            // Candidate must be created using firstChild to grab the DOMElement instead of the DOMDocument.
            $topCandidate = $topCandidate->firstChild;
        } elseif ($topCandidate) {
            $this->logger->info(sprintf('[Rating] Found top candidate, score: %s', $topCandidate->contentScore));
            // Find a better top candidate node if it contains (at least three) nodes which belong to `topCandidates` array
            // and whose scores are quite closed with current `topCandidate` node.
            $alternativeCandidateAncestors = [];
            for ($i = 1; $i < count($topCandidates); $i++) {
                // In some cases we may end up with a top candidate with zero content score. To avoid dividing by zero
                // we have to use max() and replace zero with a low value like 0.1
                if ($topCandidates[$i]->contentScore / max($topCandidate->contentScore, 0.1) >= 0.75) {
                    array_push($alternativeCandidateAncestors, $topCandidates[$i]->getNodeAncestors(false));
                }
            }

            $MINIMUM_TOPCANDIDATES = 3;
            if (count($alternativeCandidateAncestors) >= $MINIMUM_TOPCANDIDATES) {
                $parentOfTopCandidate = $topCandidate->parentNode;

                // Check if we are actually dealing with a DOMNode and not a DOMDocument node or higher
                while ($parentOfTopCandidate && $parentOfTopCandidate->nodeName !== 'body' && $parentOfTopCandidate->nodeType === XML_ELEMENT_NODE) {
                    $listsContainingThisAncestor = 0;
                    for ($ancestorIndex = 0; $ancestorIndex < count($alternativeCandidateAncestors) && $listsContainingThisAncestor < $MINIMUM_TOPCANDIDATES; $ancestorIndex++) {
                        $listsContainingThisAncestor += (int)in_array($parentOfTopCandidate, $alternativeCandidateAncestors[$ancestorIndex]);
                    }
                    if ($listsContainingThisAncestor >= $MINIMUM_TOPCANDIDATES) {
                        $topCandidate = $parentOfTopCandidate;
                        break;
                    }
                    $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
                }
            }

            /*
             * Because of our bonus system, parents of candidates might have scores
             * themselves. They get half of the node. There won't be nodes with higher
             * scores than our topCandidate, but if we see the score going *up* in the first
             * few steps up the tree, that's a decent sign that there might be more content
             * lurking in other places that we want to unify in. The sibling stuff
             * below does some of that - but only if we've looked high enough up the DOM
             * tree.
             */

            $parentOfTopCandidate = $topCandidate->parentNode;
            $lastScore = $topCandidate->contentScore;

            // The scores shouldn't get too low.
            $scoreThreshold = $lastScore / 3;

            /* @var DOMElement $parentOfTopCandidate */
            while ($parentOfTopCandidate && $parentOfTopCandidate->nodeName !== 'body') {
                $parentScore = $parentOfTopCandidate->contentScore;
                if ($parentScore < $scoreThreshold) {
                    break;
                }

                if ($parentScore > $lastScore) {
                    // Alright! We found a better parent to use.
                    $topCandidate = $parentOfTopCandidate;
                    $this->logger->info('[Rating] Found a better top candidate.');
                    break;
                }
                $lastScore = $parentOfTopCandidate->contentScore;
                $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
            }

            // If the top candidate is the only child, use parent instead. This will help sibling
            // joining logic when adjacent content is actually located in parent's sibling node.
            $parentOfTopCandidate = $topCandidate->parentNode;
            while ($parentOfTopCandidate && $parentOfTopCandidate->nodeName !== 'body' && count(NodeUtility::filterTextNodes($parentOfTopCandidate->childNodes)) === 1) {
                $topCandidate = $parentOfTopCandidate;
                $parentOfTopCandidate = $topCandidate->parentNode;
            }
        }

        /*
         * Now that we have the top candidate, look through its siblings for content
         * that might also be related. Things like preambles, content split by ads
         * that we removed, etc.
         */

        $this->logger->info('[Rating] Creating final article content document...');

        $articleContent = new DOMDocument('1.0', 'utf-8');
        $articleContent->createElement('div');

        $siblingScoreThreshold = max(10, $topCandidate->contentScore * 0.2);
        // Keep potential top candidate's parent node to try to get text direction of it later.
        $parentOfTopCandidate = $topCandidate->parentNode;
        $siblings = $parentOfTopCandidate->childNodes ?? null;

        $hasContent = false;

        $this->logger->info('[Rating] Adding top candidate siblings...');

        /* @var DOMElement $sibling */
        // Can't foreach here because down there we might change the tag name and that causes the foreach to skip items
        for ($i = 0; $i < ($siblings->length ?? 0); $i++) {
            $sibling = $siblings[$i];
            $append = false;

            if ($sibling === $topCandidate) {
                $this->logger->debug('[Rating] Sibling is equal to the top candidate, adding to the final article...');

                $append = true;
            } else {
                $contentBonus = 0;

                // Give a bonus if sibling nodes and top candidates have the example same classname
                if ($sibling->getAttribute('class') === $topCandidate->getAttribute('class') && $topCandidate->getAttribute('class') !== '') {
                    $contentBonus += $topCandidate->contentScore * 0.2;
                }
                if ($sibling->contentScore + $contentBonus >= $siblingScoreThreshold) {
                    $append = true;
                } elseif ($sibling->nodeName === 'p') {
                    $linkDensity = $sibling->getLinkDensity();
                    $nodeContent = $sibling->getTextContent(true);
                    $nodeContentLength = mb_strlen($nodeContent);

                    if ($nodeContentLength > 80 && $linkDensity < 0.25) {
                        $append = true;
                    } elseif ($nodeContentLength > 0 && $nodeContentLength < 80 && $linkDensity < 0.25 && preg_match('/\.( |$)/', $nodeContent)) {
                        $append = true;
                    }
                }
            }

            if ($append) {
                $this->logger->debug(sprintf('[Rating] Appending sibling to final article, content is: \'%s\'', substr($sibling->nodeValue, 0, 128)));

                $hasContent = true;

                if (!in_array(strtolower($sibling->nodeName), $this->alterToDIVExceptions)) {
                    /*
                     * We have a node that isn't a common block level element, like a form or td tag.
                     * Turn it into a div so it doesn't get filtered out later by accident.
                     */
                    $sibling = NodeUtility::setNodeTag($sibling, 'div');
                }

                $import = $articleContent->importNode($sibling, true);
                $articleContent->appendChild($import);

                /*
                 * No node shifting needs to be check because when calling getChildren, an array is made with the
                 * children of the parent node, instead of using the DOMElement childNodes function, which, when used
                 * along with appendChild, would shift the nodes position and the current foreach will behave in
                 * unpredictable ways.
                 */
            }
        }

        if ($hasContent) {
            $articleContent = $this->prepArticle($articleContent);
            // Find out text direction from ancestors of final top candidate.
            $ancestors = array_merge([$parentOfTopCandidate, $topCandidate], $parentOfTopCandidate->getNodeAncestors());
            foreach ($ancestors as $ancestor) {
                $articleDir = $ancestor->getAttribute('dir');
                if ($articleDir) {
                    $this->setDirection($articleDir);
                    $this->logger->debug(sprintf('[Rating] Found article direction: %s', $articleDir));
                    break;
                }
            }

            return $articleContent;
        } else {
            return false;
        }
    }

    /**
     * Cleans up the final article.
     */
    public function prepArticle(DOMDocument $article): DOMDocument
    {
        $this->logger->info('[PrepArticle] Preparing final article...');

        $this->_cleanStyles($article);
        $this->_clean($article, 'style');

        // Check for data tables before we continue, to avoid removing items in
        // those tables, which will often be isolated even though they're
        // visually linked to other content-ful elements (text, images, etc.).
        $this->_markDataTables($article);

        $this->_fixLazyImages($article);

        // Clean out junk from the article content
        $this->_cleanConditionally($article, 'form');
        $this->_cleanConditionally($article, 'fieldset');
        $this->_clean($article, 'object');
        $this->_clean($article, 'embed');
        $this->_clean($article, 'footer');
        $this->_clean($article, 'link');
        $this->_clean($article, 'aside');

        // Clean out elements have "share" in their id/class combinations from final top candidates,
        // which means we don't remove the top candidates even they have "share".

        $shareElementThreshold = $this->configuration->getCharThreshold();

        foreach ($article->childNodes as $child) {
            $this->_cleanMatchedNodes($child, function ($node, $matchString) use ($shareElementThreshold) {
                return (preg_match(NodeUtility::$regexps['shareElements'], $matchString) && mb_strlen($node->textContent) < $shareElementThreshold);
            });
        }

        /*
         * If there is only one h2 and its text content substantially equals article title,
         * they are probably using it as a header and not a subheader,
         * so remove it since we already extract the title separately.
         */
        /*
        $h2 = $article->getElementsByTagName('h2');
        if ($h2->length === 1) {
            $lengthSimilarRate = (mb_strlen($h2->item(0)->textContent) - mb_strlen($this->getTitle())) / max(mb_strlen($this->getTitle()), 1);

            if (abs($lengthSimilarRate) < 0.5) {
                if ($lengthSimilarRate > 0) {
                    $titlesMatch = strpos($h2->item(0)->textContent, $this->getTitle()) !== false;
                } else {
                    $titlesMatch = strpos($this->getTitle(), $h2->item(0)->textContent) !== false;
                }
                if ($titlesMatch) {
                    $this->logger->info('[PrepArticle] Found title repeated in an H2 node, removing...');
                    $this->_clean($article, 'h2');
                }
            }
        }
        */

        $this->_clean($article, 'iframe');
        $this->_clean($article, 'input');
        $this->_clean($article, 'textarea');
        $this->_clean($article, 'select');
        $this->_clean($article, 'button');
        $this->_cleanHeaders($article);

        // Do these last as the previous stuff may have removed junk
        // that will affect these
        $this->_cleanConditionally($article, 'table');
        $this->_cleanConditionally($article, 'ul');
        $this->_cleanConditionally($article, 'div');

        // replace H1 with H2 as H1 should be only title that is displayed separately
        foreach (iterator_to_array($article->getElementsByTagName('h1')) as $h1) {
            NodeUtility::setNodeTag($h1, 'h2');
        }

        $this->_cleanExtraParagraphs($article);

        foreach (iterator_to_array($article->getElementsByTagName('br')) as $br) {
            $next = NodeUtility::nextNode($br->nextSibling);
            if ($next && $next->nodeName === 'p') {
                $this->logger->debug('[PrepArticle] Removing br node next to a p node.');
                $br->parentNode->removeChild($br);
            }
        }

        // Remove single-cell tables
        foreach ($article->shiftingAwareGetElementsByTagName('table') as $table) {
            /** @var DOMElement $table */
            $tbody = $table->hasSingleTagInsideElement('tbody') ? $table->firstElementChild : $table;
            if ($tbody->hasSingleTagInsideElement('tr')) {
                $row = $tbody->firstElementChild;
                if ($row->hasSingleTagInsideElement('td')) {
                    $cell = $row->firstElementChild;
                    $cell = NodeUtility::setNodeTag($cell, (array_reduce(iterator_to_array($cell->childNodes), function ($carry, $node) {
                        return $node->isPhrasingContent() && $carry;
                    }, true)) ? 'p' : 'div');
                    $table->parentNode->replaceChild($cell, $table);
                }
            }
        }

        return $article;
    }

    /**
     * Look for 'data' (as opposed to 'layout') tables, for which we use
     * similar checks as
     * https://dxr.mozilla.org/mozilla-central/rev/71224049c0b52ab190564d3ea0eab089a159a4cf/accessible/html/HTMLTableAccessible.cpp#920.
     */
    public function _markDataTables(DOMDocument $article): void
    {
        $tables = $article->getElementsByTagName('table');
        foreach ($tables as $table) {
            /** @var DOMElement $table */
            $role = $table->getAttribute('role');
            if ($role === 'presentation') {
                $table->setReadabilityDataTable(false);
                continue;
            }
            $datatable = $table->getAttribute('datatable');
            if ($datatable == '0') {
                $table->setReadabilityDataTable(false);
                continue;
            }
            $summary = $table->getAttribute('summary');
            if ($summary) {
                $table->setReadabilityDataTable(true);
                continue;
            }

            $caption = $table->getElementsByTagName('caption');
            if ($caption->length > 0 && $caption->item(0)->childNodes->length > 0) {
                $table->setReadabilityDataTable(true);
                continue;
            }

            // If the table has a descendant with any of these tags, consider a data table:
            foreach (['col', 'colgroup', 'tfoot', 'thead', 'th'] as $dataTableDescendants) {
                if ($table->getElementsByTagName($dataTableDescendants)->length > 0) {
                    $table->setReadabilityDataTable(true);
                    continue 2;
                }
            }

            // Nested tables indicate a layout table:
            if ($table->getElementsByTagName('table')->length > 0) {
                $table->setReadabilityDataTable(false);
                continue;
            }

            $sizeInfo = $table->getRowAndColumnCount();
            if ($sizeInfo['rows'] >= 10 || $sizeInfo['columns'] > 4) {
                $table->setReadabilityDataTable(true);
                continue;
            }
            // Now just go by size entirely:
            $table->setReadabilityDataTable($sizeInfo['rows'] * $sizeInfo['columns'] > 10);
        }
    }

    /**
     * convert images and figures that have properties like data-src into images that can be loaded without JS
     */
    public function _fixLazyImages(DOMDocument $article): void
    {
        $images = $this->_getAllNodesWithTag($article, ['img', 'picture', 'figure']);
        foreach ($images as $elem) {
            // In some sites (e.g. Kotaku), they put 1px square image as base64 data uri in the src attribute.
            // So, here we check if the data uri is too short, just might as well remove it.
            if ($elem->getAttribute('src') && preg_match(NodeUtility::$regexps['b64DataUrl'], $elem->getAttribute('src'), $parts)) {
                // Make sure it's not SVG, because SVG can have a meaningful image in under 133 bytes.
                if ($parts[1] === 'image/svg+xml') {
                    continue;
                }

                // Make sure this element has other attributes which contains image.
                // If it doesn't, then this src is important and shouldn't be removed.
                $srcCouldBeRemoved = false;
                for ($i = 0; $i < $elem->attributes->length; $i++) {
                    $attr = $elem->attributes->item($i);
                    if ($attr->name === 'src') {
                        continue;
                    }

                    if (preg_match('/\.(jpg|jpeg|png|webp)/i', $attr->value)) {
                        $srcCouldBeRemoved = true;
                        break;
                    }
                }

                // Here we assume if image is less than 100 bytes (or 133B after encoded to base64)
                // it will be too small, therefore it might be placeholder image.
                if ($srcCouldBeRemoved) {
                    $b64starts = stripos($elem->getAttribute('src'), 'base64') + 7;
                    $b64length = strlen($elem->getAttribute('src')) - $b64starts;
                    if ($b64length < 133) {
                        $elem->removeAttribute('src');
                    }
                }
            }

            // Don't remove if there's a src or srcset attribute, and there's no sign of 'lazy' loading in the class
            // attribute value.
            if (($elem->getAttribute('src') || $elem->getAttribute('srcset')) && mb_stripos($elem->getAttribute('class'), 'lazy') === false) {
                continue;
            }

            for ($j = 0; $j < $elem->attributes->length; $j++) {
                $attr = $elem->attributes->item($j);
                if ($attr->name === 'src' || $attr->name === 'srcset' || $attr->name === 'alt') {
                    continue;
                }
                $copyTo = null;
                if (preg_match('/\.(jpg|jpeg|png|webp)\s+\d/', $attr->value)) {
                    $copyTo = 'srcset';
                } elseif (preg_match('/^\s*\S+\.(jpg|jpeg|png|webp)\S*\s*$/', $attr->value)) {
                    $copyTo = 'src';
                }
                if ($copyTo) {
                    //if this is an img or picture, set the attribute directly
                    if ($elem->tagName === 'img' || $elem->tagName === 'picture') {
                        $elem->setAttribute($copyTo, $attr->value);
                    } elseif ($elem->tagName === 'figure' && empty($this->_getAllNodesWithTag($elem, ['img', 'picture']))) {
                        //if the item is a <figure> that does not contain an image or picture, create one and place it inside the figure
                        //see the nytimes-3 testcase for an example
                        $img = $article->createElement('img');
                        $img->setAttribute($copyTo, $attr->value);
                        $elem->appendChild($img);
                    }
                }
            }
        }
    }

    /**
     * Remove the style attribute on every e and under.
     **/
    public function _cleanStyles(DOMDocument|DOMNode|DOMElement|DOMText|DOMProcessingInstruction|DOMCdataSection $node): void
    {
        if (property_exists($node, 'tagName') && $node->tagName === 'svg') {
            return;
        }

        // Do not bother if there's no method to remove an attribute
        if (method_exists($node, 'removeAttribute')) {
            $presentational_attributes = ['align', 'background', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'frame', 'hspace', 'rules', 'style', 'valign', 'vspace'];
            // Remove `style` and deprecated presentational attributes
            foreach ($presentational_attributes as $presentational_attribute) {
                $node->removeAttribute($presentational_attribute);
            }

            $deprecated_size_attribute_elems = ['table', 'th', 'td', 'hr', 'pre'];
            if (property_exists($node, 'tagName') && in_array($node->tagName, $deprecated_size_attribute_elems)) {
                $node->removeAttribute('width');
                $node->removeAttribute('height');
            }
        }

        $cur = $node->firstChild;
        while ($cur !== null) {
            $this->_cleanStyles($cur);
            $cur = $cur->nextSibling;
        }
    }

    /**
     * Clean out elements that match the specified conditions
     *
     * @param $node DOMElement Node to clean
     * @param $filter callable Function determines whether a node should be removed
     **/
    public function _cleanMatchedNodes(DOMElement $node, callable $filter): void
    {
        $endOfSearchMarkerNode = NodeUtility::getNextNode($node, true);
        $next = NodeUtility::getNextNode($node);
        while ($next && $next !== $endOfSearchMarkerNode) {
            if ($filter($next, sprintf('%s %s', $next->getAttribute('class'), $next->getAttribute('id')))) {
                $this->logger->debug(sprintf('Removing matched node, node class was: \'%s\', id: \'%s\'', $next->getAttribute('class'), $next->getAttribute('id')));
                $next = NodeUtility::removeAndGetNext($next);
            } else {
                $next = NodeUtility::getNextNode($next);
            }
        }
    }

    /**
     * Clean extra paragraphs.
     */
    public function _cleanExtraParagraphs(DOMDocument $article): void
    {
        $paragraphs = $this->_getAllNodesWithTag($article, ['p']);
        $length = count($paragraphs);

        for ($i = 0; $i < $length; $i++) {
            $paragraph = $paragraphs[$length - 1 - $i];

            $imgCount = $paragraph->getElementsByTagName('img')->length;
            $embedCount = $paragraph->getElementsByTagName('embed')->length;
            $objectCount = $paragraph->getElementsByTagName('object')->length;
            // At this point, nasty iframes have been removed, only remain embedded video ones.
            $iframeCount = $paragraph->getElementsByTagName('iframe')->length;
            $totalCount = $imgCount + $embedCount + $objectCount + $iframeCount;

            if ($totalCount === 0 && !preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $paragraph->textContent)) {
                $this->logger->debug(sprintf('[PrepArticle] Removing extra paragraph. Text content was: \'%s\'', substr($paragraph->textContent, 0, 128)));
                $paragraph->parentNode->removeChild($paragraph);
            }
        }
    }

    private function getTextDensity($e, array $tags) {
        $textLength = mb_strlen($e->getTextContent(true));
        if ($textLength === 0) {
            return 0;
        }
        $childrenLength = 0;
        $children = $this->_getAllNodesWithTag($e, $tags);
        foreach ($children as $child) {
            $childrenLength += mb_strlen($child->getTextContent(true));
        }
        return $childrenLength / $textLength;
    }

    /**
     * Clean conditionally.
     *
     * @param DOMDocument $article
     * @param string $tag Tag to clean conditionally
     */
    public function _cleanConditionally(DOMDocument $article, string $tag): void
    {
        if (!$this->configuration->getCleanConditionally()) {
            return;
        }

        /*
         * Gather counts for other typical elements embedded within.
         * Traverse backwards so we can remove nodes at the same time
         * without effecting the traversal.
         */

        $allNodesWithTag = $this->_getAllNodesWithTag($article, [$tag]);
        $length = count($allNodesWithTag);
        for ($i = 0; $i < $length; $i++) {
            /** @var $node DOMElement */
            $node = $allNodesWithTag[$length - 1 - $i];

            $isList = in_array($tag, ['ul', 'ol']);
            /*
            // Doesn't seem to work as expected
            // compared to JS version: https://github.com/mozilla/readability/commit/3c833899866ffb1f9130767110197fd6f5c08d4c
            if (!$isList) {
                $listLength = 0;
                $listNodes = $this->_getAllNodesWithTag($node, ['ul', 'ol']);
                array_walk($listNodes, function ($list) use(&$listLength) {
                    $listLength += mb_strlen($list->getTextContent());
                });
                $nodeTextLength = mb_strlen($node->getTextContent());
                if (!$nodeTextLength) {
                    $isList = true;
                } else {
                    $isList = $listLength / $nodeTextLength > 0.9;
                }
            }
            */

            // First check if this node IS data table, in which case don't remove it.
            if ($tag === 'table' && $node->isReadabilityDataTable()) {
                continue;
            }

            // Next check if we're inside a data table, in which case don't remove it as well.
            if ($node->hasAncestorTag('table', -1, function ($node) {
                return $node->isReadabilityDataTable();
            })) {
                continue;
            }

            if ($node->hasAncestorTag('code')) {
                continue;
            }

            $weight = 0;
            if ($this->configuration->getWeightClasses()) {
                $weight = $node->getClassWeight();
            }

            if ($weight < 0) {
                $this->logger->debug(sprintf('[PrepArticle] Removing tag \'%s\' with 0 or less weight', $tag));

                NodeUtility::removeNode($node);
                continue;
            }

            if (substr_count($node->getTextContent(false), ',') < 10) {
                /*
                 * If there are not very many commas, and the number of
                 * non-paragraph elements is more than paragraphs or other
                 * ominous signs, remove the element.
                 */

                $p = $node->getElementsByTagName('p')->length;
                $img = $node->getElementsByTagName('img')->length;
                $li = $node->getElementsByTagName('li')->length - 100;
                $input = $node->getElementsByTagName('input')->length;
                $headingDensity = $this->getTextDensity($node, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);

                $embedCount = 0;
                $embeds = $this->_getAllNodesWithTag($node, ['object', 'embed', 'iframe']);

                foreach ($embeds as $embedNode) {
                    for ($j = 0; $j < $embedNode->attributes->length; $j++) {
                        if (preg_match(NodeUtility::$regexps['videos'], $embedNode->attributes->item($j)->nodeValue)) {
                            continue 3;
                        }
                    }

                    // For embed with <object> tag, check inner HTML as well.
                    if ($embedNode->tagName === "object" && preg_match(NodeUtility::$regexps['videos'], $embedNode->C14N())) {
                        continue 2;
                    }

                    $embedCount++;
                }

                $linkDensity = $node->getLinkDensity();
                $contentLength = mb_strlen($node->getTextContent(true));

                $haveToRemove =
                    ($img > 1 && $p / $img < 0.5 && !$node->hasAncestorTag('figure')) ||
                    (!$isList && $li > $p) ||
                    ($input > floor($p / 3)) ||
                    (!$isList && $headingDensity < 0.9 && $contentLength < 25 && ($img === 0 || $img > 2) && !$node->hasAncestorTag('figure')) ||
                    (!$isList && $weight < 25 && $linkDensity > 0.2) ||
                    ($weight >= 25 && $linkDensity > 0.5) ||
                    (($embedCount === 1 && $contentLength < 75) || $embedCount > 1);

                if ($haveToRemove) {
                    $this->logger->debug(sprintf('[PrepArticle] Removing tag \'%s\'.', $tag));

                    NodeUtility::removeNode($node);
                }
            }
        }
    }

    public function _getAllNodesWithTag($node, array $tagNames): array {
        $nodes = [];
        foreach ($tagNames as $tag) {
            $nodeList = $node->getElementsByTagName($tag);
            foreach ($nodeList as $n) {
                $nodes[] = $n;
            }
        }
        return $nodes;
    }

    /**
     * Clean a node of all elements of type "tag".
     * (Unless it's a youtube/vimeo video. People love movies.).
     *
     * @param $article DOMDocument
     * @param $tag string tag to clean
     **/
    public function _clean(DOMDocument $article, string $tag): void
    {
        $isEmbed = in_array($tag, ['object', 'embed', 'iframe']);

        $allNodesWithTag = $this->_getAllNodesWithTag($article, [$tag]);
        $length = count($allNodesWithTag);
        for ($i = 0; $i < $length; $i++) {
            $item = $allNodesWithTag[$length - 1 - $i];

            // Allow youtube and vimeo videos through as people usually want to see those.
            if ($isEmbed) {
                $attributeValues = [];
                foreach ($item->attributes as $value) {
                    $attributeValues[] = $value->nodeValue;
                }
                $attributeValues = implode('|', $attributeValues);

                // First, check the elements attributes to see if any of them contain youtube or vimeo
                if (preg_match(NodeUtility::$regexps['videos'], $attributeValues)) {
                    continue;
                }

                // For embed with <object> tag, check inner HTML as well.
                if ($item->tagName === 'object' && preg_match(NodeUtility::$regexps['videos'], $item->C14N())) {
                    continue;
                }
            }
            $this->logger->debug(sprintf('[PrepArticle] Removing node \'%s\'.', $item->tagName));

            NodeUtility::removeNode($item);
        }
    }

    /**
     * Clean out spurious headers from an Element.
     **/
    public function _cleanHeaders(DOMDocument $article): void
    {
        $headingNodes = $this->_getAllNodesWithTag($article, ['h1', 'h2']);
        /** @var $header DOMElement */
        foreach ($headingNodes as $header) {
            $weight = 0;
            if ($this->configuration->getWeightClasses()) {
                $weight = $header->getClassWeight();
            }
            $shouldRemove = $weight < 0;

            if ($shouldRemove) {
                $this->logger->debug(sprintf('[PrepArticle] Removing H node with 0 or less weight. Content was: \'%s\'', substr($header->nodeValue, 0, 128)));

                NodeUtility::removeNode($header);
            }
        }
    }

    /**
     * Check if this node is an H1 or H2 element whose content is mostly
     * the same as the article title.
     *
     * @param DOMNode the node to check.
     * @return boolean indicating whether this is a title-like header.
     */
    private function headerDuplicatesTitle(DOMNode|DOMText|DOMElement|DOMProcessingInstruction $node): bool
    {
        if ($node->nodeName !== 'h1' && $node->nodeName !== 'h2') {
            return false;
        }
        if (!isset($this->title)) {
            return false;
        }
        $heading = $node->getTextContent(false);
        $this->logger->debug(sprintf('Evaluating similarity of header: %s"', $heading));
        return $this->textSimilarity($this->title, $heading) > 0.75;
    }

    /**
     * Removes the class="" attribute from every element in the given
     * subtree.
     *
     * Readability.js has a special filter to avoid cleaning the classes that the algorithm adds. We don't add classes
     * here so no need to filter those.
     **/
    public function _cleanClasses(DOMDocument|DOMText|DOMNode|DOMElement|DOMProcessingInstruction $node): void
    {
        if ($node->hasAttribute('class')) {
            $node->removeAttribute('class');
        }

        for ($node = $node->getFirstElementChild(); $node !== null; $node = $node->nextSibling) {
            $this->_cleanClasses($node);
        }
    }

    /**
     * Post process content.
     */
    public function postProcessContent(DOMDocument $article): DOMDocument
    {
        $this->logger->info('[PostProcess] PostProcessing content...');

        // Readability cannot open relative uris so we convert them to absolute uris.
        if ($this->configuration->getFixRelativeURLs()) {
            foreach (iterator_to_array($article->getElementsByTagName('a')) as $link) {
                /** @var DOMElement $link */
                $href = $link->getAttribute('href');
                if ($href) {
                    // Remove links with javascript: URIs, since
                    // they won't work after scripts have been removed from the page.
                    if (str_starts_with($href, 'javascript:')) {
                        $this->logger->debug(sprintf('[PostProcess] Removing \'javascript:\' link. Content is: \'%s\'', substr($link->textContent, 0, 128)));

                        // if the link only contains simple text content, it can be converted to a text node
                        if ($link->childNodes->length === 1 && $link->childNodes->item(0)->nodeType === XML_TEXT_NODE) {
                            $text = $article->createTextNode($link->textContent);
                            $link->parentNode->replaceChild($text, $link);
                        } else {
                            // if the link has multiple children, they should all be preserved
                            $container = $article->createElement('span');
                            while ($link->firstChild) {
                                $container->appendChild($link->firstChild);
                            }
                            $link->parentNode->replaceChild($container, $link);
                        }
                    } else {
                        $this->logger->debug(sprintf('[PostProcess] Converting link to absolute URI: \'%s\'', substr($href, 0, 128)));

                        try {
                            $absoluteURL = $this->toAbsoluteURI($href);
                        } catch (\Exception $err) {
                            $this->logger->debug('[PostProcess] Invalid URL encountered');
                            $absoluteURL = '';
                        }
                        $link->setAttribute('href', $absoluteURL);
                    }
                }
            }

            $medias = $this->_getAllNodesWithTag($article, [
                'img', 'picture', 'figure', 'video', 'audio', 'source'
            ]);

            array_walk($medias, function ($media) {
                $src = $media->getAttribute('src');
                $poster = $media->getAttribute('poster');
                $srcset = $media->getAttribute('srcset');

                if ($src) {
                    $this->logger->debug(sprintf('[PostProcess] Converting image URL to absolute URI: \'%s\'', substr($src, 0, 128)));

                    try {
                        $media->setAttribute('src', $this->toAbsoluteURI($src));
                    } catch (\Exception $err) {
                        $this->logger->debug('[PostProcess] Invalid URL encountered');
                        $media->setAttribute('src', '');
                    }
                }

                if ($poster) {
                    $this->logger->debug(sprintf('[PostProcess] Converting image URL to absolute URI: \'%s\'', substr($poster, 0, 128)));

                    try {
                        $media->setAttribute('poster', $this->toAbsoluteURI($poster));
                    } catch (\Exception $err) {
                        $this->logger->debug('[PostProcess] Invalid URL encountered');
                        $media->setAttribute('poster', '');
                    }

                }

                if ($srcset) {
                    $newSrcset = preg_replace_callback(NodeUtility::$regexps['srcsetUrl'], function ($matches) {
                        $this->logger->debug(sprintf('[PostProcess] Converting image URL to absolute URI: \'%s\'', substr($matches[1], 0, 128)));
                        try {
                            return $this->toAbsoluteURI($matches[1]) . $matches[2] . $matches[3];
                        } catch (\Exception $err) {
                            // Leave as is
                            return $matches[1] . $matches[2] . $matches[3];
                        }
                    }, $srcset);

                    $media->setAttribute('srcset', $newSrcset);
                }
            });
        }

        $this->simplifyNestedElements($article);

        if (!$this->configuration->getKeepClasses()) {
            $this->_cleanClasses($article);
        }

        return $article;
    }

    /**
     * Iterate over a NodeList, and return the first node that passes
     * the supplied test function
     *
     * @param  array nodeList The NodeList.
     * @param  callable fn    The test function.
     */
    private function findNode(array $nodeList, callable $fn): DOMNode|DOMText|DOMElement|null
    {
        foreach ($nodeList as $node) {
            if ($fn($node)) {
                return $node;
            }
        }
        return null;
    }

    /**
     * To string
     */
    public function __toString(): string
    {
        return sprintf('<h1>%s</h1>%s', $this->getTitle(), $this->getContent());
    }

    /**
     * Get title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set title.
     */
    protected function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * Get content.
     */
    public function getContent(): ?string
    {
        if ($this->content instanceof DOMDocument) {
            $html5 = new HTML5(['disable_html_ns' => true]);
            // by using childNodes below we make sure HTML5PHP's serialiser
            // doesn't output the <!DOCTYPE html> string at the start.
            return $html5->saveHTML($this->content->childNodes);
        } else {
            return null;
        }
    }

    /**
     * Return DOMDocument holding either only the content or the whole document.
     */
    public function getDOMDocument(bool $contentOnly = true): ?DOMDocument
    {
        if ($contentOnly) {
            return $this->content;
        } else {
            return $this->dom;
        }
    }

    /**
     * Set content.
     */
    protected function setContent(DOMDocument $content): void
    {
        $this->content = $content;
    }

    /**
     * Get excerpt.
     */
    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    /**
     * Set excerpt.
     */
    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    /**
     * Get main image
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Set main image
     */
    protected function setImage(?string $image): void
    {
        $this->image = $image;
    }

    /**
     * Get author.
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * Set author.
     */
    protected function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    /**
     * Get site name.
     */
    public function getSiteName(): ?string
    {
        return $this->siteName;
    }

    /**
     * Set site name.
     */
    protected function setSiteName(?string $siteName): void
    {
        $this->siteName = $siteName;
    }

    /**
     * Get direction.
     */
    public function getDirection(): ?string
    {
        return $this->direction;
    }

    /**
     * Set direction.
     */
    protected function setDirection(?string $direction): void
    {
        $this->direction = $direction;
    }
}
